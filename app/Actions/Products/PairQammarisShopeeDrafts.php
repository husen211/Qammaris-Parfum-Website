<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\ProductVariant;
use App\Services\ProductImportPayloadHasher;
use App\Services\ProductImportPreviewer;
use App\Services\ProductImportProductSnapshot;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PairQammarisShopeeDrafts
{
    public const VERSION = 'qammaris-pairs-v1';

    public function __construct(
        private ProductImportPreviewer $catalog,
        private ProductImportPayloadHasher $hasher,
        private MapExternalProductIdentity $map,
        private ProductImportProductSnapshot $snapshots,
    ) {}

    public function assertEnvironment(): void
    {
        if (! app()->environment(['staging', 'testing'])) {
            throw new DomainException('Shopee pairing is restricted to staging.');
        }
    }

    public function preview(array $input): ProductImportBatch
    {
        $this->assertEnvironment();
        Validator::make($input, [
            'schema' => ['required', 'in:'.self::VERSION],
            'provenance' => ['required', 'array:mapping_sha256,media_sha256,basic_sha256'],
            'provenance.*' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'provenance.mapping_sha256' => ['required'],
            'provenance.media_sha256' => ['required'],
            'provenance.basic_sha256' => ['required'],
            'data' => ['required', 'array', 'min:1', 'max:1000'],
            'data.*' => ['required', 'array:shopee_id,shopee_name,majoo_sku,status,description,photos,candidates'],
            'data.*.shopee_id' => ['required', 'string', 'regex:/^[0-9]{1,30}$/', 'distinct:strict'],
            'data.*.shopee_name' => ['required', 'string', 'max:255'],
            'data.*.majoo_sku' => ['present', 'nullable', 'string', 'max:255'],
            'data.*.status' => ['required', 'in:sku,kuat,perlu_cek,ambigu,tidak_ketemu'],
            'data.*.description' => ['required', 'string', 'max:20000'],
            'data.*.photos' => ['required', 'array', 'size:3'],
            'data.*.photos.*' => ['nullable', 'string', 'max:2048', 'url:https'],
            'data.*.candidates' => ['present', 'array', 'max:20'],
        ])->validate();
        if (! array_is_list($input['data'])) {
            throw new DomainException('Rows must be a list.');
        }
        foreach ($input['data'] as $row) {
            if (! array_is_list($row['photos']) || ! array_is_list($row['candidates'])) {
                throw new DomainException('Photos and candidates must be lists.');
            }
        }

        return DB::transaction(function () use ($input): ProductImportBatch {
            DB::table('qammaris_app_sync_states')->where('id', 'products')->lockForUpdate()->first();
            $sources = DB::table('qammaris_app_products')->orderBy('id')->lockForUpdate()->get()
                ->map(fn ($s) => json_decode($s->snapshot, true, flags: JSON_THROW_ON_ERROR));
            $bySku = $sources->filter(fn ($s) => is_string($s['sku'] ?? null) && $s['sku'] !== '')->groupBy('sku');
            $approvedSkus = collect($input['data'])->filter(fn ($r) => in_array($r['status'], ['sku', 'kuat'], true))->countBy('majoo_sku');
            $catalogHash = $this->catalogHash();
            $sourceHash = $this->hash($input);
            $key = $this->hash([self::VERSION, $sourceHash, $this->hash($sources->all()), $catalogHash]);
            if ($existing = ProductImportBatch::where('idempotency_key', $key)->first()) {
                return $existing->load('rows');
            }
            $rows = [];
            foreach ($input['data'] as $index => $raw) {
                $source = null;
                $product = null;
                $action = 'review';
                $issue = $raw['status'];
                if (in_array($raw['status'], ['sku', 'kuat'], true)) {
                    $matches = $bySku->get($raw['majoo_sku'], collect());
                    $action = 'blocked';
                    $issue = 'sku_missing_or_nonunique';
                    if ($matches->count() === 1 && $approvedSkus->get($raw['majoo_sku']) === 1) {
                        $source = $matches->first();
                        $identity = ProductExternalIdentity::where('provider', 'qammaris_app')->where('external_product_id', $source['id'])->first();
                        $product = $identity ? Product::find($identity->product_id) : null;
                        $issue = 'hidden_unmapped_or_protected_product';
                        if ($product && ! $source['hidden'] && $product->publication_status === Product::PUBLICATION_DRAFT && ! $product->is_active) {
                            $other = ProductExternalIdentity::where('provider', 'shopee')->where('external_product_id', $raw['shopee_id'])->first();
                            $current = $product->externalIdentities()->where('provider', 'shopee')->first();
                            $issue = 'shopee_identity_conflict';
                            if ((! $other || $other->product_id === $product->id) && (! $current || $current->external_product_id === $raw['shopee_id'])) {
                                $issue = 'existing_description_conflict';
                                if (trim((string) $product->description) === '' || $product->description === $raw['description']) {
                                    $action = 'pair';
                                    $issue = '';
                                }
                            }
                        }
                    }
                }
                $data = array_merge($raw, [
                    'provenance' => $input['provenance'],
                    'source_uuid' => $source['id'] ?? null,
                    'source_hash' => $source ? $this->hash($source) : null,
                    // Existing media is retained. Only image-less drafts acquire these candidates.
                    'foto_utama_url' => $action === 'pair' && ! $product->images()->exists() ? $raw['photos'][0] : '',
                    'foto_2_url' => $action === 'pair' && ! $product->images()->exists() ? $raw['photos'][1] : '',
                    'foto_3_url' => $action === 'pair' && ! $product->images()->exists() ? $raw['photos'][2] : '',
                ]);
                $issues = $issue === '' ? [] : [['field' => 'mapping', 'severity' => 'review', 'message' => $issue]];
                $rows[] = [
                    'line_number' => $index + 2, 'status' => $action === 'pair' ? 'valid' : 'review',
                    'candidate_action' => $action, 'provider' => 'shopee', 'external_product_id' => $raw['shopee_id'],
                    'matched_product_id' => $product?->id, 'normalized_data' => $data, 'issues' => $issues,
                    'payload_hash' => $this->hasher->hash($data, $issues, $action),
                ];
            }
            $valid = count(array_filter($rows, fn ($r) => $r['status'] === 'valid'));
            $batch = ProductImportBatch::create([
                'actor_id' => null, 'source_filename' => 'owner-authorized-shopee-mapping', 'source_size' => strlen(json_encode($input)),
                'source_fingerprint' => $sourceHash, 'contract_version' => self::VERSION, 'catalog_state_fingerprint' => $catalogHash,
                'idempotency_key' => $key, 'status' => 'previewed', 'total_rows' => count($rows), 'valid_rows' => $valid,
                'review_rows' => count($rows) - $valid, 'error_rows' => 0,
            ]);
            $batch->rows()->createMany($rows);

            return $batch->load('rows');
        }, 3);
    }

    public function apply(int $batchId): ProductImportBatch
    {
        $this->assertEnvironment();

        return DB::transaction(function () use ($batchId): ProductImportBatch {
            DB::table('qammaris_app_sync_states')->where('id', 'products')->lockForUpdate()->first();
            $sources = DB::table('qammaris_app_products')->orderBy('id')->lockForUpdate()->get()
                ->map(fn ($s) => json_decode($s->snapshot, true, flags: JSON_THROW_ON_ERROR))
                ->filter(fn ($s) => is_string($s['sku'] ?? null) && $s['sku'] !== '')->groupBy('sku');
            $batch = ProductImportBatch::whereKey($batchId)->lockForUpdate()->firstOrFail();
            if ($batch->contract_version !== self::VERSION || $batch->actor_id !== null) {
                throw new DomainException('Not an Owner-authorized Shopee pairing batch.');
            }
            if ($batch->status === 'applied') {
                return $batch->load('rows');
            }
            if ($batch->status !== 'previewed' || ! hash_equals($batch->catalog_state_fingerprint, $this->catalogHash())) {
                throw new DomainException('Catalog changed; create a new preview.');
            }
            $rows = $batch->rows()->lockForUpdate()->get();
            foreach ($rows as $row) {
                $data = $row->normalized_data;
                if (! hash_equals($row->payload_hash, $this->hasher->hash($data, $row->issues, $row->candidate_action))) {
                    throw new DomainException('Preview changed.');
                }
                if ($row->candidate_action === 'pair') {
                    $matches = $sources->get($data['majoo_sku'], collect());
                    if ($matches->count() !== 1 || $matches->first()['id'] !== $data['source_uuid']
                        || ! hash_equals($data['source_hash'], $this->hash($matches->first()))) {
                        throw new DomainException('Source changed; create a new preview.');
                    }
                }
            }
            $applied = 0;
            foreach ($rows as $row) {
                if ($row->candidate_action !== 'pair') {
                    $row->update(['apply_status' => 'blocked_protected', 'apply_message' => 'Owner selection or protected-record review required; unchanged.', 'applied_at' => now()]);

                    continue;
                }
                $product = Product::whereKey($row->matched_product_id)->lockForUpdate()->firstOrFail();
                self::assertImageTarget($row, $product);
                $before = $this->snapshots->capture($product);
                $this->map->handle($product, 'shopee', $row->external_product_id);
                if (trim((string) $product->description) === '') {
                    $product->update(['description' => $row->normalized_data['description']]);
                }
                $row->update(['apply_status' => 'updated', 'applied_product_id' => $product->id,
                    'apply_message' => 'Owner-approved exact SKU/UUID Shopee pair; existing fields/media and draft status retained.',
                    'before_snapshot' => $before, 'after_snapshot' => $this->snapshots->capture($product), 'applied_at' => now()]);
                $applied++;
            }
            $batch->update(['status' => 'applied', 'applied_at' => now(), 'applied_by' => null, 'applied_rows' => $applied, 'blocked_rows' => $rows->count() - $applied]);

            return $batch->load('rows');
        }, 3);
    }

    public static function assertImageTarget(ProductImportRow $row, Product $product): void
    {
        $uuid = $row->normalized_data['source_uuid'] ?? null;
        $source = $uuid ? DB::table('qammaris_app_products')->where('id', $uuid)->first() : null;
        $snapshot = $source ? json_decode($source->snapshot, true, flags: JSON_THROW_ON_ERROR) : null;
        if ($product->publication_status !== Product::PUBLICATION_DRAFT || $product->is_active || ! $snapshot || $snapshot['hidden']
            || ! $product->externalIdentities()->where('provider', 'qammaris_app')->where('external_product_id', $uuid)->exists()) {
            throw new DomainException('Pair target is no longer a visible connected draft.');
        }
        $shopee = $product->externalIdentities()->where('provider', 'shopee')->first();
        if ($shopee && $shopee->external_product_id !== $row->external_product_id) {
            throw new DomainException('Shopee pair ownership changed.');
        }
        if ($row->apply_status === 'updated' && ! $shopee) {
            throw new DomainException('Shopee pair ownership missing.');
        }
    }

    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function catalogHash(): string
    {
        return $this->hash([$this->catalog->catalogStateFingerprint(),
            ProductVariant::orderBy('id')->get(['id', 'product_id', 'volume', 'price', 'sku', 'is_active'])->toArray(),
            DB::table('product_images')->orderBy('id')->get()->toArray(),
        ]);
    }
}
