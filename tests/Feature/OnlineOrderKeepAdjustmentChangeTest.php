<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOnlineOrder;
use App\Actions\Orders\OnlineOrderAdjustments;
use App\Actions\Orders\OnlineOrderChangeRequests;
use App\Actions\Orders\OnlineOrderFulfillment;
use App\Actions\Orders\RecordOnlineOrderMoney;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OrderActionNotAllowed;
use App\Exceptions\OrderValidationFailed;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\User;
use App\Support\OrderActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** ORD-02c slice 5: keep (D9), price adjustments with Super Admin approval, customer change requests (D5). */
class OnlineOrderKeepAdjustmentChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    private OnlineOrderFulfillment $ops;

    private int $variant;

    protected function setUp(): void
    {
        parent::setUp();
        config(['orders.v2_enabled' => true]);
        $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER, 'name' => 'Rina Staf'])->fresh();
        $this->ops = app(OnlineOrderFulfillment::class);
        $brand = Brand::create(['name' => 'Keep Brand', 'is_active' => true]);
        $category = Category::create(['name' => 'EDP']);
        $product = Product::create([
            'name' => 'Keep Synthetic', 'brand_id' => $brand->id, 'category_id' => $category->id, 'description' => 'Synthetic',
            'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []], 'gender' => 'Unisex', 'base_price' => 200000,
            'publication_status' => 'published', 'is_active' => true, 'availability_source' => 'manual',
            'availability_status' => 'available', 'availability_checked_at' => now(),
        ]);
        $this->variant = $product->variants()->create(['volume' => 50, 'price' => 200000, 'stock' => 0, 'is_active' => true])->id;
    }

    public function test_keep_expires_into_needs_action_never_auto_cancels_and_converts_when_paid(): void
    {
        $order = $this->ops->startKeep($o = $this->order('pickup'), $o->revision, $this->actor());
        $this->assertSame('active', $order->keepState());
        $this->assertEqualsWithDelta(now()->addHours(24)->timestamp, $order->keep_until->timestamp, 5);

        $order = $this->ops->confirmKeepStock($order, $order->revision, $this->actor());
        $this->assertSame($this->staff->id, $order->keep_stock_confirmed_by);
        $this->assertSame('keep_stock_set_aside', $this->ops->lastEvent->kind);

        $this->travel(25)->hours();
        $order = $order->fresh();
        $this->assertSame(['expired', 'active'], [$order->keepState(), $order->lifecycle], 'Past the deadline: needs action, not cancelled');
        $order = $this->ops->extendKeep($order, $order->revision, $this->actor(), 12);
        $this->assertSame('active', $order->keepState());
        $this->assertRejected(fn () => $this->ops->extendKeep($order, $order->revision, $this->actor(), 500), OrderValidationFailed::class);

        $order = app(RecordOnlineOrderMoney::class)->recordPayment($order, $order->revision, $this->staff, '200000', 'cash', 'proof_in_chat');
        $this->assertSame('converted', $order->keep_status, 'Paid: the keep turns into a normal order');
        $this->assertRejected(fn () => $this->ops->startKeep($order, $order->revision, $this->actor()), InvalidOrderTransition::class);

        $released = $this->ops->startKeep($r = $this->order('pickup'), $r->revision, $this->actor(), 6);
        $this->assertRejected(fn () => $this->ops->releaseKeep($released, $released->revision, $this->actor(), 'x'), OrderValidationFailed::class);
        $released = $this->ops->releaseKeep($released, $released->revision, $this->actor(), 'Customer tidak jadi ambil');
        $this->assertSame(['released', 'active'], [$released->keep_status, $released->lifecycle]);

        $cancelled = $this->ops->startKeep($c = $this->order('pickup'), $c->revision, $this->actor());
        $cancelled = $this->ops->cancel($cancelled, $cancelled->revision, $this->actor(), 'Tidak jadi beli');
        $this->assertSame('released', $cancelled->keep_status);
    }

    public function test_price_adjustments_need_super_admin_and_overpayment_is_shown_not_refunded(): void
    {
        $adjustments = app(OnlineOrderAdjustments::class);
        $money = app(RecordOnlineOrderMoney::class);
        $order = $this->order('pickup');
        $order = $money->recordPayment($order, $order->revision, $this->staff, '200000', 'transfer', 'proof_in_chat');
        $this->assertSame('paid', $order->payment_status);

        $order = $adjustments->request($order, $order->revision, $this->staff, '-20000', 'Diskon member lupa diterapkan');
        $adjustment = $order->adjustments()->sole();
        $this->assertSame(['pending', '200000.00'], [$adjustment->status, $order->customerTotal()], 'Pending adjustments do not change the total');
        try {
            $adjustments->approve($order, $order->revision, $this->staff, $adjustment->id);
            $this->fail('Staff Order approved a price change');
        } catch (HttpException $denied) {
            $this->assertSame(403, $denied->getStatusCode());
        }

        $order = $adjustments->approve($order, $order->revision, $this->owner, $adjustment->id);
        $this->assertSame(['180000.00', 2000000], [$order->customerTotal(), OnlineOrderAdjustments::overpaidCents($order)]);
        $this->assertSame([null, 'paid'], [$order->refund_status, $order->payment_status], 'Overpayment is visible; no refund is assumed');
        $this->assertStringContainsString('lebih bayar', $order->events()->reorder('id', 'desc')->value('note'));

        $order = $money->decideRefund($order, $order->revision, $this->owner, '20000', 'Kembalikan selisih diskon');
        $this->assertSame(['pending', 'refund_pending'], [$order->refund_status, $order->payment_status]);

        $this->assertRejected(fn () => $adjustments->request($order, $order->revision, $this->staff, '0', 'Nol tidak boleh'), OrderValidationFailed::class);
        $order = $adjustments->request($order, $order->revision, $this->staff, '-500000', 'Terlalu besar sekali');
        $big = $order->adjustments()->where('status', 'pending')->sole();
        $this->assertRejected(fn () => $adjustments->approve($order, $order->revision, $this->owner, $big->id), OrderValidationFailed::class);
        $this->assertRejected(fn () => $adjustments->reject($order, $order->revision, $this->owner, $big->id, 'no'), OrderValidationFailed::class);
        $order = $adjustments->reject($order, $order->revision, $this->owner, $big->id, 'Salah input nominal');
        $this->assertSame('rejected', $big->fresh()->status);
    }

    public function test_an_approved_discount_can_complete_a_handed_over_order(): void
    {
        $order = $this->packed($this->order('pickup'));
        $order = $this->ops->handover($order, $order->revision, $this->actor(), 'customer');
        $order = app(RecordOnlineOrderMoney::class)->recordPayment($order, $order->revision, $this->staff, '190000', 'cash', 'proof_in_chat');
        $this->assertSame(['unpaid', 'active'], [$order->payment_status, $order->lifecycle]);

        $adjustments = app(OnlineOrderAdjustments::class);
        $order = $adjustments->request($order, $order->revision, $this->staff, '-10000', 'Potongan harga disepakati');
        $order = $adjustments->approve($order, $order->revision, $this->owner, $order->adjustments()->sole()->id);
        $this->assertSame(['paid', 'completed'], [$order->payment_status, $order->lifecycle]);
        $this->assertContains('completed', $order->events()->pluck('kind')->all());
    }

    public function test_customer_changes_become_reviewed_requests_once_packing_started(): void
    {
        $requests = app(OnlineOrderChangeRequests::class);
        $order = $this->order('local_delivery');
        $this->assertFalse(OnlineOrderChangeRequests::customerMustRequest($order), 'Unpaid and not started: the customer edits directly');
        $order = $this->ops->startPreparation($order, $order->revision, $this->actor());
        $this->assertTrue(OnlineOrderChangeRequests::customerMustRequest($order));

        $order = $requests->submit($order, ['address' => 'Jl. Baru 9', 'customer_note' => 'Pagar hitam']);
        $request = $order->changeRequests()->sole();
        $this->assertSame([['address' => 'Jl. Baru 9', 'customer_note' => 'Pagar hitam'], 'customer', 'pending'], [$request->changes, $request->source, $request->status]);
        $this->assertSame('Jl. Contoh 1', $order->address, 'Nothing changes before review');
        $this->assertRejected(fn () => $requests->submit($order, ['customer_note' => 'Lagi']), InvalidOrderTransition::class);

        $order = $requests->approve($order, $order->revision, $this->staff, $request->id);
        $this->assertSame(['Jl. Baru 9', 'Pagar hitam', 'approved'], [$order->address, $order->customer_note, $request->fresh()->status]);

        // Changing the delivery type may change the cost: Super Admin only.
        $order = $requests->submit($order, ['fulfillment' => 'pickup']);
        $switch = $order->changeRequests()->where('status', 'pending')->sole();
        $this->assertRejected(fn () => $requests->approve($order, $order->revision, $this->staff, $switch->id), OrderActionNotAllowed::class);
        $order = $requests->approve($order, $order->revision, $this->owner, $switch->id);
        $this->assertSame(['pickup', null, 'not_needed'], [$order->fulfillment, $order->address, $order->courier_status]);

        $this->assertRejected(fn () => $requests->submit($order, ['customer_phone' => '123']), OrderValidationFailed::class);
        $this->assertRejected(fn () => $requests->submit($order, ['fulfillment' => 'pickup']), OrderValidationFailed::class, 'unchanged');
        $order = $requests->submit($order, ['customer_name' => 'Nama Baru']);
        $pending = $order->changeRequests()->where('status', 'pending')->sole();
        $this->assertRejected(fn () => $requests->reject($order, $order->revision, $this->staff, $pending->id, 'x'), OrderValidationFailed::class);
        $order = $requests->reject($order, $order->revision, $this->staff, $pending->id, 'Nama sesuai KTP tetap');
        $this->assertSame('rejected', $pending->fresh()->status);

        $order = $this->packed($order);
        $order = $this->ops->handover($order, $order->revision, $this->actor(), 'customer');
        $this->assertRejected(fn () => $requests->submit($order, ['customer_note' => 'Telat']), InvalidOrderTransition::class);
    }

    public function test_courier_already_requested_blocks_switching_the_delivery_type(): void
    {
        $requests = app(OnlineOrderChangeRequests::class);
        $order = $this->order('local_delivery');
        $order = $this->ops->requestCourier($order, $order->revision, $this->actor(), 'gosend');
        $order = $requests->submit($order, ['fulfillment' => 'pickup']);
        $request = $order->changeRequests()->sole();
        $this->assertRejected(fn () => $requests->approve($order, $order->revision, $this->owner, $request->id), InvalidOrderTransition::class);
    }

    public function test_slice_five_operations_are_v2_only(): void
    {
        config(['orders.v2_enabled' => false]);
        $legacy = $this->order('pickup');
        $this->assertRejected(fn () => app(OnlineOrderAdjustments::class)->request($legacy, $legacy->revision, $this->staff, '-1000', 'Diskon kecil'), InvalidOrderTransition::class);
        $this->assertRejected(fn () => app(OnlineOrderChangeRequests::class)->submit($legacy, ['customer_note' => 'Ubah']), InvalidOrderTransition::class);
        $this->assertRejected(fn () => $this->ops->startKeep($legacy, $legacy->revision, $this->actor()), InvalidOrderTransition::class);
    }

    private function actor(): OrderActor
    {
        return OrderActor::user($this->staff);
    }

    private function order(string $fulfillment): OnlineOrder
    {
        [$order] = app(CreateOnlineOrder::class)->handle($this->owner, [$this->variant => 1], [
            'customer_name' => 'Customer Sintetis', 'customer_phone' => '081234567890', 'fulfillment' => $fulfillment,
            'address' => $fulfillment === 'pickup' ? null : 'Jl. Contoh 1', 'postcode' => $fulfillment === 'intercity' ? '94111' : null,
            'packaging' => 'no_paperbag', 'customer_note' => null,
        ]);

        return $order->fresh(['items']);
    }

    private function packed(OnlineOrder $order): OnlineOrder
    {
        $items = $order->items()->get()->map(fn ($item) => ['line_id' => $item->line_id, 'quantity' => $item->quantity])->all();

        return $this->ops->pack($order, $order->fresh()->revision, $this->actor(), $items);
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
