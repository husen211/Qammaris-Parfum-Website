<?php

namespace Tests\Feature;

use App\Models\ProductImage;
use App\Services\ProductMediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaStorageTest extends TestCase
{
    private const DISK = 'product-media-test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'media.product_disk' => self::DISK,
            'media.product_directory' => 'products',
        ]);
        Storage::fake(self::DISK);
        Storage::fake('public');
    }

    public function test_upload_uses_configured_disk_and_returns_verified_object_key(): void
    {
        $path = app(ProductMediaStorage::class)->store($this->fakeImage('product.png'));

        $this->assertStringStartsWith('products/', $path);
        Storage::disk(self::DISK)->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_image_url_reads_canonical_and_legacy_prefixed_paths_from_configured_disk(): void
    {
        Storage::disk(self::DISK)->put('products/legacy.jpg', 'legacy-image');
        $expected = Storage::disk(self::DISK)->url('products/legacy.jpg');

        foreach ([
            'products/legacy.jpg',
            '/storage/products/legacy.jpg',
            'public/products/legacy.jpg',
            'storage/public/products/legacy.jpg',
        ] as $storedPath) {
            $image = new ProductImage(['image_path' => $storedPath]);

            $this->assertSame($expected, $image->image_url);
        }
    }

    public function test_missing_or_unsafe_local_path_returns_placeholder(): void
    {
        foreach (['products/missing.jpg', '../private/secret.jpg', "products/bad\0path.jpg"] as $storedPath) {
            $image = new ProductImage(['image_path' => $storedPath]);

            $this->assertSame(ProductImage::PLACEHOLDER_URL, $image->image_url);
        }
    }

    public function test_remote_http_url_is_not_hotlinked(): void
    {
        $image = new ProductImage(['image_path' => 'https://legacy-provider.test/image.jpg']);

        $this->assertSame(ProductImage::PLACEHOLDER_URL, $image->image_url);
        $this->assertNull(app(ProductMediaStorage::class)->normalizeLocalPath($image->image_path));
    }

    public function test_cleanup_normalizes_local_paths_and_ignores_remote_or_unsafe_values(): void
    {
        Storage::disk(self::DISK)->put('products/new.jpg', 'new-image');
        Storage::disk(self::DISK)->put('products/keep.jpg', 'keep-image');

        $deleted = app(ProductMediaStorage::class)->delete([
            'storage/products/new.jpg',
            'https://provider.test/remote.jpg',
            '../private/secret.jpg',
        ]);

        $this->assertTrue($deleted);
        Storage::disk(self::DISK)->assertMissing('products/new.jpg');
        Storage::disk(self::DISK)->assertExists('products/keep.jpg');
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
