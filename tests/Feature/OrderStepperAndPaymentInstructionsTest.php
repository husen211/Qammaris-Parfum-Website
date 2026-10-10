<?php

namespace Tests\Feature;

use App\Actions\Orders\OnlineOrderFulfillment;
use App\Models\StorePaymentAccount;
use App\Models\User;
use App\Support\OnlineOrderMessages;
use App\Support\OnlineOrderStepper;
use App\Support\OrderActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/** ORD-04: stepper state comes from the backend only; payment instructions use verified Owner accounts only. */
class OrderStepperAndPaymentInstructionsTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase;

    private User $staff;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['orders.simple_ux' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
    }

    public function test_exactly_one_current_step_follows_the_order_state(): void
    {
        $ops = app(OnlineOrderFulfillment::class);
        $actor = OrderActor::user($this->staff);
        $current = fn ($order) => [OnlineOrderStepper::current($order->fresh()), collect(OnlineOrderStepper::steps($order->fresh()))->where('state', 'current')->count()];

        $this->assertSame(['konfirmasi', 1], $current($this->v2Order(null)), 'awaiting customer data');
        $draft = $this->v2Order('pickup');
        $draft->forceFill(['lifecycle' => 'draft'])->save();
        $this->assertSame(['konfirmasi', 1], $current($draft), 'website draft');

        $order = $this->v2Order('local_delivery');
        $this->assertSame(['pembayaran', 1], $current($order));
        $order = $this->payInFull($order, $this->staff);
        $this->assertSame(['persiapan', 1], $current($order));
        $order = $ops->pack($order, $order->revision, $actor, $order->items->map(fn ($i) => ['line_id' => $i->line_id, 'quantity' => $i->quantity])->all());
        $this->assertSame(['pengiriman', 1], $current($order));
        $order = $ops->requestCourier($order, $order->revision, $actor, 'maxim');
        $order = $ops->handover($order, $order->revision, $actor, 'courier');
        $this->assertSame(['selesai', 0], $current($order), 'completed: every step done');
        $this->assertSame(['done', 'done', 'done', 'done', 'done'], array_column(OnlineOrderStepper::steps($order->fresh()), 'state'));

        $cancelled = $this->v2Order('pickup');
        $cancelled = $ops->cancel($cancelled, $cancelled->revision, $actor, 'Customer tidak jadi');
        $this->assertSame([null, 0], $current($cancelled));
    }

    public function test_admin_page_shows_one_step_with_its_primary_action_and_summarises_finished_steps(): void
    {
        $order = $this->payInFull($this->v2Order('pickup'), $this->staff);
        $page = $this->actingAs($this->staff)->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('data-step="persiapan" data-state="current"', false)
            ->assertSee('data-step="pembayaran" data-state="done"', false)
            ->assertSee('Konfirmasi packing')->assertDontSee('Tandai Lunas')->assertDontSee('Sudah diambil customer');
        $this->assertSame(1, substr_count($page->getContent(), 'aria-current="step"'));
    }

    public function test_payment_accounts_need_super_admin_and_a_matching_retyped_number(): void
    {
        $this->actingAs($this->staff)->get(route('admin.orders.payment-accounts.index'))->assertForbidden();
        $this->post(route('admin.orders.payment-accounts.store'), ['bank_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Qammaris'])->assertForbidden();

        $this->actingAs($this->owner);
        $this->post(route('admin.orders.payment-accounts.store'), ['bank_name' => 'BCA', 'account_number' => '12 3456 7890', 'account_holder' => 'Qammaris Parfum Sintetis'])->assertSessionHas('success');
        $account = StorePaymentAccount::sole();
        $this->assertSame(['1234567890', false], [$account->account_number, $account->isUsable()]);
        $this->post(route('admin.orders.payment-accounts.store'), ['bank_name' => 'B', 'account_number' => '12ab', 'account_holder' => 'Q'])
            ->assertSessionHasErrors(['bank_name', 'account_number', 'account_holder']);

        $order = $this->v2Order('pickup');
        $text = fn () => app(OnlineOrderMessages::class)->paymentInstructions($order->fresh());
        $this->assertStringNotContainsString('1234567890', $text(), 'Unverified: never shown');
        $this->assertStringContainsString('Nomor rekening resmi dikirim admin', $text());

        $this->post(route('admin.orders.payment-accounts.verify', $account), ['retyped_number' => '1234567809', 'confirmed' => '1'])->assertSessionHasErrors('retyped_number');
        $this->post(route('admin.orders.payment-accounts.verify', $account), ['retyped_number' => '1234567890'])->assertSessionHasErrors('confirmed');
        $this->assertFalse($account->fresh()->isUsable());
        $this->post(route('admin.orders.payment-accounts.verify', $account), ['retyped_number' => '1234-5678-90', 'confirmed' => '1'])->assertSessionHas('success');
        $this->assertSame($this->owner->id, $account->fresh()->verified_by);
        $this->assertStringContainsString('BCA 1234567890 a.n. Qammaris Parfum Sintetis', $text());

        $this->post(route('admin.orders.payment-accounts.deactivate', $account))->assertSessionHas('success');
        $this->assertStringNotContainsString('1234567890', $text());
        $this->assertNotNull($account->fresh()->deactivated_at, 'Kept for the audit, never deleted');
    }

    public function test_instructions_show_the_final_total_and_appear_once_the_ongkir_is_set(): void
    {
        $account = StorePaymentAccount::create(['bank_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Qammaris Sintetis', 'created_by' => $this->owner->id]);
        $account->forceFill(['verified_at' => now(), 'verified_by' => $this->owner->id])->save();
        $order = $this->v2Order('local_delivery');
        $this->actingAs($this->staff)->get(route('admin.orders.show', $order))
            ->assertSee('Isi ongkir dan siapa yang membayarnya dulu')->assertDontSee('Salin Total & Instruksi Pembayaran', false);

        $this->post(route('admin.orders.v2.shipping', $order), ['revision' => $order->revision, 'shipping_fee' => '15000', 'shipping_payer' => 'added_to_transfer'])->assertSessionHas('order_notice.type', 'success');
        $order->refresh();
        $page = $this->get(route('admin.orders.show', $order))->assertSee('Salin Total & Instruksi Pembayaran', false)->assertSee('Tandai Lunas');
        $text = app(OnlineOrderMessages::class)->paymentInstructions($order);
        foreach (['Pesanan *'.$order->code.'*', 'Subtotal produk: Rp 400.000', 'Ongkir: Rp 15.000', '*Total pembayaran: Rp 415.000*', 'BCA 1234567890 a.n. Qammaris Sintetis', 'kirim foto atau screenshot bukti pembayaran'] as $part) {
            $this->assertStringContainsString($part, $text);
        }
        $this->assertStringContainsString('wa.me/6281234567890?text=', $page->getContent(), 'Staff can send it to the customer chat');
        $this->assertSame('unpaid', $order->fresh()->payment_status, 'Copying instructions never marks paid');
    }
}
