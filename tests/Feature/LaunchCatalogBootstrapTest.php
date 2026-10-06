<?php

namespace Tests\Feature;

use App\Actions\Products\BootstrapLaunchCatalog;
use App\Actions\Products\MapExternalProductIdentity;
use App\Models\Brand;
use App\Models\Product;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class LaunchCatalogBootstrapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['media.product_disk' => 'public']);
    }

    public function test_reviewed_preview_remaps_ids_and_reuses_publication_media_offer_and_identity_operations(): void
    {
        [$data, $media] = $this->packet();
        $action = app(BootstrapLaunchCatalog::class);
        $batch = $action->preview($data, $media);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('brands', 0);
        $this->assertSame($batch->id, $action->preview($data, $media)->id);
        $result = $action->apply($batch->id);
        $public = Product::where('slug', 'reviewed-original-slug')->sole();
        $this->assertNotSame(100, $public->id);
        $this->assertNotSame(50, $public->brand_id);
        $this->assertSame('published', $public->publication_status);
        $this->assertSame('150000.00', $public->activeOffer->price);
        $this->assertSame('products/launch.png', $public->images->sole()->image_path);
        $this->assertSame(2, $public->externalIdentities->count());
        $this->assertSame('draft', Product::where('name', 'Draft waiting for photos')->sole()->publication_status);
        $this->assertSame(2, $result->applied_rows);
        $this->assertNull($result->actor_id);
        $this->assertNull($result->applied_by);
        $before = Product::orderBy('id')->get()->toArray();
        $action->apply($batch->id);
        $this->assertSame($before, Product::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('product_images', 1);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_catalog_and_changed_review_are_never_overwritten(): void
    {
        [$data, $media] = $this->packet();
        $action = app(BootstrapLaunchCatalog::class);
        $batch = $action->preview($data, $media);
        $data['products'][0]['description'] = 'Different proposal';
        try {
            $action->preview($data, $media);
            $this->fail('A changed packet must not replace its review.');
        } catch (DomainException) {
            $this->assertDatabaseCount('product_import_batches', 1);
        }
        $brand = Brand::create(['name' => 'Retained brand', 'is_active' => true]);
        try {
            $action->apply($batch->id);
            $this->fail('A nonempty target must be refused.');
        } catch (DomainException) {
            $this->assertSame('Retained brand', $brand->fresh()->name);
            $this->assertDatabaseCount('products', 0);
            $this->assertSame('previewed', $batch->fresh()->status);
        }
    }

    public function test_image_replacement_after_preview_prevents_every_catalog_write(): void
    {
        [$data, $media] = $this->packet();
        $action = app(BootstrapLaunchCatalog::class);
        $batch = $action->preview($data, $media);
        Storage::disk('public')->put('products/launch.png', 'replaced image');
        try {
            $action->apply($batch->id);
            $this->fail('Changed image must be refused.');
        } catch (DomainException) {
            $this->assertDatabaseCount('products', 0);
            $this->assertDatabaseCount('brands', 0);
            $this->assertSame('previewed', $batch->fresh()->status);
        }
    }

    public function test_late_identity_failure_rolls_back_products_media_publication_and_audit_outcomes(): void
    {
        [$data, $media] = $this->packet();
        $batch = app(BootstrapLaunchCatalog::class)->preview($data, $media);
        $real = app(MapExternalProductIdentity::class);
        $mock = Mockery::mock(MapExternalProductIdentity::class);
        $mock->shouldReceive('handle')->andReturnUsing(function ($p, $provider, $id) use ($real) {
            if ($id === 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2') {
                throw new DomainException('Synthetic last-row failure');
            }

            return $real->handle($p, $provider, $id);
        });
        $this->app->instance(MapExternalProductIdentity::class, $mock);
        try {
            app(BootstrapLaunchCatalog::class)->apply($batch->id);
            $this->fail('Last-row failure must roll back.');
        } catch (DomainException) {
            foreach (['products', 'brands', 'categories', 'product_variants', 'product_images', 'product_external_identities'] as $table) {
                $this->assertDatabaseCount($table, 0);
            }
            $this->assertSame('previewed', $batch->fresh()->status);
            $this->assertTrue($batch->rows()->get()->every(fn ($r) => $r->apply_status === 'pending' && $r->applied_product_id === null));
            Storage::disk('public')->assertExists('products/launch.png');
        }
    }

    public function test_duplicate_identity_or_orphan_media_is_refused_before_review(): void
    {
        [$data, $media] = $this->packet();
        $data['identities'][2]['external_product_id'] = $data['identities'][0]['external_product_id'];
        try {
            app(BootstrapLaunchCatalog::class)->preview($data, $media);
            $this->fail('Duplicate UUID must be refused.');
        } catch (DomainException) {
            $this->assertDatabaseCount('product_import_batches', 0);
            $this->assertDatabaseCount('products', 0);
        }
    }

    public function test_command_refuses_local_or_legacy_target_without_any_write(): void
    {
        $this->artisan('catalog:bootstrap-launch', ['catalog' => 'missing.json', 'media' => 'missing.json', '--apply' => true])
            ->assertFailed();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_import_batches', 0);
    }

    private function packet(): array
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQ0AAAAASUVORK5CYII=');
        Storage::disk('public')->put('products/launch.png', $png);
        $base = ['brand_id' => 50, 'category_id' => 60, 'base_price' => '150000.00', 'compare_at_price' => null,
            'fragrance_notes' => null, 'gender' => 'Unisex', 'is_best_seller' => 0, 'meta_description' => null,
            'availability_status' => 'available', 'availability_source' => 'qammaris_app',
            'availability_checked_at' => '2026-10-06 00:00:00', 'availability_restock_eta' => null];
        $data = ['schema' => 'fresh-launch-transfer-preview-v1', 'fixture_excluded' => 1, 'source_ids_only' => true,
            'products' => [
                ['id' => 100, 'name' => 'Complete perfume', 'slug' => 'reviewed-original-slug', 'description' => 'Approved copy', 'publication_status' => 'published', ...$base],
                ['id' => 101, 'name' => 'Draft waiting for photos', 'slug' => 'waiting-draft', 'description' => null, 'publication_status' => 'draft', ...$base],
            ],
            'offers' => [['id' => 500, 'product_id' => 100, 'volume' => 50, 'price' => '150000.00', 'stock' => 0, 'sku' => null]],
            'images' => [['id' => 1000, 'product_id' => 100, 'image_path' => 'products/launch.png', 'is_primary' => 1, 'sort_order' => 0]],
            'identities' => [
                ['product_id' => 100, 'provider' => 'qammaris_app', 'external_product_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1'],
                ['product_id' => 100, 'provider' => 'shopee', 'external_product_id' => '123456'],
                ['product_id' => 101, 'provider' => 'qammaris_app', 'external_product_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2'],
            ],
            'brands' => [['id' => 50, 'name' => 'Approved brand', 'slug' => 'approved-brand', 'description' => null, 'is_active' => 1]],
            'categories' => [['id' => 60, 'name' => 'Eau de Parfum', 'slug' => 'edp', 'description' => null, 'is_active' => 1]],
        ];
        $media = ['media' => [['staging_product_id' => 100, 'staging_image_id' => 1000, 'path' => 'products/launch.png',
            'sha256' => hash('sha256', $png), 'bytes' => strlen($png), 'mime_type' => 'image/png', 'is_primary' => true]]];

        return [$data, $media];
    }
}
