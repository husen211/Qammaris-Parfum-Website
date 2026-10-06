<?php

namespace Tests\Feature;

use App\Actions\Products\ApplyQammarisAppAvailability;
use App\Actions\Products\ArchiveProductImage;
use App\Actions\Products\MapExternalProductIdentity;
use App\Actions\Products\SyncQammarisAppFeed;
use App\Jobs\SyncQammarisAppProducts;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class QammarisAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['qammaris_app.api_key' => 'synthetic-read-key', 'qammaris_app.webhook_secret' => 'synthetic-hmac-secret']);
        Http::preventStrayRequests();
    }

    public function test_webhook_authenticates_raw_body_and_persists_a_database_job_before_202(): void
    {
        $raw = json_encode($this->signal(999999), JSON_PRETTY_PRINT);
        $this->signed($raw)->assertStatus(202);
        $job = DB::table('jobs')->sole();
        $this->assertSame('qammaris-app', $job->queue);
        $this->assertStringContainsString(SyncQammarisAppProducts::class, json_decode($job->payload, true)['displayName']);
        $this->assertSame(0, $this->checkpoint());
        Http::assertNothingSent();
    }

    public function test_database_queue_worker_processes_the_acknowledged_job(): void
    {
        $id = (string) Str::uuid();
        Http::fakeSequence()->push($this->page([$this->row($id, 7)], 7));
        $this->signed(json_encode($this->signal(999)))->assertStatus(202);
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'qammaris-app', '--once' => true])->assertSuccessful();
        $this->assertSame(7, $this->checkpoint());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('qammaris_app_products', 1);
    }

    public function test_concurrent_checkpoint_progress_discards_the_older_fetched_page(): void
    {
        $id = (string) Str::uuid();
        Http::fake(function ($request) use ($id) {
            if (str_contains($request->url(), 'after_seq=0')) {
                DB::table('qammaris_app_sync_states')->update(['checkpoint' => 20]);

                return Http::response($this->page([$this->row($id, 10)], 10));
            }

            return Http::response($this->page([], 20));
        });
        $this->assertFalse(app(SyncQammarisAppFeed::class)->handle());
        $this->assertSame(20, $this->checkpoint());
        $this->assertDatabaseCount('qammaris_app_products', 0);
    }

    public function test_merge_and_other_department_tombstones_preserve_original_product_and_mapping(): void
    {
        $id = (string) Str::uuid();
        $product = $this->product();
        app(MapExternalProductIdentity::class)->handle($product, 'qammaris_app', $id);
        Http::fakeSequence()
            ->push($this->page([$this->row($id, 1, ['merged_into' => (string) Str::uuid(), 'hidden' => true])], 1))
            ->push($this->page([$this->row($id, 2, ['department_code' => 'lainnya', 'hidden' => true])], 2));
        $sync = app(SyncQammarisAppFeed::class);
        $sync->handle();
        $sync->handle();
        $this->assertFalse($product->fresh()->isPubliclyVisible());
        $this->assertTrue($product->fresh()->isPublished());
        $this->get(route('products.show', $product))->assertNotFound();
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('product_external_identities', ['product_id' => $product->id, 'external_product_id' => $id]);
        $this->assertSame('published', $product->fresh()->publication_status);
    }

    public function test_additive_migration_preserves_existing_identity_price_and_availability(): void
    {
        $migration = require database_path('migrations/2026_10_05_000001_create_qammaris_app_integration.php');
        $migration->down();
        $product = $this->product();
        $before = $product->only(['id', 'slug', 'base_price', 'availability_status', 'publication_status']);
        $migration->up();
        $this->assertSame($before, $product->fresh()->only(array_keys($before)));
        $this->assertFalse($product->fresh()->qammaris_app_hidden);
        $this->assertSame(0, $this->checkpoint());
        $this->assertDatabaseCount('qammaris_app_products', 0);
    }

    public function test_hidden_published_product_still_protects_its_last_image(): void
    {
        $product = $this->product();
        $product->forceFill(['qammaris_app_hidden' => true])->save();
        $image = ProductImage::create([
            'product_id' => $product->id, 'image_path' => 'synthetic/retained.jpg', 'is_primary' => true, 'sort_order' => 0,
        ]);
        try {
            app(ArchiveProductImage::class)->handle($product, $image->id);
            $this->fail('Hidden source must not weaken published image protection.');
        } catch (DomainException) {
            $this->assertFalse($image->fresh()->trashed());
            $this->assertTrue($product->fresh()->isPublished());
        }
    }

    public function test_webhook_rejects_missing_tampered_expired_and_future_signatures(): void
    {
        Queue::fake();
        $raw = json_encode($this->signal());
        $this->call('POST', '/integrations/qammaris-app/webhook', content: $raw)->assertUnauthorized();
        $this->signed($raw.' ', signatureBody: $raw)->assertUnauthorized();
        $this->signed($raw, now()->timestamp - 301)->assertUnauthorized();
        $this->signed($raw, now()->timestamp + 301)->assertUnauthorized();
        Queue::assertNothingPushed();
    }

    public function test_webhook_validates_signed_payload_and_size(): void
    {
        Queue::fake();
        $this->signed('{')->assertStatus(422);
        $this->signed(json_encode(array_replace($this->signal(), ['id' => '../../products'])))->assertStatus(422);
        $this->signed(json_encode(array_replace($this->signal(), ['revision' => 2])))->assertStatus(422);
        $this->signed(str_repeat('x', 16385))->assertStatus(413);
        Queue::assertNothingPushed();
    }

    public function test_webhook_disabled_configuration_and_queue_failure_do_not_acknowledge(): void
    {
        config(['qammaris_app.api_key' => '']);
        $this->signed(json_encode($this->signal()))->assertStatus(503);
        config(['qammaris_app.api_key' => 'synthetic-read-key']);
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('synthetic queue failure'));
        $this->signed(json_encode($this->signal()))->assertStatus(503);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_webhook_duplicate_and_out_of_order_signals_only_wake_feed_from_local_checkpoint(): void
    {
        Queue::fake();
        foreach ([500, 3, 500] as $seq) {
            $this->signed(json_encode($this->signal($seq)))->assertStatus(202);
        }
        Queue::assertPushed(SyncQammarisAppProducts::class, 3);
        $this->assertSame(0, $this->checkpoint());
    }

    public function test_feed_paginates_using_checkpoint_and_does_not_overwrite_catalog_fields(): void
    {
        $id = (string) Str::uuid();
        $product = $this->product();
        app(MapExternalProductIdentity::class)->handle($product, 'qammaris_app', $id);
        $before = $product->only(['id', 'slug', 'name', 'base_price', 'publication_status', 'is_active']);
        $offer = ProductVariant::create([
            'product_id' => $product->id, 'volume' => 100, 'price' => 250000, 'stock' => 4, 'sku' => 'WEBSITE-SKU', 'is_active' => true,
        ]);
        $image = ProductImage::create([
            'product_id' => $product->id, 'image_path' => 'synthetic/preserved.jpg', 'is_primary' => true, 'sort_order' => 0,
        ]);
        $beforeOffer = $offer->fresh()->getAttributes();
        $beforeImage = $image->fresh()->getAttributes();
        $first = $this->row($id, 10, ['stock_status_at' => '2020-01-01T00:00:00Z']);
        Http::fakeSequence()->push($this->page([$first], 10, true))->push($this->page([], 10));
        $this->assertFalse(app(SyncQammarisAppFeed::class)->handle());
        $product->refresh();
        $this->assertSame($before, $product->only(array_keys($before)));
        $this->assertSame($beforeOffer, $offer->fresh()->getAttributes());
        $this->assertSame($beforeImage, $image->fresh()->getAttributes());
        $this->assertSame('available', $product->effective_availability);
        $this->assertSame('qammaris_app', $product->availability_source);
        $this->assertSame(10, $this->checkpoint());
        $this->assertDatabaseCount('qammaris_app_changes', 1);
        $this->assertSame($product->id, DB::table('qammaris_app_changes')->value('product_id'));
        $requests = Http::recorded();
        $this->assertStringContainsString('after_seq=0', $requests[0][0]->url());
        $this->assertStringContainsString('after_seq=10', $requests[1][0]->url());
        $this->assertTrue($requests[0][0]->hasHeader('X-Api-Key', 'synthetic-read-key'));
    }

    public function test_sold_out_otw_revert_and_hidden_are_revision_driven_without_changing_publication(): void
    {
        $id = (string) Str::uuid();
        $product = $this->product();
        app(MapExternalProductIdentity::class)->handle($product, 'qammaris_app', $id);
        Http::fakeSequence()
            ->push($this->page([$this->row($id, 4, ['availability' => 'sold_out', 'restock_eta' => '2026-10-12'])], 4))
            ->push($this->page([$this->row($id, 8, ['active' => false, 'hidden' => true])], 8))
            ->push($this->page([$this->row($id, 12)], 12));
        $sync = app(SyncQammarisAppFeed::class);
        $sync->handle();
        $this->assertSame('sold_out', $product->fresh()->effective_availability);
        $this->assertSame('2026-10-12', $product->fresh()->availability_restock_eta->format('Y-m-d'));
        $sync->handle();
        $this->assertFalse($product->fresh()->isPubliclyVisible());
        $this->assertTrue($product->fresh()->isPublished());
        $this->assertFalse(Product::published()->whereKey($product->id)->exists());
        $this->assertSame('published', $product->fresh()->publication_status);
        $sync->handle();
        $this->assertTrue($product->fresh()->isPubliclyVisible());
        $this->assertNull($product->fresh()->availability_restock_eta);
        $this->assertSame('available', $product->fresh()->effective_availability);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('qammaris_app_changes', 3);
    }

    public function test_unmapped_products_are_cached_and_explicit_mapping_replays_snapshot_after_checkpoint(): void
    {
        $id = (string) Str::uuid();
        $row = $this->row($id, 25, ['costPrice' => 10, 'employee' => 'must not retain']);
        Http::fakeSequence()->push($this->page([$row], 25));
        app(SyncQammarisAppFeed::class)->handle();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('qammaris_app_products', 1);
        $snapshot = DB::table('qammaris_app_products')->value('snapshot');
        $this->assertStringNotContainsString('costPrice', $snapshot);
        $this->assertStringNotContainsString('employee', $snapshot);
        $product = $this->product();
        $this->artisan('qammaris-app:map', ['product_id' => $product->id, 'external_id' => $id])->assertSuccessful();
        $this->assertDatabaseCount('product_external_identities', 0);
        $this->artisan('qammaris-app:map', ['product_id' => $product->id, 'external_id' => $id, '--confirm' => true])->assertSuccessful();
        $this->artisan('qammaris-app:map', ['product_id' => $product->id, 'external_id' => $id, '--confirm' => true])->assertSuccessful();
        $this->assertDatabaseCount('qammaris_app_changes', 1);
        $this->assertSame('available', $product->fresh()->effective_availability);
    }

    public function test_duplicate_or_older_product_revision_is_no_op_and_checkpoint_can_advance(): void
    {
        $id = (string) Str::uuid();
        $product = $this->product();
        app(MapExternalProductIdentity::class)->handle($product, 'qammaris_app', $id);
        Http::fakeSequence()->push($this->page([$this->row($id, 10)], 10))
            ->push($this->page([$this->row($id, 5, ['availability' => 'sold_out'])], 20));
        app(SyncQammarisAppFeed::class)->handle();
        DB::table('qammaris_app_sync_states')->update(['checkpoint' => 0]);
        app(SyncQammarisAppFeed::class)->handle();
        $this->assertSame('available', $product->fresh()->effective_availability);
        $this->assertSame(20, $this->checkpoint());
        $this->assertSame(10, (int) DB::table('qammaris_app_products')->value('revision'));
        $this->assertDatabaseCount('qammaris_app_changes', 1);
    }

    public function test_database_failure_rolls_back_product_snapshot_audit_and_checkpoint(): void
    {
        $id = (string) Str::uuid();
        $product = $this->product();
        app(MapExternalProductIdentity::class)->handle($product, 'qammaris_app', $id);
        Http::fakeSequence()->push($this->page([$this->row($id, 10)], 10));
        $this->mock(ApplyQammarisAppAvailability::class)->shouldReceive('handle')->once()
            ->andReturnUsing(function (Product $product): void {
                $product->update(['availability_status' => 'sold_out']);
                throw new RuntimeException('synthetic transaction failure');
            });
        try {
            app(SyncQammarisAppFeed::class)->handle();
            $this->fail('Expected transaction failure.');
        } catch (RuntimeException) {
            $this->assertSame('unknown', $product->fresh()->availability_status);
            $this->assertSame(0, $this->checkpoint());
            $this->assertDatabaseCount('qammaris_app_products', 0);
            $this->assertDatabaseCount('qammaris_app_changes', 0);
        }
    }

    public function test_invalid_feed_and_failed_transport_preserve_checkpoint_and_availability(): void
    {
        $id = (string) Str::uuid();
        $product = $this->product();
        app(MapExternalProductIdentity::class)->handle($product, 'qammaris_app', $id);
        $cases = [
            $this->page([$this->row($id, 2), $this->row((string) Str::uuid(), 1)], 2),
            $this->page([$this->row($id, 1, ['availability' => 'in_stock'])], 1),
            $this->page([$this->row($id, 1, ['hidden' => true])], 1),
            $this->page([$this->row($id, 1, ['revision' => 2])], 1),
            $this->page([], 0, true),
            $this->page([], -1),
            ['data' => [], 'meta' => ['next_seq' => '1', 'has_more' => false]],
        ];
        $sequence = Http::fakeSequence();
        foreach ($cases as $payload) {
            $sequence->push($payload);
        }
        foreach ([302, 401, 503] as $status) {
            $sequence->push('sensitive upstream error', $status);
        }
        foreach ($cases as $payload) {
            try {
                app(SyncQammarisAppFeed::class)->handle();
                $this->fail('Invalid feed should fail.');
            } catch (RuntimeException $exception) {
                $this->assertStringStartsWith('qammaris_app_invalid_', $exception->getMessage());
                $this->assertSame(0, $this->checkpoint());
                $this->assertSame('unknown', $product->fresh()->availability_status);
                $this->assertDatabaseCount('qammaris_app_products', 0);
            }
        }
        foreach ([302, 401, 503] as $status) {
            try {
                app(SyncQammarisAppFeed::class)->handle();
                $this->fail('Bad transport should fail.');
            } catch (RuntimeException $exception) {
                $this->assertSame('qammaris_app_feed_unavailable', $exception->getMessage());
                $this->assertNull($exception->getPrevious());
                $this->assertSame(0, $this->checkpoint());
            }
        }
    }

    public function test_filter_parity_and_outage_do_not_expire_app_availability(): void
    {
        $app = $this->product();
        $app->forceFill(['availability_status' => 'available', 'availability_source' => 'qammaris_app', 'availability_checked_at' => null])->save();
        $manual = $this->product();
        $manual->update(['availability_status' => 'available', 'availability_source' => 'manual', 'availability_checked_at' => now()->subDays(20)]);
        $this->assertSame('available', $app->effective_availability);
        $this->assertSame('unknown', $manual->effective_availability);
        $this->assertEquals([$app->id], Product::effectiveAvailability('available')->pluck('id')->all());
        $this->assertEquals([$manual->id], Product::effectiveAvailability('unknown')->pluck('id')->all());
    }

    public function test_reconciliation_command_is_disabled_without_config_and_queues_when_ready(): void
    {
        Queue::fake();
        config(['qammaris_app.api_key' => '']);
        $this->artisan('qammaris-app:sync')->assertSuccessful();
        Queue::assertNothingPushed();
        config(['qammaris_app.api_key' => 'synthetic-read-key']);
        $this->artisan('qammaris-app:sync')->assertSuccessful();
        Queue::assertPushed(SyncQammarisAppProducts::class, fn ($job) => $job->connection === 'database' && $job->queue === 'qammaris-app');
    }

    public function test_bounded_job_continues_feed_in_new_job_and_records_safe_failure(): void
    {
        Queue::fake();
        $sequence = Http::fakeSequence();
        for ($i = 1; $i <= 5; $i++) {
            $sequence->push($this->page([$this->row((string) Str::uuid(), $i)], $i, true));
        }
        $sequence->push([], 503);
        (new SyncQammarisAppProducts)->handle(app(SyncQammarisAppFeed::class));
        $this->assertSame(5, $this->checkpoint());
        Queue::assertPushed(SyncQammarisAppProducts::class, 1);
        try {
            (new SyncQammarisAppProducts)->handle(app(SyncQammarisAppFeed::class));
            $this->fail('Expected safe worker failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('synthetic-read-key', $exception->getMessage());
            $this->assertSame('feed_sync_failed', DB::table('qammaris_app_sync_states')->value('last_error'));
            $this->assertSame(5, $this->checkpoint());
        }
    }

    private function signal(int $seq = 1): array
    {
        return ['id' => (string) Str::uuid(), 'revision' => $seq, 'change_seq' => $seq, 'sent_at' => now()->toIso8601String()];
    }

    private function signed(string $raw, ?int $timestamp = null, ?string $signatureBody = null)
    {
        $timestamp ??= now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.($signatureBody ?? $raw), 'synthetic-hmac-secret');

        return $this->call('POST', '/integrations/qammaris-app/webhook', server: [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_QAMMARIS_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_QAMMARIS_SIGNATURE' => $signature,
        ], content: $raw);
    }

    private function row(string $id, int $seq, array $overrides = []): array
    {
        return array_replace([
            'id' => $id, 'sku' => 'SKU-REF', 'name' => 'Source name 100 ml', 'brand' => 'Source brand',
            'department_code' => 'timteng', 'price' => 598000, 'source' => 'majoo', 'availability' => 'available',
            'restock_eta' => null, 'stock_status_at' => '2026-10-05T10:23:00.000Z',
            'active' => true, 'merged_into' => null, 'hidden' => false, 'revision' => $seq, 'change_seq' => $seq,
        ], $overrides);
    }

    private function page(array $data, int $next, bool $more = false): array
    {
        return ['data' => $data, 'meta' => ['next_seq' => $next, 'has_more' => $more]];
    }

    private function checkpoint(): int
    {
        return (int) DB::table('qammaris_app_sync_states')->value('checkpoint');
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Website Product '.Str::random(8), 'description' => 'Original website description',
            'base_price' => 250000, 'is_active' => true, 'publication_status' => 'published', 'availability_status' => 'unknown',
        ]);
    }
}
