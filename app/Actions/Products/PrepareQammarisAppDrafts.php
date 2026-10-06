<?php

namespace App\Actions\Products;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\ProductImportBatch;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ImportedProductName;
use App\Services\ProductImportPayloadHasher;
use App\Services\ProductImportPreviewer;
use App\Services\ProductImportProductSnapshot;
use App\Services\QammarisAppMappingReview;
use App\Services\ShopeeProductMediaReview;
use DomainException;
use Illuminate\Support\Facades\DB;

class PrepareQammarisAppDrafts
{
    public const VERSION = 'qammaris-drafts-v1';

    public function __construct(
        private ImportedProductName $names,
        private ShopeeProductMediaReview $media,
        private QammarisAppMappingReview $mappingReview,
        private ProductImportPreviewer $catalog,
        private ProductImportPayloadHasher $hasher,
        private MapExternalProductIdentity $map,
        private SyncSingleOffer $offers,
        private ApplyQammarisAppAvailability $availability,
        private ProductImportProductSnapshot $productSnapshot,
    ) {}

    public function preview(array $media, ?User $actor = null): ProductImportBatch
    {
        return DB::transaction(function () use ($media, $actor): ProductImportBatch {
            // Same lock order as feed consumption: checkpoint -> source -> product.
            DB::table('qammaris_app_sync_states')->where('id', 'products')->lockForUpdate()->first();
            $sources = DB::table('qammaris_app_products')->orderBy('id')->lockForUpdate()->get()
                ->map(fn ($row) => json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR))->all();
            if ($sources === []) {
                throw new DomainException('No synchronized source snapshots.');
            }
            $catalog = Product::with(['brand', 'variants', 'externalIdentities'])->orderBy('id')->get()->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'publication_status' => $p->publication_status,
                'brand' => $p->brand?->name, 'size_ml' => $p->variants->where('is_active', true)->count() === 1
                    ? $p->variants->where('is_active', true)->first()->volume : null,
                'sku' => $p->variants->where('is_active', true)->count() === 1
                    ? $p->variants->where('is_active', true)->first()->sku : null,
                'source_uuid' => $p->externalIdentities->firstWhere('provider', 'qammaris_app')?->external_product_id,
            ])->all();
            $candidates = collect($this->mappingReview->rows($catalog, $sources, 'current-catalog'))
                ->filter(fn ($row) => $row['source_uuid'] !== '' && ! str_starts_with($row['review_status'], 'mapped'))
                ->groupBy('source_uuid');
            $matches = $this->media->match(array_values(array_filter($sources, fn ($s) => ! $s['hidden'])), $media);
            $sourceHash = $this->hash([$sources, $media]);
            $catalogHash = $this->catalogHash();
            $key = $this->hash([self::VERSION, 'owner-authorized-cli', $actor?->id, $sourceHash, $catalogHash]);
            $existing = ProductImportBatch::where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing->load('rows');
            }
            $rows = [];
            foreach ($sources as $index => $s) {
                $identity = ProductExternalIdentity::where('provider', 'qammaris_app')->where('external_product_id', $s['id'])->first();
                $action = 'create';
                $issues = [];
                if ($s['hidden'] || $identity) {
                    $action = 'skip';
                    $issues[] = $s['hidden'] ? 'hidden_tombstone' : 'existing_uuid_retained';
                } elseif ($candidates->has($s['id'])) {
                    $action = 'conflict';
                    $issues[] = 'existing_catalog_candidate_review_before_creation';
                }
                if (mb_strlen($s['name']) > 255 || trim($s['name']) === '') {
                    $action = 'conflict';
                    $issues[] = 'name_does_not_fit_website';
                }
                $size = $this->names->size($s['name']);
                $concentration = $this->names->concentration($s['name']);
                $price = is_int($s['price']) && $s['price'] > 0 && $s['price'] <= 99999999 ? $s['price'] : null;
                $photo = $matches[$s['id']] ?? ['status' => 'hidden', 'media' => null, 'candidates' => []];
                foreach (['size' => $size, 'concentration' => $concentration, 'price' => $price, 'brand' => $s['brand']] as $field => $value) {
                    if ($value === null || $value === '') {
                        $issues[] = $field.'_requires_review';
                    }
                }
                if ($s['source'] === 'app') {
                    $issues[] = 'source_app_requires_owner_review';
                }
                if ($photo['status'] !== 'strong') {
                    $issues[] = 'photo_'.$photo['status'];
                }
                if ($photo['status'] === 'strong' && trim($photo['media']['photos'][0]) === '') {
                    $issues[] = 'photo_cover_missing';
                    $photo['media']['photos'] = ['', '', ''];
                }
                if ($photo['media'] && ProductExternalIdentity::where('provider', 'shopee')
                    ->where('external_product_id', $photo['media']['id'])->exists() && ! $identity) {
                    $issues[] = 'shopee_identity_already_owned';
                    $photo['media'] = null;
                }
                $data = [
                    'nama_produk' => $s['name'], 'brand' => $s['brand'], 'harga' => $price,
                    'ukuran_ml' => $size, 'kategori' => $concentration, 'source' => $s['source'],
                    'source_snapshot' => $s, 'source_hash' => $this->hash($s),
                    'shopee_id' => $photo['media']['id'] ?? null, 'shopee_name' => $photo['media']['name'] ?? null,
                    'photo_match' => $photo['status'], 'photo_candidates' => $photo['candidates'],
                    'foto_utama_url' => $photo['media']['photos'][0] ?? '',
                    'foto_2_url' => $photo['media']['photos'][1] ?? '', 'foto_3_url' => $photo['media']['photos'][2] ?? '',
                ];
                $issues = array_map(fn ($issue) => ['field' => 'source', 'severity' => 'review', 'message' => $issue], $issues);
                $rows[] = [
                    'line_number' => $index + 1, 'status' => $action === 'conflict' ? 'error' : 'review',
                    'candidate_action' => $action, 'provider' => 'qammaris_app', 'external_product_id' => $s['id'],
                    'matched_product_id' => $identity?->product_id, 'normalized_data' => $data, 'issues' => $issues,
                    'payload_hash' => $this->hasher->hash($data, $issues, $action),
                ];
            }
            $batch = ProductImportBatch::create([
                'actor_id' => $actor?->id, 'source_filename' => $actor ? 'qammaris-feed-admin' : 'owner-authorized-qammaris-feed', 'source_size' => strlen(json_encode($sources)),
                'source_fingerprint' => $sourceHash, 'contract_version' => self::VERSION, 'catalog_state_fingerprint' => $catalogHash,
                'idempotency_key' => $key, 'status' => 'previewed', 'total_rows' => count($rows), 'valid_rows' => 0,
                'review_rows' => count(array_filter($rows, fn ($r) => $r['status'] === 'review')),
                'error_rows' => count(array_filter($rows, fn ($r) => $r['status'] === 'error')),
            ]);
            $batch->rows()->createMany($rows);

            return $batch->load('rows');
        }, 3);
    }

    public function apply(int $batchId, ?User $actor = null): ProductImportBatch
    {
        return DB::transaction(function () use ($batchId, $actor): ProductImportBatch {
            DB::table('qammaris_app_sync_states')->where('id', 'products')->lockForUpdate()->first();
            $batch = ProductImportBatch::whereKey($batchId)->lockForUpdate()->firstOrFail();
            if ($batch->contract_version !== self::VERSION || $batch->actor_id !== $actor?->id) {
                throw new DomainException('Not a Qammaris draft batch.');
            }
            if ($batch->status === 'applied') {
                return $batch->load('rows');
            }
            if ($batch->status !== 'previewed' || ! hash_equals($batch->catalog_state_fingerprint, $this->catalogHash())) {
                throw new DomainException('Catalog changed. Create a new preview.');
            }
            $rows = $batch->rows()->lockForUpdate()->get();
            foreach ($rows as $row) {
                $source = DB::table('qammaris_app_products')->where('id', $row->external_product_id)->lockForUpdate()->first();
                if (! $source || ! hash_equals($row->normalized_data['source_hash'], $this->hash(json_decode($source->snapshot, true, flags: JSON_THROW_ON_ERROR)))
                    || ! hash_equals($row->payload_hash, $this->hasher->hash($row->normalized_data, $row->issues, $row->candidate_action))) {
                    throw new DomainException('Source or preview changed. Create a new preview.');
                }
            }
            $created = 0;
            foreach ($rows as $row) {
                if ($row->candidate_action !== 'create') {
                    $row->update(['apply_status' => $row->candidate_action === 'conflict' ? 'blocked_error' : 'skipped_no_changes',
                        'apply_message' => 'Retained for review; existing products and tombstones unchanged.', 'applied_at' => now()]);

                    continue;
                }
                $data = $row->normalized_data;
                if (ProductExternalIdentity::where('provider', 'qammaris_app')->where('external_product_id', $row->external_product_id)->exists()) {
                    throw new DomainException('UUID mapping changed.');
                }
                $product = $this->createDraft($data, $row->external_product_id);
                $row->update(['apply_status' => 'created', 'applied_product_id' => $product->id,
                    'apply_message' => 'Qammaris feed created draft; source/app fields and publish completeness require review.',
                    'before_snapshot' => null, 'after_snapshot' => $this->productSnapshot->capture($product), 'applied_at' => now()]);
                $created++;
            }
            $batch->update(['status' => 'applied', 'applied_at' => now(), 'applied_by' => $actor?->id,
                'applied_rows' => $created, 'blocked_rows' => $rows->count() - $created]);

            return $batch->load('rows');
        }, 3);
    }

    /** Caller holds checkpoint/source locks in the same transaction as the feed. */
    public function createFromSnapshot(array $source): ?Product
    {
        if ($source['hidden'] || trim($source['name']) === '' || mb_strlen($source['name']) > 255) {
            return null;
        }
        $identity = ProductExternalIdentity::where('provider', 'qammaris_app')->where('external_product_id', $source['id'])->first();
        if ($identity) {
            return $identity->product;
        }
        $catalog = Product::with(['brand', 'variants', 'externalIdentities'])->orderBy('id')->get()->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'publication_status' => $p->publication_status,
            'brand' => $p->brand?->name, 'size_ml' => $p->variants->where('is_active', true)->first()?->volume,
            'sku' => $p->variants->where('is_active', true)->first()?->sku,
            'source_uuid' => $p->externalIdentities->firstWhere('provider', 'qammaris_app')?->external_product_id,
        ])->all();
        $candidate = collect($this->mappingReview->rows($catalog, [$source], 'feed'))
            ->contains(fn ($row) => $row['source_uuid'] === $source['id'] && ! str_starts_with($row['review_status'], 'mapped'));
        if ($candidate) {
            return null;
        }
        $price = $source['price'];

        return $this->createDraft([
            'nama_produk' => $source['name'], 'brand' => $source['brand'],
            'kategori' => $this->names->concentration($source['name']),
            'ukuran_ml' => $this->names->size($source['name']),
            'harga' => is_int($price) && $price > 0 && $price <= 99999999 ? $price : null,
            'shopee_id' => null, 'source_snapshot' => $source,
        ], $source['id']);
    }

    private function createDraft(array $data, string $uuid): Product
    {
        $product = Product::create([
            'name' => $data['nama_produk'], 'brand_id' => $this->taxonomy(Brand::class, $data['brand']),
            'category_id' => $this->taxonomy(Category::class, $data['kategori']), 'base_price' => $data['harga'],
            'description' => null, 'gender' => null, 'is_active' => false, 'publication_status' => 'draft',
            'published_at' => null, 'stock_quantity' => null, 'availability_status' => 'unknown',
        ]);
        $this->map->handle($product, 'qammaris_app', $uuid);
        if ($data['shopee_id'] !== null) {
            $this->map->handle($product, 'shopee', $data['shopee_id']);
        }
        if ($data['ukuran_ml'] !== null && $data['harga'] !== null) {
            // Upstream SKU may be truncated/nonunique. UUID stays the integration key.
            $this->offers->handle($product, ['volume' => $data['ukuran_ml'], 'price' => $data['harga'], 'sku' => null]);
        }
        $this->availability->handle($product, $data['source_snapshot'], true);

        return $product;
    }

    private function taxonomy(string $model, ?string $name): ?int
    {
        if ($name === null || trim($name) === '' || mb_strlen($name) > 255) {
            return null;
        }
        $matches = $model::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->lockForUpdate()->get();
        if ($matches->isNotEmpty()) {
            return $matches->count() === 1 && $matches->first()->is_active ? $matches->first()->id : null;
        }

        return $model::create(['name' => trim($name), 'is_active' => true])->id;
    }

    private function hash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function catalogHash(): string
    {
        return $this->hash([
            $this->catalog->catalogStateFingerprint(),
            ProductVariant::orderBy('id')->get(['id', 'product_id', 'volume', 'price', 'sku', 'is_active'])->toArray(),
            DB::table('product_images')->orderBy('id')->get()->toArray(),
        ]);
    }
}
