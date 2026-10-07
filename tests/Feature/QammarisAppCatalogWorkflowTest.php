<?php

namespace Tests\Feature;

use App\Actions\Products\ApplyQammarisAppAvailability;
use App\Actions\Products\SyncQammarisAppFeed;
use App\Actions\Products\SyncSingleOffer;
use App\Jobs\SyncQammarisAppProducts;
use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class QammarisAppCatalogWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private array $feedPage = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        config(['media.product_disk' => 'public', 'qammaris_app.api_key' => 'synthetic-read-key', 'qammaris_app.webhook_secret' => 'synthetic-secret']);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response($this->feedPage));
    }

    public function test_visible_feed_creates_one_draft_per_uuid_with_source_price_and_never_publishes(): void
    {
        $s = $this->source();
        $this->feed($s);
        $product = Product::sole();
        $this->assertSame('draft', $product->publication_status);
        $this->assertFalse($product->is_active);
        $this->assertSame('available', $product->availability_status);
        $this->assertSame('180000.00', $product->activeOffer->price);
        $this->assertSame(50, $product->activeOffer->volume);
        $this->assertNull($product->activeOffer->sku);
        $this->assertNull($product->description);
        $this->assertNull($product->gender);
        $this->assertSame($s['id'], $product->externalIdentities->sole()->external_product_id);
        $this->assertDatabaseCount('product_images', 0);
        $after = json_decode(DB::table('qammaris_app_changes')->value('after'), true);
        $this->assertSame('draft', $after['catalog']['publication_status']);
        $this->assertNull(json_decode(DB::table('qammaris_app_changes')->value('before'), true)['catalog']);
        DB::table('qammaris_app_sync_states')->update(['checkpoint' => 0]);
        $this->feed($s);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('qammaris_app_changes', 1);
        $this->get(route('products.show', $product))->assertNotFound();
    }

    public function test_partial_app_product_is_a_review_draft_and_hidden_or_oversize_names_do_not_create_products(): void
    {
        $s = $this->source(['name' => 'New local product', 'brand' => null, 'source' => 'app', 'price' => null]);
        $this->feed($s);
        $draft = Product::sole();
        $this->assertNull($draft->brand_id);
        $this->assertNull($draft->base_price);
        $this->assertSame('draft', $draft->publication_status);
        $this->assertDatabaseCount('product_variants', 0);
        $this->feed($this->source(['revision' => 2, 'change_seq' => 2, 'hidden' => true, 'active' => false]));
        $this->feed($this->source(['revision' => 3, 'change_seq' => 3, 'name' => str_repeat('a', 256)]));
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('qammaris_app_products', 3);
        $this->assertSame(3, (int) DB::table('qammaris_app_sync_states')->value('checkpoint'));
    }

    public function test_new_price_keeps_offer_id_size_stock_sku_slug_media_and_publication_and_audits_change(): void
    {
        $s = $this->source();
        $this->feed($s);
        $p = Product::sole();
        $offer = $p->activeOffer;
        $offer->update(['stock' => 8, 'sku' => 'WEBSITE-CODE']);
        $p->forceFill(['publication_status' => 'published', 'is_active' => true, 'compare_at_price' => 200000])->save();
        $image = $p->images()->create(['image_path' => 'products/retained.jpg', 'is_primary' => true, 'sort_order' => 0]);
        $protected = $p->only(['id', 'slug', 'name', 'publication_status', 'is_active']);
        $offerProtected = $offer->fresh()->only(['id', 'volume', 'stock', 'sku']);
        $this->feed(array_replace($s, ['revision' => 2, 'change_seq' => 2, 'price' => 220000, 'name' => 'Upstream rename', 'availability' => 'sold_out']));
        $this->assertSame($protected, $p->fresh()->only(array_keys($protected)));
        $this->assertSame($offerProtected, $offer->fresh()->only(array_keys($offerProtected)));
        $this->assertSame('220000.00', $offer->fresh()->price);
        $this->assertSame('220000.00', $p->fresh()->base_price);
        $this->assertNull($p->fresh()->compare_at_price);
        $this->assertSame('products/retained.jpg', $image->fresh()->image_path);
        $audit = DB::table('qammaris_app_changes')->where('revision', 2)->sole();
        $this->assertSame('180000.00', json_decode($audit->before, true)['offer']['price']);
        $this->assertSame('220000.00', json_decode($audit->after, true)['offer']['price']);
        $this->assertSame('qammaris_app', $audit->actor);
        DB::table('qammaris_app_sync_states')->update(['checkpoint' => 0]);
        $this->feed(array_replace($s, ['change_seq' => 1, 'revision' => 1, 'price' => 1]));
        $this->assertSame('220000.00', $offer->fresh()->price);
        $this->assertDatabaseCount('qammaris_app_changes', 2);
    }

    public function test_invalid_source_prices_retain_last_price_without_blocking_availability_or_checkpoint(): void
    {
        $s = $this->source();
        $this->feed($s);
        foreach ([0, null, 100000000] as $index => $price) {
            $revision = $index + 2;
            $this->feed(array_replace($s, ['price' => $price, 'revision' => $revision, 'change_seq' => $revision, 'availability' => 'sold_out']));
            $this->assertSame('180000.00', Product::sole()->activeOffer->price);
            $this->assertSame('sold_out', Product::sole()->availability_status);
            $this->assertSame($revision, (int) DB::table('qammaris_app_sync_states')->value('checkpoint'));
            $this->assertSame('review_invalid_price', json_decode(DB::table('qammaris_app_changes')->where('revision', $revision)->value('after'), true)['price_sync']);
        }
    }

    public function test_editor_cannot_overwrite_connected_price_identity_or_publication(): void
    {
        $this->feed($this->source());
        $product = Product::sole();
        $offer = $product->activeOffer;
        $slug = $product->slug;
        $availability = $product->availability_status;

        $this->actingAs($this->admin())->put(route('admin.products.update', $product->id), [
            'name' => $product->name,
            'brand_id' => $product->brand_id,
            'category_id' => $product->category_id,
            'description' => 'Local editor copy.',
            'gender' => 'Unisex',
            'variants' => [['id' => $offer->id, 'volume' => $offer->volume, 'price' => 1]],
            'base_price' => 1,
            'slug' => 'forged-slug',
            'availability_source' => 'manual',
            'publication_status' => 'published',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('180000.00', $product->base_price);
        $this->assertSame('180000.00', $product->activeOffer->price);
        $this->assertSame($offer->id, $product->activeOffer->id);
        $this->assertSame($slug, $product->slug);
        $this->assertSame('draft', $product->publication_status);
        $this->assertFalse($product->is_active);
        $this->assertSame('qammaris_app', $product->availability_source);
        $this->assertSame($availability, $product->availability_status);
    }

    public function test_all_shared_offer_callers_cannot_override_a_valid_source_price(): void
    {
        $this->feed($this->source());
        $p = Product::sole();
        app(SyncSingleOffer::class)->handle($p, ['id' => $p->activeOffer->id, 'volume' => 50, 'price' => 1, 'stock' => 4]);
        $this->assertSame('180000.00', $p->fresh()->base_price);
        $this->assertSame('180000.00', $p->fresh()->activeOffer->price);
    }

    public function test_draft_creation_failure_rolls_back_catalog_taxonomy_identity_source_audit_and_checkpoint(): void
    {
        $this->mock(ApplyQammarisAppAvailability::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('synthetic failure'));
        try {
            $this->feed($this->source());
            $this->fail('Expected rollback');
        } catch (RuntimeException) {
            foreach (['products', 'brands', 'categories', 'product_variants', 'product_external_identities', 'qammaris_app_products', 'qammaris_app_changes'] as $table) {
                $this->assertDatabaseCount($table, 0);
            }
            $this->assertSame(0, (int) DB::table('qammaris_app_sync_states')->value('checkpoint'));
        }
    }

    public function test_admin_inbox_is_read_only_and_protected_and_shows_partial_draft_and_source_app_review(): void
    {
        $this->feed($this->source(['name' => 'Plain app product', 'source' => 'app', 'price' => null]));
        $this->get(route('admin.app-products.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.app-products.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.app-products.index', ['status' => 'draft']))
            ->assertOk()->assertSee('Plain app product')->assertSee('Lengkapi draft')->assertSee('Dibuat di aplikasi')->assertSee('Foto')->assertSee('Harga aplikasi');
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_import_batches', 0);
    }

    public function test_admin_preview_apply_is_explicit_actor_bound_idempotent_and_never_publishes(): void
    {
        $s = $this->source();
        $this->cache($s);
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.app-products.preview'))->assertRedirect();
        $batch = ProductImportBatch::sole();
        $this->assertSame($admin->id, $batch->actor_id);
        $this->assertDatabaseCount('products', 0);
        $this->post(route('admin.app-products.apply', $batch))->assertSessionHasErrors('confirm');
        $other = $this->admin();
        $this->actingAs($other)->post(route('admin.app-products.apply', $batch), ['confirm' => 1])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.app-products.apply', $batch), ['confirm' => 1])->assertRedirect();
        $this->post(route('admin.app-products.apply', $batch), ['confirm' => 1])->assertRedirect();
        $this->assertDatabaseCount('products', 1);
        $this->assertSame('draft', Product::sole()->publication_status);
        $this->assertSame($admin->id, $batch->fresh()->applied_by);
    }

    public function test_admin_stale_source_preview_is_rejected_without_catalog_write(): void
    {
        $s = $this->source();
        $this->cache($s);
        $this->actingAs($this->admin())->post(route('admin.app-products.preview'));
        $batch = ProductImportBatch::sole();
        $this->cache(array_replace($s, ['revision' => 2, 'change_seq' => 2, 'price' => 190000]));
        $this->post(route('admin.app-products.apply', $batch), ['confirm' => 1])->assertSessionHas('error');
        $this->assertDatabaseCount('products', 0);
        $this->assertSame('previewed', $batch->fresh()->status);
    }

    public function test_admin_sync_only_queues_a_job_when_machine_configuration_is_ready(): void
    {
        Queue::fake();
        $this->actingAs($this->admin());
        config(['qammaris_app.api_key' => '']);
        $this->post(route('admin.app-products.sync'))->assertSessionHas('error');
        Queue::assertNothingPushed();
        config(['qammaris_app.api_key' => 'synthetic-read-key']);
        $this->post(route('admin.app-products.sync'))->assertSessionHas('success');
        Queue::assertPushed(SyncQammarisAppProducts::class, fn ($j) => $j->connection === 'database' && $j->queue === 'qammaris-app');
        Http::assertNothingSent();
    }

    public function test_failed_queue_dispatch_returns_safe_retry_feedback(): void
    {
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('synthetic-private-detail'));
        $this->actingAs($this->admin())->post(route('admin.app-products.sync'))
            ->assertSessionHas('error', 'Sinkronisasi belum masuk antrean. Coba lagi.')
            ->assertDontSee('synthetic-private-detail');
        Http::assertNothingSent();
    }

    public function test_inbox_escapes_source_text_and_tolerates_array_search_page_inputs(): void
    {
        $this->cache($this->source(['name' => '<script>alert(1)</script>', 'sku' => '<img src=x onerror=alert(1)>']));
        $this->actingAs($this->admin())->get(route('admin.app-products.index', ['search' => ['bad'], 'page' => ['bad']]))
            ->assertOk()->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('<img src=x onerror=alert(1)>')->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->get(route('admin.app-products.index', ['batch' => ['bad']]))->assertSessionHasErrors('batch');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_connected_admin_editor_renders_source_price_readonly(): void
    {
        $this->feed($this->source());
        $this->actingAs($this->admin())->get(route('admin.products.edit', Product::sole()->id))
            ->assertOk()->assertSee('Stok dan harga jual mengikuti Qammaris App.')
            ->assertSee('readonly', false)->assertSee('180000')->assertDontSee('36 jam')
            ->assertDontSee('name="availability_status"', false)->assertDontSee('Snapshot Stok');
    }

    public function test_connected_draft_can_receive_manual_photo_and_content_without_overwriting_source_price_or_publishing(): void
    {
        $this->feed($this->source());
        $product = Product::sole();
        $this->actingAs($this->admin())->put(route('admin.products.update', $product->id), [
            'name' => $product->name, 'brand_id' => $product->brand_id, 'category_id' => $product->category_id,
            'description' => 'Local test description.', 'gender' => 'Unisex', 'publication_action' => 'save',
            'variants' => [['id' => $product->activeOffer->id, 'volume' => 50, 'price' => 1, 'stock' => 0]],
            'new_images' => [UploadedFile::fake()->createWithContent('manual-cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=', true))],
            'return_to' => '/admin/app-products?status=draft&search=Aoera&page=2&unsafe=x',
        ])->assertSessionHasNoErrors()->assertSessionHas('success')->assertRedirect('/admin/app-products?page=2&search=Aoera&status=draft');
        $product->refresh();
        $this->assertSame('draft', $product->publication_status);
        $this->assertSame('180000.00', $product->activeOffer->price);
        $this->assertSame('available', $product->availability_status);
        $this->assertSame('Local test description.', $product->description);
        $image = $product->images()->sole();
        $this->assertTrue($image->is_primary);
        $this->assertStringStartsWith('products/', $image->image_path);
        Storage::disk('public')->assertExists($image->image_path);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();

        return $user;
    }

    public function test_inbox_search_tolerates_name_typos_but_identifiers_and_status_stay_scoped(): void
    {
        $target = $this->source(['name' => 'Reverie Aqua 100ML', 'brand' => 'Zimaya']);
        $this->cache($target);
        $hidden = $this->source(['name' => 'Reverie Hidden', 'hidden' => true]);
        $this->cache($hidden);
        $this->actingAs($this->admin());
        $result = $this->get('/admin/app-products?search=rverie+aqua')->assertOk();
        $this->assertSame([$target['id']], $result->viewData('rows')->getCollection()->map(fn ($row) => $row['source']['id'])->all());
        $this->assertSame(0, $this->get('/admin/app-products?search=rverie+aqua&status=hidden')->viewData('rows')->total());
        $this->assertSame(1, $this->get('/admin/app-products?search='.$target['id'])->viewData('rows')->total());
        $this->assertSame(0, $this->get('/admin/app-products?search=SOURCE-SKY')->viewData('rows')->total());
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('qammaris_app_products', 2);
    }

    private function source(array $overrides = []): array
    {
        return array_replace([
            'id' => (string) Str::uuid(), 'sku' => 'SOURCE-SKU', 'name' => 'Aoera Review EDP 50 ML',
            'brand' => 'Aoera', 'department_code' => 'lokal', 'price' => 180000, 'source' => 'majoo',
            'availability' => 'available', 'restock_eta' => null, 'stock_status_at' => null,
            'active' => true, 'merged_into' => null, 'hidden' => false, 'revision' => 1, 'change_seq' => 1,
        ], $overrides);
    }

    private function feed(array $s): void
    {
        $this->feedPage = ['data' => [$s], 'meta' => ['next_seq' => $s['change_seq'], 'has_more' => false]];
        app(SyncQammarisAppFeed::class)->handle();
    }

    private function cache(array $s): void
    {
        DB::table('qammaris_app_products')->updateOrInsert(['id' => $s['id']], [
            'snapshot' => json_encode($s), 'revision' => $s['revision'], 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
