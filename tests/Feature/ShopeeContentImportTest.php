<?php

namespace Tests\Feature;

use App\Actions\Products\AcquireProductImportRowImages;
use App\Actions\Products\ApplyShopeeContent;
use App\Actions\Products\QueueProductImportImages;
use App\Exceptions\InvalidProductImportFile;
use App\Imports\Products\ShopeeContentXlsx;
use App\Jobs\AcquireProductImportRowImages as ImageJob;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Models\User;
use App\Services\ShopeeContentPreviewer;
use App\Services\ShopeeProductCopy;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\ShopeeWorkbook;
use Tests\TestCase;

class ShopeeContentImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $files = [];

    public function test_database_reservation_outlives_image_job_timeout(): void
    {
        $this->assertGreaterThan((new ImageJob(1))->timeout, config('queue.connections.database.retry_after'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        config(['media.product_disk' => 'public', 'product_imports.image_acquisition.allowed_hosts' => ['images.example.test']]);
        Http::preventStrayRequests();
        Queue::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Category::create(['name' => 'Eau de Parfum', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file->getRealPath())) {
                unlink($file->getRealPath());
            }
        }
        parent::tearDown();
    }

    public function test_real_xlsx_preview_is_read_only_and_joins_by_id_not_row_order(): void
    {
        $p = $this->product();
        $batch = $this->preview();
        $row = $batch->rows()->first();
        $this->assertSame($p->id, $row->matched_product_id);
        $this->assertSame('Parfum unisex.', $row->normalized_data['fields']['description']);
        $this->assertNull($p->fresh()->description);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertFalse($p->fresh()->is_active);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_shopee_media_instruction_rows_are_accepted_but_missing_product_ids_are_not(): void
    {
        $file = $this->file('media', [['', '', '', '', 'Wajib', 'Opsional', 'Opsional'],
            ['', '', '', '', 'Mohon masukkan link foto. Masing-masing produk harus mempunyai min. 1 foto.', 'Mohon masukkan link foto.', ''],
            ['501', '', 'Demo EDP 100ML', '', 'https://images.example.test/a.png', '', '']]);
        $this->assertCount(1, app(ShopeeContentXlsx::class)->read($file, 'media'));
        $file = $this->file('basic', [['', '', 'Invalid missing ID', 'Copy']]);
        $this->expectException(InvalidProductImportFile::class);
        app(ShopeeContentXlsx::class)->read($file, 'basic');
    }

    public function test_http_upload_validates_files_and_joins_different_row_order(): void
    {
        $this->product();
        $basic = $this->file('basic', [['501', '', 'Demo EDP 100ML', 'Parfum unisex.'], ['502', '', 'Different EDP 50ML', 'copy']]);
        $media = $this->file('media', [['502', '', 'Different EDP 50ML', '', 'https://images.example.test/b.png', '', ''], ['501', '', 'Demo EDP 100ML', '', 'https://images.example.test/a.png', '', '']]);
        $this->actingAs($this->admin)->post(route('admin.shopee-imports.preview'), ['basic_file' => $basic, 'media_file' => $media])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.shopee-imports.index', ['batch' => 1]));
        $this->assertSame(2, ProductImportBatch::first()->total_rows);
        $this->assertSame('https://images.example.test/a.png', ProductImportBatch::first()->rows()->where('external_product_id', '501')->first()->normalized_data['foto_utama_url']);
        $this->post(route('admin.shopee-imports.preview'))->assertSessionHasErrors(['basic_file', 'media_file']);
    }

    public function test_wrong_size_and_conflicting_audience_remain_for_review(): void
    {
        $p = $this->product();
        $preview = app(ShopeeContentPreviewer::class);
        $data = $preview->plan(['id' => '501', 'name' => 'Demo EDP 50ML', 'description' => 'Parfum unisex.', 'photos' => ['', '', '']], $p);
        $this->assertNull($data['product_id']);
        $this->assertNull(app(ShopeeProductCopy::class)->gender('Demo EDP', "Gender: Men\nGender: Woman"));
        $this->assertSame('Wanita', app(ShopeeProductCopy::class)->gender('Cute Woman', ''));
    }

    public function test_missing_offer_uses_app_price(): void
    {
        $p = $this->product();
        $p->variants()->delete();
        $p->forceFill(['base_price' => 0])->save();
        $batch = $this->preview();
        app(ApplyShopeeContent::class)->handle($batch, $this->admin);
        $this->assertSame('275000.00', $p->variants()->first()->price);
        $this->assertSame(100, $p->variants()->first()->volume);
        $this->assertSame('draft', $p->fresh()->publication_status);
    }

    public function test_missing_source_price_keeps_copy_but_does_not_invent_offer_or_publish(): void
    {
        $p = $this->product(['base_price' => 0]);
        $p->variants()->delete();
        $uuid = $p->externalIdentities()->value('external_product_id');
        $source = json_decode(DB::table('qammaris_app_products')->where('id', $uuid)->value('snapshot'), true);
        $source['price'] = null;
        DB::table('qammaris_app_products')->where('id', $uuid)->update(['snapshot' => json_encode($source)]);
        $batch = $this->preview();
        app(ApplyShopeeContent::class)->handle($batch, $this->admin);
        $this->assertSame('Parfum unisex.', $p->fresh()->description);
        $this->assertSame(0, $p->variants()->count());
        $this->expectException(DomainException::class);
        try {
            app(ApplyShopeeContent::class)->publish($batch, $this->admin, [$batch->rows()->first()->id]);
        } finally {
            $this->assertSame('draft', $p->fresh()->publication_status);
        }
    }

    public function test_empty_xml_and_duplicate_ids_are_friendly_file_errors(): void
    {
        $empty = $this->file('basic', [['501', '', 'Demo', 'Copy']]);
        $zip = new \ZipArchive;
        $zip->open($empty->getRealPath());
        $zip->addFromString('xl/worksheets/sheet1.xml', '');
        $zip->close();
        $duplicate = $this->file('basic', [['501', '', 'Demo', 'Copy'], ['501', '', 'Other', 'Copy']]);
        foreach ([$empty, $duplicate] as $file) {
            try {
                app(ShopeeContentXlsx::class)->read($file, 'basic');
                $this->fail('Malformed workbook accepted');
            } catch (InvalidProductImportFile $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
    }

    public function test_tampered_preview_cannot_apply_and_imported_text_is_escaped(): void
    {
        $p = $this->product();
        $batch = $this->preview();
        $row = $batch->rows()->first();
        $data = $row->normalized_data;
        $data['source']['description'] = '<script>alert("unsafe")</script>';
        $data['fields']['description'] = $data['source']['description'];
        $row->forceFill(['normalized_data' => $data])->save();
        $this->actingAs($this->admin)->get(route('admin.shopee-imports.index', ['batch' => $batch->id]))
            ->assertOk()->assertDontSee('<script>alert("unsafe")</script>', false)->assertSee('&lt;script&gt;', false);
        $this->expectException(DomainException::class);
        try {
            app(ApplyShopeeContent::class)->handle($batch, $this->admin);
        } finally {
            $this->assertNull($p->fresh()->description);
            $this->assertDatabaseCount('product_external_identities', 1);
        }
    }

    public function test_pair_missing_ids_or_changed_names_is_rejected_without_audit_or_mutation(): void
    {
        $this->product();
        $this->expectException(InvalidProductImportFile::class);
        try {
            $this->preview(mediaId: 'other');
        } finally {
            $this->assertDatabaseCount('product_import_batches', 0);
            $this->assertNull(Product::first()->description);
        }
    }

    public function test_formula_and_external_entity_are_rejected(): void
    {
        foreach ([[true, false], [false, true]] as [$formula, $entity]) {
            $file = $this->file('basic', [['501', '', 'A', 'copy']], $formula, $entity);
            try {
                app(ShopeeContentXlsx::class)->read($file, 'basic');
                $this->fail('Unsafe XML/formula accepted');
            } catch (InvalidProductImportFile $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
    }

    public function test_ssrf_url_is_rejected_before_any_network_call(): void
    {
        $this->expectException(InvalidProductImportFile::class);
        try {
            $this->preview(url: 'http://127.0.0.1/private');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_apply_enriches_only_content_then_images_and_explicit_publish(): void
    {
        $p = $this->product();
        $batch = $this->preview();
        $action = app(ApplyShopeeContent::class);
        $this->assertSame(1, $action->handle($batch, $this->admin));
        $row = $batch->rows()->first();
        $p->refresh();
        $this->assertSame('draft', $p->publication_status);
        $this->assertSame('Unisex', $p->gender);
        $this->assertSame('sold_out', $p->availability_status);
        $this->assertSame('275000.00', $p->base_price);
        $this->assertSame('unchanged-url', $p->slug);
        $this->assertSame(501, (int) $p->externalIdentities()->where('provider', 'shopee')->value('external_product_id'));
        Http::fake(['images.example.test/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);
        app(QueueProductImportImages::class)->handle($batch, $this->admin);
        Queue::assertPushed(ImageJob::class, fn ($job) => $job->connection === 'database' && $job->queue === 'product-import-images');
        app(AcquireProductImportRowImages::class)->handle($row->id);
        $this->assertDatabaseCount('product_images', 1);
        $this->assertSame('draft', $p->fresh()->publication_status);
        $this->assertSame(1, $action->publish($batch, $this->admin, [$row->id]));
        $this->assertSame('published', $p->fresh()->publication_status);
        $this->assertSame('sold_out', $p->fresh()->availability_status);
        $this->assertSame(0, $action->publish($batch, $this->admin, [$row->id]));
        $this->assertSame(0, $action->handle($batch, $this->admin));
    }

    public function test_old_copy_is_retained_until_admin_explicitly_previews_replacement(): void
    {
        $p = $this->product(['description' => 'Owner copy']);
        $batch = $this->preview();
        $row = $batch->rows()->first();
        $this->assertArrayNotHasKey('description', $row->normalized_data['fields']);
        app(ApplyShopeeContent::class)->choose($batch, $row, $this->admin, $p->id, true);
        $this->assertSame('Owner copy', $p->fresh()->description);
        app(ApplyShopeeContent::class)->handle($batch, $this->admin);
        $this->assertSame('Parfum unisex.', $p->fresh()->description);
        $this->assertSame($this->admin->id, $row->fresh()->resolved_by);
    }

    public function test_queue_dispatch_failure_keeps_content_and_makes_images_retryable(): void
    {
        $p = $this->product();
        $batch = $this->preview();
        app(ApplyShopeeContent::class)->handle($batch, $this->admin);
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Queue offline'));
        try {
            app(QueueProductImportImages::class)->handle($batch, $this->admin);
            $this->fail('Queue failure was ignored');
        } catch (DomainException $error) {
            $this->assertStringContainsString('Coba unduh foto lagi', $error->getMessage());
        }
        $this->assertSame('completed_with_errors', $batch->rows()->first()->image_acquisition_status);
        $this->assertSame('Parfum unisex.', $p->fresh()->description);
        $this->assertSame('draft', $p->fresh()->publication_status);
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_mixed_ready_and_incomplete_selection_rolls_back_all_publication(): void
    {
        $ready = $this->product(['description' => 'Complete', 'gender' => 'Unisex']);
        Storage::disk('public')->put('products/ready.png', $this->png());
        $ready->images()->create(['image_path' => 'products/ready.png', 'is_primary' => true, 'sort_order' => 0]);
        $incomplete = $this->product(['name' => 'Incomplete EDP 100ML', 'slug' => 'incomplete']);
        $batch = app(ShopeeContentPreviewer::class)->preview($this->admin,
            $this->file('basic', [['501', '', 'Demo EDP 100ML', 'Parfum unisex.'], ['502', '', 'Incomplete EDP 100ML', 'Parfum unisex.']]),
            $this->file('media', [['501', '', 'Demo EDP 100ML', '', '', '', ''], ['502', '', 'Incomplete EDP 100ML', '', '', '', '']]));
        app(ApplyShopeeContent::class)->handle($batch, $this->admin);
        try {
            app(ApplyShopeeContent::class)->publish($batch, $this->admin, $batch->rows()->pluck('id')->all());
            $this->fail('Incomplete selection was published');
        } catch (DomainException $error) {
            $this->assertNotEmpty($error->getMessage());
        }
        $this->assertSame('draft', $ready->fresh()->publication_status);
        $this->assertSame('draft', $incomplete->fresh()->publication_status);
        $this->assertSame(0, $batch->rows()->where('resolution_status', 'published')->count());
    }

    public function test_product_editor_keeps_only_safe_import_return_context(): void
    {
        $p = $this->product();
        $path = route('admin.shopee-imports.index', ['batch' => 1, 'page' => 2], false);
        $this->actingAs($this->admin)->get(route('admin.products.edit', [$p->id, 'return_to' => $path]))
            ->assertOk()->assertSee(e($path), false);
        $this->get(route('admin.products.edit', [$p->id, 'return_to' => 'https://example.test/admin/shopee-imports?batch=1']))
            ->assertOk()->assertDontSee('https://example.test', false);
    }

    public function test_changed_target_blocks_atomic_apply_and_fresh_choice_allows_retry(): void
    {
        $p = $this->product();
        $batch = $this->preview();
        $p->forceFill(['description' => 'Human edit'])->save();
        try {
            app(ApplyShopeeContent::class)->handle($batch, $this->admin);
            $this->fail('Stale apply accepted');
        } catch (DomainException $error) {
            $this->assertStringContainsString('berubah', $error->getMessage());
        }
        $this->assertDatabaseCount('product_external_identities', 1);
        app(ApplyShopeeContent::class)->choose($batch, $batch->rows()->first(), $this->admin, $p->id, false);
        app(ApplyShopeeContent::class)->handle($batch, $this->admin);
        $this->assertSame('Human edit', $p->fresh()->description);
    }

    public function test_manual_choice_cannot_rebind_occupied_shopee_identity_or_other_size(): void
    {
        $p = $this->product();
        $other = $this->product(['name' => 'Other EDP 100ML', 'slug' => 'other']);
        $batch = $this->preview();
        $other->externalIdentities()->create(['provider' => 'shopee', 'external_product_id' => '501']);
        $this->expectException(DomainException::class);
        app(ApplyShopeeContent::class)->choose($batch, $batch->rows()->first(), $this->admin, $p->id, false);
    }

    public function test_unknown_match_is_not_created_and_can_be_chosen_later(): void
    {
        $p = $this->product(['name' => 'Different Name']);
        $batch = $this->preview();
        $this->assertNull($batch->rows()->first()->matched_product_id);
        $this->assertSame(0, app(ApplyShopeeContent::class)->handle($batch, $this->admin));
        $this->assertDatabaseCount('products', 1);
        app(ApplyShopeeContent::class)->choose($batch, $batch->rows()->first(), $this->admin, $p->id, false);
        $this->assertSame(1, app(ApplyShopeeContent::class)->handle($batch, $this->admin));
    }

    public function test_reupload_and_retry_keep_existing_media_and_do_not_duplicate_sources(): void
    {
        $p = $this->product();
        $batch = $this->preview();
        app(ApplyShopeeContent::class)->handle($batch, $this->admin);
        Http::fake(['images.example.test/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);
        app(QueueProductImportImages::class)->handle($batch, $this->admin);
        app(AcquireProductImportRowImages::class)->handle($batch->rows()->first()->id);
        $originalImage = $p->images()->first()->id;
        app(QueueProductImportImages::class)->handle($batch, $this->admin);
        app(AcquireProductImportRowImages::class)->handle($batch->rows()->first()->id);
        $fresh = $this->preview();
        $this->assertSame('', $fresh->rows()->first()->normalized_data['foto_utama_url']);
        app(ApplyShopeeContent::class)->handle($fresh, $this->admin);
        $queued = app(QueueProductImportImages::class)->handle($fresh, $this->admin);
        $this->assertSame(0, $queued['queued_rows']);
        $this->assertDatabaseCount('product_images', 1);
        $this->assertSame($originalImage, $p->images()->first()->id);
    }

    public function test_published_product_can_receive_additional_photo_without_replacing_cover(): void
    {
        $p = $this->product(['description' => 'Owner copy', 'gender' => 'Unisex', 'publication_status' => 'published', 'is_active' => true]);
        Storage::disk('public')->put('products/manual.png', $this->png());
        $image = $p->images()->create(['image_path' => 'products/manual.png', 'is_primary' => true, 'sort_order' => 0]);
        $batch = $this->preview();
        app(ApplyShopeeContent::class)->handle($batch, $this->admin);
        Http::fake(['images.example.test/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);
        app(QueueProductImportImages::class)->handle($batch, $this->admin);
        app(AcquireProductImportRowImages::class)->handle($batch->rows()->first()->id);
        $this->assertDatabaseCount('product_images', 2);
        $this->assertTrue($image->fresh()->is_primary);
        $this->assertSame('published', $p->fresh()->publication_status);
        $this->assertSame('Owner copy', $p->fresh()->description);
    }

    public function test_hidden_or_changed_image_target_does_not_download_or_attach(): void
    {
        $p = $this->product();
        $batch = $this->preview();
        app(ApplyShopeeContent::class)->handle($batch, $this->admin);
        app(QueueProductImportImages::class)->handle($batch, $this->admin);
        $p->forceFill(['qammaris_app_hidden' => true])->save();
        app(AcquireProductImportRowImages::class)->handle($batch->rows()->first()->id);
        Http::assertNothingSent();
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame('completed_with_errors', $batch->rows()->first()->image_acquisition_status);
    }

    public function test_failed_cover_remains_draft_and_retry_succeeds(): void
    {
        $p = $this->product();
        $batch = $this->preview();
        app(ApplyShopeeContent::class)->handle($batch, $this->admin);
        Http::fake(['images.example.test/*' => Http::sequence()->push('', 404)->push($this->png(), 200, ['Content-Type' => 'image/png'])]);
        app(QueueProductImportImages::class)->handle($batch, $this->admin);
        app(AcquireProductImportRowImages::class)->handle($batch->rows()->first()->id);
        $this->assertSame('draft', $p->fresh()->publication_status);
        $this->assertDatabaseCount('product_images', 0);
        app(QueueProductImportImages::class)->handle($batch, $this->admin);
        app(AcquireProductImportRowImages::class)->handle($batch->rows()->first()->id);
        $this->assertDatabaseCount('product_images', 1);
    }

    public function test_admin_routes_are_authenticated_actor_bound_and_child_scoped(): void
    {
        $this->get(route('admin.shopee-imports.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.shopee-imports.index'))->assertForbidden();
        $p = $this->product();
        $batch = $this->preview();
        $this->actingAs($this->admin)->get(route('admin.shopee-imports.index', ['batch' => $batch->id]))->assertOk()->assertSee('Periksa produk');
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($other)->get(route('admin.shopee-imports.index', ['batch' => $batch->id]))->assertNotFound();
        $this->post(route('admin.shopee-imports.apply', $batch), ['confirm' => '1'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.shopee-imports.apply', $batch))->assertSessionHasErrors('confirm');
        $alien = ProductImportBatch::create(['contract_version' => 'other', 'actor_id' => $this->admin->id, 'idempotency_key' => Str::random(64), 'source_filename' => 'other', 'source_size' => 1, 'source_fingerprint' => Str::random(64), 'catalog_state_fingerprint' => Str::random(64), 'status' => 'previewed', 'total_rows' => 1, 'valid_rows' => 1, 'review_rows' => 0, 'error_rows' => 0]);
        $alienRow = $alien->rows()->create(['line_number' => 2, 'status' => 'valid', 'candidate_action' => 'other', 'normalized_data' => [], 'issues' => [], 'payload_hash' => Str::random(64)]);
        $this->post(route('admin.shopee-imports.choose', [$batch->id, $alienRow->id]), ['product_id' => $p->id])->assertNotFound();
        $this->assertDatabaseCount('product_images', 0);
    }

    private function preview(string $mediaId = '501', string $url = 'https://images.example.test/a.png'): ProductImportBatch
    {
        return app(ShopeeContentPreviewer::class)->preview($this->admin,
            $this->file('basic', [['501', '', 'Demo EDP 100ML', "READY STOCK ✨\nParfum unisex.\nGratis ongkir\n#demo"]]),
            $this->file('media', [[$mediaId, '', 'Demo EDP 100ML', 'Perfumes', $url, $url, '']]));
    }

    private function file(string $kind, array $rows, bool $formula = false, bool $entity = false)
    {
        $file = ShopeeWorkbook::make($kind, $rows, $formula, $entity);
        $this->files[] = $file;

        return $file;
    }

    private function product(array $fields = []): Product
    {
        $brand = Brand::firstOrCreate(['name' => 'Demo'], ['is_active' => true]);
        $p = Product::create($fields + ['name' => 'Demo EDP 100ML', 'slug' => 'unchanged-url', 'brand_id' => $brand->id,
            'category_id' => Category::first()->id, 'base_price' => 275000, 'publication_status' => 'draft', 'is_active' => false,
            'availability_source' => 'qammaris_app', 'availability_status' => 'sold_out']);
        $p->variants()->create(['volume' => 100, 'price' => 275000, 'stock' => 0, 'is_active' => true]);
        $uuid = (string) Str::uuid();
        $p->externalIdentities()->create(['provider' => 'qammaris_app', 'external_product_id' => $uuid]);
        DB::table('qammaris_app_products')->insert(['id' => $uuid, 'revision' => 1,
            'snapshot' => json_encode(['id' => $uuid, 'name' => $p->name, 'hidden' => false, 'active' => true, 'merged_into' => null, 'price' => 275000]),
            'created_at' => now(), 'updated_at' => now()]);

        return $p->fresh();
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    }
}
