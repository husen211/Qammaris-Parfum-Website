<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaCopyCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCE_DISK = 'product-media-source-test';

    private const TARGET_DISK = 'product-media-target-test';

    private const MANIFEST_DISK = 'product-media-manifest-test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'media.product_directory' => 'products',
            'media.manifest_disk' => self::MANIFEST_DISK,
            'media.manifest_directory' => 'media-migrations',
        ]);
        Storage::fake(self::SOURCE_DISK);
        Storage::fake(self::TARGET_DISK);
        Storage::fake(self::MANIFEST_DISK);
    }

    public function test_dry_run_is_default_and_does_not_write_target(): void
    {
        $this->createReferencedImage('products/dry-run.jpg', 'dry-run-content');

        $this->runCommand()->assertSuccessful();

        Storage::disk(self::TARGET_DISK)->assertMissing('products/dry-run.jpg');
        $manifest = $this->latestManifest();
        $this->assertSame('dry_run', $manifest['mode']);
        $this->assertSame(['planned_copy' => 1], $manifest['summary']);
        $this->assertSame('planned_copy', $manifest['records'][0]['status']);
    }

    public function test_apply_copies_and_verifies_without_removing_source(): void
    {
        $this->createReferencedImage('products/copy.jpg', 'copy-content');

        $this->runCommand(true)->assertSuccessful();

        Storage::disk(self::SOURCE_DISK)->assertExists('products/copy.jpg');
        Storage::disk(self::TARGET_DISK)->assertExists('products/copy.jpg');
        $this->assertSame(
            Storage::disk(self::SOURCE_DISK)->get('products/copy.jpg'),
            Storage::disk(self::TARGET_DISK)->get('products/copy.jpg')
        );

        $manifest = $this->latestManifest();
        $this->assertSame('apply', $manifest['mode']);
        $this->assertSame('copied_verified', $manifest['records'][0]['status']);
        $this->assertSame($manifest['records'][0]['source'], $manifest['records'][0]['target']);
    }

    public function test_repeated_apply_is_idempotent_and_reports_already_verified(): void
    {
        $this->createReferencedImage('products/idempotent.jpg', 'same-content');
        $this->runCommand(true)->assertSuccessful();

        Storage::disk(self::MANIFEST_DISK)->deleteDirectory('media-migrations');
        $this->runCommand(true)->assertSuccessful();

        $manifest = $this->latestManifest();
        $this->assertSame(['already_verified' => 1], $manifest['summary']);
        $this->assertSame('same-content', Storage::disk(self::TARGET_DISK)->get('products/idempotent.jpg'));
    }

    public function test_existing_target_mismatch_is_reported_and_not_overwritten(): void
    {
        $this->createReferencedImage('products/conflict.jpg', 'source-content');
        Storage::disk(self::TARGET_DISK)->put('products/conflict.jpg', 'target-content');

        $this->runCommand(true)->assertFailed();

        $manifest = $this->latestManifest();
        $this->assertSame(['target_mismatch' => 1], $manifest['summary']);
        $this->assertSame('target-content', Storage::disk(self::TARGET_DISK)->get('products/conflict.jpg'));
        Storage::disk(self::SOURCE_DISK)->assertExists('products/conflict.jpg');
    }

    public function test_missing_and_invalid_sources_fail_without_stopping_manifest_creation(): void
    {
        $product = $this->createDraft('Invalid Sources');
        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'products/missing.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'https://provider.test/remote.jpg',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $this->runCommand()->assertFailed();

        $manifest = $this->latestManifest();
        $this->assertSame(1, $manifest['summary']['invalid_path']);
        $this->assertSame(1, $manifest['summary']['missing_source']);
        $this->assertFalse($manifest['success']);
        $this->assertCount(2, $manifest['records']);
    }

    public function test_manifest_groups_duplicate_references_and_contains_no_disk_credentials(): void
    {
        $first = $this->createDraft('First Reference');
        $second = $this->createDraft('Second Reference');
        Storage::disk(self::SOURCE_DISK)->put('products/shared.jpg', 'shared-content');
        $firstImage = ProductImage::create([
            'product_id' => $first->id,
            'image_path' => 'products/shared.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        $secondImage = ProductImage::create([
            'product_id' => $second->id,
            'image_path' => 'products/shared.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        config([
            'filesystems.disks.'.self::TARGET_DISK.'.key' => 'ACCESS_KEY_MUST_NOT_LEAK',
            'filesystems.disks.'.self::TARGET_DISK.'.secret' => 'SECRET_MUST_NOT_LEAK',
        ]);

        $this->runCommand()->assertSuccessful();

        $manifest = $this->latestManifest();
        $this->assertCount(1, $manifest['records']);
        $this->assertEqualsCanonicalizing(
            [$firstImage->id, $secondImage->id],
            $manifest['records'][0]['image_ids']
        );
        $encoded = json_encode($manifest, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('ACCESS_KEY_MUST_NOT_LEAK', $encoded);
        $this->assertStringNotContainsString('SECRET_MUST_NOT_LEAK', $encoded);
        $this->assertStringNotContainsString('shared-content', $encoded);
    }

    public function test_same_source_and_target_disk_is_rejected_before_copy(): void
    {
        $this->createReferencedImage('products/same-disk.jpg', 'content');

        $this->artisan('product-media:copy-verify', [
            '--source' => self::SOURCE_DISK,
            '--target' => self::SOURCE_DISK,
        ])->assertFailed();

        Storage::disk(self::MANIFEST_DISK)->assertDirectoryEmpty('media-migrations');
        Storage::disk(self::SOURCE_DISK)->assertExists('products/same-disk.jpg');
    }

    private function runCommand(bool $apply = false)
    {
        $arguments = [
            '--source' => self::SOURCE_DISK,
            '--target' => self::TARGET_DISK,
        ];

        if ($apply) {
            $arguments['--apply'] = true;
        }

        return $this->artisan('product-media:copy-verify', $arguments);
    }

    private function latestManifest(): array
    {
        $files = Storage::disk(self::MANIFEST_DISK)->allFiles('media-migrations');
        sort($files);
        $this->assertNotEmpty($files);

        return json_decode(
            Storage::disk(self::MANIFEST_DISK)->get(end($files)),
            true,
            flags: JSON_THROW_ON_ERROR
        );
    }

    private function createReferencedImage(string $path, string $contents): ProductImage
    {
        $product = $this->createDraft('Copy Verify Product');
        Storage::disk(self::SOURCE_DISK)->put($path, $contents);

        return ProductImage::create([
            'product_id' => $product->id,
            'image_path' => $path,
            'is_primary' => true,
            'sort_order' => 0,
        ]);
    }

    private function createDraft(string $name): Product
    {
        return Product::create([
            'name' => $name,
            'publication_status' => Product::PUBLICATION_DRAFT,
            'is_active' => false,
        ]);
    }
}
