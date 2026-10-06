<?php

namespace App\Actions\Products;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Services\ProductImportPayloadHasher;
use App\Services\ProductImportProductSnapshot;
use App\Services\ProductMediaStorage;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** One-time, reviewed launch transfer into an empty catalog; never a recurring sync. */
class BootstrapLaunchCatalog
{
    public const VERSION = 'launch-bootstrap-v1';

    public function __construct(
        private MapExternalProductIdentity $identities,
        private SyncSingleOffer $offers,
        private AttachProductImage $images,
        private PublishProduct $publish,
        private ProductMediaStorage $storage,
        private ProductImportPayloadHasher $hasher,
        private ProductImportProductSnapshot $snapshots,
    ) {}

    public function preview(array $data, array $media): ProductImportBatch
    {
        $rows = $this->validatedRows($data, $media);
        $fingerprint = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        $key = hash('sha256', self::VERSION.':one-owner-approved-launch');

        return DB::transaction(function () use ($rows, $fingerprint, $key): ProductImportBatch {
            $existing = ProductImportBatch::where('contract_version', self::VERSION)
                ->where('idempotency_key', $key)->first();
            if ($existing) {
                if (! hash_equals($existing->source_fingerprint, $fingerprint)) {
                    throw new DomainException('A different launch packet was already reviewed for this target.');
                }

                return $existing->load('rows');
            }
            $this->assertEmpty();
            $batch = ProductImportBatch::create([
                'actor_id' => null, 'source_filename' => 'owner-approved-launch.json',
                'source_size' => strlen(json_encode($rows, JSON_THROW_ON_ERROR)),
                'source_fingerprint' => $fingerprint, 'contract_version' => self::VERSION,
                'catalog_state_fingerprint' => hash('sha256', 'empty-launch-catalog'),
                'idempotency_key' => $key, 'status' => 'previewed',
                'total_rows' => count($rows), 'valid_rows' => count($rows),
                'review_rows' => 0, 'error_rows' => 0,
            ]);
            foreach ($rows as $index => $row) {
                $batch->rows()->create([
                    'line_number' => $index + 1, 'status' => 'valid', 'candidate_action' => 'create',
                    'provider' => 'qammaris_app', 'external_product_id' => $row['uuid'],
                    'normalized_data' => $row, 'issues' => [],
                    'payload_hash' => $this->hasher->hash($row, [], 'create'),
                ]);
            }

            return $batch->load('rows');
        });
    }

    public function apply(int $batchId): ProductImportBatch
    {
        return DB::transaction(function () use ($batchId): ProductImportBatch {
            $batch = ProductImportBatch::whereKey($batchId)->lockForUpdate()->firstOrFail();
            if ($batch->contract_version !== self::VERSION) {
                throw new DomainException('This is not a reviewed launch bootstrap.');
            }
            if ($batch->status === 'applied') {
                return $batch->load('rows');
            }
            if ($batch->status !== 'previewed') {
                throw new DomainException('Launch preview is not applicable.');
            }
            $this->assertEmpty();
            $rows = $batch->rows()->lockForUpdate()->get();
            if ($rows->count() !== $batch->total_rows) {
                throw new DomainException('Launch preview row count changed.');
            }
            $payloads = [];
            foreach ($rows as $row) {
                if ($row->candidate_action !== 'create' || $row->issues !== [] ||
                    ! hash_equals($row->payload_hash, $this->hasher->hash($row->normalized_data, [], 'create'))) {
                    throw new DomainException('Launch preview changed.');
                }
                $payloads[] = $row->normalized_data;
                $this->verifyMedia($row->normalized_data['images']);
            }
            if (! hash_equals($batch->source_fingerprint, hash('sha256', json_encode($payloads, JSON_THROW_ON_ERROR)))) {
                throw new DomainException('Launch preview fingerprint changed.');
            }
            foreach ($rows as $row) {
                $data = $row->normalized_data;
                $attributes = $data['product'];
                $slug = $attributes['slug'];
                $attributes['brand_id'] = $this->taxonomy(Brand::class, $data['brand']);
                $attributes['category_id'] = $this->taxonomy(Category::class, $data['category']);
                $attributes['is_active'] = false;
                $attributes['publication_status'] = 'draft';
                $product = Product::create($attributes);
                // Slugs are reviewed source values, not regenerated target identifiers.
                $product->forceFill(['slug' => $slug, 'qammaris_app_hidden' => false,
                    'availability_restock_eta' => $attributes['availability_restock_eta'] ?? null])->save();
                if ($data['offer'] !== null) {
                    $this->offers->handle($product, $data['offer']);
                }
                foreach ($data['identities'] as $identity) {
                    $this->identities->handle($product, $identity['provider'], $identity['external_product_id']);
                }
                foreach ($data['images'] as $image) {
                    $this->images->handle($product, $image['path'], $image['is_primary']);
                }
                if ($data['publish']) {
                    $this->publish->handle($product);
                }
                $row->update([
                    'apply_status' => 'created', 'applied_product_id' => $product->id,
                    'apply_message' => 'Owner-approved fresh launch; source IDs remapped, fixture excluded.',
                    'before_snapshot' => null, 'after_snapshot' => $this->snapshots->capture($product),
                    'applied_at' => now(),
                ]);
            }
            $batch->update(['status' => 'applied', 'applied_by' => null, 'applied_at' => now(),
                'applied_rows' => $rows->count(), 'blocked_rows' => 0]);

            return $batch->load('rows');
        }, 3);
    }

    private function assertEmpty(): void
    {
        foreach (['products', 'product_variants', 'product_images', 'product_external_identities', 'brands', 'categories'] as $table) {
            if (DB::table($table)->exists()) {
                throw new DomainException('Launch bootstrap requires an empty catalog; existing records are never replaced.');
            }
        }
    }

    private function validatedRows(array $data, array $media): array
    {
        if (($data['schema'] ?? null) !== 'fresh-launch-transfer-preview-v1' ||
            ($data['fixture_excluded'] ?? null) !== 1 || ($data['source_ids_only'] ?? null) !== true) {
            throw new DomainException('Unsupported launch packet or fixture exclusion.');
        }
        Validator::make($data, [
            'products' => 'required|array|min:1', 'products.*.id' => 'required|integer|min:2|distinct',
            'products.*.name' => 'required|string|max:255', 'products.*.slug' => 'required|string|max:255|distinct',
            'products.*.description' => 'nullable|string', 'products.*.gender' => 'nullable|in:Pria,Wanita,Unisex',
            'products.*.publication_status' => 'required|in:published,draft',
            'products.*.base_price' => ['nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'products.*.availability_status' => 'required|in:available,sold_out,unknown',
            'products.*.availability_source' => 'required|in:qammaris_app',
            'offers' => 'present|array', 'images' => 'present|array', 'identities' => 'required|array',
            'brands' => 'present|array', 'categories' => 'present|array',
            'identities.*.provider' => 'required|in:qammaris_app,shopee',
            'identities.*.external_product_id' => 'required|string|max:191',
            'offers.*.volume' => 'required|integer|min:1|max:65535',
            'offers.*.price' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/', 'numeric', 'gt:0'],
            'offers.*.sku' => 'nullable|string|max:255',
        ])->validate();
        $ids = array_column($data['products'], 'id');
        foreach (['offers', 'images', 'identities'] as $relation) {
            foreach ($data[$relation] as $item) {
                if (! in_array($item['product_id'] ?? null, $ids, true)) {
                    throw new DomainException('Launch packet has orphan relationships.');
                }
            }
        }
        $manifest = collect($media['media'] ?? []);
        if ($manifest->count() !== count($data['images']) || $manifest->pluck('path')->unique()->count() !== $manifest->count()) {
            throw new DomainException('Launch media manifest differs from the catalog.');
        }
        $manifest = $manifest->keyBy('path');
        $brands = collect($data['brands'])->keyBy('id');
        $categories = collect($data['categories'])->keyBy('id');
        $rows = [];
        $identityKeys = [];
        foreach ($data['products'] as $product) {
            $productId = $product['id'];
            $identities = array_values(array_filter($data['identities'], fn ($i) => $i['product_id'] === $productId));
            if (count(array_filter($identities, fn ($i) => $i['provider'] === 'qammaris_app')) !== 1 ||
                count(array_unique(array_column($identities, 'provider'))) !== count($identities)) {
                throw new DomainException('Each launch product needs exactly one UUID and at most one identity per provider.');
            }
            foreach ($identities as $i) {
                if ($i['provider'] === 'qammaris_app' && ! Str::isUuid($i['external_product_id'])) {
                    throw new DomainException('Invalid launch UUID.');
                }
                $key = $i['provider'].':'.$i['external_product_id'];
                if (isset($identityKeys[$key])) {
                    throw new DomainException('Duplicate launch identity.');
                }
                $identityKeys[$key] = true;
            }
            $offers = array_values(array_filter($data['offers'], fn ($i) => $i['product_id'] === $productId));
            if (count($offers) > 1 || (isset($offers[0]) && (float) $offers[0]['price'] !== (float) $product['base_price'])) {
                throw new DomainException('Launch offer/price is inconsistent.');
            }
            $images = array_values(array_filter($data['images'], fn ($i) => $i['product_id'] === $productId));
            usort($images, fn ($a, $b) => ($b['is_primary'] <=> $a['is_primary']) ?: ($a['sort_order'] <=> $b['sort_order']));
            $imageData = [];
            foreach ($images as $image) {
                $entry = $manifest->get($image['image_path']);
                if (! $entry || ($entry['staging_product_id'] ?? null) !== $productId ||
                    ($entry['staging_image_id'] ?? null) !== $image['id']) {
                    throw new DomainException('Launch image ownership differs from manifest.');
                }
                $imageData[] = [...$entry, 'is_primary' => (bool) $image['is_primary']];
            }
            $this->verifyMedia($imageData);
            if (count($imageData) > 3 || ($imageData !== [] && count(array_filter($imageData, fn ($i) => $i['is_primary'])) !== 1)) {
                throw new DomainException('Launch primary image/count is invalid.');
            }
            $row = [
                'source_id' => $productId, 'uuid' => collect($identities)->firstWhere('provider', 'qammaris_app')['external_product_id'],
                'publish' => $product['publication_status'] === 'published',
                'product' => Arr::only($product, ['name', 'slug', 'description', 'base_price', 'compare_at_price',
                    'fragrance_notes', 'gender', 'is_best_seller', 'meta_description', 'availability_status',
                    'availability_source', 'availability_checked_at', 'availability_restock_eta']),
                'brand' => $this->validatedTaxonomy($brands->get($product['brand_id']), $product['brand_id']),
                'category' => $this->validatedTaxonomy($categories->get($product['category_id']), $product['category_id']),
                'offer' => isset($offers[0]) ? Arr::only($offers[0], ['volume', 'price', 'stock', 'sku']) : null,
                'identities' => array_map(fn ($i) => Arr::only($i, ['provider', 'external_product_id']), $identities),
                'images' => $imageData,
            ];
            if ($row['publish'] && ($row['offer'] === null || $imageData === [] || ! $row['brand'] || ! $row['category'])) {
                throw new DomainException('Published launch product is incomplete.');
            }
            if (! $row['publish'] && ($imageData !== [] || filled($product['description']))) {
                throw new DomainException('This launch retains only the approved image-less, description-less drafts.');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function validatedTaxonomy(?array $item, mixed $id): ?array
    {
        if ($id === null) {
            return null;
        }
        if ($item === null) {
            throw new DomainException('Launch taxonomy is missing.');
        }
        Validator::make($item, ['name' => 'required|string|max:255', 'slug' => 'required|string|max:255',
            'description' => 'nullable|string', 'is_active' => 'required|boolean'])->validate();

        return Arr::only($item, ['name', 'slug', 'description', 'is_active']);
    }

    private function taxonomy(string $model, ?array $data): ?int
    {
        if ($data === null) {
            return null;
        }
        $item = $model::firstOrCreate(['name' => $data['name']], $data);
        $item->forceFill(['slug' => $data['slug']])->save();

        return $item->id;
    }

    private function verifyMedia(array $images): void
    {
        foreach ($images as $image) {
            $path = $image['path'] ?? null;
            if (! is_string($path) || $this->storage->normalizeProductPath($path) !== $path ||
                ! preg_match('/^[a-f0-9]{64}$/', $image['sha256'] ?? '') || ! $this->storage->exists($path)) {
                throw new DomainException('Launch image is missing or its path/checksum is invalid.');
            }
            $bytes = Storage::disk($this->storage->diskName())->get($path);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            if (strlen($bytes) !== ($image['bytes'] ?? null) || ! hash_equals($image['sha256'], hash('sha256', $bytes)) ||
                ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $mime !== ($image['mime_type'] ?? null)) {
                throw new DomainException('Launch image bytes/checksum/MIME do not match the reviewed manifest.');
            }
        }
    }
}
