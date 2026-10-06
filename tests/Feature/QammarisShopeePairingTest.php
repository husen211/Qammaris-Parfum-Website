<?php

namespace Tests\Feature;

use App\Actions\Products\AcquireProductImportRowImages;
use App\Actions\Products\MapExternalProductIdentity;
use App\Actions\Products\PairQammarisShopeeDrafts;
use App\Actions\Products\QueueProductImportImages;
use App\Models\Product;
use App\Services\ProductCatalogRowFingerprint;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QammarisShopeePairingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        Storage::fake('public');
        config(['media.product_disk' => 'public']);
    }

    public function test_exact_feed_sku_pairs_existing_uuid_draft_and_replay_preserves_ids_price_slug_and_audit(): void
    {
        $this->assertLessThanOrEqual(20, strlen(PairQammarisShopeeDrafts::VERSION), 'Existing MySQL contract column is varchar(20).');
        [$product] = $this->target('001-SKU');
        $before = $product->fresh()->getAttributes();
        $action = app(PairQammarisShopeeDrafts::class);
        $batch = $action->preview($this->input('001-SKU'));
        $this->assertSame($batch->id, $action->preview($this->input('001-SKU'))->id);
        $this->assertNull($product->fresh()->description);
        $this->assertDatabaseCount('product_external_identities', 1);
        $result = $action->apply($batch->id);
        $after = $product->fresh()->getAttributes();
        unset($before['description'], $before['updated_at'], $after['description'], $after['updated_at']);
        $this->assertSame($before, $after);
        $this->assertSame('Factual Shopee description.', $product->fresh()->description);
        $this->assertDatabaseHas('product_external_identities', ['provider' => 'shopee', 'external_product_id' => '123', 'product_id' => $product->id]);
        $this->assertSame(1, $result->applied_rows);
        $audit = $result->rows->sole()->getAttributes();
        $action->apply($batch->id);
        $this->assertSame($audit, $result->rows->sole()->fresh()->getAttributes());
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('users', 0);
        Http::assertNothingSent();
    }

    public function test_review_unmatched_missing_duplicate_hidden_and_published_rows_do_not_mutate(): void
    {
        foreach (['perlu_cek', 'ambigu', 'tidak_ketemu'] as $status) {
            [$p] = $this->target($status);
            $batch = app(PairQammarisShopeeDrafts::class)->preview($this->input($status, ['status' => $status, 'shopee_id' => (string) $p->id]));
            app(PairQammarisShopeeDrafts::class)->apply($batch->id);
            $this->assertNull($p->fresh()->description);
        }
        $this->target('duplicate');
        $this->target('duplicate');
        $this->target('hidden', ['hidden' => true]);
        [$published] = $this->target('published', [], ['publication_status' => 'published', 'is_active' => true]);
        $before = $published->fresh()->getAttributes();
        foreach (['missing', 'duplicate', 'hidden', 'published'] as $sku) {
            $batch = app(PairQammarisShopeeDrafts::class)->preview($this->input($sku));
            $this->assertSame(0, $batch->valid_rows);
            app(PairQammarisShopeeDrafts::class)->apply($batch->id);
        }
        $this->assertSame($before, $published->fresh()->getAttributes());
        $this->assertDatabaseMissing('product_external_identities', ['provider' => 'shopee']);
    }

    public function test_two_approved_rows_for_same_sku_are_both_blocked(): void
    {
        $this->target('same');
        $input = $this->input('same');
        $input['data'][] = array_replace($input['data'][0], ['shopee_id' => '456']);
        $batch = app(PairQammarisShopeeDrafts::class)->preview($input);
        $this->assertSame(0, $batch->valid_rows);
    }

    public function test_existing_provider_and_description_conflicts_are_not_overwritten(): void
    {
        [$p] = $this->target('A', [], ['description' => 'Manual description']);
        app(MapExternalProductIdentity::class)->handle($p, 'shopee', '999');
        $batch = app(PairQammarisShopeeDrafts::class)->preview($this->input('A'));
        $this->assertSame(0, $batch->valid_rows);
        app(PairQammarisShopeeDrafts::class)->apply($batch->id);
        $this->assertSame('Manual description', $p->fresh()->description);
        [$q] = $this->target('B', [], ['description' => 'Manual description']);
        $batch = app(PairQammarisShopeeDrafts::class)->preview($this->input('B'));
        $this->assertSame('existing_description_conflict', $batch->rows->sole()->issues[0]['message']);
        [$r] = $this->target('C');
        $batch = app(PairQammarisShopeeDrafts::class)->preview($this->input('C', ['shopee_id' => '999']));
        $this->assertSame('shopee_identity_conflict', $batch->rows->sole()->issues[0]['message']);
    }

    public function test_existing_photos_and_equal_description_are_kept_without_download(): void
    {
        [$p] = $this->target('A', [], ['description' => 'Factual Shopee description.']);
        $p->images()->create(['image_path' => 'products/retained.png', 'is_primary' => true, 'sort_order' => 0]);
        app(MapExternalProductIdentity::class)->handle($p, 'shopee', '123');
        $action = app(PairQammarisShopeeDrafts::class);
        $batch = $action->apply($action->preview($this->input('A'))->id);
        $this->assertSame(0, app(QueueProductImportImages::class)->handle($batch, null)['candidate_images']);
        $this->assertDatabaseCount('product_images', 1);
        $this->assertDatabaseCount('product_external_identities', 2);
        Http::assertNothingSent();
    }

    public function test_tamper_catalog_change_source_change_or_new_duplicate_sku_abort_atomic_apply(): void
    {
        foreach (['payload', 'catalog', 'source', 'duplicate'] as $scenario) {
            [$p, $s] = $this->target($scenario);
            $input = $this->input($scenario, ['shopee_id' => (string) $p->id]);
            $action = app(PairQammarisShopeeDrafts::class);
            $batch = $action->preview($input);
            if ($scenario === 'payload') {
                $row = $batch->rows->sole();
                $data = $row->normalized_data;
                $data['description'] = 'Tampered';
                $row->update(['normalized_data' => $data]);
            } elseif ($scenario === 'catalog') {
                $p->update(['name' => 'Manual newer name']);
            } elseif ($scenario === 'source') {
                $s['revision'] = 2;
                DB::table('qammaris_app_products')->where('id', $s['id'])->update(['snapshot' => json_encode($s)]);
            } else {
                $this->target($scenario);
            }
            try {
                $action->apply($batch->id);
                $this->fail('Changed preview must fail.');
            } catch (DomainException) {
                $this->assertNull($p->fresh()->description);
                $this->assertSame('previewed', $batch->fresh()->status);
            }
        }
        $this->assertDatabaseMissing('product_external_identities', ['provider' => 'shopee']);
    }

    public function test_cover_failure_blocks_additional_then_retry_is_idempotent(): void
    {
        $this->target('A');
        $action = app(PairQammarisShopeeDrafts::class);
        $batch = $action->apply($action->preview($this->input('A'))->id);
        $row = $batch->rows->sole();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        Http::fakeSequence()->push('not image', 200)->push($png, 200)->push($png, 200)->push($png, 200);
        app(QueueProductImportImages::class)->handle($batch, null);
        app(AcquireProductImportRowImages::class)->handle($row->id);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame(['failed', 'blocked', 'blocked'], array_column($row->fresh()->image_acquisition_outcomes, 'status'));
        app(QueueProductImportImages::class)->handle($batch, null);
        app(AcquireProductImportRowImages::class)->handle($row->id);
        $this->assertDatabaseCount('product_images', 3);
        app(QueueProductImportImages::class)->handle($batch, null);
        app(AcquireProductImportRowImages::class)->handle($row->id);
        Http::assertSentCount(4);
        foreach (Product::sole()->images as $image) {
            Storage::disk('public')->assertExists($image->image_path);
        }
    }

    public function test_hidden_or_rebound_target_after_enqueue_blocks_network_and_storage(): void
    {
        foreach (['hidden', 'identity'] as $scenario) {
            [$p, $s] = $this->target($scenario);
            $action = app(PairQammarisShopeeDrafts::class);
            $batch = $action->apply($action->preview($this->input($scenario, ['shopee_id' => (string) $p->id]))->id);
            app(QueueProductImportImages::class)->handle($batch, null);
            if ($scenario === 'hidden') {
                $s['hidden'] = true;
                DB::table('qammaris_app_products')->where('id', $s['id'])->update(['snapshot' => json_encode($s)]);
            } else {
                $p->externalIdentities()->where('provider', 'shopee')->delete();
            }
            app(AcquireProductImportRowImages::class)->handle($batch->rows->sole()->id);
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_command_refuses_unconfirmed_nonprivate_and_production_operations(): void
    {
        $this->artisan('qammaris-app:shopee-pairs', ['--apply' => '1'])->assertFailed();
        $this->artisan('qammaris-app:shopee-pairs', ['--file' => base_path('composer.json')])->assertFailed();
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('qammaris-app:shopee-pairs', ['--apply' => '1', '--confirm' => true])->assertFailed();
        $this->expectException(DomainException::class);
        app(PairQammarisShopeeDrafts::class)->preview($this->input('A'));
    }

    public function test_explicit_owner_choice_pairs_only_selected_candidate_and_records_provenance(): void
    {
        [$p, $source] = $this->target('choice');
        [$choices, $review, $original] = $this->ownerChoice($p, $source);
        $action = app(PairQammarisShopeeDrafts::class);
        $batch = $action->previewOwnerChoices($choices, $review, $original->id);
        $this->assertSame(1, $batch->valid_rows);
        $this->assertSame('owner-selected-shopee-candidates', $batch->source_filename);
        $this->assertSame($batch->id, $action->previewOwnerChoices($choices, $review, $original->id)->id);
        $this->assertSame($choices['data'][0], $batch->rows->sole()->normalized_data['owner_selection']['choice']);
        $result = $action->apply($batch->id);
        $this->assertSame(1, $result->applied_rows);
        $this->assertSame('draft', $p->fresh()->publication_status);
        $this->assertSame('Factual Shopee description.', $p->fresh()->description);
        $before = $result->rows->sole()->getAttributes();
        $this->assertSame($before, $action->apply($batch->id)->rows->sole()->getAttributes());
        $this->assertSame(3, app(QueueProductImportImages::class)->handle($result, null)['candidate_images']);
        $this->assertSame('blocked_protected', $original->rows->sole()->fresh()->apply_status);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_stale_owner_choice_or_occupied_identity_is_held_without_rebinding(): void
    {
        foreach (['stale', 'occupied'] as $scenario) {
            [$p, $source] = $this->target($scenario);
            if ($scenario === 'occupied') {
                app(MapExternalProductIdentity::class)->handle($p, 'shopee', '999');
            }
            [$choices, $review, $original] = $this->ownerChoice($p, $source);
            if ($scenario === 'stale') {
                $p->update(['description' => 'Owner edit']);
            }
            $before = $p->fresh()->getAttributes();
            $action = app(PairQammarisShopeeDrafts::class);
            $batch = $action->previewOwnerChoices($choices, $review, $original->id);
            $this->assertSame(0, $batch->valid_rows);
            $action->apply($batch->id);
            $this->assertSame($before, $p->fresh()->getAttributes());
        }
        $this->assertDatabaseMissing('product_external_identities', ['provider' => 'shopee', 'external_product_id' => '123']);
        Http::assertNothingSent();
    }

    public function test_owner_choices_reject_non_candidate_tampered_review_duplicate_targets_and_changed_provenance(): void
    {
        [$p, $source] = $this->target('choice');
        [$choices, $review, $original] = $this->ownerChoice($p, $source);
        foreach (['sku', 'review', 'provenance', 'duplicate', 'uuid'] as $scenario) {
            $c = $choices;
            $r = $review;
            if ($scenario === 'sku') {
                $c['data'][0]['majoo_sku'] = 'not-supplied';
            }
            if ($scenario === 'review') {
                $r['data'][0]['description'] = 'Changed source text';
            }
            if ($scenario === 'provenance') {
                $c['capture_sha256'] = str_repeat('f', 64);
            }
            if ($scenario === 'duplicate') {
                $c['data'][] = $c['data'][0];
            }
            if ($scenario === 'uuid') {
                $c['data'][0]['uuid'] = (string) Str::uuid();
            }
            try {
                app(PairQammarisShopeeDrafts::class)->previewOwnerChoices($c, $r, $original->id);
                $this->fail('Invalid Owner choice accepted: '.$scenario);
            } catch (DomainException|ValidationException) {
                $this->assertDatabaseCount('product_import_batches', 1);
            }
        }
        $this->assertNull($p->fresh()->description);
    }

    public function test_direct_mapping_json_cannot_claim_owner_selection(): void
    {
        $this->target('choice');
        $this->expectException(ValidationException::class);
        app(PairQammarisShopeeDrafts::class)->preview($this->input('choice', ['status' => 'owner']));
    }

    private function ownerChoice(Product $p, array $source): array
    {
        $input = $this->input($source['sku'], ['status' => 'ambigu', 'candidates' => [['sku' => $source['sku'], 'name' => $source['name'], 'score' => '0.8']]]);
        $action = app(PairQammarisShopeeDrafts::class);
        $original = $action->apply($action->preview($input)->id);
        $existing = $p->externalIdentities()->where('provider', 'shopee')->value('external_product_id');
        $fingerprint = app(ProductCatalogRowFingerprint::class)->hash($p->fresh());
        $choice = ['shopee_id' => '123', 'shopee_name' => $input['data'][0]['shopee_name'], 'majoo_sku' => $source['sku'],
            'uuid' => $source['id'], 'website_id' => $p->id, 'expected_fingerprint' => $fingerprint,
            'requires_conflict_review' => $existing !== null && $existing !== '123', 'existing_shopee_id' => $existing];
        $choices = ['schema' => 'qammaris-shopee-owner-choices-v1', 'provenance' => $input['provenance'],
            'capture_sha256' => str_repeat('d', 64), 'captured_at' => '2026-10-06T03:26:17Z', 'data' => [$choice]];
        $review = ['schema' => 'qammaris-shopee-pair-review-v1', 'provenance' => $input['provenance'],
            'capture_sha256' => $choices['capture_sha256'], 'captured_at' => $choices['captured_at'],
            'data' => [array_merge($input['data'][0], ['candidates' => [['sku' => $source['sku'], 'uuid' => $source['id'],
                'website_id' => $p->id, 'fingerprint' => $fingerprint, 'existing_shopee_id' => $existing]]])]];

        return [$choices, $review, $original];
    }

    private function target(string $sku, array $source = [], array $product = []): array
    {
        $s = array_replace(['id' => (string) Str::uuid(), 'sku' => $sku, 'name' => 'Feed product EDP 50 ml', 'brand' => 'Aoera',
            'hidden' => false, 'revision' => 1, 'change_seq' => 1], $source);
        DB::table('qammaris_app_products')->insert(['id' => $s['id'], 'revision' => 1, 'snapshot' => json_encode($s), 'created_at' => now(), 'updated_at' => now()]);
        $p = Product::create(array_replace(['name' => 'Website retained name', 'publication_status' => 'draft', 'is_active' => false, 'base_price' => 180000], $product));
        app(MapExternalProductIdentity::class)->handle($p, 'qammaris_app', $s['id']);

        return [$p, $s];
    }

    private function input(string $sku, array $overrides = []): array
    {
        return ['schema' => PairQammarisShopeeDrafts::VERSION,
            'provenance' => ['mapping_sha256' => str_repeat('a', 64), 'media_sha256' => str_repeat('b', 64), 'basic_sha256' => str_repeat('c', 64)],
            'data' => [array_replace(['shopee_id' => '123', 'shopee_name' => 'Different source name EDP 50 ml', 'majoo_sku' => $sku, 'status' => 'kuat',
                'description' => 'Factual Shopee description.', 'photos' => ['https://cf.shopee.co.id/cover', 'https://cf.shopee.co.id/one', 'https://cf.shopee.co.id/two'], 'candidates' => []], $overrides)]];
    }
}
