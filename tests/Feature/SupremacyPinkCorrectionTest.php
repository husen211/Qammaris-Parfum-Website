<?php

namespace Tests\Feature;

use App\Actions\Products\MapExternalProductIdentity;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Services\ShopeeContentPreviewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupremacyPinkCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('tools/correct_supremacy_pink.php');
        Storage::fake('public');
        config(['media.product_disk' => 'public']);
    }

    private function fixture(): array
    {
        $brand = Brand::create(['name' => 'Afnan', 'is_active' => true]);
        $category = Category::create(['name' => 'Eau de Parfum', 'is_active' => true]);
        foreach ([[879, 'Purple', 'f9b89ff7-7900-49b6-ac07-a012eea8c04d', '6290171002055', 460000, 'sold_out'],
            [686, 'Pink', '84a10c0b-c5ba-4d9c-a622-d8d7969327f7', '6290171002048', 479000, 'available']] as [$id, $color, $uuid, $sku, $price, $status]) {
            $p = new Product;
            $p->forceFill(['id' => $id, 'name' => 'Supremacy '.$color, 'brand_id' => $brand->id, 'base_price' => $price,
                'category_id' => $id === 879 ? $category->id : null, 'description' => $id === 879 ? 'SUPREMACY PINK POUR FEMME' : null,
                'gender' => $id === 879 ? 'Wanita' : null, 'publication_status' => $id === 879 ? 'published' : 'draft',
                'is_active' => $id === 879, 'availability_source' => 'qammaris_app', 'availability_status' => $status])->save();
            app(MapExternalProductIdentity::class)->handle($p, 'qammaris_app', $uuid);
            DB::table('qammaris_app_products')->insert(['id' => $uuid, 'revision' => 1, 'snapshot' => json_encode([
                'id' => $uuid, 'sku' => $sku, 'price' => $price, 'hidden' => false, 'active' => true, 'merged_into' => null]),
                'created_at' => now(), 'updated_at' => now()]);
        }
        [$purple, $pink] = pinkProducts();
        app(MapExternalProductIdentity::class)->handle($purple, 'shopee', '42131600634');
        $purple->variants()->create(['volume' => 100, 'price' => 460000, 'stock' => 0, 'is_active' => true]);
        for ($i = 0; $i < 3; $i++) {
            Storage::disk('public')->put('products/pink-original-'.$i.'.jpg', 'photo-'.$i);
            $purple->images()->create(['image_path' => 'products/pink-original-'.$i.'.jpg', 'is_primary' => $i === 0, 'sort_order' => $i]);
        }
        $batch = new ProductImportBatch;
        $batch->forceFill(['id' => 6, 'source_filename' => 'fixture', 'source_size' => 1, 'source_fingerprint' => str_repeat('a', 64),
            'catalog_state_fingerprint' => str_repeat('b', 64), 'idempotency_key' => str_repeat('c', 64), 'contract_version' => 'shopee-content-v1',
            'status' => 'applied', 'total_rows' => 1, 'valid_rows' => 1, 'review_rows' => 0, 'error_rows' => 0])->save();
        $source = ['id' => '42131600634', 'name' => 'Afnan Supremacy Pink Pour Femme Eau de Parfum 100ML'];
        $row = $batch->rows()->create(['line_number' => 1, 'status' => 'valid', 'candidate_action' => 'shopee_content', 'provider' => 'shopee',
            'external_product_id' => '42131600634', 'matched_product_id' => 879, 'normalized_data' => ['source' => $source], 'issues' => [], 'payload_hash' => pinkHash($source)]);
        [$purple, $pink] = pinkProducts();
        $preview = app(ShopeeContentPreviewer::class);
        $manifest = ['before' => [pinkSnapshot($purple), pinkSnapshot($pink)], 'fingerprints' => [$preview->fingerprint($purple), $preview->fingerprint($pink)],
            'source' => $source, 'media' => []];
        foreach ($purple->images as $i => $image) {
            $to = 'products/pink-copy-'.$i.'.jpg';
            Storage::disk('public')->copy($image->image_path, $to);
            $manifest['media'][] = ['from' => $image->image_path, 'to' => $to];
        }

        return [$manifest, $row];
    }

    public function test_exact_correction_preserves_application_identity_price_status_urls_and_original_media(): void
    {
        [$manifest, $row] = $this->fixture();
        $historical = $row->fresh()->getAttributes();
        DB::transaction(fn () => pinkMutate($manifest, $row));
        [$purple, $pink] = pinkProducts();
        $this->assertSame('draft', $purple->publication_status);
        $this->assertNull($purple->description);
        $this->assertCount(0, $purple->images);
        $this->assertCount(3, $purple->images()->onlyTrashed()->get());
        $this->assertSame('published', $pink->publication_status);
        $this->assertCount(3, $pink->images);
        $this->assertSame('479000.00', $pink->variants->sole()->price);
        $this->assertSame('460000.00', $purple->variants->sole()->price);
        $this->assertSame('available', $pink->availability_status);
        $this->assertSame('sold_out', $purple->availability_status);
        foreach ([$purple, $pink] as $index => $product) {
            $this->assertSame($manifest['before'][$index]['product']['slug'], $product->slug);
            $originalApp = collect($manifest['before'][$index]['identities'])->firstWhere('provider', 'qammaris_app');
            $this->assertSame($originalApp['external_product_id'], $product->externalIdentities->firstWhere('provider', 'qammaris_app')->external_product_id);
        }
        foreach ($manifest['media'] as $media) {
            $this->assertSame(Storage::disk('public')->get($media['from']), Storage::disk('public')->get($media['to']));
        }
        $this->assertSame($historical, $row->fresh()->getAttributes());
        $this->assertDatabaseHas('product_external_identities', ['provider' => 'shopee', 'external_product_id' => '42131600634', 'product_id' => 686]);
        $this->assertSame(2, ProductImportBatch::where('contract_version', 'owner-pink-fix-v1')->sole()->rows()->count());
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('product_external_identities', 3);
    }

    public function test_rehearsal_rolls_back_all_database_mutations(): void
    {
        [$manifest, $row] = $this->fixture();
        DB::beginTransaction();
        pinkMutate($manifest, $row);
        DB::rollBack();
        [$purple, $pink] = pinkProducts();
        $this->assertSame($manifest['before'], [pinkSnapshot($purple), pinkSnapshot($pink)]);
        $this->assertDatabaseCount('product_import_batches', 1);
    }

    public function test_stale_content_is_rejected_without_rebinding(): void
    {
        [$manifest, $row] = $this->fixture();
        Product::findOrFail(686)->update(['name' => 'Owner changed target']);
        try {
            DB::transaction(fn () => pinkMutate($manifest, $row));
            $this->fail('Stale preview accepted.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Preview stale; correction not applied.', $e->getMessage());
        }
        $this->assertDatabaseHas('product_external_identities', ['provider' => 'shopee', 'external_product_id' => '42131600634', 'product_id' => 879]);
    }

    public function test_missing_photo_rolls_back_identity_offer_and_content(): void
    {
        [$manifest, $row] = $this->fixture();
        Storage::disk('public')->delete($manifest['media'][1]['to']);
        try {
            DB::transaction(fn () => pinkMutate($manifest, $row));
            $this->fail('Missing photo accepted.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('File gambar', $e->getMessage());
        }
        [$purple, $pink] = pinkProducts();
        $this->assertSame($manifest['before'], [pinkSnapshot($purple), pinkSnapshot($pink)]);
        $this->assertDatabaseCount('product_import_batches', 1);
    }
}
