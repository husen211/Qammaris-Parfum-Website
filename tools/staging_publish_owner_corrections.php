<?php

// One approved staging operation, not a general publication/import interface.
use App\Actions\Products\EvaluateProductPublicationReadiness;
use App\Actions\Products\PublishProduct;
use App\Actions\Products\SyncSingleOffer;
use App\Models\Category;
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
$path = storage_path('app/private/p8-06-owner-followup-20261006/approved-corrections.csv');
$approvedHash = 'fa63aa3c03bf037e4eab0b8eb1988783b12ed79531407fe94629028ef603227e';
if (! is_file($path) || ! hash_equals($approvedHash, hash_file('sha256', $path))) {
    throw new RuntimeException('The exact Owner-approved list is required.');
}
$handle = fopen($path, 'rb');
$headers = fgetcsv($handle, escape: '');
$headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
if ($headers !== ['product_id', 'uuid', 'expected_updated_at', 'expected_row_fingerprint', 'slug', 'name', 'brand', 'category', 'gender', 'price', 'size_ml', 'availability', 'create_offer']) {
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
if (count($input) !== 5 || count(array_unique(array_column($input, 'product_id'))) !== 5
    || count(array_unique(array_column($input, 'uuid'))) !== 5 || in_array('1', array_column($input, 'product_id'), true)) {
    throw new RuntimeException('Only the exact distinct 5-product wave is authorized.');
}

if (array_map('intval', array_column($input, 'product_id')) !== [29, 34, 35, 36, 350]) {
    throw new RuntimeException('Only the five specifically approved catalog records are authorized.');
}

function ownerCorrectionHash(mixed $value): string
{
    return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function ownerCorrectionSnapshot(Product $product): array
{
    return [
        'product' => $product->getAttributes(),
        'variants' => $product->variants()->orderBy('id')->lockForUpdate()->get()->map->getAttributes()->all(),
        'images' => $product->images()->withTrashed()->orderBy('id')->lockForUpdate()->get()->map->getAttributes()->all(),
        'identities' => $product->externalIdentities()->orderBy('id')->lockForUpdate()->get()->map->getAttributes()->all(),
    ];
}

function ownerCorrectionAuditDigest(ProductImportBatch $batch): string
{
    return ownerCorrectionHash([$batch->getAttributes(), $batch->rows()->get()->map->getAttributes()->all()]);
}

// Same lock order as the stock feed: checkpoint -> source -> products.
// Rehearsal calls the real publisher and audit writes, then rolls ALL of them back.
DB::beginTransaction();
try {
    DB::table('qammaris_app_sync_states')->where('id', 'products')->lockForUpdate()->firstOrFail();
    $sources = DB::table('qammaris_app_products')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    $key = ownerCorrectionHash(['owner-correct-v1', $approvedHash]);
    $batch = ProductImportBatch::where('idempotency_key', $key)->lockForUpdate()->first();
    if ($batch && ($batch->contract_version !== 'owner-correct-v1' || $batch->source_fingerprint !== $approvedHash)) {
        throw new RuntimeException('Publication audit contract mismatch.');
    }
    if ($batch?->status === ProductImportBatch::STATUS_APPLIED) {
        $result = ['mode' => $mode, 'batch_id' => $batch->id, 'status' => 'applied', 'rows' => $batch->applied_rows,
            'replay_no_op' => true, 'audit_digest' => ownerCorrectionAuditDigest($batch)];
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
    if ($products->count() !== 5) {
        throw new RuntimeException('An approved product is missing.');
    }
    $existingRows = $batch?->rows()->lockForUpdate()->get()->keyBy('matched_product_id');
    if ($existingRows && $existingRows->count() !== 5) {
        throw new RuntimeException('Publication preview row count changed.');
    }
    $prepared = [];
    foreach ($input as $index => $raw) {
        $product = $products->get((int) $raw['product_id']);
        $snapshot = ownerCorrectionSnapshot($product);
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
            || (string) $product->base_price !== $raw['price'] || (float) $product->base_price <= 0
            || (float) $source['price'] !== (float) $raw['price']) {
            throw new RuntimeException('Approval guard or readiness changed: '.$product->id);
        }
        $categories = Category::active()->where('name', $raw['category'])->lockForUpdate()->get();
        if ($categories->count() !== 1 || ! in_array($raw['gender'], ['Pria', 'Unisex'], true)) {
            throw new RuntimeException('Approved category/audience unavailable.');
        }
        $expectedBlockers = $raw['create_offer'] === '1' ? ['gender', 'offer'] : ['category', 'gender'];
        if (array_keys(app(EvaluateProductPublicationReadiness::class)->handle($product)) !== $expectedBlockers) {
            throw new RuntimeException('Unexpected draft readiness: '.$product->id);
        }
        if ($raw['create_offer'] === '1') {
            if ($snapshot['variants'] !== [] || $product->category_id !== $categories->first()->id) {
                throw new RuntimeException('Only a missing offer on the unchanged Extrait category may be created.');
            }
        } elseif (count($snapshot['variants']) !== 1 || ! $snapshot['variants'][0]['is_active']
            || (int) $snapshot['variants'][0]['volume'] !== (int) $raw['size_ml']
            || $snapshot['variants'][0]['price'] !== $raw['price'] || $product->category_id !== null) {
            throw new RuntimeException('Existing approved offer/category changed.');
        }
        $data = ['approval' => $raw, 'source_hash' => ownerCorrectionHash($source), 'product_hash' => ownerCorrectionHash($snapshot)];
        $payloadHash = app(ProductImportPayloadHasher::class)->hash($data, [], 'correct_publish');
        if ($existingRows) {
            $row = $existingRows->get($product->id);
            if (! $row || $row->candidate_action !== 'correct_publish' || $row->status !== 'valid' || $row->issues !== []
                || $row->provider !== 'qammaris_app' || $row->external_product_id !== $raw['uuid']
                || $row->line_number !== $index + 2 || $row->apply_status !== 'pending'
                || ! hash_equals($payloadHash, $row->payload_hash)
                || ownerCorrectionHash($row->normalized_data) !== ownerCorrectionHash($data)
                || ownerCorrectionHash($row->before_snapshot) !== ownerCorrectionHash($snapshot)) {
                throw new RuntimeException('Preview, source or product changed: '.$product->id);
            }
        }
        $prepared[] = ['line_number' => $index + 2, 'status' => 'valid', 'candidate_action' => 'correct_publish',
            'provider' => 'qammaris_app', 'external_product_id' => $raw['uuid'], 'matched_product_id' => $product->id,
            'normalized_data' => $data, 'issues' => [], 'payload_hash' => $payloadHash,
            'apply_status' => 'pending', 'before_snapshot' => $snapshot];
    }
    if (! $batch) {
        $batch = ProductImportBatch::create([
            'actor_id' => null, 'source_filename' => 'owner-approved-four-perfumes-five-sizes-20261006', 'source_size' => filesize($path),
            'source_fingerprint' => $approvedHash, 'contract_version' => 'owner-correct-v1',
            'catalog_state_fingerprint' => ownerCorrectionHash(array_column($prepared, 'before_snapshot')),
            'idempotency_key' => $key, 'status' => 'previewed', 'total_rows' => 5, 'valid_rows' => 5,
            'review_rows' => 0, 'error_rows' => 0, 'applied_rows' => 0, 'blocked_rows' => 0,
        ]);
        foreach ($prepared as $row) {
            $batch->rows()->create($row);
        }
    }
    if (! hash_equals($batch->catalog_state_fingerprint, ownerCorrectionHash(array_column($prepared, 'before_snapshot')))) {
        throw new RuntimeException('Preview catalog guard changed.');
    }
    if ($mode !== 'preview') {
        foreach ($batch->rows()->get() as $row) {
            $product = $products->get($row->matched_product_id);
            $approval = $row->normalized_data['approval'];
            $category = Category::active()->where('name', $approval['category'])->sole();
            $product->forceFill(['category_id' => $category->id, 'gender' => $approval['gender']])->save();
            if ($approval['create_offer'] === '1') {
                app(SyncSingleOffer::class)->handle($product, ['volume' => (int) $approval['size_ml'],
                    'price' => $approval['price'], 'sku' => null]);
            }
            $product = app(PublishProduct::class)->handle($product);

            $after = ownerCorrectionSnapshot($product);
            foreach ($row->before_snapshot['product'] as $field => $value) {
                if (! in_array($field, ['category_id', 'gender', 'publication_status', 'is_active', 'published_at', 'archived_at', 'updated_at'], true)
                    && $after['product'][$field] !== $value) {
                    throw new RuntimeException('Publisher changed a protected field.');
                }
            }
            foreach (['images', 'identities'] as $relation) {
                if ($after[$relation] !== $row->before_snapshot[$relation]) {
                    throw new RuntimeException('Publisher changed a protected relationship.');
                }
            }
            if ($after['product']['category_id'] !== $category->id || $after['product']['gender'] !== $approval['gender']) {
                throw new RuntimeException('Approved correction was not applied exactly.');
            }
            if ($approval['create_offer'] === '1') {
                if (count($after['variants']) !== 1 || (int) $after['variants'][0]['volume'] !== (int) $approval['size_ml']
                    || $after['variants'][0]['price'] !== $approval['price'] || ! $after['variants'][0]['is_active']
                    || $after['variants'][0]['sku'] !== null || $after['variants'][0]['stock'] !== 0) {
                    throw new RuntimeException('New offer does not match Owner-approved size and unchanged price.');
                }
            } elseif ($after['variants'] !== $row->before_snapshot['variants']) {
                throw new RuntimeException('An existing offer changed.');
            }
            if (! $product->isPubliclyVisible()) {
                throw new RuntimeException('Published product is not visible.');
            }
            $row->update(['apply_status' => 'updated', 'applied_product_id' => $product->id,
                'apply_message' => 'Owner approved named category/audience/size corrections and staging publication; existing operations used.',
                'after_snapshot' => $after, 'applied_at' => now()]);
        }
        $batch->update(['status' => 'applied', 'applied_rows' => 5, 'blocked_rows' => 0, 'applied_by' => null, 'applied_at' => now()]);
    }
    $result = ['mode' => $mode, 'batch_id' => $batch->id, 'status' => $batch->status, 'rows' => 5,
        'approval_sha256' => $approvedHash, 'rolled_back' => $mode === 'rehearse', 'replay_no_op' => false,
        'audit_digest' => ownerCorrectionAuditDigest($batch)];
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
