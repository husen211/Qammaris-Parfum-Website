<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOnlineOrder;
use App\Actions\Orders\OnlineOrderWorkflow;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\User;
use App\Support\OnlineOrderState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** ORD-02c slice 1: separate state dimensions, ORD-01 backfill/sync, and the derived queue (contract r4.1 §7, §9). */
class OnlineOrderStateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private OnlineOrderWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $this->workflow = app(OnlineOrderWorkflow::class);
    }

    public function test_new_orders_get_a_public_ulid_and_whatsapp_source_and_start_awaiting_customer(): void
    {
        $order = $this->order(null);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $order->public_id);
        $this->assertNotSame($order->public_id, $this->order(null)->public_id);
        $this->assertSame('whatsapp', $order->source);
        $this->assertState($order, ['lifecycle' => 'awaiting_customer', 'payment_status' => 'unpaid', 'preparation_status' => 'not_started', 'handover_status' => 'pending']);
        $this->assertNull(OnlineOrderState::queue($order));
    }

    public function test_local_delivery_steps_map_to_separate_dimensions_and_queue(): void
    {
        $order = $this->order('local_delivery');
        $this->assertState($order, ['lifecycle' => 'active', 'payment_status' => 'unpaid', 'courier_booking_responsibility' => 'store',
            'courier_status' => 'unassigned', 'jnt_status' => null, 'handover_status' => 'pending', 'delivery_status' => 'unconfirmed']);
        $this->assertSame('needs_handling', OnlineOrderState::queue($order));
        $this->assertTrue(OnlineOrderState::flags($order)['unpaid']);

        $order = $this->step($order, 'paid', ['payment_method' => 'qris']);
        $this->assertState($order, ['payment_status' => 'paid', 'preparation_status' => 'not_started']);
        $this->assertNotNull($order->payment_confirmed_at);
        $this->assertFalse(OnlineOrderState::flags($order)['unpaid']);

        $order = $this->step($order, 'shipped', ['courier' => 'maxim']);
        $this->assertState($order, ['preparation_status' => 'packed', 'courier_provider' => 'maxim', 'courier_status' => 'arrived',
            'handover_status' => 'handed_over', 'handed_to' => 'courier', 'delivery_status' => 'unconfirmed', 'lifecycle' => 'active']);
        $this->assertNotNull($order->handed_over_at);
        $this->assertSame('in_delivery', OnlineOrderState::queue($order));

        $order = $this->step($order, 'completed');
        $this->assertState($order, ['lifecycle' => 'completed', 'delivery_status' => 'delivered']);
        $this->assertNotNull($order->delivered_at);
        $this->assertSame('done', OnlineOrderState::queue($order));
    }

    public function test_intercity_jnt_and_pickup_orders(): void
    {
        $jnt = $this->step($this->step($this->order('intercity'), 'paid', ['payment_method' => 'transfer']), 'shipped', ['courier' => 'jnt', 'tracking_number' => 'JX123']);
        $this->assertState($jnt, ['jnt_status' => 'picked_up', 'handover_status' => 'handed_over', 'handed_to' => 'jnt', 'courier_status' => 'not_needed', 'courier_provider' => null]);
        $this->assertEquals($jnt->handed_over_at, $jnt->jnt_picked_up_at, 'J&T pickup and handover are one moment (R5)');

        $pickup = $this->step($this->order('pickup'), 'paid', ['payment_method' => 'cash']);
        $this->assertState($pickup, ['courier_status' => 'not_needed', 'jnt_status' => null, 'handover_status' => 'pending']);
        $pickup = $this->step($pickup, 'completed');
        $this->assertState($pickup, ['lifecycle' => 'completed', 'handover_status' => 'handed_over', 'handed_to' => 'customer', 'delivery_status' => 'unconfirmed']);
        $this->assertSame('done', OnlineOrderState::queue($pickup));
    }

    public function test_revert_clears_later_dimensions_and_their_timestamps(): void
    {
        $order = $this->step($this->step($this->order('local_delivery'), 'paid', ['payment_method' => 'qris']), 'shipped', ['courier' => 'grab']);
        $order = $this->workflow->revert($order, 'shipped', $this->owner);
        $this->assertState($order, ['handover_status' => 'pending', 'handed_to' => null, 'preparation_status' => 'not_started', 'courier_status' => 'unassigned']);
        $this->assertNull($order->handed_over_at);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_cancel_after_payment_needs_reconciliation_and_never_assumes_a_refund_is_owed(): void
    {
        $order = $this->step($this->order('local_delivery'), 'paid', ['payment_method' => 'transfer']);
        $order = $this->workflow->cancel($order, 'Customer batal', $this->owner);
        $this->assertState($order, ['lifecycle' => 'cancelled', 'payment_status' => 'paid', 'refund_status' => 'needs_reconciliation']);
        $this->assertFalse(OnlineOrderState::flags($order)['open_refund'], 'Unknown refund history is not an open refund');
        $this->assertNull(OnlineOrderState::queue($order));

        $order = $this->workflow->revert($order, 'cancelled', $this->owner);
        $this->assertState($order, ['lifecycle' => 'active', 'payment_status' => 'paid', 'refund_status' => null]);

        $unpaid = $this->workflow->cancel($this->order('pickup'), 'Tidak jadi', $this->owner);
        $this->assertState($unpaid, ['lifecycle' => 'cancelled', 'payment_status' => 'unpaid', 'refund_status' => null]);
    }

    public function test_pending_staff_reimbursement_does_not_block_completion_but_refunds_and_issues_do(): void
    {
        $order = $this->step($this->step($this->order('local_delivery'), 'paid', ['payment_method' => 'qris']), 'shipped', ['courier' => 'maxim']);
        $order = $this->workflow->recordStaffAdvance($order, '20000', 'Andi');
        $this->assertTrue(OnlineOrderState::flags($order)['open_reimbursement']);
        $this->assertTrue(OnlineOrderState::canComplete($order), 'Owner R8: an open reimbursement never holds back completion');
        $this->assertFalse(OnlineOrderState::canComplete($order, openIssues: 1));
        $this->assertSame('has_issue', OnlineOrderState::queue($order, openIssues: 1));

        $order->payment_status = 'refund_pending';
        $this->assertFalse(OnlineOrderState::canComplete($order));
        $this->assertFalse(OnlineOrderState::canComplete($this->order('pickup')), 'Not handed over or paid yet');
    }

    public function test_queue_rules_match_the_contract_order(): void
    {
        $order = fn (array $state) => (new OnlineOrder)->forceFill($state + [
            'lifecycle' => 'active', 'fulfillment' => 'local_delivery', 'preparation_status' => 'not_started',
            'courier_status' => 'unassigned', 'jnt_status' => null, 'handover_status' => 'pending', 'delivery_status' => 'unconfirmed',
        ]);
        $cases = [
            [['lifecycle' => 'draft'], 0, false, null],
            [['lifecycle' => 'cancelled'], 1, false, null],
            [['lifecycle' => 'completed'], 1, false, 'has_issue'],
            [['lifecycle' => 'completed'], 0, false, 'done'],
            [['fulfillment' => 'pickup', 'handover_status' => 'handed_over'], 0, false, 'done'],
            [['handover_status' => 'handed_over', 'delivery_status' => 'delivered'], 0, false, 'done'],
            [['handover_status' => 'handed_over'], 0, false, 'in_delivery'],
            [['preparation_status' => 'packed', 'courier_status' => 'requested'], 0, false, 'awaiting_pickup'],
            [['fulfillment' => 'intercity', 'preparation_status' => 'packed', 'courier_status' => 'not_needed', 'jnt_status' => 'qr_available'], 0, false, 'awaiting_pickup'],
            [['preparation_status' => 'packed'], 0, false, 'ready'],
            [['preparation_status' => 'preparing'], 0, false, 'preparing'],
            [[], 0, true, 'preparing'],
            [[], 0, false, 'needs_handling'],
        ];
        foreach ($cases as $i => [$state, $issues, $claimed, $expected]) {
            $this->assertSame($expected, OnlineOrderState::queue($order($state), $issues, $claimed), "case #{$i}");
        }
        $this->assertEqualsCanonicalizing(OnlineOrderState::QUEUES, array_values(array_unique(array_filter(array_column($cases, 3)))));
    }

    public function test_backfill_rebuilds_state_from_stage_and_event_times_and_is_idempotent(): void
    {
        $shipped = $this->step($this->step($this->order('local_delivery'), 'paid', ['payment_method' => 'qris']), 'shipped', ['courier' => 'maxim']);
        $cancelled = $this->workflow->cancel($this->step($this->order('intercity'), 'paid', ['payment_method' => 'transfer']), 'Batal', $this->owner);
        $waiting = $this->order(null);
        $expected = OnlineOrder::orderBy('id')->get()->mapWithKeys(fn ($o) => [$o->id => $this->stateOf($o)])->all();

        // Simulate rows written before ORD-02c: the new columns are empty.
        DB::table('online_orders')->update(array_fill_keys(array_keys($this->stateOf($shipped)), null));
        DB::table('online_order_events')->where('online_order_id', $shipped->id)->where('stage', 'shipped')->update(['created_at' => '2026-10-01 05:06:07']);

        $migration = require database_path('migrations/2026_10_08_300001_add_order_state_dimensions_to_online_orders.php');
        $migration->backfill();
        $migration->backfill();

        foreach ([$shipped, $cancelled, $waiting] as $order) {
            $fresh = $order->fresh();
            $this->assertEquals(array_diff_key($expected[$order->id], array_flip(['payment_confirmed_at', 'handed_over_at', 'jnt_picked_up_at', 'delivered_at'])),
                array_diff_key($this->stateOf($fresh), array_flip(['payment_confirmed_at', 'handed_over_at', 'jnt_picked_up_at', 'delivered_at'])), "order {$order->id}");
        }
        $this->assertSame('2026-10-01 05:06:07', $shipped->fresh()->handed_over_at->format('Y-m-d H:i:s'), 'Handover time comes from the shipped event');
        $this->assertSame(['paid', 'needs_reconciliation'], [$cancelled->fresh()->payment_status, $cancelled->fresh()->refund_status],
            'A paid order cancelled under ORD-01 needs reconciliation; no refund debt is assumed');
    }

    private function stateOf(OnlineOrder $order): array
    {
        return $order->only(['lifecycle', 'payment_status', 'payment_confirmed_at', 'preparation_status', 'courier_booking_responsibility',
            'courier_provider', 'courier_status', 'jnt_status', 'jnt_picked_up_at', 'handover_status', 'handed_to', 'handed_over_at',
            'delivery_status', 'delivered_at']);
    }

    private function assertState(OnlineOrder $order, array $expected): void
    {
        $this->assertSame($expected, $order->fresh()->only(array_keys($expected)));
    }

    private function order(?string $fulfillment): OnlineOrder
    {
        $customer = $fulfillment === null ? null : [
            'customer_name' => 'Customer Sintetis', 'customer_phone' => '081234567890', 'fulfillment' => $fulfillment,
            'address' => $fulfillment === 'pickup' ? null : 'Jl. Contoh 1', 'postcode' => $fulfillment === 'intercity' ? '94111' : null,
            'packaging' => 'no_paperbag', 'customer_note' => null,
        ];

        return app(CreateOnlineOrder::class)->handle($this->owner, [$this->variantId() => 1], $customer)[0];
    }

    private function step(OnlineOrder $order, string $to, array $extra = []): OnlineOrder
    {
        $order = $order->fresh();

        return $this->workflow->advance($order, $order->stage, $to, 'admin', $this->owner, extra: $extra);
    }

    private function variantId(): int
    {
        static $cache = [];
        $key = spl_object_id($this);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $brand = Brand::create(['name' => 'State Brand', 'is_active' => true]);
        $category = Category::create(['name' => 'EDP']);
        $product = Product::create([
            'name' => 'State Synthetic', 'brand_id' => $brand->id, 'category_id' => $category->id, 'description' => 'Synthetic',
            'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []], 'gender' => 'Unisex', 'base_price' => 200000,
            'publication_status' => 'published', 'is_active' => true, 'availability_source' => 'manual',
            'availability_status' => 'available', 'availability_checked_at' => now(),
        ]);

        return $cache[$key] = $product->variants()->create(['volume' => 50, 'price' => 200000, 'stock' => 0, 'is_active' => true])->id;
    }
}
