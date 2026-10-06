<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\ProductVariant;
use App\Services\ProductCatalogRowFingerprint;
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
        return $this->previewInput($input);
    }

    public function previewOwnerChoices(array $choices, array $review, int $sourceBatchId): ProductImportBatch
    {
        $this->assertEnvironment();
        Validator::make($choices, [
            'schema' => ['required', 'in:qammaris-shopee-owner-choices-v1'],
            'provenance' => ['required', 'array:mapping_sha256,media_sha256,basic_sha256'],
            'capture_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'captured_at' => ['required', 'string', 'max:64'],
            'data' => ['required', 'array', 'min:1', 'max:1000'],
            'data.*' => ['required', 'array:shopee_id,shopee_name,majoo_sku,uuid,website_id,expected_fingerprint,requires_conflict_review,existing_shopee_id'],
            'data.*.shopee_id' => ['required', 'string', 'regex:/^[0-9]{1,30}$/', 'distinct:strict'],
            'data.*.shopee_name' => ['required', 'string', 'max:255'],
            'data.*.majoo_sku' => ['required', 'string', 'max:255', 'distinct:strict'],
            'data.*.uuid' => ['required', 'uuid', 'distinct:strict'],
            'data.*.website_id' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'data.*.expected_fingerprint' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'data.*.requires_conflict_review' => ['required', 'boolean'],
            'data.*.existing_shopee_id' => ['present', 'nullable', 'string', 'max:30'],
        ])->validate();
        if (! array_is_list($choices['data']) || ($review['schema'] ?? null) !== 'qammaris-shopee-pair-review-v1'
            || ($review['provenance'] ?? null) !== $choices['provenance']
            || ($review['capture_sha256'] ?? null) !== $choices['capture_sha256']
            || ($review['captured_at'] ?? null) !== $choices['captured_at'] || ! is_array($review['data'] ?? null)) {
            throw new DomainException('Owner choice review provenance mismatch.');
        }
        $source = ProductImportBatch::with('rows')->findOrFail($sourceBatchId);
        if ($source->contract_version !== self::VERSION || $source->actor_id !== null || $source->status !== 'applied') {
            throw new DomainException('Expected the original applied pairing batch.');
        }
        $reviewRows = collect($review['data'])->keyBy('shopee_id');
        if ($reviewRows->count() !== count($review['data'])) {
            throw new DomainException('Duplicate review source.');
        }
        $rows = [];
        $evidence = [];
        foreach ($choices['data'] as $choice) {
            $original = $source->rows->firstWhere('external_product_id', $choice['shopee_id']);
            $shown = $reviewRows->get($choice['shopee_id']);
            if (! $original || ! $shown || $original->candidate_action !== 'review'
                || ! in_array($original->normalized_data['status'], ['perlu_cek', 'ambigu'], true)
                || ! hash_equals($original->payload_hash, $this->hasher->hash($original->normalized_data, $original->issues, $original->candidate_action))) {
                throw new DomainException('Not an unchanged candidate-review source row.');
            }
            $raw = $original->normalized_data;
            foreach (['shopee_id', 'shopee_name', 'description', 'photos', 'status'] as $field) {
                if (($shown[$field] ?? null) !== $raw[$field]) {
                    throw new DomainException('Review content differs from audited source.');
                }
            }
            $matches = collect($shown['candidates'])->filter(fn ($c) => ($c['sku'] ?? null) === $choice['majoo_sku']
                && ($c['uuid'] ?? null) === $choice['uuid'] && ($c['website_id'] ?? null) === $choice['website_id']);
            $candidate = $matches->count() === 1 ? $matches->first() : null;
            $conflict = $candidate && ! empty($candidate['existing_shopee_id']) && $candidate['existing_shopee_id'] !== $choice['shopee_id'];
            if ($raw['provenance'] !== $choices['provenance'] || $choice['shopee_name'] !== $raw['shopee_name']
                || ! collect($raw['candidates'])->contains(fn ($c) => $c['sku'] === $choice['majoo_sku'])
                || ! $candidate || ($candidate['fingerprint'] ?? null) !== $choice['expected_fingerprint']
                || ($candidate['existing_shopee_id'] ?? null) !== $choice['existing_shopee_id']
                || $choice['requires_conflict_review'] !== (bool) $conflict) {
                throw new DomainException('Choice is not an exact supplied review candidate.');
            }
            $rows[] = array_merge(array_intersect_key($raw, array_flip(['shopee_id', 'shopee_name', 'majoo_sku', 'status', 'description', 'photos', 'candidates'])), [
                'majoo_sku' => $choice['majoo_sku'], 'status' => 'owner',
            ]);
            $evidence[$choice['shopee_id']] = [
                'choice' => $choice, 'source_batch_id' => $source->id, 'capture_sha256' => $choices['capture_sha256'],
                'choices_sha256' => $this->hash($choices), 'review_sha256' => $this->hash($review),
            ];
        }

        return $this->previewInput(['schema' => self::VERSION, 'provenance' => $choices['provenance'], 'data' => $rows], $evidence);
    }

    private function previewInput(array $input, array $ownerEvidence = []): ProductImportBatch
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
            'data.*.status' => ['required', 'in:sku,kuat,perlu_cek,ambigu,tidak_ketemu'.($ownerEvidence ? ',owner' : '')],
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

        return DB::transaction(function () use ($input, $ownerEvidence): ProductImportBatch {
            DB::table('qammaris_app_sync_states')->where('id', 'products')->lockForUpdate()->first();
            $sources = DB::table('qammaris_app_products')->orderBy('id')->lockForUpdate()->get()
                ->map(fn ($s) => json_decode($s->snapshot, true, flags: JSON_THROW_ON_ERROR));
            $bySku = $sources->filter(fn ($s) => is_string($s['sku'] ?? null) && $s['sku'] !== '')->groupBy('sku');
            $approvedSkus = collect($input['data'])->filter(fn ($r) => in_array($r['status'], ['sku', 'kuat', 'owner'], true))->countBy('majoo_sku');
            $catalogHash = $this->catalogHash();
            $sourceHash = $this->hash([$input, $ownerEvidence]);
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
                if (in_array($raw['status'], ['sku', 'kuat', 'owner'], true)) {
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
                                $issue = $raw['status'] === 'owner' ? 'owner_choice_changed_or_description_conflict' : 'existing_description_conflict';
                                if ((trim((string) $product->description) === '' || $product->description === $raw['description'])
                                    && $this->ownerTargetMatches($ownerEvidence[$raw['shopee_id']] ?? null, $source, $product, $raw)) {
                                    $action = 'pair';
                                    $issue = '';
                                }
                            }
                        }
                    }
                }
                $data = array_merge($raw, [
                    'provenance' => $input['provenance'],
                    'owner_selection' => $ownerEvidence[$raw['shopee_id']] ?? null,
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
                'actor_id' => null, 'source_filename' => $ownerEvidence ? 'owner-selected-shopee-candidates' : 'owner-authorized-shopee-mapping', 'source_size' => strlen(json_encode($input)),
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
                    'apply_message' => $row->normalized_data['status'] === 'owner'
                        ? 'Explicit Owner candidate choice; existing fields/media and draft status retained.'
                        : 'Owner-approved exact SKU/UUID Shopee pair; existing fields/media and draft status retained.',
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

    private function ownerTargetMatches(?array $evidence, array $source, Product $product, array $raw): bool
    {
        if ($raw['status'] !== 'owner') {
            return true;
        }
        $choice = $evidence['choice'] ?? null;

        return $choice && $choice['requires_conflict_review'] === false
            && $choice['uuid'] === $source['id'] && $choice['website_id'] === $product->id
            && hash_equals($choice['expected_fingerprint'], app(ProductCatalogRowFingerprint::class)->hash($product));
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
