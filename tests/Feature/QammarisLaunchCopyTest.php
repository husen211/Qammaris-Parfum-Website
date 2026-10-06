<?php

namespace Tests\Feature;

use App\Actions\Products\ApplyProductMaintenanceBatch;
use App\Imports\Products\ProductMaintenanceCsv;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Services\ProductCatalogRowFingerprint;
use App\Services\ProductImportPayloadHasher;
use App\Services\ProductMaintenanceBatchRecorder;
use App\Services\ProductMaintenancePreviewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class QammarisLaunchCopyTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name = 'Source Perfume'): Product
    {
        Storage::fake('public');
        config(['media.product_disk' => 'public']);
        $brand = Brand::firstOrCreate(['name' => 'Source Brand'], ['is_active' => true]);
        $category = Category::firstOrCreate(['name' => 'Eau de Parfum'], ['is_active' => true]);
        $product = Product::create(['name' => $name, 'slug' => str($name)->slug(), 'brand_id' => $brand->id,
            'category_id' => $category->id, 'publication_status' => 'draft', 'is_active' => false,
            'availability_status' => 'sold_out', 'availability_source' => 'qammaris_app']);
        $product->variants()->create(['volume' => 100, 'price' => 100000, 'stock' => 0, 'is_active' => true]);
        Storage::disk('public')->put('products/test-'.$product->id.'.jpg', 'retained-image');
        $product->images()->create(['image_path' => 'products/test-'.$product->id.'.jpg', 'is_primary' => true, 'sort_order' => 0]);
        $product->externalIdentities()->create(['provider' => 'qammaris_app', 'external_product_id' => fake()->uuid()]);
        $product->externalIdentities()->create(['provider' => 'shopee', 'external_product_id' => (string) (5000 + $product->id)]);

        return $product->fresh();
    }

    private function preview(Product $product, array $fields = []): ProductImportBatch
    {
        $data = array_replace(array_fill_keys(ProductMaintenanceCsv::HEADERS, ''), [
            'product_id' => (string) $product->id, 'expected_updated_at' => $product->updated_at->toIso8601String(),
            'expected_row_fingerprint' => app(ProductCatalogRowFingerprint::class)->hash($product),
            'deskripsi_produk' => 'Source notes: citrus and musk.', 'gender' => 'Pria',
        ], $fields);
        $stream = fopen('php://temp', 'w+b');
        fputcsv($stream, ProductMaintenanceCsv::HEADERS, escape: '');
        fputcsv($stream, array_values($data), escape: '');
        rewind($stream);
        $file = UploadedFile::fake()->createWithContent('wave-copy.csv', stream_get_contents($stream));
        fclose($stream);

        return app(ProductMaintenanceBatchRecorder::class)->recordLaunchCopy($file, app(ProductMaintenancePreviewer::class)->preview($file));
    }

    public function test_owner_cli_copy_is_audited_idempotent_and_keeps_protected_records(): void
    {
        $product = $this->product();
        $before = $product->only(['slug', 'name', 'publication_status', 'is_active', 'availability_status', 'base_price']);
        $batch = $this->preview($product);
        $this->assertSame($batch->id, $this->preview($product)->id);
        $this->assertNull($batch->actor_id);
        $this->assertStringStartsWith('owner-authorized-cli-', $batch->source_filename);
        $apply = app(ApplyProductMaintenanceBatch::class);
        $result = $apply->handle($batch, null);
        $this->assertSame('applied', $result->status);
        $this->assertNull($result->applied_by);
        $this->assertSame('Pria', $product->fresh()->gender);
        $this->assertSame($before, $product->fresh()->only(array_keys($before)));
        $this->assertSame(1, $result->applied_rows);
        $this->assertNotSame($result->rows->first()->before_snapshot, $result->rows->first()->after_snapshot);
        $after = $product->fresh()->getAttributes();
        $audit = $result->rows->first()->getAttributes();
        $this->assertSame($result->id, $apply->handle($result, null)->id);
        $this->assertSame($after, $product->fresh()->getAttributes());
        $this->assertSame($audit, $result->rows->first()->fresh()->getAttributes());
        $this->assertSame(2, $product->externalIdentities()->count());
        $this->assertSame(1, $product->images()->count());
        $this->assertSame('100000.00', $product->variants()->first()->price);
    }

    public function test_unclear_audience_stays_blank_and_draft(): void
    {
        $product = $this->product();
        $batch = $this->preview($product, ['gender' => '']);
        app(ApplyProductMaintenanceBatch::class)->handle($batch, null);
        $this->assertNull($product->fresh()->gender);
        $this->assertSame('draft', $product->fresh()->publication_status);
        $this->assertNotEmpty($product->fresh()->description);
    }

    public function test_price_or_name_proposals_are_not_accepted_by_copy_recorder(): void
    {
        $product = $this->product();
        $this->expectException(RuntimeException::class);
        $this->preview($product, ['nama_produk' => 'Forbidden name']);
    }

    public function test_hidden_published_or_missing_image_products_are_not_eligible(): void
    {
        foreach (['hidden', 'published', 'image'] as $state) {
            $product = $this->product('Source '.$state);
            if ($state === 'hidden') {
                $product->forceFill(['qammaris_app_hidden' => true])->save();
            } elseif ($state === 'published') {
                $product->update(['publication_status' => 'published', 'is_active' => true]);
            } else {
                $product->images()->delete();
            }
            try {
                $this->preview($product->fresh());
                $this->fail('Protected product accepted.');
            } catch (RuntimeException) {
                $this->assertNull($product->fresh()->description);
            }
        }
        $this->assertSame(0, ProductImportBatch::count());
    }

    public function test_stale_catalog_and_tampered_payload_never_mutate_copy(): void
    {
        $product = $this->product();
        $batch = $this->preview($product);
        $product->update(['name' => 'Changed by owner']);
        $this->assertSame('stale', app(ApplyProductMaintenanceBatch::class)->handle($batch, null)->status);
        $this->assertNull($product->fresh()->description);
        $batch = $this->preview($product->fresh());
        $row = $batch->rows->first();
        $data = $row->normalized_data;
        $data['deskripsi_produk'] = 'Tampered';
        $row->update(['normalized_data' => $data]);
        $this->assertSame('invalid', app(ApplyProductMaintenanceBatch::class)->handle($batch, null)->status);
        $this->assertNull($product->fresh()->description);
    }

    public function test_rehashed_protected_change_rolls_back_copy(): void
    {
        $product = $this->product();
        $batch = $this->preview($product);
        $row = $batch->rows->first();
        $data = $row->normalized_data;
        $data['nama_produk'] = 'Protected overwrite';
        $data['_changes'][] = ['field' => 'nama_produk'];
        $row->update(['normalized_data' => $data, 'payload_hash' => app(ProductImportPayloadHasher::class)->hash($data, $row->issues, $row->candidate_action)]);
        try {
            app(ApplyProductMaintenanceBatch::class)->handle($batch, null);
            $this->fail('Protected change applied.');
        } catch (RuntimeException) {
            $this->assertNull($product->fresh()->description);
            $this->assertSame('Source Perfume', $product->fresh()->name);
        }
    }

    public function test_machine_cannot_use_a_human_maintenance_batch(): void
    {
        $product = $this->product();
        $batch = $this->preview($product);
        $batch->update(['contract_version' => ProductMaintenanceCsv::VERSION]);
        $this->expectException(RuntimeException::class);
        app(ApplyProductMaintenanceBatch::class)->handle($batch, null);
    }

    public function test_production_and_missing_cli_confirmation_are_refused(): void
    {
        $product = $this->product();
        $batch = $this->preview($product);
        $this->artisan('qammaris-app:launch-copy', ['--apply' => $batch->id])->assertFailed();
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('qammaris-app:launch-copy', ['--apply' => $batch->id, '--confirm' => true])->assertFailed();
        $this->assertNull($product->fresh()->description);
    }
}
