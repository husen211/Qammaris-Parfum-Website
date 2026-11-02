<?php

namespace Tests\Feature;

use App\Actions\Products\PublishProduct;
use App\Exceptions\ProductNotReadyForPublication;
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

        config(['media.product_disk' => 'product-media-test']);
        Storage::fake('product-media-test');
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
        $this->assertSame([], Storage::disk('product-media-test')->allFiles('products'));
    }

    public function test_failed_update_preserves_existing_state_and_cleans_new_images(): void
    {
        $product = $this->createExistingProduct();
        Storage::disk('product-media-test')->put('products/existing.png', 'existing-image');
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
            Storage::disk('product-media-test')->allFiles('products')
        );
    }

    public function test_publication_failure_after_upload_rolls_back_create_and_cleans_files(): void
    {
        $this->mock(PublishProduct::class)->shouldReceive('handle')->once()
            ->andThrow(new ProductNotReadyForPublication(['category' => 'Kategori berubah sebelum publikasi.']));

        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->validStorePayload())
            ->assertSessionHasErrors(['publication' => 'Kategori berubah sebelum publikasi.']);

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame([], Storage::disk('product-media-test')->allFiles('products'));
    }

    public function test_publication_failure_after_upload_restores_update_and_keeps_existing_file(): void
    {
        $product = $this->createExistingProduct();
        Storage::disk('product-media-test')->put('products/existing.png', 'existing-image');
        $product->images()->create(['image_path' => 'products/existing.png', 'is_primary' => true, 'sort_order' => 0]);
        $this->mock(PublishProduct::class)->shouldReceive('handle')->once()
            ->andThrow(new ProductNotReadyForPublication(['category' => 'Kategori berubah sebelum publikasi.']));
        $payload = $this->validUpdatePayload($product);
        $payload['name'] = 'Must roll back';
        $payload['publication_action'] = 'published';
        $payload['new_images'] = [$this->fakeImage('new.png')];

        $this->actingAs($this->admin)->put(route('admin.products.update', $product->id), $payload)
            ->assertSessionHasErrors(['publication' => 'Kategori berubah sebelum publikasi.']);

        $this->assertSame('Existing Product', $product->fresh()->name);
        $this->assertDatabaseCount('product_images', 1);
        $this->assertSame(['products/existing.png'], Storage::disk('product-media-test')->allFiles('products'));
    }

    public function test_pasted_webp_photos_save_next_to_existing_ones_within_the_three_photo_limit(): void
    {
        // CAT-MEDIA-PASTE-01: the browser turns a pasted image into foto-tempel-N.webp and submits it as a normal upload.
        $webp = fn (string $name) => UploadedFile::fake()->createWithContent($name, base64_decode('UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAwA0JaQAA3AA/vuUAAA=', true));
        $product = $this->createExistingProduct();
        Storage::disk('product-media-test')->put('products/existing.png', 'existing-image');
        $product->images()->create(['image_path' => 'products/existing.png', 'is_primary' => true, 'sort_order' => 0]);

        $payload = $this->validUpdatePayload($product);
        $payload['new_images'] = [$webp('foto-tempel-1.webp'), $webp('foto-tempel-2.webp')];
        $this->actingAs($this->admin)->put(route('admin.products.update', $product->id), $payload)->assertSessionHasNoErrors();

        $images = $product->images()->orderBy('sort_order')->get();
        $this->assertCount(3, $images);
        $this->assertSame([true, false, false], $images->pluck('is_primary')->all());
        $this->assertStringEndsWith('.webp', $images[1]->image_path);
        Storage::disk('product-media-test')->assertExists($images[2]->image_path);

        $payload['new_images'] = [$webp('foto-tempel-3.webp')];
        $this->actingAs($this->admin)->put(route('admin.products.update', $product->id), $payload)->assertSessionHasErrors('new_images');
        $this->assertSame(3, $product->images()->count());
    }

    public function test_editor_sent_back_with_errors_asks_the_browser_to_restore_pasted_photos(): void
    {
        // CAT-MEDIA-PASTE-02: only a page returned with validation errors restores the photos kept from the failed submit.
        $this->withoutVite();
        $product = $this->createExistingProduct();
        $edit = route('admin.products.edit', $product->id);
        $page = $this->actingAs($this->admin)->get($edit)->assertOk()
            ->assertSee('data-publish-check', false)->assertSee('data-required-for-publish="Isi ukuran (ml) sebelum publish."', false);
        $this->assertStringNotContainsString('data-restore', $page->getContent());

        $payload = $this->validUpdatePayload($product);
        $payload['variants'][0]['volume'] = '';
        $payload['publication_action'] = 'published';
        $this->from($edit)->put(route('admin.products.update', $product->id), $payload)->assertRedirect($edit)->assertSessionHasErrors();
        $this->get($edit)->assertOk()->assertSee('data-restore', false);
        $this->assertStringNotContainsString('data-restore', $this->get($edit)->getContent());
    }

    public function test_add_and_edit_pages_offer_text_split_and_compare_price_options(): void
    {
        // CAT-TEXT-PASTE-01 / CAT-COMPARE-PRICE-01: browser helpers only; the server rules for these fields are unchanged.
        $this->withoutVite();
        $product = $this->createExistingProduct();
        foreach ([route('admin.products.create'), route('admin.products.edit', $product->id)] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk()
                ->assertSee('data-text-paste', false)->assertSee('Pisahkan dari deskripsi')
                ->assertSee('data-compare-input', false)->assertSee('data-price-input="#offer-price"', false)
                ->assertSee('Pakai harga coret yang wajar');
        }
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
