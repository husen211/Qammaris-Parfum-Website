<?php

namespace Tests\Feature;

use App\Actions\Orders\RecordOnlineOrderMoney;
use App\Models\OnlineOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/** ORD-15 (Owner 2026-11-11): an order left unpaid for 48 hours after it could be paid is cancelled automatically. */
class UnpaidOrderExpiryTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['orders.simple_ux' => true, 'orders.unpaid_cancel_hours' => 48]);
    }

    public function test_an_unpaid_order_gets_a_deadline_and_is_cancelled_after_48_hours(): void
    {
        $order = $this->payableOrder();
        $this->sweep();
        $order->refresh();
        $this->assertTrue($order->payment_due_at->between(now()->addHours(47)->addMinutes(59), now()->addHours(48)->addMinute()));
        $revision = $order->revision;

        $this->get(route('orders.customer.show', $order->customer_token_encrypted))->assertOk()
            ->assertSee('data-payment-deadline', false)->assertSee('Bayar sebelum')->assertSee('WITA')->assertSee('pesanan dibatalkan otomatis');
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $this->actingAs($staff)->get(route('admin.orders.show', $order))->assertSee('data-admin-payment-deadline', false)->assertSee('Batal otomatis bila belum dibayar sampai');

        $this->travel(47)->hours();
        $this->sweep();
        $this->assertSame(['active', $revision], [$order->fresh()->lifecycle, $order->fresh()->revision]);

        $this->travel(2)->hours();
        $this->sweep();
        $order->refresh();
        $this->assertSame(['cancelled', 'Belum dibayar dalam 48 jam'], [$order->lifecycle, $order->cancel_reason]);
        $event = $order->events()->reorder('id', 'desc')->first();
        $this->assertSame(['cancelled', 'system', 'system', 'Sistem (otomatis)'], [$event->kind, $event->actor_type, $event->source, $event->actorLabel()]);
        $this->assertStringContainsString('Dibatalkan otomatis: belum dibayar dalam 48 jam', $event->note);
    }

    public function test_money_a_keep_or_an_unfinished_total_keeps_the_order(): void
    {
        $partly = $this->payableOrder();
        app(RecordOnlineOrderMoney::class)->recordPayment($partly, $partly->revision, $this->orderOwner, '10000', 'transfer', 'proof_in_chat');
        $kept = $this->payableOrder();
        $kept->forceFill(['keep_status' => 'active', 'keep_until' => now()->addDays(5)])->saveQuietly();
        // Ongkir not set yet: the total is not final, so it cannot be paid and has no deadline.
        $waiting = $this->v2Order('local_delivery');

        $this->sweep();
        $this->assertSame([null, null, null], [$partly->fresh()->payment_due_at, $kept->fresh()->payment_due_at, $waiting->fresh()->payment_due_at]);

        $this->travel(3)->days();
        $this->sweep();
        $this->assertSame(['active', 'active', 'active'], [$partly->fresh()->lifecycle, $kept->fresh()->lifecycle, $waiting->fresh()->lifecycle]);
    }

    public function test_paying_in_time_keeps_the_order_and_a_late_payment_under_the_lock_wins(): void
    {
        $paid = $this->payableOrder();
        $this->sweep();
        $this->travel(20)->hours();
        $this->payInFull($paid, $this->orderOwner);
        $this->travel(40)->hours();
        $this->sweep();
        $this->assertSame(['active', 'paid'], [$paid->fresh()->lifecycle, $paid->fresh()->payment_status]);
    }

    public function test_switched_off_with_zero_hours(): void
    {
        config(['orders.unpaid_cancel_hours' => 0]);
        $order = $this->payableOrder();
        $this->sweep();
        $this->travel(5)->days();
        $this->sweep();
        $this->assertSame(['active', null], [$order->fresh()->lifecycle, $order->fresh()->payment_due_at]);
    }

    private function payableOrder(): OnlineOrder
    {
        $order = $this->v2Order('local_delivery');
        $order->forceFill(['shipping_fee' => '15000.00', 'shipping_payer' => 'added_to_transfer'])->saveQuietly();
        $order = $order->fresh(['items']);
        $this->assertTrue($order->customerCanPay());

        return $order;
    }

    private function sweep(): void
    {
        $this->artisan('qammaris:orders:expire-unpaid')->assertSuccessful();
    }
}
