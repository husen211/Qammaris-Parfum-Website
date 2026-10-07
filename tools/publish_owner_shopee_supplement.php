<?php

// One Owner-approved 21-product production operation; not a general import API.
use App\Actions\Products\AttachProductImage;
use App\Actions\Products\EvaluateProductPublicationReadiness;
use App\Actions\Products\MapExternalProductIdentity;
use App\Actions\Products\PrepareQammarisAppDrafts;
use App\Actions\Products\PublishProduct;
use App\Actions\Products\SyncSingleOffer;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\ProductImportBatch;
use App\Services\ImportedProductImageDownloader;
use App\Services\ProductCatalogRowFingerprint;
use App\Services\ProductImportPayloadHasher;
use App\Services\ProductMediaStorage;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function supplementalHash(mixed $value): string
{
    return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
}

function supplementalSnapshot(Product $product): array
{
    return ['product' => $product->getAttributes(),
        'variants' => $product->variants()->orderBy('id')->get()->map->getAttributes()->all(),
        'images' => $product->images()->withTrashed()->orderBy('id')->get()->map->getAttributes()->all(),
        'identities' => $product->externalIdentities()->orderBy('id')->get()->map->getAttributes()->all()];
}

function supplementalGuard(array $input): array
{
    $cached = DB::table('qammaris_app_products')->where('id', $input['uuid'])->first();
    $source = $cached ? json_decode($cached->snapshot, true, flags: JSON_THROW_ON_ERROR) : null;
    if (! $source || supplementalHash($source) !== supplementalHash($input['source'])
        || $source['hidden'] || ! $source['active'] || $source['merged_into'] !== null
        || ! is_int($source['price']) || $source['price'] < 1 || $source['price'] > 99999999) {
        throw new RuntimeException('Source changed or is not publishable: row '.$input['row']);
    }
    $identity = ProductExternalIdentity::where('provider', 'qammaris_app')
        ->where('external_product_id', $input['uuid'])->first();
    $product = $identity ? Product::with(['brand', 'category', 'variants', 'images', 'externalIdentities'])->findOrFail($identity->product_id) : null;
    if ($product?->id !== $input['expected_product_id']) {
        throw new RuntimeException('Target ownership changed: row '.$input['row']);
    }
    if (ProductExternalIdentity::where('provider', 'shopee')->where('external_product_id', $input['shopee_id'])->exists()) {
        throw new RuntimeException('Shopee source is already bound: row '.$input['row']);
    }
    if ($product && ($product->publication_status !== 'draft' || $product->is_active || $product->qammaris_app_hidden
        || ! $product->brand?->is_active || $product->images->isNotEmpty()
        || trim((string) $product->description) !== '' || $product->externalIdentities->contains('provider', 'shopee')
        || ! hash_equals($input['expected_fingerprint'], app(ProductCatalogRowFingerprint::class)->hash($product)))) {
        throw new RuntimeException('Target is no longer an unchanged blank draft: row '.$input['row']);
    }
    if ($product && ($product->variants->count() > 1
        || ($product->variants->isNotEmpty() && (! $product->variants->first()->is_active || (int) $product->variants->first()->volume !== $input['size_ml'])))) {
        throw new RuntimeException('Existing size/offer conflicts: row '.$input['row']);
    }
    if ($product?->category && (! $product->category->is_active || $product->category->name !== $input['category'])) {
        throw new RuntimeException('Existing category conflicts: row '.$input['row']);
    }

    return [$source, $product];
}

function supplementalFill(array $data, array $media): Product
{
    [$source, $product] = supplementalGuard($data);
    $product ??= app(PrepareQammarisAppDrafts::class)->createFromSnapshot($source);
    if (! $product) {
        throw new RuntimeException('New UUID has an unresolved existing-product candidate.');
    }
    $categories = Category::where('name', $data['category'])->lockForUpdate()->get();
    if ($categories->count() > 1 || ($categories->count() === 1 && ! $categories->first()->is_active)) {
        throw new RuntimeException('Category is ambiguous or inactive.');
    }
    $category = $categories->first();
    if (! $category && $data['category'] === 'Hair & Body Mist') {
        $category = Category::create(['name' => 'Hair & Body Mist', 'is_active' => true]);
    }
    if (! $category) {
        throw new RuntimeException('Approved category is unavailable.');
    }
    app(MapExternalProductIdentity::class)->handle($product, 'shopee', $data['shopee_id']);
    $fields = ['description' => $data['description'], 'category_id' => $category->id, 'gender' => $data['gender']];
    if ($data['correct_name'] !== null) {
        $fields['name'] = $data['correct_name'];
    }
    $product->forceFill($fields)->save();
    $offer = $product->variants()->first();
    app(SyncSingleOffer::class)->handle($product, ['id' => $offer?->id, 'volume' => $data['size_ml'],
        'price' => $source['price'], 'stock' => $offer?->stock ?? 0, 'sku' => $offer?->sku]);
    if (count($media) !== count($data['photos']) || count($media) < 1 || count($media) > 3) {
        throw new RuntimeException('Incomplete approved photo set.');
    }
    $disk = Storage::disk(app(ProductMediaStorage::class)->diskName());
    foreach ($media as $image) {
        $bytes = $disk->get($image['object_key']);
        if ($image['status'] !== 'stored' || strlen($bytes) !== $image['size'] || ! hash_equals($image['checksum'], hash('sha256', $bytes))) {
            throw new RuntimeException('Stored image integrity changed.');
        }
        app(AttachProductImage::class)->handle($product, $image['object_key']);
    }

    return app(PublishProduct::class)->handle($product->fresh());
}

function supplementalInspectTransferredImage(string $path, array $record): UploadedFile
{
    $size = filesize($path);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $dimensions = @getimagesize($path);
    $types = config('product_imports.image_acquisition.allowed_mime_types');
    if ($size < 1 || $size > config('product_imports.image_acquisition.max_bytes', 8388608)
        || $size !== $record['size'] || ! hash_equals($record['checksum'], hash_file('sha256', $path))
        || ! isset($types[$mime]) || $mime !== $record['mime_type'] || $types[$mime] !== $record['extension']
        || ! is_array($dimensions) || $dimensions[0] !== $record['width'] || $dimensions[1] !== $record['height']
        || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 12000 || $dimensions[1] > 12000) {
        throw new RuntimeException('Transferred photo does not match verified bytes/MIME/dimensions.');
    }

    return new UploadedFile($path, 'shopee.'.$record['extension'], $mime, UPLOAD_ERR_OK, true);
}

if (defined('QAMMARIS_SUPPLEMENTAL_LIBRARY_ONLY')) {
    return;
}

try {
    require getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (! $app->environment('production') || DB::connection()->getDriverName() !== 'mysql'
        || DB::connection()->getDatabaseName() !== 'u429527638_qam_launch') {
        throw new RuntimeException('Approved production target required.');
    }
    $mode = $argv[1] ?? '';
    if (! in_array($mode, ['preview', 'download', 'ingest', 'apply', 'verify'], true)) {
        throw new RuntimeException('Explicit operation mode required.');
    }
    $path = storage_path('app/private/p8-09-owner-21-20261006/approved.json');
    $approvedHash = '2d42fabcc24dc23400ec9f413934d92f8cccc76804f735a87b54b63bd885f67e';
    if (! is_file($path) || ! hash_equals($approvedHash, hash_file('sha256', $path))) {
        throw new RuntimeException('Exact approved source manifest required.');
    }
    $input = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $rows = $input['data'];
    if ($input['schema'] !== 'owner-shopee-v1' || count($rows) !== 21
        || count(array_unique(array_column($rows, 'uuid'))) !== 21
        || count(array_unique(array_column($rows, 'shopee_id'))) !== 21) {
        throw new RuntimeException('Exact distinct 21-product scope required.');
    }
    foreach ($rows as $row) {
        if (! Str::isUuid($row['uuid']) || ! preg_match('/^[0-9]{1,30}$/', $row['shopee_id'])
            || ! in_array($row['gender'], ['Pria', 'Wanita', 'Unisex'], true)
            || ! in_array($row['category'], ['Eau de Parfum', 'Extrait de Parfum', 'Hair & Body Mist'], true)
            || ! is_int($row['size_ml']) || $row['size_ml'] < 1 || $row['size_ml'] > 10000
            || trim($row['description']) === '' || mb_strlen($row['description']) > 20000
            || count($row['photos']) < 1 || count($row['photos']) > 3) {
            throw new RuntimeException('Approved row validation failed.');
        }
        foreach ($row['photos'] as $url) {
            if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https'
                || ! in_array(parse_url($url, PHP_URL_HOST), config('product_imports.image_acquisition.allowed_hosts'), true)) {
                throw new RuntimeException('Unapproved image source.');
            }
        }
    }
    $key = supplementalHash(['owner-shopee-v1', $approvedHash]);
    $batch = ProductImportBatch::where('idempotency_key', $key)->first();
    if ($batch?->status === 'applied') {
        $verified = [];
        foreach ($batch->rows as $r) {
            $p = Product::with('images', 'variants', 'externalIdentities')->findOrFail($r->applied_product_id);
            $data = $r->normalized_data;
            if ($p->publication_status !== 'published' || ! $p->is_active || app(EvaluateProductPublicationReadiness::class)->handle($p) !== []
                || $p->externalIdentities->firstWhere('provider', 'shopee')?->external_product_id !== $data['shopee_id']
                || $p->externalIdentities->firstWhere('provider', 'qammaris_app')?->external_product_id !== $data['uuid']) {
                throw new RuntimeException('Published result verification failed.');
            }
            $verified[] = ['name' => $p->name, 'slug' => $p->slug, 'size_ml' => $p->variants->first()->volume,
                'price' => $p->base_price, 'photos' => $p->images->count(), 'availability' => $p->availability_status];
        }
        echo json_encode(['mode' => $mode, 'batch_id' => $batch->id, 'replay_no_op' => true,
            'verified' => $verified, 'published_total' => Product::where('publication_status', 'published')->count(),
            'draft_total' => Product::where('publication_status', 'draft')->count()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return;
    }
    if ($mode === 'download' || $mode === 'ingest') {
        if (! $batch || $batch->status !== 'previewed') {
            throw new RuntimeException('Inspect persisted preview before downloading.');
        }
        $incoming = storage_path('app/private/p8-09-owner-21-20261006/incoming');
        $transferred = $mode === 'ingest' ? json_decode(file_get_contents($incoming.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR) : null;
        if ($transferred !== null && count($transferred) !== 60) {
            throw new RuntimeException('Expected exactly the60verified approved photo candidates.');
        }
        foreach ($batch->rows as $r) {
            $data = $r->normalized_data;
            if (! hash_equals($r->payload_hash, app(ProductImportPayloadHasher::class)->hash($data, [], 'supplement_publish'))) {
                throw new RuntimeException('Preview payload changed.');
            }
            supplementalGuard($data);
            $outcomes = $r->image_acquisition_outcomes ?? [];
            foreach ($data['photos'] as $index => $url) {
                if (isset($outcomes[$index]) && $outcomes[$index]['status'] === 'stored'
                    && app(ProductMediaStorage::class)->exists($outcomes[$index]['object_key'])) {
                    continue;
                }
                if ($mode === 'ingest') {
                    $record = $transferred[$data['row'].'-'.$index] ?? null;
                    if (! $record || $record['source_row'] !== $data['row'] || $record['photo_index'] !== $index
                        || $record['filename'] !== $data['row'].'-'.$index.'.'.$record['extension']
                        || ! preg_match('/^[0-9]+-[012]\.(jpg|png|webp)$/', $record['filename'])) {
                        throw new RuntimeException('Transferred source slot mismatch.');
                    }
                    $file = supplementalInspectTransferredImage($incoming.'/'.$record['filename'], $record);
                    $stored = app(ProductMediaStorage::class)->store($file);
                    $outcomes[$index] = ['slot' => ['foto_utama_url', 'foto_2_url', 'foto_3_url'][$index], 'status' => 'stored',
                        'object_key' => $stored, 'checksum' => $record['checksum'], 'size' => $record['size'],
                        'mime_type' => $record['mime_type'], 'width' => $record['width'], 'height' => $record['height'],
                        'acquisition' => 'same-Laravel-downloader-local-SSH-transfer-server-reinspection'];
                    $r->forceFill(['image_acquisition_outcomes' => $outcomes, 'image_acquisition_status' => 'processing'])->save();

                    continue;
                }
                $download = null;
                try {
                    $download = app(ImportedProductImageDownloader::class)->download($url);
                    $file = new UploadedFile($download['path'], 'shopee.'.$download['extension'], $download['mime_type'], UPLOAD_ERR_OK, true);
                    $stored = app(ProductMediaStorage::class)->store($file);
                    $outcomes[$index] = ['slot' => ['foto_utama_url', 'foto_2_url', 'foto_3_url'][$index], 'status' => 'stored',
                        'object_key' => $stored, 'checksum' => $download['checksum'], 'size' => $download['size'],
                        'mime_type' => $download['mime_type'], 'width' => $download['width'], 'height' => $download['height']];
                    $r->forceFill(['image_acquisition_outcomes' => $outcomes, 'image_acquisition_status' => 'processing'])->save();
                } finally {
                    if ($download && is_file($download['path'])) {
                        unlink($download['path']);
                    }
                }
            }
            $r->forceFill(['image_acquisition_status' => 'completed', 'image_acquisition_completed_at' => now()])->save();
        }
        echo json_encode(['mode' => $mode, 'batch_id' => $batch->id, 'rows' => 21,
            'stored_photos' => $batch->rows()->get()->sum(fn ($r) => count($r->image_acquisition_outcomes ?? [])), 'catalog_unchanged' => true]);

        return;
    }
    DB::beginTransaction();
    DB::table('qammaris_app_sync_states')->where('id', 'products')->lockForUpdate()->firstOrFail();
    DB::table('qammaris_app_products')->whereIn('id', array_column($rows, 'uuid'))->orderBy('id')->lockForUpdate()->get();
    Product::whereIn('id', array_filter(array_column($rows, 'expected_product_id')))->orderBy('id')->lockForUpdate()->get();
    $prepared = [];
    foreach ($rows as $index => $data) {
        [, $p] = supplementalGuard($data);
        $prepared[] = ['line_number' => $index + 2, 'status' => 'valid', 'candidate_action' => 'supplement_publish',
            'provider' => 'shopee', 'external_product_id' => $data['shopee_id'], 'matched_product_id' => $p?->id,
            'normalized_data' => $data, 'issues' => [], 'payload_hash' => app(ProductImportPayloadHasher::class)->hash($data, [], 'supplement_publish'),
            'apply_status' => 'pending', 'before_snapshot' => $p ? supplementalSnapshot($p) : null];
    }
    if (! $batch) {
        if ($mode !== 'preview') {
            throw new RuntimeException('Persist and inspect the preview first.');
        }
        $batch = ProductImportBatch::create(['actor_id' => null, 'source_filename' => 'owner-approved-shopee21-production-20261006',
            'source_size' => filesize($path), 'source_fingerprint' => $approvedHash, 'contract_version' => 'owner-shopee-v1',
            'catalog_state_fingerprint' => supplementalHash(array_column($prepared, 'before_snapshot')), 'idempotency_key' => $key,
            'status' => 'previewed', 'total_rows' => 21, 'valid_rows' => 21, 'review_rows' => 0, 'error_rows' => 0]);
        $batch->rows()->createMany($prepared);
    }
    $batch = ProductImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
    if ($batch->status !== 'previewed' || ! hash_equals($batch->catalog_state_fingerprint, supplementalHash(array_column($prepared, 'before_snapshot')))) {
        throw new RuntimeException('Source/target preview changed.');
    }
    $persisted = $batch->rows()->lockForUpdate()->get();
    if ($persisted->count() !== 21) {
        throw new RuntimeException('Audit preview rows changed.');
    }
    foreach ($persisted as $index => $r) {
        $expected = $prepared[$index];
        if ($r->candidate_action !== 'supplement_publish' || $r->apply_status !== 'pending' || $r->issues !== []
            || $r->provider !== 'shopee' || $r->external_product_id !== $expected['external_product_id']
            || $r->matched_product_id !== $expected['matched_product_id'] || $r->line_number !== $expected['line_number']
            || ! hash_equals($r->payload_hash, $expected['payload_hash'])
            || supplementalHash($r->normalized_data) !== supplementalHash($expected['normalized_data'])
            || supplementalHash($r->before_snapshot) !== supplementalHash($expected['before_snapshot'])) {
            throw new RuntimeException('Persisted preview tampered or changed.');
        }
    }
    if ($mode === 'apply') {
        $oldPublished = Product::where('publication_status', 'published')->orderBy('id')->get()->map(fn ($p) => supplementalSnapshot($p));
        $protectedHash = supplementalHash($oldPublished);
        foreach ($persisted as $r) {
            $p = supplementalFill($r->normalized_data, $r->image_acquisition_outcomes ?? []);
            $r->forceFill(['apply_status' => $r->matched_product_id ? 'updated' : 'created', 'applied_product_id' => $p->id,
                'after_snapshot' => supplementalSnapshot($p), 'applied_at' => now(),
                'apply_message' => 'Explicit Owner21 production enrichment/publication; app price; retained existing URLs; machine CLI attribution.'])->save();
        }
        $unchanged = Product::whereIn('id', $oldPublished->pluck('product.id'))->orderBy('id')->get()->map(fn ($p) => supplementalSnapshot($p));
        if (! hash_equals($protectedHash, supplementalHash($unchanged))) {
            throw new RuntimeException('Previously published catalog changed; rolling back.');
        }
        $batch->forceFill(['status' => 'applied', 'applied_at' => now(), 'applied_by' => null, 'applied_rows' => 21, 'blocked_rows' => 0])->save();
    } elseif ($mode !== 'preview') {
        throw new RuntimeException('Use apply only after media acquisition.');
    }
    DB::commit();
    echo json_encode(['mode' => $mode, 'batch_id' => $batch->id, 'status' => $batch->status, 'rows' => 21,
        'old_published_retained' => $mode === 'apply', 'published_total' => Product::where('publication_status', 'published')->count(),
        'draft_total' => Product::where('publication_status', 'draft')->count()], JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    if (isset($app) && DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    echo json_encode(['error' => 'Owner21 operation stopped without catalog commit.',
        'type' => get_class($error), 'detail' => $error instanceof RuntimeException ? $error->getMessage() : 'Value-free framework failure.']);
    exit(1);
}
