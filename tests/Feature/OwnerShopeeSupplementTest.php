<?php

namespace Tests\Feature;

use App\Actions\Products\MapExternalProductIdentity;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductCatalogRowFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OwnerShopeeSupplementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! defined('QAMMARIS_SUPPLEMENTAL_LIBRARY_ONLY')) {
            define('QAMMARIS_SUPPLEMENTAL_LIBRARY_ONLY', true);
        }
        require_once base_path('tools/publish_owner_shopee_supplement.php');
        Storage::fake('public');
        config(['media.product_disk' => 'public']);
        Category::create(['name' => 'Eau de Parfum', 'is_active' => true]);
    }

    public function test_enrichment_publishes_with_source_price_retained_slug_and_sold_out_status(): void
    {
        [$data, $p] = $this->target();
        $oldSlug = $p->slug;
        $data['correct_name'] = 'Corrected Cute Woman';
        $result = DB::transaction(fn () => supplementalFill($data, $this->media()));
        $this->assertSame($p->id, $result->id);
        $this->assertSame($oldSlug, $result->slug);
        $this->assertSame('Corrected Cute Woman', $result->name);
        $this->assertSame('published', $result->publication_status);
        $this->assertSame('sold_out', $result->availability_status);
        $this->assertSame('189000.00', $result->activeOffer->price);
        $this->assertSame(100, $result->activeOffer->volume);
        $this->assertSame('12345', $result->externalIdentities->firstWhere('provider', 'shopee')->external_product_id);
        $this->assertSame(1, $result->images->count());
        $this->assertTrue($result->images->first()->is_primary);
    }

    public function test_unmapped_uuid_creates_real_product_with_source_availability_and_not_a_fixture(): void
    {
        [$data] = $this->target(false);
        $result = DB::transaction(fn () => supplementalFill($data, $this->media()));
        $this->assertSame('published', $result->publication_status);
        $this->assertSame('qammaris_app', $result->availability_source);
        $this->assertSame('sold_out', $result->availability_status);
        $this->assertSame($data['uuid'], $result->externalIdentities->firstWhere('provider', 'qammaris_app')->external_product_id);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_changed_source_is_rejected_before_catalog_write(): void
    {
        [$data] = $this->target();
        $source = array_replace($data['source'], ['revision' => 2, 'change_seq' => 2, 'price' => 200000]);
        DB::table('qammaris_app_products')->where('id', $data['uuid'])->update(['snapshot' => json_encode($source)]);
        $this->expectException(RuntimeException::class);
        supplementalGuard($data);
    }

    public function test_occupied_shopee_identity_is_never_rebound(): void
    {
        [$data] = $this->target();
        $other = Product::create(['name' => 'Other retained product']);
        app(MapExternalProductIdentity::class)->handle($other, 'shopee', $data['shopee_id']);
        try {
            supplementalGuard($data);
            $this->fail('Occupied source accepted.');
        } catch (RuntimeException) {
            $this->assertSame($other->id, $other->externalIdentities->sole()->product_id);
            $this->assertDatabaseCount('product_images', 0);
        }
    }

    public function test_corrupt_photo_rolls_back_copy_identity_offer_and_publication(): void
    {
        [$data, $p] = $this->target();
        $media = $this->media();
        $media[0]['checksum'] = str_repeat('0', 64);
        try {
            DB::transaction(fn () => supplementalFill($data, $media));
            $this->fail('Corrupt image accepted.');
        } catch (RuntimeException) {
            $this->assertNull($p->fresh()->description);
            $this->assertSame('draft', $p->fresh()->publication_status);
            $this->assertDatabaseCount('product_external_identities', 1);
            $this->assertDatabaseCount('product_variants', 0);
            $this->assertDatabaseCount('product_images', 0);
        }
    }

    public function test_existing_copy_is_never_overwritten(): void
    {
        [$data, $p] = $this->target();
        $p->update(['description' => 'Retained human description']);
        $data['expected_fingerprint'] = app(ProductCatalogRowFingerprint::class)->hash($p->fresh());
        $this->expectException(RuntimeException::class);
        supplementalGuard($data);
    }

    public function test_transferred_nonimage_is_rejected_even_with_matching_checksum(): void
    {
        Storage::disk('public')->put('fake.txt', 'This is not an image');
        $path = Storage::disk('public')->path('fake.txt');
        $record = ['size' => filesize($path), 'checksum' => hash_file('sha256', $path),
            'mime_type' => 'image/png', 'extension' => 'png', 'width' => 1, 'height' => 1];
        $this->expectException(RuntimeException::class);
        supplementalInspectTransferredImage($path, $record);
    }

    public function test_transferred_image_is_checked_before_storage(): void
    {
        $media = $this->media();
        $path = Storage::disk('public')->path($media[0]['object_key']);
        $record = array_merge($media[0], ['mime_type' => 'image/png', 'extension' => 'png', 'width' => 1, 'height' => 1]);
        $file = supplementalInspectTransferredImage($path, $record);
        $this->assertSame('image/png', $file->getMimeType());
        $record['width'] = 1000;
        $this->expectException(RuntimeException::class);
        supplementalInspectTransferredImage($path, $record);
    }

    private function target(bool $mapped = true): array
    {
        $source = ['id' => (string) Str::uuid(), 'name' => 'Synthetic source EDP 100 ml', 'sku' => 'SYNTHETIC',
            'brand' => 'Synthetic Brand', 'department_code' => 'local', 'price' => 189000, 'source' => 'majoo',
            'availability' => 'sold_out', 'restock_eta' => null, 'stock_status_at' => '2026-10-06T01:00:00Z',
            'active' => true, 'merged_into' => null, 'hidden' => false, 'revision' => 1, 'change_seq' => 1];
        DB::table('qammaris_app_products')->insert(['id' => $source['id'], 'revision' => 1,
            'snapshot' => json_encode($source), 'created_at' => now(), 'updated_at' => now()]);
        $brand = Brand::create(['name' => 'Synthetic Brand', 'is_active' => true]);
        $p = $mapped ? Product::create(['name' => 'Retained original name', 'brand_id' => $brand->id,
            'base_price' => 189000, 'publication_status' => 'draft', 'is_active' => false,
            'availability_status' => 'sold_out', 'availability_source' => 'qammaris_app']) : null;
        if ($p) {
            app(MapExternalProductIdentity::class)->handle($p, 'qammaris_app', $source['id']);
            $p = $p->fresh();
        }
        $data = ['row' => 7, 'uuid' => $source['id'], 'source' => $source, 'shopee_id' => '12345',
            'expected_product_id' => $p?->id, 'expected_fingerprint' => $p ? app(ProductCatalogRowFingerprint::class)->hash($p) : null,
            'size_ml' => 100, 'category' => 'Eau de Parfum', 'gender' => 'Wanita', 'description' => 'Factual source description.',
            'photos' => ['https://cf.shopee.co.id/synthetic-cover'], 'correct_name' => null];

        return [$data, $p];
    }

    private function media(): array
    {
        $path = 'products/synthetic.png';
        Storage::disk('public')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jZfsAAAAASUVORK5CYII='));
        $bytes = Storage::disk('public')->get($path);

        return [['status' => 'stored', 'object_key' => $path, 'size' => strlen($bytes), 'checksum' => hash('sha256', $bytes)]];
    }
}
