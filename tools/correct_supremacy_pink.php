<?php

// Owner-approved correction only: Shopee Pink 42131600634, website 879 -> 686.
use App\Actions\Products\AttachProductImage;
use App\Actions\Products\EvaluateProductPublicationReadiness;
use App\Actions\Products\PublishProduct;
use App\Actions\Products\SyncSingleOffer;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Services\ProductMediaStorage;
use App\Services\ShopeeContentPreviewer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function pinkSnapshot(Product $product): array
{
    return ['product' => $product->getAttributes(),
        'variants' => $product->variants()->orderBy('id')->get()->map->getAttributes()->all(),
        'images' => $product->images()->withTrashed()->orderBy('id')->get()->map->getAttributes()->all(),
        'identities' => $product->externalIdentities()->orderBy('id')->get()->map->getAttributes()->all()];
}

function pinkHash(array $data): string
{
    return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
}

function pinkProducts(): array
{
    return [Product::with(['brand', 'category', 'variants', 'images', 'externalIdentities'])->findOrFail(879),
        Product::with(['brand', 'category', 'variants', 'images', 'externalIdentities'])->findOrFail(686)];
}

function pinkGuard(Product $purple, Product $pink): void
{
    foreach ([[$purple, 'f9b89ff7-7900-49b6-ac07-a012eea8c04d', '6290171002055'],
        [$pink, '84a10c0b-c5ba-4d9c-a622-d8d7969327f7', '6290171002048']] as [$product, $uuid, $sku]) {
        if ($product->externalIdentities->firstWhere('provider', 'qammaris_app')?->external_product_id !== $uuid) {
            throw new RuntimeException('Application identity changed.');
        }
        $raw = DB::table('qammaris_app_products')->where('id', $uuid)->value('snapshot');
        $source = $raw ? json_decode($raw, true, flags: JSON_THROW_ON_ERROR) : null;
        if (! $source || $source['sku'] !== $sku || $source['hidden'] || ! $source['active'] || $source['merged_into'] !== null
            || ! is_int($source['price']) || $source['price'] < 1 || $source['price'] > 99999999) {
            throw new RuntimeException('Application source is unavailable or changed.');
        }
    }
    if (ProductExternalIdentity::where('provider', 'shopee')->where('external_product_id', '42131600634')->value('product_id') !== 879
        || $pink->externalIdentities->contains('provider', 'shopee') || $pink->publication_status !== 'draft'
        || $pink->images->isNotEmpty() || $pink->variants->isNotEmpty() || trim((string) $pink->description) !== ''
        || $pink->category_id !== null || $pink->gender !== null || $pink->brand_id !== $purple->brand_id
        || ! $pink->brand?->is_active || $purple->publication_status !== 'published'
        || ! str_contains(strtoupper($purple->description), 'SUPREMACY PINK')
        || $purple->category?->name !== 'Eau de Parfum' || ! $purple->category->is_active
        || $purple->gender !== 'Wanita' || $purple->images->count() !== 3
        || $purple->images->where('is_primary', true)->count() !== 1
        || $purple->variants->count() !== 1 || (int) $purple->variants->first()->volume !== 100) {
        throw new RuntimeException('Exact approved two-product baseline changed.');
    }
}

function pinkMutate(array $manifest, ProductImportRow $sourceRow): ProductImportBatch
{
    $previewer = app(ShopeeContentPreviewer::class);
    $source = $sourceRow->normalized_data['source'];
    DB::table('qammaris_app_sync_states')->where('id', 'products')->lockForUpdate()->first();
    DB::table('qammaris_app_products')->whereIn('id', ['f9b89ff7-7900-49b6-ac07-a012eea8c04d', '84a10c0b-c5ba-4d9c-a622-d8d7969327f7'])->orderBy('id')->lockForUpdate()->get();
    Product::whereIn('id', [686, 879])->orderBy('id')->lockForUpdate()->get();
    [$purple, $pink] = pinkProducts();
    pinkGuard($purple, $pink);
    if ($manifest['fingerprints'] !== [$previewer->fingerprint($purple), $previewer->fingerprint($pink)]
        || pinkHash($source) !== pinkHash($manifest['source'])) {
        throw new RuntimeException('Preview stale; correction not applied.');
    }
    // The one explicit rebind exception is recorded below; ordinary import guards remain intact.
    $identity = ProductExternalIdentity::where('provider', 'shopee')->where('external_product_id', '42131600634')->lockForUpdate()->sole();
    $identity->forceFill(['product_id' => 686])->save();
    $pink->forceFill(['description' => $purple->description, 'category_id' => $purple->category_id, 'gender' => 'Wanita'])->save();
    app(SyncSingleOffer::class)->handle($pink, ['volume' => 100, 'price' => $pink->base_price, 'stock' => 0]);
    foreach ($manifest['media'] as $media) {
        app(AttachProductImage::class)->handle($pink, $media['to']);
    }
    $purple->forceFill(['publication_status' => 'draft', 'is_active' => false, 'description' => null])->save();
    foreach ($purple->images as $image) {
        $image->delete(); // Soft archive: original metadata/files are retained.
    }
    app(PublishProduct::class)->handle($pink->fresh());
    [$purple, $pink] = pinkProducts();
    $key = hash('sha256', 'owner-supremacy-pink-correction-20261007');
    $batch = ProductImportBatch::create(['actor_id' => $sourceRow->batch->actor_id, 'applied_by' => $sourceRow->batch->actor_id,
        'source_filename' => 'owner-approved-supremacy-pink-correction-20261007', 'source_size' => 0,
        'source_fingerprint' => pinkHash($source), 'catalog_state_fingerprint' => pinkHash($manifest['before']),
        'contract_version' => 'owner-pink-fix-v1', 'idempotency_key' => $key, 'status' => 'applied', 'applied_at' => now(),
        'total_rows' => 2, 'valid_rows' => 2, 'review_rows' => 0, 'error_rows' => 0, 'applied_rows' => 2, 'blocked_rows' => 0]);
    foreach ([$purple, $pink] as $index => $product) {
        $batch->rows()->create(['line_number' => $index + 1, 'status' => 'valid', 'candidate_action' => 'identity_correction',
            'provider' => 'shopee', 'external_product_id' => '42131600634', 'matched_product_id' => $product->id,
            'normalized_data' => ['source' => $source, 'owner_authorization' => 'Explicit Pink/Purple correction 2026-10-07; machine SSH executor', 'source_batch' => 6],
            'issues' => [], 'payload_hash' => pinkHash($source), 'apply_status' => 'updated', 'applied_product_id' => $product->id,
            'before_snapshot' => $manifest['before'][$index], 'after_snapshot' => pinkSnapshot($product), 'applied_at' => now(),
            'apply_message' => 'Owner-approved Pink content/identity correction; original UUIDs, slugs, source prices/status retained. Original media soft-archived, not deleted.']);
    }

    return $batch;
}

// Importing the procedure in isolated tests must not execute its production driver.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}

try {
    require getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (! $app->environment('production') || DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'u429527638_qam_launch') {
        throw new RuntimeException('Approved production database required.');
    }
    $mode = $argv[1] ?? 'preview';
    if (! in_array($mode, ['preview', 'rehearse', 'apply', 'verify'], true)) {
        throw new RuntimeException('Explicit correction mode required.');
    }
    $disk = Storage::disk(app(ProductMediaStorage::class)->diskName());
    $private = Storage::disk('local');
    $manifestPath = 'supremacy-pink-20261007/preview.json';
    $receiptPath = 'supremacy-pink-20261007/applied.json';
    [$purple, $pink] = pinkProducts();
    if ($mode === 'verify' || $private->exists($receiptPath)) {
        $receipt = json_decode($private->get($receiptPath), true, flags: JSON_THROW_ON_ERROR);
        if ($purple->publication_status !== 'draft' || $purple->images->isNotEmpty()
            || trim((string) $purple->description) !== '' || $pink->publication_status !== 'published'
            || $pink->images->count() !== 3 || $pink->images->where('is_primary', true)->count() !== 1
            || ProductExternalIdentity::where('provider', 'shopee')->where('external_product_id', '42131600634')->value('product_id') !== 686
            || app(EvaluateProductPublicationReadiness::class)->handle($pink) !== []) {
            throw new RuntimeException('Correction verification failed.');
        }
        foreach ($pink->images as $image) {
            if (! $disk->exists($image->image_path)) {
                throw new RuntimeException('Corrected photo missing.');
            }
        }
        echo json_encode(['verified' => true, 'audit_batch' => $receipt['audit_batch'], 'pink' => $pink->only(['id', 'name', 'slug', 'publication_status', 'availability_status', 'base_price']),
            'purple' => $purple->only(['id', 'name', 'slug', 'publication_status', 'availability_status', 'base_price']), 'pink_images' => 3], JSON_THROW_ON_ERROR).PHP_EOL;
        exit;
    }
    pinkGuard($purple, $pink);
    $sourceRow = ProductImportRow::where('batch_id', 6)->where('external_product_id', '42131600634')->sole();
    $source = $sourceRow->normalized_data['source'];
    if ($source['id'] !== '42131600634' || ! str_contains(strtolower($source['name']), 'supremacy pink')
        || ! str_contains(strtolower($source['name']), '100ml')) {
        throw new RuntimeException('Exact Shopee Pink source required.');
    }
    $previewer = app(ShopeeContentPreviewer::class);
    if ($mode === 'preview') {
        $manifest = ['before' => [pinkSnapshot($purple), pinkSnapshot($pink)],
            'fingerprints' => [$previewer->fingerprint($purple), $previewer->fingerprint($pink)],
            'source_row' => $sourceRow->getAttributes(), 'source' => $source, 'media' => []];
        foreach ($purple->images->sortBy('sort_order') as $image) {
            $bytes = $disk->get($image->image_path);
            if (! is_string($bytes) || strlen($bytes) === 0) {
                throw new RuntimeException('Source photo missing.');
            }
            $manifest['media'][] = ['from' => $image->image_path, 'to' => 'products/supremacy-pink-686-20261007-'.$image->id.'.'.pathinfo($image->image_path, PATHINFO_EXTENSION),
                'checksum' => hash('sha256', $bytes), 'size' => strlen($bytes)];
        }
        if ($private->exists($manifestPath)) {
            throw new RuntimeException('Preview already exists; do not overwrite rollback evidence.');
        }
        if (! $private->put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Rollback snapshot could not be persisted.');
        }
        echo json_encode(['preview' => true, 'from_product' => 879, 'to_product' => 686, 'shopee_id' => '42131600634',
            'photos' => 3, 'pink_size_ml' => 100, 'pink_gender' => 'Wanita', 'purple_after' => 'draft', 'pink_after' => 'published',
            'source_history' => ProductImportRow::where('provider', 'shopee')->where('external_product_id', '42131600634')->orderBy('id')->get(['id', 'batch_id', 'matched_product_id', 'applied_product_id'])->toArray()], JSON_THROW_ON_ERROR).PHP_EOL;
        exit;
    }
    $manifest = json_decode($private->get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
    foreach ($manifest['media'] as $media) {
        if (! hash_equals($media['checksum'], hash('sha256', $disk->get($media['from'])))) {
            throw new RuntimeException('Source photo bytes changed.');
        }
        if (! $disk->exists($media['to']) && ! $disk->copy($media['from'], $media['to'])) {
            throw new RuntimeException('Photo copy failed.');
        }
        if (! hash_equals($media['checksum'], hash('sha256', $disk->get($media['to'])))) {
            throw new RuntimeException('Copied photo failed verification.');
        }
    }
    DB::beginTransaction();
    $batch = pinkMutate($manifest, $sourceRow);
    [$purple, $pink] = pinkProducts();
    if ($mode === 'rehearse') {
        DB::rollBack();
        [$purple, $pink] = pinkProducts();
        if ($manifest['fingerprints'] !== [$previewer->fingerprint($purple), $previewer->fingerprint($pink)]) {
            throw new RuntimeException('Rehearsal rollback failed.');
        }
        echo "Rehearsal passed: readiness, exact rebind, media and audit; database rollback verified.\n";
    } else {
        DB::commit();
        if (! $private->put($receiptPath, json_encode(['audit_batch' => $batch->id, 'applied_at' => now()->toIso8601String(),
            'after' => [pinkSnapshot($purple), pinkSnapshot($pink)]], JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Committed correction: receipt write failed; inspect audit before retry.');
        }
        echo json_encode(['applied' => true, 'audit_batch' => $batch->id, 'pink_id' => 686, 'purple_id' => 879], JSON_THROW_ON_ERROR).PHP_EOL;
    }
} catch (Throwable $error) {
    if (isset($app) && DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
}
