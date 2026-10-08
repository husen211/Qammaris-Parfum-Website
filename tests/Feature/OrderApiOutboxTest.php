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
        $this->assertSame(['local', 'intercity', 'customerCourier', 'pickup', 'issue', 'costs'], array_column($first, 'scenario'));

        foreach ($first as $fixture) {
            $response = $this->orderApi('GET', '/orders/'.$fixture['id'])->assertOk();
            $order = $this->assertMatchesApiSchema($response, 'Order');
            $this->assertStringStartsWith('E2E ', $order->fulfillment->recipient->name);
            $this->assertSame(['active', null, null, null, [], []], [$order->lifecycle, $order->claims->preparation, $order->claims->courier_booking, $order->claims->handover, $order->costs, $order->issues]);
        }
        $local = $this->assertMatchesApiSchema($this->orderApi('GET', '/orders/'.$first[0]['id']), 'Order');
        $this->assertCount(2, $local->items);
        $this->assertSame('customer', $this->assertMatchesApiSchema($this->orderApi('GET', '/orders/'.$first[2]['id']), 'Order')->fulfillment->courier->booking_responsibility);
        $this->assertSame('jnt', $this->assertMatchesApiSchema($this->orderApi('GET', '/orders/'.$first[1]['id']), 'Order')->fulfillment->jnt ? 'jnt' : 'none');
        $this->get('/products')->assertDontSee('E2E Parfum');

        // Rerun: clean fixtures are reused. A used one (claimed) is replaced.
        $this->orderApi('POST', '/orders/'.$first[0]['id'].'/claims', ['task' => 'preparation', 'actor' => $this->actor()], ['Idempotency-Key' => Str::random(24)])->assertOk();
        Artisan::call('qammaris:order-api:fixtures', ['--actor' => 'pemilik-uji']);
        $second = json_decode(Artisan::output(), true)['fixtures'];
        $this->assertNotSame($first[0]['id'], $second[0]['id']);
        $this->assertSame(array_slice(array_column($first, 'id'), 1), array_slice(array_column($second, 'id'), 1));

        // Reset: earlier fixtures are cancelled (not deleted), six new ones are created.
        $before = OnlineOrder::count();
        Artisan::call('qammaris:order-api:fixtures', ['--actor' => 'pemilik-uji', '--reset' => true]);
        $third = json_decode(Artisan::output(), true)['fixtures'];
        $this->assertSame($before + 6, OnlineOrder::count());
        $this->assertSame([], array_intersect(array_column($second, 'id'), array_column($third, 'id')));
        $this->assertSame('cancelled', OnlineOrder::where('public_id', $second[1]['id'])->value('lifecycle'));
        $this->assertSame('E2E reset', OnlineOrder::where('public_id', $second[1]['id'])->value('cancel_reason'));
    }
}
