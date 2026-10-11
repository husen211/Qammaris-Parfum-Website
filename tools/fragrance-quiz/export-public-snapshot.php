<?php

use App\Models\Product;
use App\Services\FragranceQuizService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Operator-only stdout export. No public route, credentials or files are created.
$root = realpath(getenv('QAMMARIS_QUIZ_AUDIT_ROOT') ?: getcwd());
if (! $root || ! is_file($root.'/bootstrap/app.php')) {
    fwrite(STDERR, "Invalid application root.\n");
    exit(1);
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
try {
    // All source/baseline queries share one consistent, enforced read-only snapshot.
    if ($connection->getDriverName() !== 'mysql') {
        throw new RuntimeException('MySQL is required for this operator export.');
    }
    $cases = json_decode(base64_decode(getenv('QAMMARIS_QUIZ_AUDIT_CASES_B64') ?: '', true) ?: '', true, 64, JSON_THROW_ON_ERROR);
    if (($cases['schema'] ?? '') !== 'qammaris-preference-scenarios-v1' || count($cases['cases'] ?? []) !== 30) {
        throw new RuntimeException('Invalid scenario contract.');
    }
    $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $connection->statement('SET TRANSACTION READ ONLY');
    $connection->beginTransaction();
    $products = Product::published()->with(['brand', 'category', 'variants', 'primaryImage'])->orderBy('id')->limit(5001)->get();
    if ($products->count() > 5000) {
        throw new RuntimeException('Catalog exceeds the audit bound.');
    }
    $rows = $products->map(function ($product) {
        $offers = $product->variants->where('is_active', true);
        $offer = $offers->count() === 1 ? $offers->first() : null;

        return [
            'id' => $product->id, 'name' => $product->name, 'slug' => $product->slug,
            'brand' => $product->brand?->name, 'category' => $product->category?->name,
            'gender' => $product->gender, 'description' => $product->description,
            'notes' => $product->fragrance_notes, 'active_offer_count' => $offers->count(),
            'price' => $offer?->price, 'size_ml' => $offer?->volume,
            'availability' => $product->effective_availability,
            'primary_image_present' => $product->primaryImage !== null,
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    })->all();
    $publicIds = array_column($rows, 'id');
    $baselines = [];
    $service = app(FragranceQuizService::class);
    foreach ($cases['cases'] as $case) {
        if ($case['kind'] !== 'normal') {
            continue;
        }
        $answers = $case['legacy_answers'];
        foreach (config('fragrance_quiz.questions') as $key => $question) {
            if (! array_key_exists($answers[$key] ?? '', $question['options'])) {
                throw new RuntimeException('Invalid legacy answer.');
            }
        }
        $result = $service->recommend($answers);
        $ids = $result['products']->pluck('id')->all();
        if (array_diff($ids, $publicIds)) {
            throw new RuntimeException('Baseline contains a non-public product.');
        }
        $baselines[] = ['case_id' => $case['id'], 'legacy_answers' => $answers, 'product_ids' => $ids, 'tags' => $result['tags']];
    }
    $connection->rollBack();
    $payload = [
        'schema' => 'qammaris-public-fragrance-audit-v1', 'label' => 'production-public-read-only',
        'captured_at' => gmdate('c'),
        'revision' => is_file($root.'/release-revision.txt') ? trim(file_get_contents($root.'/release-revision.txt')) : null,
        'catalog_sha256' => hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        'legacy_input_sha256' => hash('sha256', json_encode($cases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        'products' => $rows, 'legacy_baselines' => $baselines,
    ];
    echo base64_encode(gzencode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 9))."\n";
} catch (Throwable $error) {
    if ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
    fwrite(STDERR, 'Public fragrance audit export failed: '.$error::class."\n");
    exit(1);
}
