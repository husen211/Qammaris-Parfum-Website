<?php

namespace App\Console\Commands;

use App\Actions\Products\EvaluateProductPublicationReadiness;
use App\Models\Product;
use App\Services\QammarisAppMappingReview;
use App\Services\SpreadsheetSafeCell;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReviewQammarisAppMappings extends Command
{
    protected $signature = 'qammaris-app:review-mappings
        {--source-file= : Private allowlisted source snapshot JSON; defaults to synchronized cache}
        {--catalog-label= : Required explicit baseline label, such as local-preview-not-production}
        {--output= : CSV path within storage/app/private; never an apply/import file}';

    protected $description = 'Read-only UUID candidate and catalog/media gap review; no mapping or catalog mutations';

    public function handle(QammarisAppMappingReview $review, EvaluateProductPublicationReadiness $readiness, SpreadsheetSafeCell $safeCell): int
    {
        $label = trim((string) $this->option('catalog-label'));
        $output = (string) $this->option('output');
        $parent = realpath(dirname($output));
        $private = realpath(storage_path('app/private'));
        if ($label === '' || mb_strlen($label) > 100 || $private === false || $parent === false
            || ! str_starts_with(str_replace('\\', '/', $parent).'/', str_replace('\\', '/', $private).'/')
            || strtolower(pathinfo($output, PATHINFO_EXTENSION)) !== 'csv' || file_exists($output)) {
            $this->error('Provide a baseline label and a new CSV output in an existing storage/app/private directory.');

            return self::FAILURE;
        }
        $stream = null;
        try {
            $sourceFile = (string) $this->option('source-file');
            if ($sourceFile !== '') {
                if (! is_file($sourceFile) || filesize($sourceFile) > 16 * 1024 * 1024) {
                    throw new \RuntimeException('Invalid review snapshot file.');
                }
                $payload = json_decode(file_get_contents($sourceFile), true, 64, JSON_THROW_ON_ERROR);
                if (($payload['schema'] ?? '') !== 'qammaris-app-review-snapshot-v1' || ! is_array($payload['data'] ?? null)) {
                    throw new \RuntimeException('Invalid review snapshot format.');
                }
                $sources = $payload['data'];
            } else {
                $sources = DB::table('qammaris_app_products')->orderBy('id')->get(['snapshot'])
                    ->map(fn ($row) => json_decode($row->snapshot, true, 64, JSON_THROW_ON_ERROR))->all();
            }
            if (count($sources) > 5000) {
                throw new \RuntimeException('Review snapshot is too large.');
            }
            $catalog = Product::query()->with(['brand', 'category', 'variants', 'images', 'externalIdentities'])
                ->orderBy('id')->get()->map(function (Product $product) use ($readiness): array {
                    $offers = $product->variants->where('is_active', true);
                    $offer = $offers->count() === 1 ? $offers->first() : null;
                    $issues = array_keys($readiness->handle($product));
                    $mediaIssues = array_values(array_filter($issues, fn ($key) => str_starts_with($key, 'primary_image')));
                    if ($product->images->contains(fn ($image) => str_contains(strtolower($image->image_path), 'placeholder'))) {
                        $mediaIssues[] = 'placeholder_requires_real_photo';
                    }

                    return [
                        'id' => $product->id, 'name' => $product->name, 'slug' => $product->slug,
                        'publication_status' => $product->publication_status, 'brand' => $product->brand?->name,
                        'size_ml' => $offer?->volume, 'sku' => $offer?->sku, 'price' => $offer?->price,
                        'source_uuid' => $product->externalIdentities->firstWhere('provider', 'qammaris_app')?->external_product_id,
                        'catalog_issues' => array_values(array_diff($issues, $mediaIssues)), 'media_issues' => $mediaIssues,
                    ];
                })->all();
            $rows = $review->rows($catalog, $sources, $label);
            $stream = fopen($output, 'x');
            if ($stream === false) {
                throw new \RuntimeException('Cannot create review CSV.');
            }
            $review->write($stream, $rows, $safeCell);
            fclose($stream);
            $stream = null;
            $this->info(count($catalog).' website products; '.count($sources).' source snapshots; '.count($rows).' review rows.');
            $this->info('Review only: no UUID mapping, price, publication, database or media write. Not an apply file.');

            return self::SUCCESS;
        } catch (Throwable) {
            if (is_resource($stream)) {
                fclose($stream);
                unlink($output);
            }
            $this->error('Review could not be completed. Check baseline, snapshot and output configuration; no catalog mutation applied.');

            return self::FAILURE;
        }
    }
}
