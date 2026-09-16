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

class AdminProductPublicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Brand $brand;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();
        $this->brand = Brand::create([
            'name' => 'Publication Brand',
            'is_active' => true,
        ]);
        $this->category = Category::create(['name' => 'EDP']);
    }

    public function test_admin_can_save_a_minimal_named_draft_without_offer_or_image(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'Draft Working Name',
                'publication_action' => Product::PUBLICATION_DRAFT,
            ]);

        $draft = Product::query()->sole();

        $response
            ->assertRedirect(route('admin.products.edit', $draft->id))
            ->assertSessionHas('success', 'Draft berhasil disimpan. Lengkapi data sebelum dipublikasikan.');

        $this->assertSame(Product::PUBLICATION_DRAFT, $draft->publication_status);
        $this->assertFalse($draft->is_active);
        $this->assertNull($draft->brand_id);
        $this->assertNull($draft->base_price);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('product_images', 0);
        $this->get(route('products.show', $draft))->assertNotFound();
    }

    public function test_direct_publish_rejects_incomplete_input_without_creating_a_product(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'Incomplete Publish',
                'publication_action' => Product::PUBLICATION_PUBLISHED,
            ])
            ->assertSessionHasErrors([
                'brand_id',
                'category_id',
                'description',
                'gender',
                'variants',
                'images',
            ]);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_draft_catalog_filter_and_editor_show_publication_blockers(): void
    {
        $draft = Product::create([
            'name' => 'Filtered Draft Product',
            'publication_status' => Product::PUBLICATION_DRAFT,
            'is_active' => false,
        ]);
        $context = ['publication' => Product::PUBLICATION_DRAFT];
        $returnPath = route('admin.products.index', $context, false);

        $this->actingAs($this->admin)
            ->get(route('admin.products.index', $context))
            ->assertOk()
            ->assertSee($draft->name)
            ->assertSee('Draft')
            ->assertSee(e(route('admin.products.edit', [
                'product' => $draft->id,
                'return_to' => $returnPath,
            ])), false);

        $this->actingAs($this->admin)
            ->get(route('admin.products.edit', [
                'product' => $draft->id,
                'return_to' => $returnPath,
            ]))
            ->assertOk()
            ->assertSee('Brand belum dipilih atau sudah nonaktif.')
            ->assertSee('Kategori belum dipilih.')
            ->assertSee('Produk harus mempunyai tepat satu ukuran dan harga aktif.')
            ->assertSee('Produk harus mempunyai tepat satu foto utama.')
            ->assertSee('name="return_to" value="'.e($returnPath).'"', false);
    }

    public function test_complete_draft_can_be_published_without_changing_availability(): void
    {
        $draft = $this->createCompleteProduct(Product::PUBLICATION_DRAFT, false);
        $payload = $this->validUpdatePayload($draft, [
            'publication_action' => Product::PUBLICATION_PUBLISHED,
            'availability_status' => Product::AVAILABILITY_SOLD_OUT,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $draft->id), $payload)
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success', 'Product updated successfully!');

        $draft->refresh();

        $this->assertSame(Product::PUBLICATION_PUBLISHED, $draft->publication_status);
        $this->assertTrue($draft->is_active);
        $this->assertNotNull($draft->published_at);
        $this->assertSame(Product::AVAILABILITY_SOLD_OUT, $draft->availability_status);
    }

    public function test_draft_publish_is_rejected_without_primary_image_and_state_is_preserved(): void
    {
        $draft = $this->createCompleteProduct(Product::PUBLICATION_DRAFT, false, withImage: false);
        $payload = $this->validUpdatePayload($draft, [
            'publication_action' => Product::PUBLICATION_PUBLISHED,
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.products.edit', $draft->id))
            ->put(route('admin.products.update', $draft->id), $payload)
            ->assertRedirect(route('admin.products.edit', $draft->id))
            ->assertSessionHasErrors('new_images');

        $draft->refresh();

        $this->assertSame(Product::PUBLICATION_DRAFT, $draft->publication_status);
        $this->assertFalse($draft->is_active);
        $this->assertNull($draft->published_at);
    }

    public function test_restore_uses_publish_gate_and_redirects_incomplete_product_to_editor(): void
    {
        $archived = $this->createCompleteProduct(Product::PUBLICATION_ARCHIVED, false, withImage: false);
        $returnPath = route('admin.products.index', [
            'publication' => Product::PUBLICATION_ARCHIVED,
        ], false);

        $this->actingAs($this->admin)
            ->patch(route('admin.products.restore', $archived->id), [
                'return_to' => $returnPath,
            ])
            ->assertRedirect(route('admin.products.edit', [
                'product' => $archived->id,
                'return_to' => $returnPath,
            ]))
            ->assertSessionHasErrors('publication');

        $archived->refresh();

        $this->assertSame(Product::PUBLICATION_ARCHIVED, $archived->publication_status);
        $this->assertFalse($archived->is_active);
    }

    public function test_legacy_published_product_remains_published_after_normal_edit(): void
    {
        $legacy = $this->createCompleteProduct(Product::PUBLICATION_PUBLISHED, true, withImage: false);
        $payload = $this->validUpdatePayload($legacy, [
            'name' => 'Legacy Published Updated',
            'publication_action' => 'save',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $legacy->id), $payload)
            ->assertRedirect(route('admin.products.index'));

        $legacy->refresh();

        $this->assertSame('Legacy Published Updated', $legacy->name);
        $this->assertSame(Product::PUBLICATION_PUBLISHED, $legacy->publication_status);
        $this->assertTrue($legacy->is_active);
    }

    private function createCompleteProduct(
        string $publicationStatus,
        bool $isActive,
        bool $withImage = true
    ): Product {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => 'Publication Test Product',
            'description' => 'Product used to verify publication workflow.',
            'base_price' => 125000,
            'gender' => 'Unisex',
            'is_active' => $isActive,
            'publication_status' => $publicationStatus,
            'availability_status' => Product::AVAILABILITY_SOLD_OUT,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'volume' => 100,
            'price' => 125000,
            'stock' => 0,
            'is_active' => true,
        ]);

        if ($withImage) {
            $path = 'products/publication-test-'.$product->id.'.png';
            Storage::disk('public')->put($path, 'image-content');
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $path,
                'is_primary' => true,
                'sort_order' => 0,
            ]);
        }

        return $product;
    }

    private function validUpdatePayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'name' => $product->name,
            'brand_id' => $product->brand_id,
            'category_id' => $product->category_id,
            'description' => $product->description,
            'compare_at_price' => null,
            'gender' => 'Unisex',
            'top_notes' => '',
            'middle_notes' => '',
            'base_notes' => '',
            'availability_status' => $product->availability_status,
            'availability_confirmed' => '0',
            'variants' => [[
                'id' => $product->variants()->firstOrFail()->id,
                'volume' => 100,
                'price' => 125000,
                'stock' => 0,
            ]],
        ], $overrides);
    }
}
