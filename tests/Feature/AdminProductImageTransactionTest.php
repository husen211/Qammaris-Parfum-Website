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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AdminProductImageTransactionTest extends TestCase
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
            'name' => 'Transaction Brand',
            'is_active' => true,
        ]);
        $this->category = Category::create(['name' => 'EDP']);
    }

    public function test_failed_create_rolls_back_database_and_newly_uploaded_images(): void
    {
        $this->failOnSecondProductImageCreate();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.products.store'), $this->validStorePayload());

        $response->assertSessionHas('error', 'Produk gagal disimpan. Silakan coba lagi.');
        $response->assertSessionMissing('error_detail');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('products'));
    }

    public function test_failed_update_preserves_existing_state_and_cleans_new_images(): void
    {
        $product = $this->createExistingProduct();
        Storage::disk('public')->put('products/existing.png', 'existing-image');
        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'products/existing.png',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $this->failOnSecondProductImageCreate();

        $payload = $this->validUpdatePayload($product);
        $payload['name'] = 'Name That Must Roll Back';
        $payload['new_images'] = [
            $this->fakeImage('new-one.png'),
            $this->fakeImage('new-two.png'),
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product->id), $payload);

        $response->assertSessionHas('error', 'Produk gagal diperbarui. Silakan coba lagi.');
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Existing Product',
        ]);
        $this->assertDatabaseCount('product_images', 1);
        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'image_path' => 'products/existing.png',
        ]);
        $this->assertSame(
            ['products/existing.png'],
            Storage::disk('public')->allFiles('products')
        );
    }

    private function failOnSecondProductImageCreate(): void
    {
        $createdImages = 0;

        Event::listen('eloquent.creating: '.ProductImage::class, function () use (&$createdImages): void {
            $createdImages++;

            if ($createdImages === 2) {
                throw new RuntimeException('Simulated database failure containing internal details.');
            }
        });
    }

    private function validStorePayload(): array
    {
        return [
            'name' => 'Transaction Product',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'description' => 'Valid description.',
            'compare_at_price' => 150000,
            'gender' => 'Unisex',
            'top_notes' => '',
            'middle_notes' => '',
            'base_notes' => '',
            'variants' => [[
                'volume' => 100,
                'price' => 100000,
                'stock' => 0,
            ]],
            'images' => [
                $this->fakeImage('first.png'),
                $this->fakeImage('second.png'),
            ],
        ];
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
            'sku' => 'TRANSACTION-001',
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

    private function fakeImage(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
