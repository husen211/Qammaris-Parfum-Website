<?php

namespace Tests\Feature;

use App\Actions\Orders\OnlineOrderAdjustments;
use App\Actions\Orders\OnlineOrderFulfillment;
use App\Actions\Orders\RecordOnlineOrderMoney;
use App\Exceptions\OrderRevisionConflict;
use App\Exceptions\PaymentRequired;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderPayment;
use App\Models\User;
use App\Support\OnlineOrderState;
use App\Support\OrderActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsV2Orders;
use Tests\Concerns\SignsOrderApiRequests;
use Tests\TestCase;

/**
 * ORD-04 P0 (Owner UAT 2026-10-10): packing, courier booking and handover need a fully paid order, enforced in the
 * domain for the Admin PWA and the App API alike. Keep holds stock unpaid but never packs or ships.
 */
class OrderPaymentGateTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase, SignsOrderApiRequests;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableOrderApi();
        Storage::fake('local');
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
    }

    public function test_api_refuses_every_fulfillment_step_on_an_unpaid_order_with_payment_required(): void
    {
        foreach (['local_delivery', 'intercity', 'pickup'] as $fulfillment) {
            $order = $this->v2Order($fulfillment);
            foreach (['preparation', 'courier_booking', 'handover'] as $task) {
                $this->assertPaymentRequired($this->api($order, '/claims', ['task' => $task, 'actor' => $this->actor()]));
            }
            $this->assertSame([0, 'not_started', 'pending'], [$order->claims()->count(), $order->fresh()->preparation_status, $order->fresh()->handover_status], $fulfillment);
            $this->assertSame('needs_handling', OnlineOrderState::queue($order->fresh()), 'r4.2 queue unchanged; flags.unpaid is the badge');
        }
    }

    public function test_api_bypass_after_a_claim_is_refused_when_payment_is_reversed(): void
    {
        // Claims taken while paid, then the payment is corrected: every step waits for payment again.
        $order = $this->paidV2Order('intercity');
        foreach (['preparation', 'courier_booking', 'handover'] as $task) {
            $this->api($order, '/claims', ['task' => $task, 'actor' => $this->actor()])->assertOk();
        }
        $order = $this->reversePayment($order);
        $this->assertSame('unpaid', $order->payment_status);
        $items = $order->items->map(fn ($item) => ['line_id' => $item->line_id, 'quantity' => $item->quantity])->all();

        $this->assertPaymentRequired($this->api($order, '/preparation', ['status' => 'preparing', 'expected_revision' => $order->revision, 'actor' => $this->actor()]));
        $this->assertPaymentRequired($this->api($order, '/preparation', ['status' => 'packed', 'packed_items' => $items, 'expected_revision' => $order->revision, 'actor' => $this->actor()]));
        $this->assertPaymentRequired($this->api($order, '/jnt', ['status' => 'pickup_requested', 'expected_revision' => $order->revision, 'actor' => $this->actor()]));
        $this->assertPaymentRequired($this->api($order, '/jnt', ['status' => 'picked_up', 'expected_revision' => $order->revision, 'actor' => $this->actor()]));
        $this->assertPaymentRequired($this->api($order, '/handover', ['handed_to' => 'jnt', 'expected_revision' => $order->revision, 'actor' => $this->actor()]));
        $this->assertSame([$order->revision, 'not_started', 'not_requested', 'pending'],
            [$order->fresh()->revision, $order->fresh()->preparation_status, $order->fresh()->jnt_status, $order->fresh()->handover_status], 'Nothing changed');

        // Releasing a claim stays possible.
        $this->orderApi('DELETE', '/orders/'.$order->public_id.'/claims/handover', ['actor' => $this->actor()], ['Idempotency-Key' => Str::random(24)])->assertOk();
    }

    public function test_refund_pending_is_blocked_and_issues_costs_release_and_delivery_are_not_gated(): void
    {
        // Overpaid by a discount, refund decided but not paid out: refund_pending is not "paid".
        $order = $this->paidV2Order('local_delivery');
        $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $adjustments = app(OnlineOrderAdjustments::class);
        $order = $adjustments->request($order, $order->revision, $this->staff, '-10000', 'Diskon member terlambat');
        $order = $adjustments->approve($order, $order->revision, $owner, $order->adjustments()->sole()->id);
        $order = app(RecordOnlineOrderMoney::class)->decideRefund($order, $order->revision, $owner, '10000', 'Kembalikan selisih diskon');
        $this->assertSame(['active', 'refund_pending'], [$order->lifecycle, $order->payment_status]);
        $this->assertPaymentRequired($this->api($order, '/claims', ['task' => 'preparation', 'actor' => $this->actor()]));

        // Not gated on an unpaid order: issues and costs (App expense records).
        $unpaid = $this->v2Order('local_delivery');
        $this->api($unpaid, '/issues', ['type' => 'customer_unreachable', 'note' => 'Customer belum membalas', 'actor' => $this->actor()])->assertOk();
        $this->orderApi('PUT', '/orders/'.$unpaid->public_id.'/costs/exp_0a1b2c3d4e5f60718293a4b5c6d7e8f9', [
            'kind' => 'actual_shipping', 'status' => 'active', 'amount' => 20000,
            'funding' => [['source' => 'customer_cash_held', 'amount' => 15000], ['source' => 'staff_advance', 'amount' => 5000]],
            'reimbursement' => ['status' => 'awaiting_proof', 'amount' => 5000, 'proof' => 'pending', 'waiver' => null, 'updated_at' => '2026-10-10T03:10:00Z'],
            'source_version' => 1, 'actor' => $this->actor(),
        ], ['Idempotency-Key' => Str::random(24)])->assertOk();

        // Delivery confirmation after a handover stays possible even if the payment is later corrected.
        $paid = $this->paidV2Order('local_delivery');
        $ops = app(OnlineOrderFulfillment::class);
        $actor = OrderActor::user($this->staff);
        $paid = $ops->pack($paid, $paid->revision, $actor, $paid->items->map(fn ($i) => ['line_id' => $i->line_id, 'quantity' => $i->quantity])->all());
        $paid = $ops->requestCourier($paid, $paid->revision, $actor, 'maxim');
        $paid = $ops->handover($paid, $paid->revision, $actor, 'courier');
        $this->api($paid, '/delivery', ['expected_revision' => $paid->revision, 'actor' => $this->actor()])->assertOk();
        $this->assertSame('delivered', $paid->fresh()->delivery_status);
    }

    public function test_local_courier_and_handover_wait_for_payment_after_packing_and_a_short_payment_is_not_enough(): void
    {
        $ops = app(OnlineOrderFulfillment::class);
        $actor = OrderActor::user($this->staff);
        $order = $this->paidV2Order('local_delivery');
        $order = $ops->pack($order, $order->revision, $actor, $order->items->map(fn ($i) => ['line_id' => $i->line_id, 'quantity' => $i->quantity])->all());
        $order = $this->reversePayment($order);
        app(RecordOnlineOrderMoney::class)->recordPayment($order, $order->revision, $this->staff, '1000', 'transfer', null);
        $order = $order->fresh();
        $this->assertSame(['packed', 'unpaid'], [$order->preparation_status, $order->payment_status], 'Partly paid is unpaid');

        $this->assertThrows(fn () => $ops->requestCourier($order, $order->revision, $actor, 'maxim'), PaymentRequired::class);
        $this->assertThrows(fn () => $ops->handover($order, $order->revision, $actor, 'courier'), PaymentRequired::class);

        $order = $this->payInFull($order, $this->staff);
        $order = $ops->requestCourier($order, $order->revision, $actor, 'maxim');
        $order = $ops->handover($order, $order->revision, $actor, 'courier');
        $this->assertSame(['handed_over', 'completed'], [$order->handover_status, $order->lifecycle]);
    }

    public function test_admin_pwa_routes_cannot_bypass_the_rule(): void
    {
        $order = $this->v2Order('local_delivery');
        $this->actingAs($this->staff);
        $packed = $order->items->mapWithKeys(fn ($item) => [$item->line_id => $item->quantity])->all();
        foreach ([
            ['admin.orders.v2.preparation', []],
            ['admin.orders.v2.pack', ['packed' => $packed]],
            ['admin.orders.v2.courier', ['provider' => 'maxim', 'status' => 'requested']],
            ['admin.orders.v2.handover', ['handed_to' => 'courier']],
        ] as [$route, $data]) {
            $this->post(route($route, $order), ['revision' => $order->fresh()->revision] + $data)
                ->assertSessionHas('order_notice', fn ($notice) => $notice['type'] === 'error' && str_contains($notice['message'], 'belum lunas'));
        }
        $this->assertSame([1, 'not_started', 'unassigned', 'pending'], [$order->fresh()->revision, $order->fresh()->preparation_status, $order->fresh()->courier_status, $order->fresh()->handover_status]);
    }

    public function test_paid_orders_run_every_step_for_pickup_local_and_jnt(): void
    {
        $this->actingAs($this->staff);
        foreach (['pickup' => 'customer', 'local_delivery' => 'courier', 'intercity' => 'jnt'] as $fulfillment => $handedTo) {
            $order = $this->paidV2Order($fulfillment);
            $this->post(route('admin.orders.v2.pack', $order), ['revision' => $order->revision, 'packed' => $order->items->mapWithKeys(fn ($i) => [$i->line_id => $i->quantity])->all()])
                ->assertSessionHas('order_notice.type', 'success');
            if ($fulfillment === 'local_delivery') {
                $this->post(route('admin.orders.v2.courier', $order), ['revision' => $order->fresh()->revision, 'provider' => 'gosend', 'status' => 'requested'])->assertSessionHas('order_notice.type', 'success');
            }
            if ($fulfillment === 'intercity') {
                $this->post(route('admin.orders.v2.jnt', $order), ['revision' => $order->fresh()->revision, 'status' => 'pickup_requested'])->assertSessionHas('order_notice.type', 'success');
            }
            $route = $fulfillment === 'intercity' ? 'admin.orders.v2.jnt' : 'admin.orders.v2.handover';
            $this->post(route($route, $order), ['revision' => $order->fresh()->revision] + ($fulfillment === 'intercity' ? ['status' => 'picked_up'] : ['handed_to' => $handedTo]))
                ->assertSessionHas('order_notice.type', 'success');
            $this->assertSame(['handed_over', $handedTo, 'completed', 'done'], [$order->fresh()->handover_status, $order->fresh()->handed_to, $order->fresh()->lifecycle, OnlineOrderState::queue($order->fresh())], $fulfillment);
        }
    }

    public function test_keep_holds_stock_unpaid_but_never_packs_and_cancel_still_works(): void
    {
        $ops = app(OnlineOrderFulfillment::class);
        $actor = OrderActor::user($this->staff);
        $order = $this->v2Order('pickup');
        $order = $ops->startKeep($order, $order->revision, $actor);
        $order = $ops->confirmKeepStock($order, $order->revision, $actor);
        $this->assertSame(['active', 'unpaid'], [$order->keep_status, $order->payment_status]);
        $this->assertThrows(fn () => $ops->pack($order, $order->revision, $actor, $order->items->map(fn ($i) => ['line_id' => $i->line_id, 'quantity' => $i->quantity])->all()), PaymentRequired::class);
        $this->assertThrows(fn () => $ops->handover($order, $order->revision, $actor, 'customer'), PaymentRequired::class);

        $order = $ops->cancel($order, $order->revision, $actor, 'Customer tidak jadi');
        $this->assertSame(['cancelled', 'released'], [$order->lifecycle, $order->keep_status]);
    }

    public function test_two_staff_acting_at_once_get_one_change_and_one_conflict(): void
    {
        $ops = app(OnlineOrderFulfillment::class);
        $order = $this->paidV2Order('pickup');
        $other = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $items = $order->items->map(fn ($i) => ['line_id' => $i->line_id, 'quantity' => $i->quantity])->all();
        $ops->pack($order, $order->revision, OrderActor::user($this->staff), $items);
        $this->assertThrows(fn () => $ops->handover($order, $order->revision, OrderActor::user($other), 'customer'), OrderRevisionConflict::class);
        $this->assertSame('pending', $order->fresh()->handover_status);

        // Same for the website confirmation of a guest checkout.
        $draft = $this->v2Order('pickup');
        $draft->forceFill(['lifecycle' => 'draft', 'source' => 'website'])->save();
        $draft = $draft->fresh();
        $ops->confirmWebsiteOrder($draft, $draft->revision, OrderActor::user($this->staff));
        $this->assertThrows(fn () => $ops->confirmWebsiteOrder($draft, $draft->revision, OrderActor::user($other)), OrderRevisionConflict::class);
        $this->assertSame(1, $draft->events()->where('kind', 'website_confirmed')->count());
    }

    private function reversePayment(OnlineOrder $order): OnlineOrder
    {
        $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $entry = OnlineOrderPayment::where('online_order_id', $order->id)->where('type', 'payment')->latest('id')->firstOrFail();

        return app(RecordOnlineOrderMoney::class)->reverse($order->fresh(), $order->fresh()->revision, $owner, $entry->id, 'Transfer ternyata belum masuk')->fresh(['items']);
    }

    private function api(OnlineOrder $order, string $suffix, array $body): TestResponse
    {
        return $this->orderApi('POST', '/orders/'.$order->public_id.$suffix, $body, ['Idempotency-Key' => Str::random(24)]);
    }

    private function assertPaymentRequired(TestResponse $response): void
    {
        $error = $this->assertApiError($response, 409, 'invalid_transition');
        $this->assertSame('payment_required', $error->error->details->reason);
        $this->assertStringContainsString('belum lunas', $error->error->message);
    }
}
