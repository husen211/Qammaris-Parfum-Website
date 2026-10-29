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

    public function test_otw_draft_gets_its_offer_when_the_app_price_arrives_and_the_name_has_a_size(): void
    {
        $sized = $this->source(['name' => 'Inception 100ml', 'source' => 'app', 'price' => null]);
        $unsized = $this->source(['name' => 'Hawaiian Crush', 'source' => 'app', 'price' => null, 'revision' => 2, 'change_seq' => 2]);
        $this->feed($sized);
        $this->feed($unsized);
        $this->assertDatabaseCount('product_variants', 0);

        $this->feed(array_replace($sized, ['price' => 425000, 'revision' => 3, 'change_seq' => 3]));
        $this->feed(array_replace($unsized, ['price' => 228000, 'revision' => 4, 'change_seq' => 4]));
        $product = Product::where('name', 'Inception 100ml')->sole();
        $this->assertSame([100, '425000.00', true], [$product->activeOffer->volume, $product->activeOffer->price, $product->activeOffer->is_active]);
        $this->assertSame(['draft', false], [$product->publication_status, $product->is_active], 'A price never publishes the draft.');
        $this->assertSame('synced_new_offer', json_decode(DB::table('qammaris_app_changes')->where('external_id', $sized['id'])->where('revision', 3)->value('after'), true)['price_sync']);
        $waiting = Product::where('name', 'Hawaiian Crush')->sole();
        $this->assertSame([0, '228000.00'], [$waiting->variants()->count(), $waiting->base_price]);
        $this->assertSame('review_missing_offer', json_decode(DB::table('qammaris_app_changes')->where('external_id', $unsized['id'])->where('revision', 4)->value('after'), true)['price_sync']);

        $this->feed(array_replace($sized, ['price' => 430000, 'revision' => 5, 'change_seq' => 5]));
        $this->assertSame([1, '430000.00'], [$product->variants()->count(), $product->activeOffer()->sole()->price]);
    }

    public function test_owner_fills_drafts_that_were_waiting_for_a_price_once_and_only_eligible_ones(): void
    {
        $waiting = $this->source(['name' => 'Inception 100ml', 'source' => 'app', 'price' => null]);
        $unsized = $this->source(['name' => 'Hawaiian Crush', 'source' => 'app', 'price' => null, 'revision' => 2, 'change_seq' => 2]);
        $hidden = $this->source(['name' => 'Merged OTW 50ml', 'source' => 'app', 'price' => null, 'revision' => 3, 'change_seq' => 3]);
        foreach ([$waiting, $unsized, $hidden] as $source) {
            $this->feed($source);
        }
        // Before ORD-12 the price revision was applied without an offer; the feed never replays it.
        $this->cache(array_replace($waiting, ['price' => 425000, 'revision' => 5]));
        $this->cache(array_replace($unsized, ['price' => 228000, 'revision' => 6]));
        $this->cache(array_replace($hidden, ['price' => 199000, 'revision' => 7, 'hidden' => true, 'merged_into' => (string) Str::uuid()]));
        $this->feed($this->source(['name' => 'Live 50ml', 'price' => 150000, 'revision' => 4, 'change_seq' => 4]));
        $live = Product::where('name', 'Live 50ml')->sole();
        $live->forceFill(['publication_status' => 'published', 'is_active' => true])->save();
        $live->variants()->delete();
        $target = Product::where('name', 'Inception 100ml')->sole();
        $owner = $this->admin();

        $page = $this->actingAs($owner)->get('/admin/products')->assertOk()
            ->assertSee('1 draft sudah punya harga di aplikasi, tapi belum bisa dipesan')->assertSee('100 ml · Rp 425.000');
        $this->assertSame([$target->id], $page->viewData('waiting')->map(fn ($row) => $row['product']->id)->all());

        $ids = Product::pluck('id')->all();
        $this->post(route('admin.app-products.fill-offers'), ['product_ids' => $ids])
            ->assertRedirect(route('admin.products.index'))->assertSessionHas('success', '1 draft sekarang punya ukuran dan harga, dan bisa dipilih di Pesanan Online.');
        $this->assertSame([100, '425000.00'], [$target->activeOffer->volume, $target->activeOffer->price]);
        $this->assertSame('draft', $target->fresh()->publication_status);
        $this->assertSame(1, DB::table('product_variants')->count());
        $audit = DB::table('product_admin_changes')->sole();
        $this->assertSame([$target->id, $owner->id, 'product_updated'], [$audit->product_id, $audit->actor_id, $audit->action]);

        $this->post(route('admin.app-products.fill-offers'), ['product_ids' => $ids])
            ->assertSessionHas('success', 'Tidak ada draft yang diubah; datanya sudah berubah. Muat ulang halaman ini.');
        $this->assertSame(1, DB::table('product_variants')->count());
        $this->get('/admin/products')->assertDontSee('belum bisa dipesan');
        $this->post(route('admin.app-products.fill-offers'), ['product_ids' => 'x'])->assertSessionHasErrors('product_ids');
    }

    public function test_otw_draft_follows_the_app_merge_into_the_majoo_product_in_either_order(): void
    {
        // Merge arrives before the Majoo product: the draft moves, then takes the Majoo state when it arrives.
        $otw = $this->source(['name' => 'Project 1945 Water of maluku', 'brand' => null, 'source' => 'app', 'price' => null]);
        $majoo = $this->source(['name' => 'Project 1945 Waters of Maluku 100ml', 'brand' => 'Project 1945', 'price' => 350000, 'revision' => 3, 'change_seq' => 3]);
        $this->feed($otw);
        $draft = Product::sole();
        $this->feed($this->merged($otw, $majoo['id'], 2));
        $this->assertSame($majoo['id'], $draft->externalIdentities()->sole()->external_product_id);
        $this->assertTrue($draft->fresh()->qammaris_app_hidden);
        $this->feed($majoo);
        $draft->refresh();
        $this->assertSame(1, Product::count());
        $this->assertSame(['draft', false, 'available'], [$draft->publication_status, $draft->qammaris_app_hidden, $draft->availability_status]);
        $this->assertSame([100, '350000.00'], [$draft->activeOffer->volume, $draft->activeOffer->price]);
        $this->assertSame('followed_merge', json_decode(DB::table('qammaris_app_changes')->where('external_id', $otw['id'])->where('revision', 2)->value('after'), true)['merge']);

        // Majoo product synced first but held for review (no Website product): the merge links and applies it at once.
        $otw2 = $this->source(['name' => 'Trails of Bromo', 'source' => 'app', 'price' => null, 'revision' => 4, 'change_seq' => 4]);
        $this->feed($otw2);
        $held = $this->source(['name' => 'Trails of Bromo 50ml', 'price' => 280000, 'revision' => 5]);
        $this->cache($held);
        $this->feed($this->merged($otw2, $held['id'], 6));
        $moved = Product::where('name', 'Trails of Bromo')->sole();
        $this->assertSame($held['id'], $moved->externalIdentities()->sole()->external_product_id);
        $this->assertSame([false, 50, '280000.00'], [$moved->qammaris_app_hidden, $moved->activeOffer->volume, $moved->activeOffer->price]);
        $this->assertSame(2, Product::count());

        $this->actingAs($this->admin());
        $page = $this->get('/admin/app-products')->assertOk();
        $this->assertEqualsCanonicalizing([$moved->id, $draft->id], $page->viewData('rows')->getCollection()->pluck('product.id')->all());
        $this->assertSame(0, $this->get('/admin/app-products?status=hidden')->viewData('rows')->getCollection()->whereNotNull('product')->count());
    }

    public function test_merge_into_a_majoo_product_that_already_has_a_page_retires_only_an_untouched_draft(): void
    {
        $majoo = $this->source(['name' => 'Saff Las Pozas 35ml', 'brand' => 'Saff', 'price' => 245000]);
        $this->feed($majoo);
        $kept = Product::sole();
        $bare = $this->source(['name' => 'SAFF', 'source' => 'app', 'price' => null, 'revision' => 2, 'change_seq' => 2]);
        $photo = $this->source(['name' => 'SAFF tester', 'source' => 'app', 'price' => null, 'revision' => 3, 'change_seq' => 3]);
        $live = $this->source(['name' => 'Saff Live 35ml', 'price' => 245000, 'revision' => 4, 'change_seq' => 4]);
        foreach ([$bare, $photo, $live] as $source) {
            $this->feed($source);
        }
        Product::where('name', 'SAFF tester')->sole()->images()->create(['image_path' => 'products/tester.jpg', 'is_primary' => true, 'sort_order' => 0]);
        Product::where('name', 'Saff Live 35ml')->sole()->forceFill(['publication_status' => 'published', 'is_active' => true, 'published_at' => now()])->save();

        $this->feed($this->merged($bare, $majoo['id'], 5));
        $this->feed($this->merged($photo, $majoo['id'], 6));
        $this->feed($this->merged($live, $majoo['id'], 7));

        $this->assertSame('archived', Product::where('name', 'SAFF')->sole()->publication_status);
        $this->assertSame('draft', Product::where('name', 'SAFF tester')->sole()->publication_status, 'A draft with a photo waits for the Owner.');
        $this->assertSame('published', Product::where('name', 'Saff Live 35ml')->sole()->publication_status);
        $this->assertSame($majoo['id'], $kept->externalIdentities()->sole()->external_product_id);
        $merge = fn (array $s, int $revision) => json_decode(DB::table('qammaris_app_changes')->where('external_id', $s['id'])->where('revision', $revision)->value('after'), true)['merge'] ?? null;
        $this->assertSame(['archived_merged_duplicate', 'merged_review', null], [$merge($bare, 5), $merge($photo, 6), $merge($live, 7)]);

        $this->actingAs($this->admin());
        $this->get('/admin/products')->assertOk()->assertSee('Saff Las Pozas 35ml')->assertSee('SAFF tester')->assertDontSee('>SAFF<', false)
            ->assertSee('Perlu dicek');
        $this->get('/admin/products?publication=archived')->assertOk()->assertSee('>SAFF<', false)->assertDontSee('Saff Las Pozas 35ml');
    }

    public function test_one_product_list_shows_status_source_and_what_is_missing(): void
    {
        $ready = $this->source(['name' => 'Inception 100ml', 'price' => 425000]);
        $unsized = $this->source(['name' => 'Hawaiian Crush', 'price' => 228000, 'revision' => 2, 'change_seq' => 2]);
        $otw = $this->source(['name' => 'OTW Baru', 'source' => 'app', 'price' => null, 'revision' => 3, 'change_seq' => 3]);
        $live = $this->source(['name' => 'Live Hidden 50ml', 'price' => 199000, 'revision' => 4, 'change_seq' => 4]);
        foreach ([$ready, $unsized, $otw, $live] as $source) {
            $this->feed($source);
        }
        $hidden = Product::where('name', 'Live Hidden 50ml')->sole();
        $hidden->forceFill(['publication_status' => 'published', 'is_active' => true, 'published_at' => now()])->save();
        $this->feed($this->merged($live, (string) Str::uuid(), 5));
        $manual = Product::create(['name' => 'Manual Draft', 'publication_status' => 'draft', 'is_active' => false]);
        $id = fn (string $name) => Product::where('name', $name)->sole()->id;

        $this->actingAs($this->admin());
        $page = $this->get('/admin/products')->assertOk()
            ->assertSee('Perlu dicek: Ukuran belum diisi')->assertSee('Perlu dicek: Harga belum ada di aplikasi')
            ->assertSee('Perlu dicek: Disembunyikan di aplikasi')->assertSee('· dari App')->assertSee('· manual')
            ->assertSee('100 ml · Rp 425.000')->assertSee('Belum ada ukuran &amp; harga', false)->assertDontSee('Produk dari aplikasi');
        $notes = $page->viewData('rowNotes');
        $this->assertSame([true, null], [$notes[$id('Inception 100ml')]['from_app'], $notes[$id('Inception 100ml')]['check']]);
        $this->assertContains('foto', $notes[$id('Inception 100ml')]['missing']);
        $this->assertContains('deskripsi', $notes[$id('Inception 100ml')]['missing']);
        $this->assertNotContains('ukuran & harga', $notes[$id('Inception 100ml')]['missing']);
        $this->assertFalse($notes[$manual->id]['from_app']);
        $this->assertSame(['all' => 5, 'draft' => 4, 'published' => 1, 'check' => 3, 'archived' => 0], $page->viewData('counts'));

        $check = $this->get('/admin/products?publication=check')->assertOk()->assertSee('aria-current="page"', false);
        $this->assertEqualsCanonicalizing([$id('Hawaiian Crush'), $id('OTW Baru'), $hidden->id], $check->viewData('products')->pluck('id')->all());
        $this->assertSame([$hidden->id], $this->get('/admin/products?publication=published')->viewData('products')->pluck('id')->all());
        $this->get('/admin/products?publication=check&search=zzzz')->assertOk()->assertSee('Produk tidak ditemukan');
        $this->get('/admin/products?publication=nope')->assertOk();
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
        $this->get(route('admin.app-products.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get(route('admin.app-products.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.app-products.index', ['status' => 'draft']))
            ->assertOk()->assertSee('Plain app product')->assertSee('Lengkapi draft')->assertSee('Dibuat di aplikasi')->assertSee('Foto')->assertSee('Harga aplikasi');
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_import_batches', 0);
    }

    public function test_draft_of_a_product_hidden_in_the_app_leaves_semua_and_can_be_archived(): void
    {
        $s = $this->source(['name' => 'Merged app duplicate', 'source' => 'app', 'price' => null]);
        $this->feed($s);
        $this->feed(array_replace($s, ['revision' => 2, 'change_seq' => 2, 'hidden' => true, 'active' => false]));
        $draft = Product::sole();
        $this->assertSame('draft', $draft->publication_status);
        $this->actingAs($this->admin());

        $all = $this->get(route('admin.app-products.index'))->assertOk()->assertDontSee('Merged app duplicate');
        $this->assertSame(0, $all->viewData('counts')['all']);
        $this->get(route('admin.app-products.index', ['status' => 'hidden']))->assertOk()
            ->assertSee('Merged app duplicate')->assertSee('Arsipkan draft')->assertDontSee('Lengkapi draft')->assertDontSee('Belum siap tayang');
        $this->get(route('admin.products.index'))->assertSee('Arsipkan Merged app duplicate');

        $this->from(route('admin.app-products.index', ['status' => 'hidden']))->delete(route('admin.products.destroy', $draft->id))
            ->assertRedirect(route('admin.app-products.index', ['status' => 'hidden']))->assertSessionHas('success');
        $draft->refresh();
        $this->assertSame('archived', $draft->publication_status);
        $this->assertNotNull($draft->archived_at);
        $this->assertSame($s['id'], $draft->externalIdentities->sole()->external_product_id);
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
            ->assertOk()->assertSee('Terhubung ke Qammaris App: Aoera Review EDP 50 ML')->assertSee('Dari Majoo · Harga di App Rp 180.000 · Stok di App Tersedia')
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

    private function merged(array $s, string $into, int $sequence): array
    {
        return array_replace($s, ['active' => false, 'merged_into' => $into, 'hidden' => true, 'revision' => $sequence, 'change_seq' => $sequence]);
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
