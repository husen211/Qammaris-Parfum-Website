<?php

namespace Tests\Feature;

use App\Models\OnlineOrder;
use App\Models\OnlineOrderPayment;
use App\Models\User;
use App\Support\OrderApi\OrderApiSchema;
use App\Support\OrderApi\OrderSerializer;
use App\Support\Rupiah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/** ORD-03 Operational UX: simpler actions that keep payment integrity, audit, authorization and API v1 unchanged. */
class OrderOperationalUxTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase;

    private User $staff;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
    }

    public function test_payment_has_no_visible_source_but_the_ledger_records_an_internal_one_and_api_v1_stays_valid(): void
    {
        $order = $this->v2Order();
        $this->actingAs($this->staff)->post(route('admin.orders.v2.payments', $order), ['revision' => $order->revision, 'amount' => '100000', 'payment_method' => 'transfer'])
            ->assertSessionHas('order_notice.type', 'success');
        $entry = OnlineOrderPayment::sole();
        $this->assertSame(['admin_recorded', null], [$entry->confirmation_source, $order->fresh()->payment_confirmation_source]);
        $this->assertSame([], OrderApiSchema::errors(json_decode(json_encode(OrderSerializer::order($order->fresh()))), 'Order'), 'API v1 enum unchanged');

        $rest = (int) (Rupiah::minorUnits($order->fresh()->customerTotal()) / 100) - 100000;
        $this->post(route('admin.orders.v2.payments', $order), ['revision' => $order->fresh()->revision, 'amount' => (string) $rest, 'payment_method' => 'qris', 'recorded_in_majoo' => '1']);
        $order->refresh();
        $this->assertSame(['paid', 'majoo', true], [$order->payment_status, $order->payment_confirmation_source, $order->recorded_in_majoo]);
        $this->assertSame('majoo', OnlineOrderPayment::orderByDesc('id')->first()->confirmation_source);
    }

    public function test_staff_sets_the_customer_shipping_fee_only_under_the_flag_and_before_any_payment(): void
    {
        $order = $this->v2Order();
        $shipping = fn (OnlineOrder $o, array $data) => $this->post(route('admin.orders.v2.shipping', $o), ['revision' => $o->fresh()->revision] + $data);
        $this->actingAs($this->staff);

        // Flag off: unchanged ORD-02 rule (Super Admin only).
        $shipping($order, ['shipping_fee' => '20000', 'shipping_payer' => 'added_to_transfer'])->assertSessionHas('order_notice.type', 'error');
        $this->get(route('admin.orders.show', $order))->assertSee('Ongkir diatur Super Admin.')->assertDontSee('Simpan ongkir');

        config(['orders.simple_ux' => true]);
        $this->get(route('admin.orders.show', $order))->assertSee('Simpan ongkir')->assertDontSee('Toko bayar driver dengan');
        $shipping($order, ['shipping_fee' => '20.000', 'shipping_payer' => 'added_to_transfer'])->assertSessionHas('order_notice.type', 'success');
        $order->refresh();
        $this->assertSame(['20000.00', 'added_to_transfer'], [$order->shipping_fee, $order->shipping_payer]);
        $this->assertStringContainsString('ongkir - → Rp 20.000', $order->events()->reorder('id', 'desc')->first()->note, 'Audit keeps the money change');
        // Driver funding stays Super Admin.
        $shipping($order, ['shipping_fee' => '20000', 'shipping_payer' => 'added_to_transfer', 'driver_funding' => 'cashier_cash'])->assertSessionHas('order_notice.type', 'error');
        $this->assertNull($order->fresh()->driver_funding);

        // After a payment the staff path closes; the change becomes a price adjustment for Super Admin.
        $this->post(route('admin.orders.v2.payments', $order), ['revision' => $order->fresh()->revision, 'amount' => '50000', 'payment_method' => 'transfer']);
        $shipping($order, ['shipping_fee' => '30000', 'shipping_payer' => 'added_to_transfer'])
            ->assertSessionHas('order_notice', fn ($notice) => $notice['type'] === 'error' && str_contains($notice['message'], 'penyesuaian harga'));
        $this->assertSame('20000.00', $order->fresh()->shipping_fee);
        $this->get(route('admin.orders.show', $order))->assertSee('Sudah ada pembayaran. Ubah total lewat Opsi lanjutan');

        // Super Admin keeps full control (audited).
        $this->actingAs($this->owner);
        $shipping($order, ['shipping_fee' => '30000', 'shipping_payer' => 'added_to_transfer', 'driver_funding' => 'cashier_cash'])->assertSessionHas('order_notice.type', 'success');
        $this->assertSame(['30000.00', 'cashier_cash'], [$order->fresh()->shipping_fee, $order->fresh()->driver_funding]);
    }

    public function test_staff_still_cannot_approve_prices_or_refunds(): void
    {
        config(['orders.simple_ux' => true]);
        $order = $this->v2Order();
        $this->actingAs($this->staff)->post(route('admin.orders.v2.adjustments', $order), ['revision' => $order->revision, 'direction' => 'discount', 'amount' => '10000', 'reason' => 'Diskon member uji'])
            ->assertSessionHas('order_notice.type', 'success');
        $adjustment = $order->adjustments()->sole();
        $this->post(route('admin.orders.v2.adjustments.decide', [$order, $adjustment->id, 'approve']), ['revision' => $order->fresh()->revision])->assertForbidden();
        $this->post(route('admin.orders.money.refund-decision', $order), ['revision' => $order->fresh()->revision, 'refund_due' => '0', 'reason' => 'uji'])->assertForbidden();
        $this->get(route('admin.orders.show', $order))->assertDontSee('Setujui</button>', false)->assertDontSee('id="keuangan"', false);
    }

    public function test_customer_link_page_has_no_site_navigation_and_saves_a_payment_preference_without_paying(): void
    {
        $order = $this->v2Order(null);
        $token = $order->customer_token_encrypted;
        $form = ['customer_name' => 'E2E Rina', 'customer_phone' => '081200000099', 'fulfillment' => 'local_delivery', 'address' => 'Depan masjid', 'packaging' => 'paperbag'];

        $page = $this->get(route('orders.customer.show', $token))->assertOk()->assertSee('data-order-page', false)->assertDontSee('data-cart-count', false);
        $page->assertDontSee('name="payment_preference"', false)->assertDontSee('kecamatan');
        $this->post(route('orders.customer.submit', $token), $form)->assertSessionHasNoErrors();

        config(['orders.simple_ux' => true]);
        $order = $this->v2Order(null);
        $token = $order->customer_token_encrypted;
        $this->get(route('orders.customer.show', $token))->assertSee('name="payment_preference"', false)->assertSee('QRIS');
        $this->post(route('orders.customer.submit', $token), $form)->assertSessionHasErrors('payment_preference');
        $this->post(route('orders.customer.submit', $token), $form + ['payment_preference' => 'qris'])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(['qris', 'unpaid', 0], [$order->payment_preference, $order->payment_status, $order->payments()->count()], 'A preference is never a payment');
        $this->assertArrayNotHasKey('payment_preference', OrderSerializer::order($order)['payment'], 'Not part of API v1');

        $this->actingAs($this->staff)->get(route('admin.orders.show', $order))->assertSee('Customer memilih: <strong>QRIS</strong>', false)
            ->assertSee('<option value="qris" selected>', false);
        $this->get(route('orders.customer.show', $token))->assertSee('Kirim Sharelok')->assertDontSee('ikon lampiran');
    }

    public function test_packing_is_one_action_and_still_requires_every_line(): void
    {
        $order = $this->v2Order();
        [$first, $second] = $order->items->all();
        $this->actingAs($this->staff)->get(route('admin.orders.show', $order))
            ->assertSee('Centang barang yang sudah masuk paket')->assertDontSee('Mulai siapkan');

        $this->post(route('admin.orders.v2.pack', $order), ['revision' => $order->revision, 'packed' => [$first->line_id => $first->quantity]])
            ->assertSessionHas('order_notice.type', 'error');
        $this->assertSame('not_started', $order->fresh()->preparation_status);
        $this->post(route('admin.orders.v2.pack', $order), ['revision' => $order->fresh()->revision, 'packed' => [$first->line_id => $first->quantity, $second->line_id => $second->quantity]])
            ->assertSessionHas('order_notice.type', 'success');
        $this->assertSame('packed', $order->fresh()->preparation_status);
        // Two staff on an old revision: the second is a conflict, never a second packing.
        $this->post(route('admin.orders.v2.pack', $order), ['revision' => $order->revision, 'packed' => [$first->line_id => $first->quantity, $second->line_id => $second->quantity]])
            ->assertSessionHas('order_notice.type', 'conflict');
    }

    public function test_jnt_main_flow_is_request_waiting_picked_up_and_the_qr_is_optional_and_private(): void
    {
        $order = $this->v2Order('intercity');
        $this->actingAs($this->staff);
        $this->get(route('admin.orders.show', $order))->assertSee('Pickup J&amp;T belum diminta', false);
        $this->post(route('admin.orders.v2.pack', $order), ['revision' => $order->revision, 'packed' => $order->items->mapWithKeys(fn ($i) => [$i->line_id => $i->quantity])->all()]);
        $this->post(route('admin.orders.v2.jnt', $order), ['revision' => $order->fresh()->revision, 'status' => 'pickup_requested']);
        $this->get(route('admin.orders.show', $order))->assertSee('Menunggu kurir J&amp;T', false)->assertSee('Sudah di-pickup J&T', false)->assertSee('Opsi lanjutan: QR & resi', false);

        // Optional QR: private storage, admin-only download, wrong type refused.
        $this->post(route('admin.orders.v2.jnt.qr', $order), ['revision' => $order->fresh()->revision, 'qr' => UploadedFile::fake()->create('qr.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('qr');
        $this->post(route('admin.orders.v2.jnt.qr', $order), ['revision' => $order->fresh()->revision, 'qr' => UploadedFile::fake()->image('qr.png')])
            ->assertSessionHas('order_notice.type', 'success');
        $this->assertCount(1, Storage::disk('local')->allFiles('order-qr'));
        $this->get(route('admin.orders.v2.jnt.qr.show', $order))->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private');
        auth()->logout();
        $this->get(route('admin.orders.v2.jnt.qr.show', $order))->assertRedirect();

        // Picked up without a tracking number; it can follow later.
        $order = $this->v2Order('intercity');
        $this->actingAs($this->staff);
        $this->post(route('admin.orders.v2.pack', $order), ['revision' => $order->revision, 'packed' => $order->items->mapWithKeys(fn ($i) => [$i->line_id => $i->quantity])->all()]);
        $this->post(route('admin.orders.v2.jnt', $order), ['revision' => $order->fresh()->revision, 'status' => 'pickup_requested']);
        $this->post(route('admin.orders.v2.jnt', $order), ['revision' => $order->fresh()->revision, 'status' => 'picked_up'])->assertSessionHas('order_notice.type', 'success');
        $this->assertSame(['picked_up', 'handed_over', null, null], [$order->fresh()->jnt_status, $order->fresh()->handover_status, $order->fresh()->tracking_number, $order->fresh()->jnt_qr_path]);
        $this->get(route('admin.orders.show', $order))->assertSee('Nomor resi');
    }
}
