<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_product_detail_is_not_publicly_accessible(): void
    {
        $this->withoutVite();

        $product = $this->createProduct(['is_active' => false]);
        $this->createVariant($product);

        $this->get(route('products.show', $product))
            ->assertNotFound();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'view_count' => 0,
        ]);
    }

    public function test_inactive_product_variant_cannot_be_added_to_cart(): void
    {
        $product = $this->createProduct(['is_active' => false]);
        $variant = $this->createVariant($product, ['stock' => 10]);

        $this->postJson(route('cart.add'), [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertNotFound();

        $this->assertEmpty(session('cart', []));
    }

    public function test_inactive_variant_cannot_be_added_to_cart(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant($product, [
            'stock' => 10,
            'is_active' => false,
        ]);

        $this->postJson(route('cart.add'), [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertNotFound();

        $this->assertEmpty(session('cart', []));
    }

    public function test_active_variant_can_be_added_to_cart(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant($product, ['stock' => 10]);

        $this->postJson(route('cart.add'), [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(2, session("cart.{$variant->id}.quantity"));
    }

    public function test_inactive_variant_in_existing_cart_cannot_be_updated(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant($product, [
            'stock' => 10,
            'is_active' => false,
        ]);

        $cartItem = [
            'variant_id' => $variant->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'slug' => $product->slug,
            'brand_name' => $product->brand->name,
            'volume' => $variant->volume,
            'price' => $variant->price,
            'quantity' => 1,
            'image' => 'placeholder.jpg',
        ];

        $this->withSession(['cart' => [$variant->id => $cartItem]])
            ->putJson(route('cart.update', $variant->id), ['quantity' => 2])
            ->assertNotFound();

        $this->assertSame(1, session("cart.{$variant->id}.quantity"));
    }

    public function test_admin_cannot_update_a_variant_owned_by_another_product(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $product = $this->createProduct(['name' => 'Target Product']);
        $ownedVariant = $this->createVariant($product, [
            'price' => 100000,
            'sku' => 'TARGET-001',
        ]);

        $otherProduct = $this->createProduct(['name' => 'Other Product']);
        $otherVariant = $this->createVariant($otherProduct, [
            'price' => 200000,
            'sku' => 'OTHER-001',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.products.update', $product->id), [
            'name' => $product->name,
            'brand_id' => $product->brand_id,
            'category_id' => $product->category_id,
            'description' => $product->description,
            'gender' => 'Unisex',
            'top_notes' => '',
            'middle_notes' => '',
            'base_notes' => '',
            'variants' => [[
                'id' => $otherVariant->id,
                'volume' => 50,
                'price' => 999999,
                'stock' => 1,
            ]],
        ]);

        $response->assertSessionHasErrors('variants.0.id');

        $this->assertDatabaseHas('product_variants', [
            'id' => $ownedVariant->id,
            'product_id' => $product->id,
            'price' => 100000,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'id' => $otherVariant->id,
            'product_id' => $otherProduct->id,
            'price' => 200000,
        ]);
    }

    private function createProduct(array $overrides = []): Product
    {
        $brand = Brand::create([
            'name' => 'Brand '.uniqid(),
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Category '.uniqid(),
        ]);

        return Product::create(array_merge([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Product '.uniqid(),
            'description' => 'Product description',
            'base_price' => 100000,
            'gender' => 'Unisex',
            'is_active' => true,
            'view_count' => 0,
        ], $overrides));
    }

    private function createVariant(Product $product, array $overrides = []): ProductVariant
    {
        return ProductVariant::create(array_merge([
            'product_id' => $product->id,
            'volume' => 100,
            'price' => 100000,
            'sku' => 'SKU-'.uniqid(),
            'stock' => 10,
            'is_active' => true,
        ], $overrides));
    }
}
