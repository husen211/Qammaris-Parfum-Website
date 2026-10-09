<?php

namespace Tests\Feature;

use App\Actions\Orders\OnlineOrderFulfillment;
use App\Models\OnlineOrder;
use App\Models\User;
use App\Support\OrderActor;
use App\Support\OrderApi\OrderApiSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsV2Orders;
use Tests\Concerns\SignsOrderApiRequests;
use Tests\TestCase;

/** ORD-02e slice A: authentication, error envelope, reads and data minimisation against the contract (r4.1 baseline, r4.2 draft additions). */
class OrderApiReadTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase, SignsOrderApiRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableOrderApi();
    }

    /** Contract r4.2: Website-opened issues are served with opened_by=null; the admin stays in the Website audit. */
    public function test_issues_from_the_website_and_the_app_are_served_with_their_source(): void
    {
        $order = $this->v2Order();
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'name' => 'Admin Website'])->fresh();
        config(['orders_api.website_issues' => true]);
        app(OnlineOrderFulfillment::class)->openIssue($order->fresh(), null, OrderActor::user($admin), 'stock_problem', 'Stok tester tinggal 1');
        app(OnlineOrderFulfillment::class)->openIssue($order->fresh(), null, OrderActor::app('665f0c2a9b1e4a0012ab34cd', 'Andi', 'employee'), 'courier_problem', 'Driver batal');

        $read = $this->assertMatchesApiSchema($this->orderApi('GET', '/orders/'.$order->public_id)->assertOk(), 'Order');
        $bySource = collect($read->issues)->keyBy('opened_by_source');
        $this->assertNull($bySource['website']->opened_by);
        $this->assertSame('665f0c2a9b1e4a0012ab34cd', $bySource['app']->opened_by->app_user_id);
        $this->assertStringNotContainsString('Admin Website', $this->orderApi('GET', '/orders/'.$order->public_id)->getContent());
        $this->assertSame([$admin->id, 'Admin Website'], [$order->issues()->whereNull('opened_by_app_user_id')->sole()->opened_by_user_id, $order->issues()->whereNull('opened_by_app_user_id')->sole()->opened_by_name]);
    }

    /** Contract r4.2: one order that cannot be serialised is reported in `unavailable`, never fails the page. */
    public function test_an_unreadable_order_is_listed_as_unavailable_and_its_detail_answers_serialization_failed(): void
    {
        $good = $this->v2Order();
        $broken = $this->v2Order();
        $this->makeUnreadable($broken);

        $page = $this->assertMatchesApiSchema($this->orderApi('GET', '/orders')->assertOk(), 'OrderListPage');
        $this->assertSame([$good->public_id], array_column($page->data, 'id'));
        $this->assertEquals([(object) ['id' => $broken->public_id, 'reason_code' => 'serialization_failed']], $page->unavailable);

        $this->assertApiError($this->orderApi('GET', '/orders/'.$broken->public_id), 500, 'serialization_failed');
        $this->assertObjectNotHasProperty('unavailable', $this->assertMatchesApiSchema($this->orderApi('GET', '/orders?lifecycle=completed')->assertOk(), 'OrderListPage'));
    }

    public function test_authentication_failures_use_the_contract_codes_in_order(): void
    {
        $order = $this->v2Order();
        $path = '/orders/'.$order->public_id;

        config(['orders_api.enabled' => false]);
        $this->assertApiError($this->orderApi('GET', $path), 503, 'unavailable');
        $this->enableOrderApi();

        $this->assertApiError($this->orderApi('GET', $path, headers: ['X-Qammaris-Client' => 'someone-else']), 401, 'unknown_client');
        $this->assertApiError($this->orderApi('GET', $path, timestamp: now()->timestamp - 301), 401, 'stale_timestamp');
        $this->assertApiError($this->orderApi('GET', $path, timestamp: now()->timestamp + 301), 401, 'stale_timestamp');
        $this->assertApiError($this->orderApi('GET', $path, secret: 'wrong-secret-value-xxxxxxxxxxxx'), 401, 'invalid_signature');
        $this->orderApi('GET', $path, timestamp: now()->timestamp - 300)->assertOk();

        // Tampering with the query after signing breaks the signature.
        $signed = $this->orderApi('GET', '/orders?limit=5');
        $signed->assertOk();
        $timestamp = now()->timestamp;
        $signature = OrderApiSignature::sign(self::API_SECRET, $timestamp, 'GET', self::API.'/orders?limit=5', '');
        $this->assertApiError($this->call('GET', self::API.'/orders?limit=6', [], [], [], [
            'HTTP_X_QAMMARIS_CLIENT' => 'qammaris-app-test', 'HTTP_X_QAMMARIS_TIMESTAMP' => (string) $timestamp, 'HTTP_X_QAMMARIS_SIGNATURE' => $signature,
        ]), 401, 'invalid_signature');
    }

    public function test_secret_rotation_accepts_current_and_previous_until_previous_is_cleared(): void
    {
        $path = '/orders/'.$this->v2Order()->public_id;
        $this->enableOrderApi(['orders_api.secret' => 'new-secret-value-0000000000000000', 'orders_api.secret_previous' => self::API_SECRET]);
        $this->orderApi('GET', $path, secret: 'new-secret-value-0000000000000000')->assertOk();
        $this->orderApi('GET', $path)->assertOk();

        config(['orders_api.secret_previous' => '']);
        $this->assertApiError($this->orderApi('GET', $path), 401, 'invalid_signature');
    }

    public function test_the_published_test_vector_verifies_on_the_server(): void
    {
        // Contract §3 GET vector: same secret, timestamp, path and signature the App computes.
        $this->enableOrderApi(['orders_api.secret' => 'example-secret-not-real-0123456789']);
        $this->travelTo(Carbon::createFromTimestamp(1791427500));
        $response = $this->call('GET', self::API.'/orders?updated_since=2026-10-08T00%3A00%3A00Z&limit=50', [], [], [], [
            'HTTP_X_QAMMARIS_CLIENT' => 'qammaris-app-test', 'HTTP_X_QAMMARIS_TIMESTAMP' => '1791427500',
            'HTTP_X_QAMMARIS_SIGNATURE' => 'd222078e2b81f8903c97f732dc99c73c72e6823cd3ae0b0efc1647d5999fd8d9',
        ]);
        $response->assertOk();
        $this->assertMatchesApiSchema($response, 'OrderListPage');
    }

    public function test_rate_limit(): void
    {
        $path = '/orders/'.$this->v2Order()->public_id;
        config(['orders_api.rate_limit_per_minute' => 2]);
        $this->orderApi('GET', $path)->assertOk();
        $this->orderApi('GET', $path)->assertOk();
        $limited = $this->assertApiError($this->orderApi('GET', $path), 429, 'rate_limited');
        $this->assertNotNull($this->orderApi('GET', $path)->headers->get('Retry-After'));

        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $limited->request_id);
    }

    public function test_order_detail_matches_the_schema_and_minimises_recipient_data(): void
    {
        foreach (['local_delivery' => ['address' => 'Jl. Sintetis 1', 'postcode' => null], 'intercity' => ['address' => 'Jl. Sintetis 1', 'postcode' => '90111'], 'pickup' => ['address' => null, 'postcode' => null]] as $type => $expected) {
            $order = $this->v2Order($type);
            $order->forceFill(['location_url' => 'https://maps.app.goo.gl/sintetis'])->save();
            $response = $this->orderApi('GET', '/orders/'.$order->public_id)->assertOk();
            $this->assertSame('1', $response->headers->get('X-Qammaris-Api-Version'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $data = $this->assertMatchesApiSchema($response, 'Order');
            $recipient = $data->fulfillment->recipient;
            $this->assertSame(['E2E Siti Rahma', '081234567890', $expected['address'], $expected['postcode']], [$recipient->name, $recipient->phone, $recipient->address, $recipient->postcode], $type);
            $this->assertSame($type === 'local_delivery' ? 'https://maps.app.goo.gl/sintetis' : null, $recipient->location_url, $type);
            $this->assertSame('https://qammarisapp.com/orders/'.$order->public_id, $data->links->app_task);
            $this->assertSame([$order->public_id, $order->code, 'active', 'needs_handling', 400000], [$data->id, $data->number, $data->lifecycle, $data->queue, $data->totals->total]);
        }

        // Closed for more than 30 days: no recipient data at all.
        $closed = $this->v2Order('pickup');
        DB::table('online_orders')->where('id', $closed->id)->update(['lifecycle' => 'cancelled', 'closed_at' => now()->subDays(31)]);
        $this->assertNull($this->assertMatchesApiSchema($this->orderApi('GET', '/orders/'.$closed->public_id), 'Order')->fulfillment->recipient);
        DB::table('online_orders')->where('id', $closed->id)->update(['closed_at' => now()->subDays(29)]);
        $this->assertNotNull($this->assertMatchesApiSchema($this->orderApi('GET', '/orders/'.$closed->public_id), 'Order')->fulfillment->recipient);

        $waiting = $this->v2Order(null);
        $this->assertNull($this->assertMatchesApiSchema($this->orderApi('GET', '/orders/'.$waiting->public_id), 'Order')->fulfillment->recipient);
    }

    public function test_unknown_and_legacy_orders_are_not_found(): void
    {
        $legacy = $this->v2Order('pickup');
        DB::table('online_orders')->where('id', $legacy->id)->update(['state_model' => OnlineOrder::STATE_LEGACY]);

        $this->assertApiError($this->orderApi('GET', '/orders/'.$legacy->public_id), 404, 'order_not_found');
        $this->assertApiError($this->orderApi('GET', '/orders/01JABCDE2F3G4H5J6K7M8N9P0Q'), 404, 'order_not_found');
        $this->assertApiError($this->orderApi('GET', '/orders/01JABCDE2F3G4H5J6K7M8N9P0Q/jnt/qr'), 404, 'order_not_found');
    }

    public function test_order_list_pages_with_an_opaque_cursor_and_never_includes_contact_data(): void
    {
        $orders = collect(range(1, 5))->map(fn ($i) => $this->v2Order($i % 2 ? 'local_delivery' : 'intercity'));
        foreach ($orders as $index => $order) {
            DB::table('online_orders')->where('id', $order->id)->update(['updated_at' => Carbon::parse('2026-10-08 10:00:00')->addMinutes($index < 2 ? 0 : $index)]);
        }

        $seen = [];
        $cursor = null;
        do {
            $response = $this->orderApi('GET', '/orders?limit=2'.($cursor ? '&cursor='.$cursor : ''))->assertOk();
            $page = $this->assertMatchesApiSchema($response, 'OrderListPage');
            $this->assertStringNotContainsString('081234567890', $response->getContent());
            $this->assertStringNotContainsString('Jl. Sintetis', $response->getContent());
            $seen = array_merge($seen, array_map(fn ($row) => $row->id, $page->data));
            $cursor = $page->next_cursor;
            $this->assertSame($cursor !== null, $page->has_more);
        } while ($cursor);
        $this->assertSame($orders->pluck('public_id')->all(), $seen, 'Ordered by (updated_at, id), each order once, ties included');

        $summary = $this->assertMatchesApiSchema($this->orderApi('GET', '/orders?limit=1'), 'OrderListPage')->data[0];
        $this->assertSame(['E2E R.', 3, 'palu'], [$summary->recipient_display, $summary->item_count, $summary->area]);

        $since = $this->assertMatchesApiSchema($this->orderApi('GET', '/orders?updated_since=2026-10-08T10%3A03%3A00Z'), 'OrderListPage');
        $this->assertSame($orders->slice(3)->pluck('public_id')->values()->all(), array_map(fn ($row) => $row->id, $since->data));

        DB::table('online_orders')->where('id', $orders[4]->id)->update(['preparation_status' => 'packed']);
        $ready = $this->assertMatchesApiSchema($this->orderApi('GET', '/orders?queue=ready&limit=1'), 'OrderListPage');
        $this->assertSame([$orders[4]->public_id], array_map(fn ($row) => $row->id, $ready->data));
        $this->assertFalse($ready->has_more);

        foreach (['limit=0', 'limit=101', 'queue=lost', 'lifecycle=open', 'cursor=bad!', 'updated_since=yesterday'] as $query) {
            $this->assertApiError($this->orderApi('GET', '/orders?'.$query), 422, 'validation_failed');
        }
    }
}
