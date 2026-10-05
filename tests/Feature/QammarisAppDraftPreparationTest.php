<?php

namespace Tests\Feature;

use App\Actions\Products\AcquireProductImportRowImages;
use App\Actions\Products\MapExternalProductIdentity;
use App\Actions\Products\PrepareQammarisAppDrafts;
use App\Actions\Products\QueueProductImportImages;
use App\Models\Product;
use App\Services\ImportedProductName;
use App\Services\ShopeeProductMediaReview;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class QammarisAppDraftPreparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['media.product_disk' => 'public']);
        Queue::fake();
        Http::preventStrayRequests();
    }

    public function test_preview_does_not_create_catalog_and_repeat_apply_creates_one_draft_per_visible_uuid(): void
    {
        $visible = $this->source();
        $this->source(['hidden' => true, 'active' => false]);
        $action = app(PrepareQammarisAppDrafts::class);
        $batch = $action->preview([]);
        $this->assertSame($batch->id, $action->preview([])->id);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('brands', 0);
        $result = $action->apply($batch->id);
        $product = Product::sole();
        $this->assertSame('draft', $product->publication_status);
        $this->assertFalse($product->is_active);
        $this->assertNull($product->published_at);
        $this->assertNull($product->description);
        $this->assertNull($product->gender);
        $this->assertNull($product->stock_quantity);
        $this->assertSame('available', $product->availability_status);
        $this->assertSame('qammaris_app', $product->availability_source);
        $this->assertSame('180000.00', $product->activeOffer->price);
        $this->assertSame(50, $product->activeOffer->volume);
        $this->assertNull($product->activeOffer->sku);
        $this->assertSame('Aoera', $product->brand->name);
        $this->assertSame('Eau de Parfum', $product->category->name);
        $this->assertSame($visible['id'], $product->externalIdentities->sole()->external_product_id);
        $this->assertSame(1, $result->applied_rows);
        $action->apply($batch->id);
        $action->apply($action->preview([])->id);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('qammaris_app_changes', 1);
        $this->assertDatabaseCount('users', 0);
        Http::assertNothingSent();
    }

    public function test_existing_mapped_published_product_price_slug_and_media_are_retained(): void
    {
        $s = $this->source();
        $p = Product::create(['name' => 'Old name', 'publication_status' => 'published', 'base_price' => 98765]);
        $p->images()->create(['image_path' => 'products/retained.png', 'is_primary' => true, 'sort_order' => 0]);
        app(MapExternalProductIdentity::class)->handle($p, 'qammaris_app', $s['id']);
        $before = $p->fresh()->getAttributes();
        $action = app(PrepareQammarisAppDrafts::class);
        $action->apply($action->preview([])->id);
        $this->assertSame($before, $p->fresh()->getAttributes());
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_images', 1);
        $this->assertDatabaseCount('qammaris_app_changes', 0);
    }

    public function test_unmapped_current_catalog_candidate_blocks_duplicate_creation(): void
    {
        $s = $this->source();
        Product::create(['name' => $s['name']]);
        $action = app(PrepareQammarisAppDrafts::class);
        $batch = $action->preview([]);
        $this->assertSame('conflict', $batch->rows->sole()->candidate_action);
        $action->apply($batch->id);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_external_identities', 0);
    }

    public function test_source_app_and_missing_size_price_stay_draft_and_flagged_without_invented_offer(): void
    {
        $this->source(['name' => 'Unconfirmed tester', 'source' => 'app', 'price' => null, 'brand' => null]);
        $action = app(PrepareQammarisAppDrafts::class);
        $batch = $action->preview([]);
        $this->assertContains('source_app_requires_owner_review', array_column($batch->rows->sole()->issues, 'message'));
        $action->apply($batch->id);
        $p = Product::sole();
        $this->assertNull($p->base_price);
        $this->assertNull($p->category_id);
        $this->assertNull($p->brand_id);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertFalse($p->isPubliclyVisible());
    }

    public function test_source_revision_change_invalidates_preview_before_any_catalog_write(): void
    {
        $s = $this->source();
        $action = app(PrepareQammarisAppDrafts::class);
        $batch = $action->preview([]);
        $s['revision'] = $s['change_seq'] = 2;
        DB::table('qammaris_app_products')->where('id', $s['id'])->update(['revision' => 2, 'snapshot' => json_encode($s)]);
        try {
            $action->apply($batch->id);
            $this->fail('Stale preview must be rejected.');
        } catch (DomainException) {
            $this->assertDatabaseCount('products', 0);
            $this->assertDatabaseCount('brands', 0);
            $this->assertSame('previewed', $batch->fresh()->status);
        }
    }

    public function test_tampered_row_and_changed_catalog_abort_without_partial_creation(): void
    {
        $this->source();
        $action = app(PrepareQammarisAppDrafts::class);
        $batch = $action->preview([]);
        $row = $batch->rows->sole();
        $data = $row->normalized_data;
        $data['harga'] = 1;
        $row->update(['normalized_data' => $data]);
        try {
            $action->apply($batch->id);
            $this->fail('Tampered preview must be rejected.');
        } catch (DomainException) {
            $this->assertDatabaseCount('products', 0);
        }
        Product::create(['name' => 'New catalog row']);
        try {
            $action->apply($batch->id);
            $this->fail('Changed catalog must be rejected.');
        } catch (DomainException) {
            $this->assertDatabaseCount('products', 1);
        }
    }

    public function test_media_matching_requires_unique_name_size_and_compatible_concentration(): void
    {
        $s = $this->source();
        $match = app(ShopeeProductMediaReview::class);
        $m = $this->media();
        $this->assertSame('strong', $match->match([$s], [$m])[$s['id']]['status']);
        $review = $match->match([$s], [array_replace($m, ['name' => 'Aoera Majestic EDT 50ML'])])[$s['id']];
        $this->assertSame('manual_review', $review['status']);
        $this->assertNull($review['media']);
        $this->assertSame('unmatched', $match->match([$s], [array_replace($m, ['name' => 'Aoera Majestic EDP 100ML'])])[$s['id']]['status']);
        $this->assertSame('ambiguous', $match->match([$s], [$m, array_replace($m, ['id' => '002'])])[$s['id']]['status']);
        $s2 = array_replace($s, ['id' => (string) Str::uuid()]);
        $this->assertSame('ambiguous', $match->match([$s, $s2], [$m])[$s['id']]['status']);
    }

    public function test_photos_are_downloaded_once_cover_first_and_provider_identity_stored(): void
    {
        $this->source();
        $action = app(PrepareQammarisAppDrafts::class);
        $batch = $action->apply($action->preview([$this->media()])->id);
        $row = $batch->rows->sole();
        $this->assertDatabaseHas('product_external_identities', ['provider' => 'shopee', 'external_product_id' => '001']);
        Http::fake(['https://cf.shopee.co.id/*' => Http::response($this->png(), 200)]);
        app(QueueProductImportImages::class)->handle($batch, null);
        app(AcquireProductImportRowImages::class)->handle($row->id);
        $this->assertDatabaseCount('product_images', 3);
        $outcomes = $row->fresh()->image_acquisition_outcomes;
        $p = Product::sole();
        $this->assertSame($outcomes[0]['product_image_id'], $p->primaryImage->id);
        foreach ($p->images as $image) {
            $this->assertStringStartsWith('products/', $image->image_path);
            Storage::disk('public')->assertExists($image->image_path);
        }
        app(QueueProductImportImages::class)->handle($batch, null);
        app(AcquireProductImportRowImages::class)->handle($row->id);
        Http::assertSentCount(3);
        $this->assertSame('draft', $p->fresh()->publication_status);
    }

    public function test_failed_cover_does_not_promote_secondary_and_retry_preserves_success(): void
    {
        $this->source();
        $action = app(PrepareQammarisAppDrafts::class);
        $batch = $action->apply($action->preview([$this->media()])->id);
        $row = $batch->rows->sole();
        Http::fakeSequence()->push('not image', 200)->push($this->png(), 200)->push($this->png(), 200)->push($this->png(), 200);
        app(QueueProductImportImages::class)->handle($batch, null);
        app(AcquireProductImportRowImages::class)->handle($row->id);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame(['failed', 'blocked', 'blocked'], array_column($row->fresh()->image_acquisition_outcomes, 'status'));
        app(QueueProductImportImages::class)->handle($batch, null);
        app(AcquireProductImportRowImages::class)->handle($row->id);
        $this->assertDatabaseCount('product_images', 3);
    }

    public function test_name_parser_never_guesses_multisize_fractional_or_bundle_volume(): void
    {
        $parser = app(ImportedProductName::class);
        foreach (['Perfume', '50 ml / 100 ml', 'Bundle 50ml', '2x50ml', '1,5ml', '0ml'] as $name) {
            $this->assertNull($parser->size($name));
        }
        $this->assertSame(50, $parser->size('Perfume 50 ML'));
        $this->assertNull($parser->concentration('Perfume EDP EDT'));
    }

    public function test_machine_image_request_is_not_allowed_for_human_csv_contract(): void
    {
        $this->source();
        $batch = app(PrepareQammarisAppDrafts::class)->preview([]);
        $batch->update(['contract_version' => 'canonical-v1']);
        $this->expectException(DomainException::class);
        app(QueueProductImportImages::class)->handle($batch, null);
    }

    public function test_preparation_command_rejects_production_and_unconfirmed_apply(): void
    {
        $this->artisan('qammaris-app:prepare-drafts', ['--apply' => '1'])->assertFailed();
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('qammaris-app:prepare-drafts', ['--apply' => '1', '--confirm' => true])->assertFailed();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_import_batches', 0);
    }

    private function source(array $overrides = []): array
    {
        $s = array_replace(['id' => (string) Str::uuid(), 'sku' => 'not-unique-truncated-sku', 'name' => 'AOERA MAJESTIC EDP 50 ML',
            'brand' => 'Aoera', 'department_code' => 'lokal', 'price' => 180000, 'source' => 'majoo',
            'availability' => 'available', 'restock_eta' => null, 'stock_status_at' => '2026-10-05T12:00:00Z',
            'active' => true, 'merged_into' => null, 'hidden' => false, 'revision' => 1, 'change_seq' => 1], $overrides);
        DB::table('qammaris_app_products')->insert(['id' => $s['id'], 'revision' => $s['revision'], 'snapshot' => json_encode($s), 'created_at' => now(), 'updated_at' => now()]);

        return $s;
    }

    private function media(): array
    {
        return ['id' => '001', 'sku' => 'different-shopee-sku', 'name' => 'Aoera Majestic Eau de Parfum 50ML',
            'photos' => ['https://cf.shopee.co.id/cover', 'https://cf.shopee.co.id/one', 'https://cf.shopee.co.id/two']];
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    }
}
