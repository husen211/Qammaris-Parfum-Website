<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminTaxonomyManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();
    }

    public function test_admin_can_create_brand_and_category_with_active_defaults(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.brands.store'), [
                'name' => 'Maison Test',
                'description' => 'Brand untuk pengujian.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.brands.index'));

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Parfum Test',
                'description' => 'Kategori untuk pengujian.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('brands', [
            'name' => 'Maison Test',
            'slug' => 'maison-test',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Parfum Test',
            'slug' => 'parfum-test',
            'is_active' => true,
        ]);
    }

    public function test_taxonomy_names_are_unique_and_required(): void
    {
        Brand::create(['name' => 'Duplicate Brand']);
        Category::create(['name' => 'Duplicate Category']);

        $this->actingAs($this->admin)
            ->post(route('admin.brands.store'), ['name' => 'Duplicate Brand', 'is_active' => '1'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Duplicate Category', 'is_active' => '1'])
            ->assertSessionHasErrors('name');
    }

    public function test_taxonomy_slug_stays_stable_when_name_changes(): void
    {
        $brand = Brand::create(['name' => 'Original Brand']);
        $category = Category::create(['name' => 'Original Category']);

        $this->actingAs($this->admin)
            ->put(route('admin.brands.update', $brand), [
                'name' => 'Renamed Brand',
                'description' => null,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.brands.index'));

        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $category), [
                'name' => 'Renamed Category',
                'description' => null,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertSame('original-brand', $brand->refresh()->slug);
        $this->assertSame('original-category', $category->refresh()->slug);
    }

    public function test_status_changes_are_reversible_and_preserve_product_relations(): void
    {
        $brand = Brand::create(['name' => 'Related Brand']);
        $category = Category::create(['name' => 'Related Category']);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Related Product',
            'publication_status' => Product::PUBLICATION_DRAFT,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.brands.status', $brand), ['is_active' => '0'])
            ->assertSessionHas('success', 'Brand berhasil dinonaktifkan.');
        $this->actingAs($this->admin)
            ->patch(route('admin.categories.status', $category), ['is_active' => '0'])
            ->assertSessionHas('success', 'Kategori berhasil dinonaktifkan.');

        $this->assertFalse($brand->refresh()->is_active);
        $this->assertFalse($category->refresh()->is_active);
        $this->assertSame($brand->id, $product->refresh()->brand_id);
        $this->assertSame($category->id, $product->category_id);

        $this->actingAs($this->admin)
            ->patch(route('admin.brands.status', $brand), ['is_active' => '1'])
            ->assertSessionHas('success', 'Brand berhasil diaktifkan.');
        $this->actingAs($this->admin)
            ->patch(route('admin.categories.status', $category), ['is_active' => '1'])
            ->assertSessionHas('success', 'Kategori berhasil diaktifkan.');

        $this->assertTrue($brand->refresh()->is_active);
        $this->assertTrue($category->refresh()->is_active);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_index_supports_search_counts_and_empty_state(): void
    {
        $matchingBrand = Brand::create(['name' => 'Findable Brand']);
        $otherBrand = Brand::create(['name' => 'Other Brand']);
        $category = Category::create(['name' => 'Findable Category']);
        Product::create([
            'brand_id' => $matchingBrand->id,
            'category_id' => $category->id,
            'name' => 'Counted Product',
            'publication_status' => Product::PUBLICATION_DRAFT,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.brands.index', ['search' => 'Findable']))
            ->assertOk()
            ->assertSee('Findable Brand')
            ->assertDontSee($otherBrand->name)
            ->assertSee('1');

        $this->actingAs($this->admin)
            ->get(route('admin.categories.index', ['search' => 'missing']))
            ->assertOk()
            ->assertSee('Kategori tidak ditemukan');
    }

    public function test_inactive_taxonomies_are_hidden_from_new_product_and_public_filters(): void
    {
        $activeBrand = Brand::create(['name' => 'Active Brand', 'is_active' => true]);
        $inactiveBrand = Brand::create(['name' => 'Inactive Brand', 'is_active' => false]);
        $activeCategory = Category::create(['name' => 'Active Category', 'is_active' => true]);
        $inactiveCategory = Category::create(['name' => 'Inactive Category', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertViewHas('brands', fn ($brands) => $brands->contains($activeBrand) && ! $brands->contains($inactiveBrand))
            ->assertViewHas('categories', fn ($categories) => $categories->contains($activeCategory) && ! $categories->contains($inactiveCategory));

        $this->get(route('products.index'))
            ->assertOk()
            ->assertViewHas('brands', fn ($brands) => $brands->contains($activeBrand) && ! $brands->contains($inactiveBrand))
            ->assertViewHas('categories', fn ($categories) => $categories->contains($activeCategory) && ! $categories->contains($inactiveCategory));
    }

    public function test_editor_keeps_current_inactive_taxonomy_but_publish_rejects_it(): void
    {
        $brand = Brand::create(['name' => 'Inactive Current Brand', 'is_active' => false]);
        $category = Category::create(['name' => 'Inactive Current Category', 'is_active' => false]);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Draft With Inactive Taxonomy',
            'publication_status' => Product::PUBLICATION_DRAFT,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.products.edit', $product->id))
            ->assertOk()
            ->assertViewHas('brands', fn ($brands) => $brands->contains($brand))
            ->assertViewHas('categories', fn ($categories) => $categories->contains($category))
            ->assertSee('Kategori belum dipilih atau sudah nonaktif.');

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product->id), [
                'name' => $product->name,
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'publication_action' => Product::PUBLICATION_PUBLISHED,
            ])
            ->assertSessionHasErrors(['brand_id', 'category_id']);

        $this->assertSame(Product::PUBLICATION_DRAFT, $product->refresh()->publication_status);
    }

    public function test_category_default_is_active_and_no_taxonomy_delete_routes_exist(): void
    {
        $category = Category::create(['name' => 'Default Active Category']);

        $this->assertTrue($category->refresh()->is_active);
        $this->assertFalse(Route::has('admin.brands.destroy'));
        $this->assertFalse(Route::has('admin.categories.destroy'));
    }
}
