<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductCatalogContextTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Brand $brand;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();
        $this->brand = Brand::create([
            'name' => 'Context Brand',
            'is_active' => true,
        ]);
        $this->category = Category::create(['name' => 'EDP']);
    }

    public function test_catalog_preserves_normalized_context_in_edit_links_and_pagination(): void
    {
        foreach (range(1, 11) as $number) {
            $this->createProduct(sprintf('Context Product %02d', $number));
        }

        $context = [
            'search' => 'Context Product',
            'brand_id' => $this->brand->id,
            'sort' => 'name_desc',
        ];
        $returnPath = route('admin.products.index', $context, false);
        $firstProduct = Product::query()->where('name', 'Context Product 11')->firstOrFail();

        $response = $this->actingAs($this->admin)->get(route('admin.products.index', [
            ...$context,
            'ignored' => 'not-preserved',
        ]));

        $response
            ->assertOk()
            ->assertSeeInOrder(['Context Product 11', 'Context Product 10'])
            ->assertSee(e(route('admin.products.edit', [
                'product' => $firstProduct->id,
                'return_to' => $returnPath,
            ])), false)
            ->assertSee(e(route('admin.products.index', [
                ...$context,
                'page' => 2,
            ])), false)
            ->assertDontSee('ignored=not-preserved', false);
    }

    public function test_edit_cancel_and_successful_update_return_to_the_same_catalog_context(): void
    {
        $product = $this->createProduct('Context Product');
        $returnPath = route('admin.products.index', [
            'page' => 2,
            'search' => 'Context',
            'brand_id' => $this->brand->id,
            'sort' => 'name_asc',
        ], false);

        $this->actingAs($this->admin)
            ->get(route('admin.products.edit', [
                'product' => $product->id,
                'return_to' => $returnPath,
            ]))
            ->assertOk()
            ->assertSee('href="'.e($returnPath).'"', false)
            ->assertSee('name="return_to" value="'.e($returnPath).'"', false);

        $payload = $this->validUpdatePayload($product);
        $payload['name'] = 'Context Product Updated';
        $payload['return_to'] = $returnPath;

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product->id), $payload)
            ->assertRedirect($returnPath)
            ->assertSessionHas('success', 'Product updated successfully!');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Context Product Updated',
        ]);
    }

    public function test_validation_failure_keeps_the_return_context_for_the_editor(): void
    {
        $product = $this->createProduct('Validation Context Product');
        $returnPath = route('admin.products.index', [
            'page' => 3,
            'sort' => 'name_desc',
        ], false);
        $editUrl = route('admin.products.edit', [
            'product' => $product->id,
            'return_to' => $returnPath,
        ]);
        $payload = $this->validUpdatePayload($product);
        $payload['name'] = '';
        $payload['return_to'] = $returnPath;

        $this->actingAs($this->admin)
            ->from($editUrl)
            ->put(route('admin.products.update', $product->id), $payload)
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors('name');

        $this->followingRedirects()
            ->get($editUrl)
            ->assertOk()
            ->assertSee('name="return_to" value="'.e($returnPath).'"', false);
    }

    public function test_external_or_malformed_return_context_falls_back_to_catalog_root(): void
    {
        $product = $this->createProduct('Safe Context Product');
        $externalReturn = 'https://evil.example/admin/products?page=9';

        $this->actingAs($this->admin)
            ->get(route('admin.products.edit', [
                'product' => $product->id,
                'return_to' => $externalReturn,
            ]))
            ->assertOk()
            ->assertSee('name="return_to" value="/admin/products"', false)
            ->assertDontSee('evil.example', false);

        $payload = $this->validUpdatePayload($product);
        $payload['return_to'] = '//evil.example/admin/products?page=9';

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product->id), $payload)
            ->assertRedirect('/admin/products');

        $this->actingAs($this->admin)
            ->get('/admin/products?search[]=bad&sort[]=name_asc&page=-2&brand_id=missing')
            ->assertOk()
            ->assertDontSee('search%5B0%5D', false);
    }

    public function test_stale_page_redirects_to_the_last_available_page(): void
    {
        foreach (range(1, 11) as $number) {
            $this->createProduct(sprintf('Stale Page Product %02d', $number));
        }

        $this->actingAs($this->admin)
            ->get(route('admin.products.index', [
                'page' => 99,
                'search' => 'Stale Page Product',
                'sort' => 'name_asc',
            ]))
            ->assertRedirect(route('admin.products.index', [
                'page' => 2,
                'search' => 'Stale Page Product',
                'sort' => 'name_asc',
            ]));
    }

    private function createProduct(string $name): Product
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => $name,
            'description' => 'Product used to verify catalog return context.',
            'base_price' => 100000,
            'gender' => 'Unisex',
            'is_active' => true,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'volume' => 100,
            'price' => 100000,
            'stock' => 0,
            'is_active' => true,
        ]);

        return $product;
    }

    private function validUpdatePayload(Product $product): array
    {
        return [
            'name' => $product->name,
            'brand_id' => $product->brand_id,
            'category_id' => $product->category_id,
            'description' => $product->description,
            'compare_at_price' => null,
            'gender' => 'Unisex',
            'top_notes' => '',
            'middle_notes' => '',
            'base_notes' => '',
            'variants' => [[
                'id' => $product->variants()->firstOrFail()->id,
                'volume' => 100,
                'price' => 100000,
                'stock' => 0,
            ]],
        ];
    }
}
