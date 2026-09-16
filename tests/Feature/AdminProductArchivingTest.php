<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductArchivingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    private ProductVariant $variant;

    private ProductImage $image;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();

        $brand = Brand::create([
            'name' => 'Archive Safety Brand',
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'EDP']);

        $this->product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Archive Safety Product',
            'description' => 'Product used to verify reversible archiving.',
            'base_price' => 175000,
            'gender' => 'Unisex',
            'is_active' => true,
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'volume' => 100,
            'price' => 175000,
            'sku' => 'ARCHIVE-001',
            'stock' => 5,
            'is_active' => true,
        ]);
        $this->image = ProductImage::create([
            'product_id' => $this->product->id,
            'image_path' => 'products/archive-safety.webp',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        Storage::disk('public')->put($this->image->image_path, 'image-content');
    }

    public function test_admin_archives_product_without_deleting_data_or_media(): void
    {
        $productId = $this->product->id;
        $slug = $this->product->slug;

        $this->actingAs($this->admin)
            ->delete(route('admin.products.destroy', $productId))
            ->assertRedirect()
            ->assertSessionHas('success', 'Produk berhasil diarsipkan. Data dan gambar tetap tersimpan.');

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'slug' => $slug,
            'is_active' => false,
            'publication_status' => Product::PUBLICATION_ARCHIVED,
        ]);
        $this->assertDatabaseHas('product_variants', ['id' => $this->variant->id]);
        $this->assertDatabaseHas('product_images', ['id' => $this->image->id]);
        Storage::disk('public')->assertExists($this->image->image_path);
        $this->get(route('products.show', $this->product))->assertNotFound();
    }

    public function test_archiving_an_inactive_product_is_idempotent(): void
    {
        $this->product->markArchived();

        $this->actingAs($this->admin)
            ->delete(route('admin.products.destroy', $this->product->id))
            ->assertRedirect()
            ->assertSessionHas('success', 'Produk ini sudah diarsipkan.');

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'is_active' => false,
        ]);
        Storage::disk('public')->assertExists($this->image->image_path);
    }

    public function test_admin_can_restore_an_archived_product(): void
    {
        $this->product->markArchived();

        $this->actingAs($this->admin)
            ->patch(route('admin.products.restore', $this->product->id))
            ->assertRedirect()
            ->assertSessionHas('success', 'Produk berhasil diaktifkan kembali.');

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'is_active' => true,
            'publication_status' => Product::PUBLICATION_PUBLISHED,
        ]);
        $this->get(route('products.show', $this->product))->assertOk();
    }

    public function test_customer_cannot_archive_or_restore_products(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->delete(route('admin.products.destroy', $this->product->id))
            ->assertForbidden();

        $this->product->update(['is_active' => false]);

        $this->actingAs($customer)
            ->patch(route('admin.products.restore', $this->product->id))
            ->assertForbidden();
    }

    public function test_legacy_image_delete_endpoint_preserves_metadata_and_file(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.products.delete-image', $this->image->id))
            ->assertRedirect()
            ->assertSessionHas(
                'error',
                'Penghapusan gambar dinonaktifkan sementara agar file dan metadata tetap aman.'
            );

        $this->assertDatabaseHas('product_images', [
            'id' => $this->image->id,
            'product_id' => $this->product->id,
            'is_primary' => true,
        ]);
        Storage::disk('public')->assertExists($this->image->image_path);
    }

    public function test_product_editor_exposes_only_safe_image_archiving(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.products.edit', $this->product->id))
            ->assertOk()
            ->assertSee('Arsipkan foto')
            ->assertSee('file tetap disimpan untuk recovery')
            ->assertDontSee('deleteImage(')
            ->assertDontSee('Delete image');
    }

    public function test_admin_catalog_exposes_clear_archive_and_restore_states(): void
    {
        $activeHtml = $this->actingAs($this->admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Tayang')
            ->assertSee('Arsipkan produk')
            ->assertDontSee('Delete')
            ->getContent();

        $this->assertStringContainsString('data dan gambar tetap tersimpan', $activeHtml);

        $this->product->markArchived();

        $this->actingAs($this->admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Diarsipkan')
            ->assertSee('Aktifkan kembali');
    }
}
