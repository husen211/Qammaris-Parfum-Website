<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOnlineOrder;
use App\Actions\Orders\OnlineOrderWorkflow;
use App\Actions\Orders\RecordOnlineOrderMoney;
use App\Exceptions\OnlineOrderRejected;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderPayment;
use App\Models\Product;
use App\Models\User;
use App\Support\OnlineOrderMoney;
use App\Support\OnlineOrderState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** ORD-02c slice 2: legacy/V2 cutover, payment/refund ledger, refund decisions and reconciliation. */
class OnlineOrderMoneyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private RecordOnlineOrderMoney $money;

    private int $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $this->money = app(RecordOnlineOrderMoney::class);
        $brand = Brand::create(['name' => 'Money Brand', 'is_active' => true]);
        $category = Category::create(['name' => 'EDP']);
        $product = Product::create([
            'name' => 'Money Synthetic', 'brand_id' => $brand->id, 'category_id' => $category->id, 'description' => 'Synthetic',
            'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []], 'gender' => 'Unisex', 'base_price' => 200000,
            'publication_status' => 'published', 'is_active' => true, 'availability_source' => 'manual',
            'availability_status' => 'available', 'availability_checked_at' => now(),
        ]);
        $this->variant = $product->variants()->create(['volume' => 50, 'price' => 200000, 'stock' => 0, 'is_active' => true])->id;
    }

    public function test_legacy_cancel_after_payment_is_reconciled_by_super_admin_with_explicit_amounts(): void
    {
        $cases = [
            'full refund already returned' => [['200000', '200000', '200000'], 'refunded', 'refunded', false],
            'partial refund still owed' => [['200000', '200000', '50000'], 'partial', 'refund_pending', true],
            'nothing to return' => [['200000', '0', '0'], 'not_required', 'paid', false],
            'decided, nothing returned yet' => [['200000', '150000', '0'], 'pending', 'refund_pending', true],
        ];
        foreach ($cases as $label => [[$received, $due, $returned], $refundStatus, $paymentStatus, $openRefund]) {
            $order = $this->legacyCancelledAfterPayment();
            $this->assertSame('needs_reconciliation', $order->refund_status);

            $order = $this->money->reconcile($order, $order->revision, $this->owner, $received, $due, $returned, 'Dicek di mutasi BCA');
            $this->assertSame([$refundStatus, $paymentStatus], [$order->refund_status, $order->payment_status], $label);
            $this->assertSame($openRefund, OnlineOrderState::flags($order)['open_refund'], $label);
            $this->assertSame(['received' => (int) $received * 100, 'refunded' => (int) $returned * 100], OnlineOrderMoney::totals($order), $label);
            $this->assertSame('reconciled', $order->events()->reorder('id', 'desc')->value('kind'));
            $this->assertSame(['reconciled'], $order->payments()->distinct()->pluck('basis')->all());
        }
    }

    public function test_reconciliation_rejects_impossible_amounts_and_unknown_history_stays_flagged(): void
    {
        $order = $this->legacyCancelledAfterPayment();
        foreach ([['200000', '250000', '0'], ['200000', '100000', '150000'], ['0', '0', '0'], ['abc', '0', '0']] as [$received, $due, $returned]) {
            $this->assertRejected(fn () => $this->money->reconcile($order->fresh(), $order->revision, $this->owner, $received, $due, $returned, 'Dicek di mutasi'));
        }
        $this->assertRejected(fn () => $this->money->reconcile($order->fresh(), $order->revision, $this->owner, '200000', '0', '0', 'x'), 'reason too short');
        $this->assertRejected(fn () => $this->money->decideRefund($order->fresh(), $order->revision, $this->owner, '0', 'Tidak ada refund'), 'decide needs reconciliation first');
        $fresh = $order->fresh();
        $this->assertSame(['needs_reconciliation', 'paid'], [$fresh->refund_status, $fresh->payment_status]);
        $this->assertSame(0, $fresh->payments()->count());

        $open = $this->legacyOrder('paid');
        $this->assertRejected(fn () => $this->money->reconcile($open, $open->revision, $this->owner, '200000', '0', '0', 'Tidak relevan'), 'only flagged orders');
    }

    public function test_v2_payment_partial_and_full_refund_with_append_only_corrections(): void
    {
        $order = $this->v2Order();
        $this->assertSame('unpaid', $order->payment_status);

        $order = $this->money->recordPayment($order, $order->revision, $this->owner, '100000', 'transfer', 'proof_in_chat');
        $this->assertSame('unpaid', $order->payment_status, 'Half of the total is not paid');
        $order = $this->money->recordPayment($order, $order->revision, $this->owner, '100000', 'qris', 'majoo');
        $this->assertSame('paid', $order->payment_status);
        $this->assertNull($order->refund_status);

        // Price reduction after payment: Super Admin decides a partial refund.
        $order = $this->money->decideRefund($order, $order->revision, $this->owner, '50000', 'Diskon member setelah bayar');
        $this->assertSame(['pending', 'refund_pending'], [$order->refund_status, $order->payment_status]);
        $order = $this->money->recordRefund($order, $order->revision, $this->owner, '20000', 'transfer', 'TRF-1');
        $this->assertSame(['partial', 'refund_pending'], [$order->refund_status, $order->payment_status]);
        $this->assertRejected(fn () => $this->money->recordRefund($order, $order->revision, $this->owner, '40000', 'transfer'), 'over remaining');
        $this->assertRejected(fn () => $this->money->decideRefund($order, $order->revision, $this->owner, '10000', 'Lebih kecil dari yang kembali'), 'below refunded');
        $this->assertRejected(fn () => $this->money->decideRefund($order, $order->revision, $this->owner, '250000', 'Lebih besar dari pembayaran'), 'above received');

        $order = $this->money->recordRefund($order, $order->revision, $this->owner, '30000', 'cash');
        $this->assertSame(['refunded', 'refunded'], [$order->refund_status, $order->payment_status]);
        $this->assertFalse(OnlineOrderState::flags($order)['open_refund']);

        // A mistaken refund entry is reversed, not deleted: the obligation reopens and both rows stay visible.
        $wrong = $order->payments()->where('type', 'refund')->where('amount', '30000.00')->first();
        $order = $this->money->reverse($order, $order->revision, $this->owner, $wrong->id, 'Transfer gagal, dana kembali');
        $this->assertSame(['partial', 'refund_pending'], [$order->refund_status, $order->payment_status]);
        $this->assertSame(5, $order->payments()->count());
        $this->assertRejected(fn () => $this->money->reverse($order, $order->revision, $this->owner, $wrong->id, 'Dua kali'), 'already reversed');
        $reversal = $order->payments()->where('basis', 'reversal')->first();
        $this->assertRejected(fn () => $this->money->reverse($order, $order->revision, $this->owner, $reversal->id, 'Balik lagi'), 'reversal of reversal');

        // Reversing a payment may not leave less received than the refund owed.
        $payment = $order->payments()->where('type', 'payment')->first();
        $smallerOrder = $this->money->decideRefund($order, $order->revision, $this->owner, '20000', 'Koreksi: refund cukup 20 ribu');
        $this->assertSame(['refunded', 'refunded'], [$smallerOrder->refund_status, $smallerOrder->payment_status]);
        $this->assertSame('refund_corrected', $smallerOrder->events()->reorder('id', 'desc')->value('kind'));
        $afterReversal = $this->money->reverse($smallerOrder, $smallerOrder->revision, $this->owner, $payment->id, 'Salah catat transfer');
        $this->assertSame(['received' => 10000000, 'refunded' => 2000000], OnlineOrderMoney::totals($afterReversal));

        $kinds = $afterReversal->events()->pluck('kind')->all();
        foreach (['payment_recorded', 'refund_decided', 'refund_recorded', 'ledger_reversed', 'refund_corrected'] as $kind) {
            $this->assertContains($kind, $kinds);
        }
        $this->assertSame(OnlineOrderPayment::count(), OnlineOrderPayment::whereNotNull('recorded_by')->count());
    }

    public function test_a_stale_revision_blocks_double_taps(): void
    {
        $order = $this->v2Order();
        $revision = $order->revision;
        $this->money->recordPayment($order, $revision, $this->owner, '200000', 'transfer', null);
        $this->assertRejected(fn () => $this->money->recordPayment($order, $revision, $this->owner, '200000', 'transfer', null), 'same tap twice');
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_only_super_admin_decides_records_reverses_and_reconciles_refunds(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $legacyAdmin = User::factory()->create(['role' => User::ROLE_LEGACY_ADMIN])->fresh();
        $order = $this->money->recordPayment($this->v2Order(), 1, $staff, '200000', 'cash', 'proof_in_chat');
        $this->assertSame('paid', $order->payment_status, 'Staff Order may record a payment');
        $flagged = $this->legacyCancelledAfterPayment();

        foreach ([$staff, $legacyAdmin] as $user) {
            foreach ([
                fn () => $this->money->decideRefund($order->fresh(), $order->fresh()->revision, $user, '0', 'Tidak ada refund'),
                fn () => $this->money->recordRefund($order->fresh(), $order->fresh()->revision, $user, '1000', 'cash'),
                fn () => $this->money->reverse($order->fresh(), $order->fresh()->revision, $user, $order->payments()->first()->id, 'Salah catat'),
                fn () => $this->money->reconcile($flagged->fresh(), $flagged->fresh()->revision, $user, '200000', '0', '0', 'Dicek di mutasi'),
            ] as $attempt) {
                try {
                    $attempt();
                    $this->fail("{$user->role} must not change refunds");
                } catch (HttpException $denied) {
                    $this->assertSame(403, $denied->getStatusCode());
                }
            }
        }
        $this->assertSame(1, OnlineOrderPayment::count());
        $this->assertSame('needs_reconciliation', $flagged->fresh()->refund_status);
    }

    public function test_legacy_and_v2_state_never_overwrite_each_other(): void
    {
        $v2 = $this->v2Order();
        DB::table('online_orders')->where('id', $v2->id)->update(['lifecycle' => 'active', 'handover_status' => 'pending', 'stage' => 'awaiting_customer']);
        $v2 = $v2->fresh();
        $v2->staff_note = 'Catatan';
        $v2->save();
        $this->assertSame('active', $v2->fresh()->lifecycle, 'The legacy stage hook skips V2 rows');

        $workflow = app(OnlineOrderWorkflow::class);
        foreach ([
            fn () => $workflow->advance($v2->fresh(), 'awaiting_customer', 'details_received', 'admin', $this->owner),
            fn () => $workflow->cancel($v2->fresh(), 'Batal', $this->owner),
            fn () => $workflow->recordStaffAdvance($v2->fresh(), '10000', 'Andi'),
        ] as $attempt) {
            $this->assertRejected($attempt, 'legacy step on V2');
        }

        $legacy = $this->legacyOrder('paid');
        $this->assertSame(OnlineOrder::STATE_LEGACY, $legacy->state_model);
    }

    public function test_slice_one_refund_assumptions_are_corrected_by_the_migration(): void
    {
        $order = $this->legacyCancelledAfterPayment();
        DB::table('online_orders')->where('id', $order->id)->update(['payment_status' => 'refund_pending', 'refund_status' => null]);
        $untouched = $this->legacyOrder('paid');

        $migration = require database_path('migrations/2026_10_09_000001_add_order_cutover_and_money_ledger.php');
        $migration->correctLegacyRefunds();
        $migration->correctLegacyRefunds();

        $this->assertSame(['paid', 'needs_reconciliation'], [$order->fresh()->payment_status, $order->fresh()->refund_status]);
        $this->assertSame(['paid', null], [$untouched->fresh()->payment_status, $untouched->fresh()->refund_status]);
    }

    private function legacyOrder(string $until): OnlineOrder
    {
        [$order] = app(CreateOnlineOrder::class)->handle($this->owner, [$this->variant => 1], [
            'customer_name' => 'Customer Sintetis', 'customer_phone' => '081234567890', 'fulfillment' => 'pickup',
            'address' => null, 'postcode' => null, 'packaging' => 'no_paperbag', 'customer_note' => null,
        ]);
        if ($until === 'paid') {
            $order = app(OnlineOrderWorkflow::class)->advance($order, 'details_received', 'paid', 'admin', $this->owner, extra: ['payment_method' => 'transfer']);
        }

        return $order->fresh();
    }

    private function legacyCancelledAfterPayment(): OnlineOrder
    {
        return app(OnlineOrderWorkflow::class)->cancel($this->legacyOrder('paid'), 'Customer batal', $this->owner)->fresh();
    }

    /** A V2 row for money tests. V2 state operations arrive in slice 3, so the row is marked directly. */
    private function v2Order(): OnlineOrder
    {
        $order = $this->legacyOrder('details');
        DB::table('online_orders')->where('id', $order->id)->update(['state_model' => OnlineOrder::STATE_V2, 'payment_status' => 'unpaid', 'revision' => 1]);

        return $order->fresh();
    }

    private function assertRejected(callable $attempt, string $label = ''): void
    {
        try {
            $attempt();
            $this->fail("Expected rejection: {$label}");
        } catch (OnlineOrderRejected) {
            $this->addToAssertionCount(1);
        }
    }
}
