<?php

// One approved staging operation, not a general publication/import interface.
use App\Actions\Products\EvaluateProductPublicationReadiness;
use App\Actions\Products\PublishProduct;
use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Services\ProductCatalogRowFingerprint;
use App\Services\ProductImportPayloadHasher;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('staging') || DB::connection()->getDriverName() !== 'mysql'
    || DB::connection()->getDatabaseName() !== 'u429527638_qam_staging') {
    throw new RuntimeException('This operation is restricted to the approved staging database.');
}

$mode = $argv[1] ?? 'preview';
if (! in_array($mode, ['preview', 'rehearse', 'apply'], true)) {
    throw new RuntimeException('Use preview, rehearse or apply.');
}
$path = storage_path('app/private/p8-06-audience-final-20261006/approved-genders.csv');
$approvedHash = 'bd85112bb23b5f12fe4c19c44a5cc86b204467ff296515203d715c3ec623f7b8';
if (! is_file($path) || ! hash_equals($approvedHash, hash_file('sha256', $path))) {
    throw new RuntimeException('The exact Owner-approved list is required.');
}
$handle = fopen($path, 'rb');
$headers = fgetcsv($handle, escape: '');
$headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
if ($headers !== ['product_id', 'uuid', 'expected_updated_at', 'expected_row_fingerprint', 'slug', 'name', 'brand', 'category', 'gender', 'price', 'size_ml', 'availability']) {
    throw new RuntimeException('Unexpected approval-list columns.');
}
$input = [];
while (($values = fgetcsv($handle, escape: '')) !== false) {
    if (count($values) !== count($headers)) {
        throw new RuntimeException('Malformed approval row.');
    }
    $input[] = array_combine($headers, $values);
}
fclose($handle);
if (count($input) !== 53 || count(array_unique(array_column($input, 'product_id'))) !== 53
    || count(array_unique(array_column($input, 'uuid'))) !== 53 || in_array('1', array_column($input, 'product_id'), true)) {
    throw new RuntimeException('Only the exact distinct 53-product wave is authorized.');
}

if (array_map('intval', array_column($input, 'product_id')) !== [9, 12, 13, 54, 57, 62, 72, 79, 90, 98, 102, 113, 136, 141, 144, 147, 150, 151, 156, 158, 163, 167, 170, 178, 191, 199, 212, 214, 221, 235, 244, 247, 249, 270, 282, 287, 288, 301, 361, 366, 372, 373, 383, 401, 404, 416, 423, 424, 430, 433, 434, 445, 446]) {
    throw new RuntimeException('Only the exact 53 Owner-approved gender records are authorized.');
}

function finalAudienceHash(mixed $value): string
{
    return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function finalAudienceSnapshot(Product $product): array
{
    return [
        'product' => $product->getAttributes(),
        'variants' => $product->variants()->orderBy('id')->lockForUpdate()->get()->map->getAttributes()->all(),
        'images' => $product->images()->withTrashed()->orderBy('id')->lockForUpdate()->get()->map->getAttributes()->all(),
        'identities' => $product->externalIdentities()->orderBy('id')->lockForUpdate()->get()->map->getAttributes()->all(),
    ];
}

function finalAudienceAuditDigest(ProductImportBatch $batch): string
{
    return finalAudienceHash([$batch->getAttributes(), $batch->rows()->get()->map->getAttributes()->all()]);
}

// Same lock order as the stock feed: checkpoint -> source -> products.
// Rehearsal calls the real publisher and audit writes, then rolls ALL of them back.
DB::beginTransaction();
try {
    DB::table('qammaris_app_sync_states')->where('id', 'products')->lockForUpdate()->firstOrFail();
    $sources = DB::table('qammaris_app_products')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    $key = finalAudienceHash(['owner-gender-v1', $approvedHash]);
    $batch = ProductImportBatch::where('idempotency_key', $key)->lockForUpdate()->first();
    if ($batch && ($batch->contract_version !== 'owner-gender-v1' || $batch->source_fingerprint !== $approvedHash)) {
        throw new RuntimeException('Publication audit contract mismatch.');
    }
    if ($batch?->status === ProductImportBatch::STATUS_APPLIED) {
        $result = ['mode' => $mode, 'batch_id' => $batch->id, 'status' => 'applied', 'rows' => $batch->applied_rows,
            'replay_no_op' => true, 'audit_digest' => finalAudienceAuditDigest($batch)];
        DB::rollBack();
        echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    }
    if ($batch && $batch->status !== ProductImportBatch::STATUS_PREVIEWED) {
        throw new RuntimeException('An invalid/stale/failed audit cannot be reactivated.');
    }
    if (! $batch && $mode !== 'preview') {
        throw new RuntimeException('Persist and inspect the preview first.');
    }
    $products = Product::whereIn('id', array_column($input, 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    if ($products->count() !== 53) {
        throw new RuntimeException('An approved product is missing.');
    }
    $existingRows = $batch?->rows()->lockForUpdate()->get()->keyBy('matched_product_id');
    if ($existingRows && $existingRows->count() !== 53) {
        throw new RuntimeException('Publication preview row count changed.');
    }
    $prepared = [];
    foreach ($input as $index => $raw) {
        $product = $products->get((int) $raw['product_id']);
        $snapshot = finalAudienceSnapshot($product);
        $source = $sources->get($raw['uuid']);
        $source = $source ? json_decode($source->snapshot, true, flags: JSON_THROW_ON_ERROR) : null;
        $identities = collect($snapshot['identities']);
        $mapped = $identities->where('provider', 'qammaris_app');
        if (! $source || $source['hidden'] || ! $source['active'] || $source['merged_into'] !== null
            || $product->qammaris_app_hidden || $product->is_active || $product->publication_status !== Product::PUBLICATION_DRAFT
            || $mapped->count() !== 1 || $mapped->first()['external_product_id'] !== $raw['uuid']
            || $identities->where('provider', 'shopee')->count() !== 1) {
            throw new RuntimeException('Publication target is no longer a visible paired draft: '.$product->id);
        }
        if ($product->updated_at->toIso8601String() !== $raw['expected_updated_at']
            || ! hash_equals($raw['expected_row_fingerprint'], app(ProductCatalogRowFingerprint::class)->hash($product))
            || $product->slug !== $raw['slug'] || $product->name !== $raw['name']
            || $product->gender !== null || ! in_array($raw['gender'], ['Pria', 'Wanita', 'Unisex'], true)
            || (string) $product->base_price !== $raw['price'] || (float) $product->base_price <= 0
            || array_keys(app(EvaluateProductPublicationReadiness::class)->handle($product)) !== ['gender']) {
            throw new RuntimeException('Approval guard or readiness changed: '.$product->id);
        }
        $data = ['owner_approval' => 'Owner final 53-ID gender list, including explicit Unisex conflicts; staging publication only; preserve names/copy/prices/offers/media/stock/URLs', 'approval' => $raw, 'source_hash' => finalAudienceHash($source), 'product_hash' => finalAudienceHash($snapshot)];
        $payloadHash = app(ProductImportPayloadHasher::class)->hash($data, [], 'gender_publish');
        if ($existingRows) {
            $row = $existingRows->get($product->id);
            if (! $row || $row->candidate_action !== 'gender_publish' || $row->status !== 'valid' || $row->issues !== []
                || $row->provider !== 'qammaris_app' || $row->external_product_id !== $raw['uuid']
                || $row->line_number !== $index + 2 || $row->apply_status !== 'pending'
                || ! hash_equals($payloadHash, $row->payload_hash)
                || finalAudienceHash($row->normalized_data) !== finalAudienceHash($data)
                || finalAudienceHash($row->before_snapshot) !== finalAudienceHash($snapshot)) {
                throw new RuntimeException('Preview, source or product changed: '.$product->id);
            }
        }
        $prepared[] = ['line_number' => $index + 2, 'status' => 'valid', 'candidate_action' => 'gender_publish',
            'provider' => 'qammaris_app', 'external_product_id' => $raw['uuid'], 'matched_product_id' => $product->id,
            'normalized_data' => $data, 'issues' => [], 'payload_hash' => $payloadHash,
            'apply_status' => 'pending', 'before_snapshot' => $snapshot];
    }
    if (! $batch) {
        $batch = ProductImportBatch::create([
            'actor_id' => null, 'source_filename' => 'owner-final-53-genders-staging-publication-20261006', 'source_size' => filesize($path),
            'source_fingerprint' => $approvedHash, 'contract_version' => 'owner-gender-v1',
            'catalog_state_fingerprint' => finalAudienceHash(array_column($prepared, 'before_snapshot')),
            'idempotency_key' => $key, 'status' => 'previewed', 'total_rows' => 53, 'valid_rows' => 53,
            'review_rows' => 0, 'error_rows' => 0, 'applied_rows' => 0, 'blocked_rows' => 0,
        ]);
        foreach ($prepared as $row) {
            $batch->rows()->create($row);
        }
    }
    if (! hash_equals($batch->catalog_state_fingerprint, finalAudienceHash(array_column($prepared, 'before_snapshot')))) {
        throw new RuntimeException('Preview catalog guard changed.');
    }
    if ($mode !== 'preview') {
        foreach ($batch->rows()->get() as $row) {
            $product = $products->get($row->matched_product_id);
            $product->forceFill(['gender' => $row->normalized_data['approval']['gender']])->save();
            $product = app(PublishProduct::class)->handle($product);
            $after = finalAudienceSnapshot($product);
            foreach ($row->before_snapshot['product'] as $field => $value) {
                if (! in_array($field, ['gender', 'publication_status', 'is_active', 'published_at', 'archived_at', 'updated_at'], true)
                    && $after['product'][$field] !== $value) {
                    throw new RuntimeException('Publisher changed a protected field.');
                }
            }
            foreach (['variants', 'images', 'identities'] as $relation) {
                if ($after[$relation] !== $row->before_snapshot[$relation]) {
                    throw new RuntimeException('Publisher changed a protected relationship.');
                }
            }
            if ($product->gender !== $row->normalized_data['approval']['gender']) {
                throw new RuntimeException('Owner gender was not applied exactly.');
            }
            if (! $product->isPubliclyVisible()) {
                throw new RuntimeException('Published product is not visible.');
            }
            $row->update(['apply_status' => 'updated', 'applied_product_id' => $product->id,
                'apply_message' => 'Owner final exact 53 genders and staging publication; existing PublishProduct gate used.',
                'after_snapshot' => $after, 'applied_at' => now()]);
        }
        $batch->update(['status' => 'applied', 'applied_rows' => 53, 'blocked_rows' => 0, 'applied_by' => null, 'applied_at' => now()]);
    }
    $result = ['mode' => $mode, 'batch_id' => $batch->id, 'status' => $batch->status, 'rows' => 53,
        'approval_sha256' => $approvedHash, 'rolled_back' => $mode === 'rehearse', 'replay_no_op' => false,
        'audit_digest' => finalAudienceAuditDigest($batch)];
    if ($mode === 'rehearse') {
        DB::rollBack();
    } else {
        DB::commit();
    }
    echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    DB::rollBack();
    throw $exception;
}
