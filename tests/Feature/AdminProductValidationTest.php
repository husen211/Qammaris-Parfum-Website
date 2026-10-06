<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Brand $brand;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();
        $this->brand = Brand::create([
            'name' => 'Validation Brand',
            'is_active' => true,
        ]);
        $this->category = Category::create(['name' => 'EDP']);
    }

    public function test_store_rejects_invalid_product_boundaries(): void
    {
        $payload = $this->validStorePayload();
        $payload['gender'] = 'Semua';
        $payload['compare_at_price'] = 100000;
        $payload['variants'][0]['volume'] = 0;
        $payload['variants'][0]['price'] = 0;
        $payload['variants'][0]['stock'] = -1;

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), $payload)
            ->assertSessionHasErrors([
                'gender',
                'variants.0.volume',
                'variants.0.price',
                'variants.0.stock',
            ]);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_store_rejects_compare_at_price_not_above_sale_price(): void
    {
        $payload = $this->validStorePayload();
        $payload['compare_at_price'] = 100000;

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), $payload)
            ->assertSessionHasErrors('compare_at_price');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_store_rejects_more_than_one_offer(): void
    {
        $payload = $this->validStorePayload();
        $payload['variants'][] = [
            'volume' => 50,
            'price' => 75000,
            'stock' => 0,
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), $payload)
            ->assertSessionHasErrors('variants');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_store_rejects_inactive_brand(): void
    {
        $this->brand->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), $this->validStorePayload())
            ->assertSessionHasErrors('brand_id');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_store_rejects_more_than_three_images(): void
    {
        $payload = $this->validStorePayload();
        $payload['images'][] = $this->fakeImage('fourth.png');

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), $payload)
            ->assertSessionHasErrors('images');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_update_rejects_new_images_above_total_limit(): void
    {
        $product = $this->createExistingProduct();

        foreach (range(1, 2) as $index) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => "products/existing-{$index}.webp",
                'is_primary' => $index === 1,
                'sort_order' => $index - 1,
            ]);
        }

        $payload = $this->validUpdatePayload($product);
        $payload['new_images'] = [
            $this->fakeImage('new-one.png'),
            $this->fakeImage('new-two.png'),
        ];

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product->id), $payload)
            ->assertSessionHasErrors('new_images');

        $this->assertDatabaseCount('product_images', 2);
    }

    public function test_update_rejects_switching_to_another_inactive_brand(): void
    {
        $product = $this->createExistingProduct();
        $inactiveBrand = Brand::create([
            'name' => 'Inactive Brand',
            'is_active' => false,
        ]);
        $payload = $this->validUpdatePayload($product);
        $payload['brand_id'] = $inactiveBrand->id;

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product->id), $payload)
            ->assertSessionHasErrors('brand_id');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'brand_id' => $this->brand->id,
        ]);
    }

    public function test_update_allows_retaining_the_products_inactive_brand(): void
    {
        $product = $this->createExistingProduct();
        $this->brand->update(['is_active' => false]);
        $payload = $this->validUpdatePayload($product);
        $payload['name'] = 'Updated Product Name';

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product->id), $payload)
            ->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'brand_id' => $this->brand->id,
            'name' => 'Updated Product Name',
        ]);
    }

    public function test_update_syncs_offer_price_without_replacing_offer_or_sku(): void
    {
        $product = $this->createExistingProduct();
        $offer = $product->variants()->firstOrFail();
        $payload = $this->validUpdatePayload($product);
        $payload['variants'][0]['price'] = 225000;
        $payload['compare_at_price'] = 250000;

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product->id), $payload)
            ->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('product_variants', [
            'id' => $offer->id,
            'product_id' => $product->id,
            'price' => 225000,
            'sku' => 'EXISTING-001',
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'base_price' => 225000,
        ]);
        $this->assertDatabaseCount('product_variants', 1);
    }

    public function test_store_accepts_valid_product_with_three_images(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), $this->validStorePayload())
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', [
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ]);
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseHas('product_variants', [
            'price' => 100000,
            'sku' => null,
        ]);
        $this->assertDatabaseHas('products', ['base_price' => 100000]);
        $this->assertDatabaseCount('product_images', 3);
        $this->assertDatabaseHas('product_images', ['is_primary' => true]);
        $this->assertCount(3, Storage::disk('public')->allFiles('products'));
    }

    private function validStorePayload(): array
    {
        return [
            'name' => 'Valid Product',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'description' => 'Deskripsi produk yang valid.',
            'compare_at_price' => 150000,
            'gender' => 'Unisex',
            'top_notes' => 'Bergamot',
            'middle_notes' => 'Rose',
            'base_notes' => 'Musk',
            'variants' => [[
                'volume' => 100,
                'price' => 100000,
                'stock' => 0,
            ]],
            'images' => [
                $this->fakeImage('cover.png'),
                $this->fakeImage('second.png'),
                $this->fakeImage('third.png'),
            ],
        ];
    }

    private function fakeImage(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    private function createExistingProduct(): Product
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => 'Existing Product',
            'description' => 'Existing description.',
            'base_price' => 100000,
            'compare_at_price' => 150000,
            'gender' => 'Unisex',
            'is_active' => true,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'volume' => 100,
            'price' => 100000,
            'sku' => 'EXISTING-001',
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
            'compare_at_price' => 150000,
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
