<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOnlineOrder;
use App\Actions\Orders\OnlineOrderFulfillment;
use App\Actions\Orders\OnlineOrderWorkflow;
use App\Actions\Orders\RecordOnlineOrderMoney;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OrderActionNotAllowed;
use App\Exceptions\OrderRevisionConflict;
use App\Exceptions\OrderValidationFailed;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\User;
use App\Support\OnlineOrderState;
use App\Support\OrderActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ORD-02c slice 3: V2 state operations, packing quantities, J&T atomic handover, issues, cancel and cutover. */
class OnlineOrderV2OperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    private OnlineOrderFulfillment $ops;

    /** @var array<int, int> variant ID => quantity */
    private array $lines = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['orders.v2_enabled' => true]);
        $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER, 'name' => 'Andi Staf'])->fresh();
        $this->ops = app(OnlineOrderFulfillment::class);
        $brand = Brand::create(['name' => 'V2 Brand', 'is_active' => true]);
        $category = Category::create(['name' => 'EDP']);
        foreach ([['V2 Alpha', 150000, 2], ['V2 Beta', 100000, 1]] as [$name, $price, $quantity]) {
            $product = Product::create([
                'name' => $name, 'brand_id' => $brand->id, 'category_id' => $category->id, 'description' => 'Synthetic',
                'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []], 'gender' => 'Unisex', 'base_price' => $price,
                'publication_status' => 'published', 'is_active' => true, 'availability_source' => 'manual',
                'availability_status' => 'available', 'availability_checked_at' => now(),
            ]);
            $this->lines[$product->variants()->create(['volume' => 50, 'price' => $price, 'stock' => 0, 'is_active' => true])->id] = $quantity;
        }
    }

    public function test_cutover_new_orders_are_v2_and_customer_details_activate_them(): void
    {
        $waiting = $this->order(null);
        $this->assertSame([OnlineOrder::STATE_V2, 'awaiting_customer', 'awaiting_customer'], [$waiting->state_model, $waiting->lifecycle, $waiting->stage]);
        $this->assertNull(OnlineOrderState::queue($waiting));
        $this->assertCount(2, array_filter($waiting->items->pluck('line_id')->all(), fn ($id) => preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id)));

        $active = app(OnlineOrderWorkflow::class)->submitCustomerDetails($waiting, $this->details('local_delivery'));
        $this->assertSame(['active', 'details_received', 'store', 'unassigned'], [$active->lifecycle, $active->stage, $active->courier_booking_responsibility, $active->courier_status]);
        $this->assertSame('needs_handling', OnlineOrderState::queue($active));

        config(['orders.v2_enabled' => false]);
        $this->assertSame(OnlineOrder::STATE_LEGACY, $this->order('pickup')->state_model, 'Flag off: still the ORD-01 model');
    }

    public function test_packing_requires_every_line_with_the_exact_quantity(): void
    {
        $order = $this->ops->startPreparation($order = $this->order('local_delivery'), $order->revision, $this->actor());
        $this->assertSame(['preparing', 'preparing'], [$order->preparation_status, OnlineOrderState::queue($order)]);
        [$alpha, $beta] = $order->items->pluck('line_id')->all();

        foreach ([
            'short quantity' => [[['line_id' => $alpha, 'quantity' => 1], ['line_id' => $beta, 'quantity' => 1]], 'packed_items.0.quantity'],
            'missing line' => [[['line_id' => $alpha, 'quantity' => 2]], "packed_items.{$beta}"],
            'unknown line' => [[['line_id' => $alpha, 'quantity' => 2], ['line_id' => $beta, 'quantity' => 1], ['line_id' => '01JABCDEFX0000000000000009', 'quantity' => 1]], 'packed_items.2.line_id'],
            'duplicate line' => [[['line_id' => $alpha, 'quantity' => 2], ['line_id' => $alpha, 'quantity' => 2], ['line_id' => $beta, 'quantity' => 1]], 'packed_items.1.line_id'],
            'string quantity' => [[['line_id' => $alpha, 'quantity' => '2'], ['line_id' => $beta, 'quantity' => 1]], 'packed_items.0.quantity'],
        ] as $label => [$items, $field]) {
            try {
                $this->ops->pack($order, $order->revision, $this->actor(), $items);
                $this->fail("Packing accepted: {$label}");
            } catch (OrderValidationFailed $failed) {
                $this->assertArrayHasKey($field, $failed->fields, $label);
            }
        }
        $this->assertSame('preparing', $order->fresh()->preparation_status);

        $good = [['line_id' => $beta, 'quantity' => 1], ['line_id' => $alpha, 'quantity' => 2]];
        $packed = $this->ops->pack($order, $order->revision, $this->actor(), $good);
        $this->assertSame(['packed', 'ready'], [$packed->preparation_status, OnlineOrderState::queue($packed)]);
        $this->assertSame([['line_id' => $alpha, 'quantity' => 2], ['line_id' => $beta, 'quantity' => 1]], $packed->fresh()->packed_items);
        $this->assertSame('packed', $this->ops->lastEvent->kind);

        $replay = $this->ops->pack($packed, $packed->revision, $this->actor(), $good);
        $this->assertSame($packed->revision, $replay->revision, 'Same packing again is a no-op');
        $this->assertNull($this->ops->lastEvent);

        try {
            $this->ops->pack($order, $order->revision, $this->actor(), $good);
            $this->fail('Stale revision accepted');
        } catch (OrderRevisionConflict $conflict) {
            $this->assertSame($packed->revision, $conflict->currentRevision);
        }
    }

    public function test_local_delivery_flow_and_completion_when_paid_after_handover(): void
    {
        $order = $this->packed($this->order('local_delivery'));
        $this->assertRejected(fn () => $this->ops->handover($order, $order->revision, $this->actor(), 'customer_courier'), InvalidOrderTransition::class);

        $order = $this->ops->requestCourier($order, $order->revision, $this->actor(), 'maxim', 'requested', 'MX-1');
        $this->assertSame(['requested', 'awaiting_pickup'], [$order->courier_status, OnlineOrderState::queue($order)]);
        $order = $this->ops->requestCourier($order, $order->revision, $this->actor(), 'maxim', 'arrived');
        $this->assertRejected(fn () => $this->ops->requestCourier($order, $order->revision, $this->actor(), 'maxim', 'requested'), InvalidOrderTransition::class);

        $order = $this->ops->handover($order, $order->revision, $this->actor(), 'courier');
        $this->assertSame(['handed_over', 'courier', 'shipped', 'in_delivery', 'active'],
            [$order->handover_status, $order->handed_to, $order->stage, OnlineOrderState::queue($order), $order->lifecycle], 'Unpaid: handed over but not completed');

        $order = app(RecordOnlineOrderMoney::class)->recordPayment($order, $order->revision, $this->staff, '400000', 'qris', 'majoo');
        $this->assertSame(['paid', 'completed', 'completed', 'done'], [$order->payment_status, $order->lifecycle, $order->stage, OnlineOrderState::queue($order)]);
        $this->assertNotNull($order->closed_at);
        $this->assertContains('completed', $order->events()->pluck('kind')->all());

        $order = $this->ops->confirmDelivery($order, $order->revision, $this->actor(), 'customer');
        $this->assertSame(['delivered', 'customer'], [$order->delivery_status, $order->delivery_confirmed_by]);
    }

    public function test_jnt_steps_are_separate_tracking_is_optional_and_pickup_is_the_handover(): void
    {
        $order = $this->order('intercity');
        $this->assertSame('not_requested', $order->jnt_status);
        $order = $this->ops->recordJnt($order, $order->revision, $this->actor(), null, 'JX-0001');
        $this->assertSame(['JX-0001', 'not_requested'], [$order->tracking_number, $order->jnt_status], 'Resi can be added any time');

        $order = $this->packed($order);
        $order = $this->ops->recordJnt($order, $order->revision, $this->actor(), 'pickup_requested');
        $this->assertSame(['pickup_requested', 'awaiting_pickup'], [$order->jnt_status, OnlineOrderState::queue($order)]);
        $order = $this->ops->recordJnt($order, $order->revision, $this->actor(), 'qr_available');
        $this->assertRejected(fn () => $this->ops->recordJnt($order, $order->revision, $this->actor(), 'pickup_requested'), InvalidOrderTransition::class);
        $this->assertRejected(fn () => $this->ops->recordJnt($order, $order->revision, $this->actor(), 'lost'), OrderValidationFailed::class);

        $before = $order->revision;
        $order = $this->ops->recordJnt($order, $order->revision, $this->actor(), 'picked_up');
        $this->assertSame($before + 1, $order->revision, 'Picked up and handover share one revision');
        $this->assertSame(['picked_up', 'handed_over', 'jnt'], [$order->jnt_status, $order->handover_status, $order->handed_to]);
        $this->assertEquals($order->jnt_picked_up_at, $order->handed_over_at);
        $this->assertSame(1, $order->events()->where('kind', 'jnt_picked_up')->count());
        $this->assertSame(0, $order->events()->where('kind', 'handed_over')->count());

        // handover(jnt) is the same rule, and unpacked orders cannot be picked up.
        $other = $this->order('intercity');
        $this->assertRejected(fn () => $this->ops->handover($other, $other->revision, $this->actor(), 'jnt'), InvalidOrderTransition::class);
        $other = $this->packed($other);
        $other = $this->ops->handover($other, $other->revision, $this->actor(), 'jnt');
        $this->assertSame(['picked_up', 'jnt'], [$other->jnt_status, $other->handed_to]);
    }

    public function test_customer_booked_courier_and_pickup_orders(): void
    {
        $order = $this->ops->setCourierResponsibility($o = $this->order('local_delivery'), $o->revision, $this->actor(), 'customer');
        $this->assertSame(['customer', 'not_needed'], [$order->courier_booking_responsibility, $order->courier_status]);
        $order = $this->packed($order);
        $this->assertRejected(fn () => $this->ops->requestCourier($order, $order->revision, $this->actor(), 'grab'), InvalidOrderTransition::class);
        $this->assertRejected(fn () => $this->ops->handover($order, $order->revision, $this->actor(), 'courier'), InvalidOrderTransition::class);
        $order = $this->ops->handover($order, $order->revision, $this->actor(), 'customer_courier');
        $this->assertSame('customer_courier', $order->handed_to);

        $pickup = $this->packed($this->order('pickup'));
        $pickup = $this->ops->handover($pickup, $pickup->revision, $this->actor(), 'customer');
        $this->assertSame(['handed_over', 'done'], [$pickup->handover_status, OnlineOrderState::queue($pickup)]);
        $this->assertRejected(fn () => $this->ops->confirmDelivery($pickup, $pickup->revision, $this->actor()), InvalidOrderTransition::class);
    }

    public function test_open_issues_block_completion_and_only_the_opener_or_owner_resolves(): void
    {
        $order = $this->packed($this->order('pickup'));
        $app = OrderActor::app('665f0c2a9b1e4a0012ab34cd', 'Ikrar', 'employee');
        $line = $order->items->first()->line_id;
        $order = $this->ops->openIssue($order, null, $app, 'stock_problem', 'Stok hanya 1', $line, 1);
        $this->assertSame(['app', '665f0c2a9b1e4a0012ab34cd', 'Ikrar'], [$this->ops->lastEvent->actor_type, $this->ops->lastEvent->actor_app_user_id, $this->ops->lastEvent->actor_display_name]);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $this->ops->lastEvent->public_id);
        $this->assertRejected(fn () => $this->ops->openIssue($order, null, $app, 'stock_problem', 'Salah baris', '01JABCDEFX0000000000000009'), OrderValidationFailed::class);

        $order = $this->ops->handover($order, $order->revision, $this->actor(), 'customer');
        $order = app(RecordOnlineOrderMoney::class)->recordPayment($order, $order->revision, $this->staff, '400000', 'cash', 'proof_in_chat');
        $this->assertSame(['active', 'has_issue'], [$order->lifecycle, OnlineOrderState::queue($order, 1)], 'Open issue holds completion');

        $issue = $order->issues()->first();
        $this->assertRejected(fn () => $this->ops->resolveIssue($order, $issue->public_id, null, $this->actor()), OrderActionNotAllowed::class);
        $order = $this->ops->resolveIssue($order, $issue->public_id, null, OrderActor::app('6650aaaabbbbccccddddeeee', 'Owner', 'owner'), 'Diganti varian lain');
        $this->assertSame(['resolved', 'completed'], [$issue->fresh()->status, $order->lifecycle], 'Resolving the last issue completes a paid, handed-over order');
    }

    public function test_cancel_rules_never_assume_refunds(): void
    {
        $unpaid = $this->order('pickup');
        $unpaid = $this->ops->cancel($unpaid, $unpaid->revision, $this->actor(), 'Customer tidak jadi');
        $this->assertSame(['cancelled', 'unpaid', null], [$unpaid->lifecycle, $unpaid->payment_status, $unpaid->refund_status]);

        $money = app(RecordOnlineOrderMoney::class);
        $partly = $this->order('pickup');
        $partly = $money->recordPayment($partly, $partly->revision, $this->staff, '100000', 'transfer', 'proof_in_chat');
        $this->assertSame('unpaid', $partly->payment_status);
        $this->assertRejected(fn () => $this->ops->cancel($partly, $partly->revision, $this->actor(), 'Customer batal'), OrderActionNotAllowed::class, 'staff with money');
        $owner = OrderActor::user($this->owner);
        $this->assertRejected(fn () => $this->ops->cancel($partly, $partly->revision, $owner, 'Customer batal'), OrderValidationFailed::class, 'refund amount required');
        $this->assertRejected(fn () => $this->ops->cancel($partly, $partly->revision, $owner, 'Customer batal', '150000'), OrderValidationFailed::class, 'more than received');

        $partly = $this->ops->cancel($partly, $partly->revision, $owner, 'Customer batal', '100000');
        $this->assertSame(['cancelled', 'pending', 'refund_pending'], [$partly->lifecycle, $partly->refund_status, $partly->payment_status]);
        $partly = $money->recordRefund($partly, $partly->revision, $this->owner, '100000', 'transfer');
        $this->assertSame(['refunded', 'refunded'], [$partly->refund_status, $partly->payment_status]);

        $kept = $this->order('pickup');
        $kept = $money->recordPayment($kept, $kept->revision, $this->staff, '400000', 'cash', 'proof_in_chat');
        $kept = $this->ops->cancel($kept, $kept->revision, $owner, 'Diganti jadi deposit', '0');
        $this->assertSame(['not_required', 'paid'], [$kept->refund_status, $kept->payment_status]);
    }

    public function test_v2_operations_refuse_legacy_orders_and_inactive_states(): void
    {
        config(['orders.v2_enabled' => false]);
        $legacy = $this->order('pickup');
        $this->assertRejected(fn () => $this->ops->startPreparation($legacy, $legacy->revision, $this->actor()), InvalidOrderTransition::class);
        config(['orders.v2_enabled' => true]);

        $waiting = $this->order(null);
        $this->assertRejected(fn () => $this->ops->startPreparation($waiting, $waiting->revision, $this->actor()), InvalidOrderTransition::class);
        $customer = OrderActor::user(User::factory()->create(['role' => User::ROLE_CUSTOMER])->fresh());
        $active = $this->order('pickup');
        $this->assertRejected(fn () => $this->ops->startPreparation($active, $active->revision, $customer), OrderActionNotAllowed::class);
        $this->assertSame(1, $active->fresh()->revision);
    }

    private function actor(): OrderActor
    {
        return OrderActor::user($this->staff);
    }

    private function order(?string $fulfillment): OnlineOrder
    {
        [$order] = app(CreateOnlineOrder::class)->handle($this->owner, $this->lines, $fulfillment === null ? null : $this->details($fulfillment));

        return $order->fresh(['items']);
    }

    private function packed(OnlineOrder $order): OnlineOrder
    {
        $items = $order->items->map(fn ($item) => ['line_id' => $item->line_id, 'quantity' => $item->quantity])->all();

        return $this->ops->pack($order, $order->fresh()->revision, $this->actor(), $items)->fresh(['items']);
    }

    private function details(string $fulfillment): array
    {
        return [
            'customer_name' => 'Customer Sintetis', 'customer_phone' => '081234567890', 'fulfillment' => $fulfillment,
            'address' => $fulfillment === 'pickup' ? null : 'Jl. Contoh 1', 'postcode' => $fulfillment === 'intercity' ? '94111' : null,
            'packaging' => 'no_paperbag', 'customer_note' => null,
        ];
    }

    private function assertRejected(callable $attempt, string $class, string $label = ''): void
    {
        try {
            $attempt();
            $this->fail("Expected {$class} {$label}");
        } catch (\Throwable $error) {
            $this->assertInstanceOf($class, $error, $label.' '.$error->getMessage());
        }
    }
}
