<?php

namespace Tests\Feature;

use App\Actions\Products\AttachProductImage;
use App\Actions\Products\SetPrimaryProductImage;
use App\Models\Product;
use App\Models\ProductImage;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const DISK = 'product-media-lifecycle-test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['media.product_disk' => self::DISK]);
        Storage::fake(self::DISK);
    }

    public function test_first_attachment_becomes_primary_automatically(): void
    {
        $product = $this->createDraft('First Image');

        $image = app(AttachProductImage::class)->handle($product, $this->storedPath('products/first.jpg'));

        $this->assertTrue($image->is_primary);
        $this->assertSame(0, $image->sort_order);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_new_primary_demotes_previous_primary_without_replacing_metadata(): void
    {
        $product = $this->createDraft('Primary Transition');
        $action = app(AttachProductImage::class);
        $first = $action->handle($product, $this->storedPath('products/first.jpg'));
        $second = $action->handle($product, $this->storedPath('products/second.jpg'), true);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertDatabaseHas('product_images', [
            'id' => $first->id,
            'image_path' => 'products/first.jpg',
            'sort_order' => 0,
        ]);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_fourth_attachment_is_rejected_without_changing_existing_metadata(): void
    {
        $product = $this->createDraft('Maximum Images');
        $action = app(AttachProductImage::class);

        foreach (range(1, ProductImage::MAX_PER_PRODUCT) as $index) {
            $action->handle($product, $this->storedPath("products/image-{$index}.jpg"));
        }

        try {
            $action->handle($product, $this->storedPath('products/image-4.jpg'));
            $this->fail('A fourth image should be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('Produk hanya boleh memiliki maksimum tiga gambar.', $exception->getMessage());
        }

        $this->assertDatabaseCount('product_images', ProductImage::MAX_PER_PRODUCT);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
        $this->assertDatabaseMissing('product_images', ['image_path' => 'products/image-4.jpg']);
    }

    public function test_set_primary_preserves_ids_paths_and_order_and_is_idempotent(): void
    {
        $product = $this->createDraft('Select Primary');
        $attach = app(AttachProductImage::class);
        $first = $attach->handle($product, $this->storedPath('products/first.jpg'));
        $second = $attach->handle($product, $this->storedPath('products/second.jpg'));
        $identity = [$second->id, $second->image_path, $second->sort_order];
        $action = app(SetPrimaryProductImage::class);

        $selected = $action->handle($product, $second->id);
        $selectedAgain = $action->handle($product, $second->id);

        $this->assertSame($identity, [$selected->id, $selected->image_path, $selected->sort_order]);
        $this->assertSame($selected->id, $selectedAgain->id);
        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_set_primary_rejects_an_image_owned_by_another_product(): void
    {
        $product = $this->createDraft('Target Product');
        $other = $this->createDraft('Other Product');
        $otherImage = app(AttachProductImage::class)->handle($other, $this->storedPath('products/other.jpg'));

        $this->expectException(ModelNotFoundException::class);

        app(SetPrimaryProductImage::class)->handle($product, $otherImage->id);
    }

    public function test_new_attachment_rejects_non_canonical_or_remote_path(): void
    {
        $product = $this->createDraft('Unsafe Paths');
        $action = app(AttachProductImage::class);

        foreach (['storage/products/prefixed.jpg', '../private/file.jpg', 'blog/not-product.jpg', 'https://provider.test/image.jpg'] as $path) {
            try {
                $action->handle($product, $path);
                $this->fail("Unsafe attachment path should be rejected: {$path}");
            } catch (\InvalidArgumentException) {
                $this->assertDatabaseMissing('product_images', ['image_path' => $path]);
            }
        }

        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_new_attachment_requires_an_existing_verified_file(): void
    {
        $product = $this->createDraft('Missing File');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('File gambar harus tersedia dan terverifikasi sebelum metadata dibuat.');

        app(AttachProductImage::class)->handle($product, 'products/missing.jpg');
    }

    private function createDraft(string $name): Product
    {
        return Product::create([
            'name' => $name,
            'publication_status' => Product::PUBLICATION_DRAFT,
            'is_active' => false,
        ]);
    }

    private function storedPath(string $path): string
    {
        Storage::disk(self::DISK)->put($path, 'test-image');

        return $path;
    }
}
