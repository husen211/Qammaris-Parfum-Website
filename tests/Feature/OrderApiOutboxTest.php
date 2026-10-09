<?php

namespace Tests\Feature;

use App\Models\IntegrationOutbox;
use App\Models\OnlineOrder;
use App\Models\User;
use App\Support\OrderApi\OrderApiSchema;
use App\Support\OrderApi\OrderApiSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsV2Orders;
use Tests\Concerns\SignsOrderApiRequests;
use Tests\TestCase;

/** ORD-02e slice C: webhook outbox, retries, failure view, task links and staging fixtures. */
class OrderApiOutboxTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase, SignsOrderApiRequests;

    private const HOOK = 'https://tunnel.example.test/api/integrations/website/orders/events';

    private const HOOK_SECRET = 'test-webhook-secret-not-real-0002';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->enableOrderApi([
            'orders_api.webhook_enabled' => true, 'orders_api.webhook_url' => self::HOOK, 'orders_api.webhook_secret' => self::HOOK_SECRET,
            'orders_api.webhook_client_id' => 'qammaris-website-test',
        ]);
    }

    public function test_every_v2_revision_is_delivered_once_with_a_signed_minimal_body(): void
    {
        Http::fake([self::HOOK => Http::response(['ok' => true], 200)]);
        $order = $this->v2Order();
        $this->assertSame(1, IntegrationOutbox::count(), 'One event for the new order, not one per internal save');

        $this->orderApi('POST', '/orders/'.$order->public_id.'/claims', ['task' => 'preparation', 'actor' => $this->actor()], ['Idempotency-Key' => Str::random(24)])->assertOk();
        $this->assertSame(2, IntegrationOutbox::count());
        $this->assertSame(2, IntegrationOutbox::whereNotNull('delivered_at')->count());

        Http::assertSent(function (HttpRequest $request) use ($order) {
            $body = json_decode($request->body(), false);
            $path = '/api/integrations/website/orders/events';
            $valid = OrderApiSchema::errors($body, 'WebhookEvent') === []
                && $request->url() === self::HOOK
                && $request->header('Idempotency-Key')[0] === $body->event_id
                && $request->header('X-Qammaris-Client')[0] === 'qammaris-website-test'
                && hash_equals(OrderApiSignature::sign(self::HOOK_SECRET, (int) $request->header('X-Qammaris-Timestamp')[0], 'POST', $path, $request->body()), $request->header('X-Qammaris-Signature')[0]);
            // No order or customer data in a webhook.
            $this->assertStringNotContainsString('E2E', $request->body());
            $this->assertStringNotContainsString('0812', $request->body());

            return $valid && $body->order_id === $order->public_id;
        });

        config(['orders_api.webhook_enabled' => false]);
        $this->v2Order();
        $this->assertSame(2, IntegrationOutbox::count(), 'Webhook off: nothing queued (App reconciles with updated_since)');
    }

    public function test_retries_sign_again_keep_the_same_bytes_and_give_up_after_24_hours(): void
    {
        $status = 503;
        Http::fake(function () use (&$status) {
            return Http::response($status === 200 ? [] : ['error' => 'down'], $status);
        });
        $this->v2Order();
        $row = IntegrationOutbox::sole();
        $this->assertSame([1, 503, 'HTTP 503'], [$row->attempts, $row->last_status, $row->last_error]);
        $this->assertNull($row->delivered_at);

        $seen = [];
        foreach ([60, 300, 900, 3600, 21600, 21600, 21600] as $delay) {
            $this->travel($delay + 1)->seconds();
            Artisan::call('qammaris:orders:deliver-webhooks');
        }
        Http::assertSent(function (HttpRequest $request) use (&$seen) {
            $seen[] = [$request->body(), $request->header('X-Qammaris-Timestamp')[0], $request->header('X-Qammaris-Signature')[0]];

            return true;
        });
        $this->assertCount(1, array_unique(array_column($seen, 0)), 'Same raw body on every attempt');
        $this->assertSame(count($seen), count(array_unique(array_column($seen, 1))), 'A new timestamp on every attempt');
        $this->assertSame(count($seen), count(array_unique(array_column($seen, 2))), 'A new signature on every attempt');

        $row->refresh();
        $this->assertNotNull($row->failed_at, 'Gave up after 24 hours');
        $this->assertNull($row->next_attempt_at);
        $attempts = $row->attempts;
        Artisan::call('qammaris:orders:deliver-webhooks');
        $this->assertSame($attempts, $row->fresh()->attempts, 'Failed rows are not retried automatically');

        // Super Admin sees it and resends: same event, delivered now.
        $status = 200;
        $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $page = $this->actingAs($owner)->get('/admin/integrations/orders')->assertOk()->assertSee('data-failed-event', false)->assertSee('Kirim ulang');
        $page->assertDontSee(self::HOOK_SECRET)->assertDontSee(self::API_SECRET)->assertSee('tunnel.example.test');
        $this->post('/admin/integrations/orders/outbox/'.$row->id.'/resend')->assertRedirect('/admin/integrations/orders');
        $this->assertNotNull($row->fresh()->delivered_at);
        $this->assertSame($row->event_id, $row->fresh()->event_id);

        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $this->actingAs($staff)->get('/admin/integrations/orders')->assertForbidden();
        $this->post('/admin/integrations/orders/outbox/'.$row->id.'/resend')->assertForbidden();
    }

    public function test_connection_errors_are_stored_without_urls_or_secrets(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to '.self::HOOK));
        $this->v2Order();
        $row = IntegrationOutbox::sole();
        $this->assertStringContainsString('ConnectionException', $row->last_error);
        $this->assertStringNotContainsString('tunnel.example.test', $row->last_error);
        $this->assertNull($row->last_status);
    }

    public function test_task_links_open_the_admin_pwa_until_the_app_is_live_then_the_app(): void
    {
        Http::fake();
        $order = $this->v2Order();
        $link = route('admin.orders.task', $order->public_id);

        config(['orders_api.app_task_links' => false]);
        $this->get($link)->assertRedirect(route('admin.orders.show', $order))->assertHeader('Cache-Control', 'no-store, private');
        config(['orders_api.app_task_links' => true]);
        $this->get($link)->assertRedirect('https://qammarisapp.com/orders/'.$order->public_id);

        $this->get(route('admin.orders.task', '01JABCDE2F3G4H5J6K7M8N9P0Q'))->assertRedirect(route('admin.orders.index'));
        $this->get(route('admin.orders.task', 'not-an-id'))->assertRedirect(route('admin.orders.index'));
        $this->assertSame([], $this->get($link)->headers->getCookies(), 'No session for the redirect');

        // The V2 group message carries the stable task link, never a bearer link.
        $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $this->actingAs($owner)->get(route('admin.orders.show', $order))->assertSee($link, false)->assertDontSee('/tugas-pesanan/', false);
    }

    public function test_super_admin_sees_app_claims_and_releases_a_stuck_one_with_a_reason(): void
    {
        Http::fake();
        $order = $this->v2Order();
        $order->claims()->create(['task' => 'preparation', 'holder_app_user_id' => '665f0c2a9b1e4a0012ab34cd', 'holder_display_name' => 'Andi', 'claimed_at' => now()]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $release = route('admin.orders.v2.claims.release', [$order, 'preparation']);

        $this->actingAs($staff)->get(route('admin.orders.show', $order))->assertSee('Dipegang di Qammaris App')->assertSee('Andi')->assertDontSee($release, false);
        $this->actingAs($staff)->post($release, ['revision' => $order->revision, 'reason' => 'Andi pulang'])->assertForbidden();

        $this->actingAs($owner)->get(route('admin.orders.show', $order))->assertSee($release, false);
        $this->actingAs($owner)->post($release, ['revision' => $order->revision])->assertSessionHasErrors('reason');
        $this->actingAs($owner)->post($release, ['revision' => $order->revision - 1, 'reason' => 'Andi pulang'])->assertSessionHas('order_notice.type', 'conflict');
        $this->assertSame(1, $order->claims()->count());

        $this->actingAs($owner)->post($release, ['revision' => $order->revision, 'reason' => 'Andi pulang'])->assertSessionHas('order_notice.type', 'success');
        $this->assertSame(0, $order->claims()->count());
        $event = $order->events()->reorder('id', 'desc')->first();
        $this->assertSame(['claim_released', $owner->id], [$event->kind, $event->actor_user_id]);
        $this->assertStringContainsString('Andi pulang', $event->note);
        // Once more to show the success notice in place, then the section disappears.
        $this->actingAs($owner)->get(route('admin.orders.show', $order))->assertSee('Klaim dilepas.')->assertSee('Tidak ada tugas yang dipegang.');
        $this->actingAs($owner)->get(route('admin.orders.show', $order))->assertDontSee('Dipegang di Qammaris App');
    }

    public function test_website_issues_wait_for_r42_while_the_api_is_enabled_so_every_order_stays_readable(): void
    {
        Http::fake();
        $order = $this->v2Order();
        $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $issue = ['revision' => $order->revision, '_section' => 'kendala', 'type' => 'stock_problem', 'note' => 'Stok kurang'];

        $this->actingAs($owner)->get(route('admin.orders.show', $order))->assertSee('kendala baru dicatat dari Qammaris App')->assertDontSee('+ Catat kendala');
        $this->actingAs($owner)->post(route('admin.orders.v2.issues', $order), $issue)->assertSessionHas('order_notice.type', 'error');
        $this->assertSame(0, $order->issues()->count());
        $this->assertMatchesApiSchema($this->orderApi('GET', '/orders')->assertOk(), 'OrderListPage');

        // r4.2: once the App reads opened_by_source, the Admin PWA may record issues while the API stays on.
        config(['orders_api.website_issues' => true]);
        $this->actingAs($owner)->get(route('admin.orders.show', $order))->assertSee('+ Catat kendala');
        $this->actingAs($owner)->post(route('admin.orders.v2.issues', $order), $issue)->assertSessionHas('order_notice.type', 'success');
        $this->assertSame(1, $order->issues()->count());
    }

    /** Contract r4.2: a seventh fixture with one Admin PWA issue, only while Website issues are enabled. */
    public function test_website_issue_fixture_exists_only_with_r42_website_issues_and_is_served_with_its_source(): void
    {
        Http::fake();
        User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'username' => 'pemilik-uji']);

        Artisan::call('qammaris:order-api:fixtures', ['--actor' => 'pemilik-uji']);
        $this->assertArrayNotHasKey('websiteIssue', json_decode(Artisan::output(), true)['fixtures']);

        config(['orders_api.website_issues' => true]);
        Artisan::call('qammaris:order-api:fixtures', ['--actor' => 'pemilik-uji']);
        $fixtures = json_decode(Artisan::output(), true)['fixtures'];
        $this->assertSame(['local', 'intercity', 'customerCourier', 'pickup', 'issue', 'costs', 'websiteIssue'], array_keys($fixtures));

        $order = $this->assertMatchesApiSchema($this->orderApi('GET', '/orders/'.$fixtures['websiteIssue']['id'])->assertOk(), 'Order');
        $this->assertSame(['active', 'has_issue', 1, null, 'website'], [$order->lifecycle, $order->queue, count($order->issues), $order->issues[0]->opened_by, $order->issues[0]->opened_by_source]);
        $this->assertSame([null, null, null, []], [$order->claims->preparation, $order->claims->courier_booking, $order->claims->handover, $order->costs]);

        // Re-run reuses it; the preflight checks it against its own expectation (exactly one Website issue).
        Artisan::call('qammaris:order-api:fixtures', ['--actor' => 'pemilik-uji']);
        $this->assertSame($fixtures['websiteIssue']['id'], json_decode(Artisan::output(), true)['fixtures']['websiteIssue']['id']);
        Artisan::call('qammaris:order-api:preflight', ['--json' => true]);
        $checks = collect(json_decode(Artisan::output(), true)['checks'])->keyBy('check');
        $this->assertSame('PASS', $checks['fixture:websiteIssue']['status'], $checks['fixture:websiteIssue']['detail']);
        $this->assertSame('PASS', $checks['fixture:issue']['status']);
    }

    public function test_fixtures_refuse_production_and_create_six_clean_rerunnable_orders(): void
    {
        Http::fake();
        $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'username' => 'pemilik-uji'])->fresh();

        $this->app['env'] = 'production';
        $this->assertSame(1, Artisan::call('qammaris:order-api:fixtures', ['--actor' => 'pemilik-uji']));
        $this->assertStringContainsString('production', Artisan::output());
        $this->assertSame(0, OnlineOrder::count());
        $this->app['env'] = 'testing';

        $this->assertSame(1, Artisan::call('qammaris:order-api:fixtures', ['--actor' => 'nobody']));
        $this->assertSame(0, Artisan::call('qammaris:order-api:fixtures', ['--actor' => 'pemilik-uji']));
        $first = json_decode(Artisan::output(), true)['fixtures'];
        $this->assertSame(['local', 'intercity', 'customerCourier', 'pickup', 'issue', 'costs'], array_keys($first));

        foreach ($first as $fixture) {
            $response = $this->orderApi('GET', '/orders/'.$fixture['id'])->assertOk();
            $order = $this->assertMatchesApiSchema($response, 'Order');
            $this->assertStringStartsWith('E2E ', $order->fulfillment->recipient->name);
            $this->assertSame('not_started', OnlineOrder::where('public_id', $fixture['id'])->value('preparation_status'), 'App preflight');
            $this->assertSame(['active', null, null, null, [], []], [$order->lifecycle, $order->claims->preparation, $order->claims->courier_booking, $order->claims->handover, $order->costs, $order->issues]);
        }
        $local = $this->assertMatchesApiSchema($this->orderApi('GET', '/orders/'.$first['local']['id']), 'Order');
        $this->assertCount(2, $local->items);
        $this->assertSame('customer', $this->assertMatchesApiSchema($this->orderApi('GET', '/orders/'.$first['customerCourier']['id']), 'Order')->fulfillment->courier->booking_responsibility);
        $this->assertSame('jnt', $this->assertMatchesApiSchema($this->orderApi('GET', '/orders/'.$first['intercity']['id']), 'Order')->fulfillment->jnt ? 'jnt' : 'none');
        $this->get('/products')->assertDontSee('E2E Parfum');

        // Rerun: clean fixtures are reused. A used one (claimed) is replaced.
        $this->orderApi('POST', '/orders/'.$first['local']['id'].'/claims', ['task' => 'preparation', 'actor' => $this->actor()], ['Idempotency-Key' => Str::random(24)])->assertOk();
        Artisan::call('qammaris:order-api:fixtures', ['--actor' => 'pemilik-uji']);
        $second = json_decode(Artisan::output(), true)['fixtures'];
        $this->assertNotSame($first['local']['id'], $second['local']['id']);
        $this->assertSame(array_slice(array_column($first, 'id'), 1), array_slice(array_column($second, 'id'), 1));

        // Reset: earlier fixtures are cancelled (not deleted), six new ones are created.
        $before = OnlineOrder::count();
        Artisan::call('qammaris:order-api:fixtures', ['--actor' => 'pemilik-uji', '--reset' => true]);
        $third = json_decode(Artisan::output(), true)['fixtures'];
        $this->assertSame($before + 6, OnlineOrder::count());
        $this->assertSame([], array_intersect(array_column($second, 'id'), array_column($third, 'id')));
        $this->assertSame('cancelled', OnlineOrder::where('public_id', $second['intercity']['id'])->value('lifecycle'));
        $this->assertSame('E2E reset', OnlineOrder::where('public_id', $second['intercity']['id'])->value('cancel_reason'));
    }
}
