<?php

namespace Tests\Feature;

use App\Actions\Products\AcquireProductImportRowImages;
use App\Actions\Products\AttachProductImage;
use App\Actions\Products\QueueProductImportImages;
use App\Jobs\AcquireProductImportRowImages as AcquireProductImportRowImagesJob;
use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AdminProductImportImageAcquisitionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        config([
            'media.product_disk' => 'public',
            'product_imports.image_acquisition.allowed_hosts' => ['images.example.test'],
            'product_imports.image_acquisition.max_bytes' => 1024 * 1024,
            'product_imports.image_acquisition.max_dimension' => 2000,
        ]);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();
    }

    public function test_only_admin_with_confirmation_can_queue_images_for_an_applied_batch(): void
    {
        Queue::fake();
        [$batch, $row] = $this->appliedRow(['foto_utama_url' => 'https://images.example.test/primary.png']);

        $this->post(route('admin.product-imports.images', $batch), ['confirm_image_acquisition' => '1'])
            ->assertRedirect(route('login'));

        $customer = User::factory()->create();
        $this->actingAs($customer)
            ->post(route('admin.product-imports.images', $batch), ['confirm_image_acquisition' => '1'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.images', $batch))
            ->assertSessionHasErrors(['confirm_image_acquisition']);

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.images', $batch), ['confirm_image_acquisition' => '1'])
            ->assertRedirect(route('admin.product-imports.create', ['batch' => $batch->id]))
            ->assertSessionHas('success');

        $this->assertSame(ProductImportRow::IMAGE_QUEUED, $row->fresh()->image_acquisition_status);
        Queue::assertPushed(
            AcquireProductImportRowImagesJob::class,
            fn (AcquireProductImportRowImagesJob $job): bool => $job->rowId === $row->id
        );
    }

    public function test_batch_must_be_applied_before_image_acquisition(): void
    {
        Queue::fake();
        [$batch] = $this->appliedRow(['foto_utama_url' => 'https://images.example.test/primary.png']);
        $batch->forceFill(['status' => ProductImportBatch::STATUS_PREVIEWED])->save();

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.images', $batch), ['confirm_image_acquisition' => '1'])
            ->assertSessionHas('error', 'Akuisisi gambar hanya tersedia untuk batch yang sudah selesai di-apply.');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_job_downloads_valid_image_to_qammaris_storage_and_skips_duplicate_url(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://images.example.test/primary.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);
        [$batch, $row, $product] = $this->appliedRow([
            'foto_utama_url' => 'https://images.example.test/primary.png',
            'foto_2_url' => 'https://images.example.test/primary.png',
        ]);

        app(QueueProductImportImages::class)->handle($batch, $this->admin);
        app(AcquireProductImportRowImages::class)->handle($row->id);

        $row->refresh();
        $image = $product->images()->sole();
        $this->assertSame(ProductImportRow::IMAGE_COMPLETED, $row->image_acquisition_status);
        $this->assertSame(['stored', 'skipped_duplicate'], collect($row->image_acquisition_outcomes)->pluck('status')->all());
        $this->assertTrue($image->is_primary);
        $this->assertStringStartsWith('products/', $image->image_path);
        $this->assertStringNotContainsString('images.example.test', $image->image_path);
        Storage::disk('public')->assertExists($image->image_path);
        Http::assertSentCount(1);
    }

    public function test_invalid_host_and_non_image_content_are_recorded_without_removing_draft(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://images.example.test/not-image' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html']),
        ]);
        [$batch, $row, $product] = $this->appliedRow([
            'foto_utama_url' => 'https://blocked.example.test/primary.png',
            'foto_2_url' => 'https://images.example.test/not-image',
        ]);

        app(QueueProductImportImages::class)->handle($batch, $this->admin);
        app(AcquireProductImportRowImages::class)->handle($row->id);

        $row->refresh();
        $this->assertSame(ProductImportRow::IMAGE_COMPLETED_WITH_ERRORS, $row->image_acquisition_status);
        $this->assertSame(['failed', 'failed'], collect($row->image_acquisition_outcomes)->pluck('status')->all());
        $this->assertSame(Product::PUBLICATION_DRAFT, $product->fresh()->publication_status);
        $this->assertDatabaseCount('product_images', 0);
        Http::assertSentCount(1);
    }

    public function test_product_that_is_no_longer_draft_is_blocked_before_network_request(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$batch, $row, $product] = $this->appliedRow([
            'foto_utama_url' => 'https://images.example.test/primary.png',
        ]);
        app(QueueProductImportImages::class)->handle($batch, $this->admin);
        $product->forceFill([
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'is_active' => true,
            'published_at' => now(),
        ])->save();

        app(AcquireProductImportRowImages::class)->handle($row->id);

        $row->refresh();
        $this->assertSame(ProductImportRow::IMAGE_COMPLETED_WITH_ERRORS, $row->image_acquisition_status);
        $this->assertSame('blocked', $row->image_acquisition_outcomes[0]['status']);
        $this->assertDatabaseCount('product_images', 0);
        Http::assertNothingSent();
    }

    public function test_retry_does_not_download_or_attach_a_stored_candidate_again(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://images.example.test/primary.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);
        [$batch, $row] = $this->appliedRow([
            'foto_utama_url' => 'https://images.example.test/primary.png',
        ]);

        app(QueueProductImportImages::class)->handle($batch, $this->admin);
        app(AcquireProductImportRowImages::class)->handle($row->id);
        $retry = app(QueueProductImportImages::class)->handle($batch, $this->admin);

        $this->assertSame(0, $retry['queued_rows']);
        $this->assertSame(0, $retry['candidate_images']);
        $this->assertDatabaseCount('product_images', 1);
        Http::assertSentCount(1);
    }

    public function test_new_storage_object_is_cleaned_when_metadata_attachment_fails(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://images.example.test/primary.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);
        [$batch, $row] = $this->appliedRow([
            'foto_utama_url' => 'https://images.example.test/primary.png',
        ]);
        $attach = Mockery::mock(AttachProductImage::class);
        $attach->shouldReceive('handle')->once()->andThrow(new DomainException('Slot gambar produk sudah penuh (maksimum tiga).'));
        $this->app->instance(AttachProductImage::class, $attach);

        app(QueueProductImportImages::class)->handle($batch, $this->admin);
        app(AcquireProductImportRowImages::class)->handle($row->id);

        $this->assertSame(ProductImportRow::IMAGE_COMPLETED_WITH_ERRORS, $row->fresh()->image_acquisition_status);
        $this->assertSame([], Storage::disk('public')->allFiles('products'));
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_redirect_and_oversized_source_are_rejected_before_storage_attachment(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        config(['product_imports.image_acquisition.max_bytes' => 32]);
        Http::fake([
            'https://images.example.test/redirect.png' => Http::response('', 302, ['Location' => 'https://images.example.test/final.png']),
            'https://images.example.test/large.png' => Http::response(str_repeat('x', 64), 200, ['Content-Type' => 'image/png']),
        ]);
        [$batch, $row] = $this->appliedRow([
            'foto_utama_url' => 'https://images.example.test/redirect.png',
            'foto_2_url' => 'https://images.example.test/large.png',
        ]);

        app(QueueProductImportImages::class)->handle($batch, $this->admin);
        app(AcquireProductImportRowImages::class)->handle($row->id);

        $this->assertSame(ProductImportRow::IMAGE_COMPLETED_WITH_ERRORS, $row->fresh()->image_acquisition_status);
        $this->assertDatabaseCount('product_images', 0);
    }

    /**
     * @param  array<string, string>  $imageUrls
     * @return array{ProductImportBatch, ProductImportRow, Product}
     */
    private function appliedRow(array $imageUrls): array
    {
        $product = Product::create([
            'name' => 'Draft Import Image Test',
            'publication_status' => Product::PUBLICATION_DRAFT,
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
            'is_active' => false,
        ]);
        $batch = ProductImportBatch::create([
            'actor_id' => $this->admin->id,
            'applied_by' => $this->admin->id,
            'source_filename' => 'test.csv',
            'source_size' => 100,
            'source_fingerprint' => str_repeat('a', 64),
            'contract_version' => 'v1',
            'catalog_state_fingerprint' => str_repeat('b', 64),
            'idempotency_key' => hash('sha256', uniqid('batch', true)),
            'status' => ProductImportBatch::STATUS_APPLIED,
            'applied_at' => now(),
            'total_rows' => 1,
            'valid_rows' => 1,
            'review_rows' => 0,
            'error_rows' => 0,
            'applied_rows' => 1,
            'blocked_rows' => 0,
            'skipped_blank_rows' => [],
        ]);
        $normalized = array_merge([
            'provider' => 'shopee',
            'kode_produk' => 'IMAGE-TEST',
            'nama_produk' => $product->name,
            'foto_utama_url' => '',
            'foto_2_url' => '',
            'foto_3_url' => '',
        ], $imageUrls);
        $row = $batch->rows()->create([
            'line_number' => 2,
            'status' => 'valid',
            'candidate_action' => 'create',
            'provider' => 'shopee',
            'external_product_id' => 'IMAGE-TEST',
            'normalized_data' => $normalized,
            'issues' => [],
            'payload_hash' => str_repeat('c', 64),
            'apply_status' => ProductImportRow::APPLY_CREATED,
            'applied_product_id' => $product->id,
            'apply_message' => 'Draft dibuat.',
            'after_snapshot' => ['product' => ['id' => $product->id]],
            'applied_at' => now(),
        ]);

        return [$batch, $row, $product];
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=', true);
    }
}
