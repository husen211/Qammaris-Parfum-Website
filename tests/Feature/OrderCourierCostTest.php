<?php

namespace Tests\Feature;

use App\Models\OnlineOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsV2Orders;
use Tests\Concerns\SignsOrderApiRequests;
use Tests\TestCase;

/**
 * ORD-08 (Owner 2026-10-17): Staff Order enters the courier fee and whose money paid it once, when booking the courier
 * on the Website. A staff advance reaches Qammaris App as a website-origin cost; the App only reports its reimbursement.
 */
class OrderCourierCostTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase, SignsOrderApiRequests;

    private const OWNER = '6650aaaabbbbccccddddeeee';

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->enableOrderApi(['orders_api.app_owner_ids' => [self::OWNER]]);
        config(['orders.simple_ux' => true, 'orders.staff_advancers' => ['Andi', 'Ikrar']]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
    }

    public function test_booking_the_courier_records_the_fee_and_whose_money_once(): void
    {
        $order = $this->packed($this->paidV2Order('local_delivery'));
        $this->actingAs($this->staff);
        $page = $this->get(route('admin.orders.show', $order))->assertOk();
        $page->assertSee('data-courier-booking', false)->assertSee('Ongkir ke kurir (Rp)')->assertSee('Uang siapa?')
            ->assertSee('value="staff:Andi"', false)->assertSee('value="staff:Ikrar"', false)->assertSee('Kasir')->assertSee('Customer bayar driver')
            ->assertSee('Kurir sudah dipesan')->assertDontSee('Toko bayar driver dengan');

        // Nothing is booked without the fee and a known payer.
        $this->book($order, ['cost_amount' => '', 'cost_payer' => ''])->assertSessionHas('order_notice.type', 'error')->assertSessionHasErrors(['cost_amount', 'cost_payer']);
        $this->book($order, ['cost_amount' => '15000', 'cost_payer' => 'staff:Budi'])->assertSessionHasErrors(['cost_payer']);
        $this->assertSame(['unassigned', 0], [$order->fresh()->courier_status, $order->costs()->count()]);

        $this->book($order, ['cost_amount' => '15.000', 'cost_payer' => 'staff:Andi'])->assertSessionHas('order_notice.type', 'success');
        $cost = $order->costs()->sole();
        $this->assertSame(['website', 'actual_shipping', 'active', '15000.00', 'Andi', 'submitted', '15000.00', 'pending', 1, $this->staff->id],
            [$cost->origin, $cost->kind, $cost->status, $cost->amount, $cost->advanced_by_name, $cost->reimbursement_status, $cost->reimbursement_amount, $cost->proof, $cost->source_version, $cost->recorded_by_user_id]);
        $this->assertSame([['source' => 'staff_advance', 'amount' => 15000]], $cost->funding);
        $this->assertMatchesRegularExpression('/^exp_[0-9a-f]{32}$/', $cost->expense_ref);
        $event = $order->events()->reorder('id', 'desc')->first();
        $this->assertSame('courier_requested', $event->kind);
        $this->assertStringContainsString('ongkir kurir Rp15.000 · ditalangi Andi', $event->note);
        $this->assertSame('requested', $order->fresh()->courier_status);

        $this->get(route('admin.orders.show', $order))->assertSee('Ongkir kurir Rp 15.000')->assertSee('uang Andi')
            ->assertSee('reimburse sedang dikirim ke Qammaris App')->assertSee('Ubah ongkir kurir')->assertDontSee('data-courier-booking', false);

        // The App reads it as a website-origin cost (r4.5).
        $read = $this->assertMatchesApiSchema($this->orderApi('GET', '/orders/'.$order->public_id)->assertOk(), 'Order');
        $this->assertSame(['website', 'Andi', null, 1, 15000], [$read->costs[0]->origin, $read->costs[0]->advanced_by->display_name, $read->costs[0]->reported_by,
            $read->costs[0]->source_version, $read->costs[0]->reimbursement->amount]);
        $this->assertSame(['reimbursement', 'open'], [$read->obligations[0]->type, $read->obligations[0]->status]);
    }

    public function test_corrections_edit_the_same_cost_and_bump_its_version(): void
    {
        $order = $this->packed($this->paidV2Order('local_delivery'));
        $this->actingAs($this->staff);
        $this->book($order, ['cost_amount' => '15000', 'cost_payer' => 'staff:Andi']);
        $cost = $order->costs()->sole();

        $revision = $order->fresh()->revision;
        $this->fix($order, '15000', 'staff:Andi')->assertSessionHas('order_notice.type', 'success');
        $this->assertSame([$revision, 1], [$order->fresh()->revision, $cost->fresh()->source_version], 'Same values: no event, no new version');

        $this->fix($order, '18000', 'staff:Ikrar');
        $this->assertSame(['18000.00', 'Ikrar', 'submitted', '18000.00', 2], [$cost->fresh()->amount, $cost->fresh()->advanced_by_name,
            $cost->fresh()->reimbursement_status, $cost->fresh()->reimbursement_amount, $cost->fresh()->source_version]);
        $this->assertSame('courier_cost', $order->events()->reorder('id', 'desc')->first()->kind);

        $this->fix($order, '18000', 'cashier');
        $this->assertSame([[['source' => 'cashier_cash', 'amount' => 18000]], null, 'not_applicable', '0.00', 3],
            [$cost->fresh()->funding, $cost->fresh()->advanced_by_name, $cost->fresh()->reimbursement_status, $cost->fresh()->reimbursement_amount, $cost->fresh()->source_version]);
        $this->fix($order, '18000', 'customer');
        $this->assertSame([['source' => 'customer_direct', 'amount' => 18000]], $cost->fresh()->funding);
        $this->assertSame(1, $order->costs()->count(), 'Always one website cost per order');
        $this->get(route('admin.orders.show', $order))->assertSee('dibayar customer ke driver');

        // Pickup orders have no courier fee.
        $pickup = $this->paidV2Order('pickup');
        $this->fix($pickup, '10000', 'cashier')->assertSessionHas('order_notice.type', 'error');
        $this->assertSame(0, $pickup->costs()->count());
    }

    public function test_jnt_pickup_request_records_the_jnt_fee(): void
    {
        $order = $this->packed($this->paidV2Order('intercity'));
        $this->actingAs($this->staff);
        $this->get(route('admin.orders.show', $order))->assertSee('Ongkir J&amp;T (Rp)', false);
        $this->post(route('admin.orders.v2.jnt', $order), ['revision' => $order->fresh()->revision, 'status' => 'pickup_requested'])->assertSessionHasErrors(['cost_amount', 'cost_payer']);
        $this->assertSame('not_requested', $order->fresh()->jnt_status ?? 'not_requested');
        $this->post(route('admin.orders.v2.jnt', $order), ['revision' => $order->fresh()->revision, 'status' => 'pickup_requested', 'cost_amount' => '27000', 'cost_payer' => 'staff:Ikrar'])
            ->assertSessionHas('order_notice.type', 'success');
        $this->assertSame(['pickup_requested', '27000.00', 'Ikrar'], [$order->fresh()->jnt_status, $order->costs()->sole()->amount, $order->costs()->sole()->advanced_by_name]);
        $this->assertStringContainsString('Pickup J&T diminta · ongkir kurir Rp27.000 · ditalangi Ikrar', $order->events()->reorder('id', 'desc')->first()->note);
        // The pickup itself needs no fee again.
        $this->post(route('admin.orders.v2.jnt', $order), ['revision' => $order->fresh()->revision, 'status' => 'picked_up'])->assertSessionHas('order_notice.type', 'success');
    }

    public function test_the_app_only_reports_the_reimbursement_of_a_website_cost(): void
    {
        $order = $this->packed($this->paidV2Order('local_delivery'));
        $this->actingAs($this->staff);
        $this->book($order, ['cost_amount' => '15000', 'cost_payer' => 'staff:Andi']);
        $cost = $order->costs()->sole();
        $waiver = ['by' => ['app_user_id' => self::OWNER, 'display_name' => 'Owner'], 'reason' => 'Otomatis disetujui: ongkir talangan dari Website',
            'at' => '2026-10-17T03:00:00Z', 'decision_id' => 'auto_'.substr($cost->expense_ref, 4, 32)];
        $report = fn (int $version, array $reimbursement, array $extra = []) => $this->report($order, $cost->expense_ref, $extra + [
            'kind' => 'actual_shipping', 'status' => 'active', 'amount' => 15000, 'funding' => [['source' => 'staff_advance', 'amount' => 15000]],
            'reimbursement' => $reimbursement + ['amount' => 15000, 'updated_at' => '2026-10-17T03:00:00Z'], 'source_version' => $version,
            'actor' => ['app_user_id' => self::OWNER, 'display_name' => 'Owner', 'app_role' => 'owner'],
        ]);

        // Same version as the Website's: nothing new.
        $this->assertNull($this->ok($report(1, ['status' => 'approved', 'proof' => 'waived', 'waiver' => $waiver]))->event_id);
        $approved = $this->ok($report(2, ['status' => 'approved', 'proof' => 'waived', 'waiver' => $waiver]));
        $this->assertNotNull($approved->event_id);
        $this->assertSame(['approved', 'waived', 2, 'website'], [$approved->order->costs[0]->reimbursement->status, $approved->order->costs[0]->reimbursement->proof,
            $approved->order->costs[0]->source_version, $approved->order->costs[0]->origin]);
        $this->assertSame('cost_reimbursement', $order->events()->reorder('id', 'desc')->first()->kind);
        $this->actingAs($this->staff)->get(route('admin.orders.show', $order))->assertSee('reimburse disetujui');

        // The Website owns the amount and funding: App values for them are ignored.
        $this->ok($report(3, ['status' => 'approved', 'proof' => 'waived', 'waiver' => $waiver], ['amount' => 15000, 'funding' => [['source' => 'staff_advance', 'amount' => 15000]], 'kind' => 'other']));
        $this->assertSame(['actual_shipping', '15000.00', 3], [$cost->fresh()->kind, $cost->fresh()->amount, $cost->fresh()->source_version]);

        // A Website correction resets the reimbursement until the App reports the new amount; a stale report is ignored.
        $this->fix($order, '20000', 'staff:Andi');
        $this->assertSame(['submitted', 4, null], [$cost->fresh()->reimbursement_status, $cost->fresh()->source_version, $cost->fresh()->waiver]);
        $this->assertNull($this->ok($report(5, ['status' => 'paid', 'proof' => 'waived', 'waiver' => $waiver]))->event_id);
        $this->assertSame(['submitted', 4], [$cost->fresh()->reimbursement_status, $cost->fresh()->source_version]);

        // Once paid in the App, the Website no longer edits the fee.
        $paid = ['kind' => 'actual_shipping', 'status' => 'active', 'amount' => 20000, 'funding' => [['source' => 'staff_advance', 'amount' => 20000]],
            'reimbursement' => ['status' => 'paid', 'proof' => 'waived', 'waiver' => $waiver, 'amount' => 20000, 'updated_at' => '2026-10-17T05:00:00Z'],
            'source_version' => 5, 'actor' => ['app_user_id' => self::OWNER, 'display_name' => 'Owner', 'app_role' => 'owner']];
        $this->ok($this->report($order, $cost->expense_ref, $paid));
        $this->assertSame('paid', $cost->fresh()->reimbursement_status);
        $this->actingAs($this->staff);
        $this->fix($order, '25000', 'staff:Andi')->assertSessionHas('order_notice', fn ($notice) => $notice['type'] === 'error' && str_contains($notice['message'], 'sudah dibayar'));
        $this->get(route('admin.orders.show', $order))->assertSee('reimburse sudah dibayar')->assertDontSee('Ubah ongkir kurir');

        // A new waiver still needs the deciding allowlisted Owner.
        $other = $this->packed($this->paidV2Order('local_delivery'));
        $this->book($other, ['cost_amount' => '9000', 'cost_payer' => 'staff:Ikrar']);
        $otherRef = $other->costs()->sole()->expense_ref;
        $this->assertApiError($this->report($other, $otherRef, ['kind' => 'actual_shipping', 'status' => 'active', 'amount' => 9000, 'funding' => [['source' => 'staff_advance', 'amount' => 9000]],
            'reimbursement' => ['status' => 'approved', 'proof' => 'waived', 'waiver' => $waiver, 'amount' => 9000, 'updated_at' => '2026-10-17T03:00:00Z'], 'source_version' => 2,
            'actor' => $this->actor()]), 403, 'action_not_allowed');
    }

    public function test_the_staff_group_link_opens_the_website_order_page(): void
    {
        config(['orders_api.app_task_links' => false]);
        $order = $this->paidV2Order('local_delivery');
        $this->actingAs($this->staff)->get(route('admin.orders.show', $order))
            ->assertSee('Buka pesanan: '.route('admin.orders.task', $order->public_id), false)->assertDontSee('login akun masing-masing');
    }

    private function packed(OnlineOrder $order): OnlineOrder
    {
        $this->actingAs($this->staff)->post(route('admin.orders.v2.pack', $order), ['revision' => $order->revision,
            'packed' => $order->items->mapWithKeys(fn ($item) => [$item->line_id => $item->quantity])->all()])->assertSessionHas('order_notice.type', 'success');

        return $order->fresh(['items']);
    }

    private function book(OnlineOrder $order, array $cost): TestResponse
    {
        return $this->post(route('admin.orders.v2.courier', $order), ['revision' => $order->fresh()->revision, 'provider' => 'maxim', 'status' => 'requested'] + $cost);
    }

    private function fix(OnlineOrder $order, string $amount, string $payer): TestResponse
    {
        return $this->post(route('admin.orders.v2.courier-cost', $order), ['revision' => $order->fresh()->revision, 'cost_amount' => $amount, 'cost_payer' => $payer]);
    }

    private function report(OnlineOrder $order, string $ref, array $body): TestResponse
    {
        return $this->orderApi('PUT', '/orders/'.$order->public_id.'/costs/'.$ref, $body, ['Idempotency-Key' => Str::random(24)]);
    }

    private function ok(TestResponse $response): object
    {
        $response->assertOk();

        return $this->assertMatchesApiSchema($response, 'MutationResponse');
    }
}
