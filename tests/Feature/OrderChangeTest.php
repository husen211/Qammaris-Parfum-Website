<?php

namespace Tests\Feature;

use App\Actions\Orders\OnlineOrderFulfillment;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\User;
use App\Support\OnlineOrderMoney;
use App\Support\OrderActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/** ORD-27 (Owner 2026-11-17): staff change products and delivery after the order was made, with the difference. */
class OrderChangeTest extends TestCase
{
    use BuildsV2Orders;
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['orders.simple_ux' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
    }

    public function test_staff_swap_a_sold_out_product_after_payment_and_the_customer_sees_what_is_left_to_pay(): void
    {
        // Alpha 2 × 150.000 + Beta 1 × 100.000, picked up and paid in full.
        $order = $this->paidV2Order('pickup');
        [$alpha, $beta] = $order->items->sortBy('product_name')->values()->all();
        $gamma = $this->variant('API Gamma', 180000);
        $this->actingAs($this->staff);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('data-change-order', false);
        $this->get(route('admin.orders.v2.edit', $order))->assertOk()->assertSee('Ubah pesanan')->assertSee('API Beta')->assertSee('Simpan perubahan');

        // Beta is sold out: swapped for Gamma.
        $this->post(route('admin.orders.v2.change', $order), [
            'revision' => $order->revision, 'fulfillment' => 'pickup', 'note' => 'Stok Beta habis',
            'items' => [['line_id' => $alpha->line_id, 'variant_id' => $alpha->variant_id, 'quantity' => 2], ['variant_id' => $gamma, 'quantity' => 1]],
        ])->assertRedirect(route('admin.orders.show', $order).'#ringkasan')->assertSessionHas('order_change.message', fn ($message) => str_contains($message, 'Kurang bayar *Rp 80.000*'));

        $order->refresh()->load('items');
        $this->assertSame(['API Alpha' => 2, 'API Gamma' => 1], $order->items->sortBy('product_name')->pluck('quantity', 'product_name')->all());
        $this->assertSame($alpha->line_id, $order->items->firstWhere('product_name', 'API Alpha')->line_id);
        $this->assertSame(['480000.00', 'unpaid'], [$order->customerTotal(), $order->payment_status]);
        $note = $order->events()->where('kind', 'order_changed')->value('note');
        $this->assertStringContainsString('Produk: 2× API Alpha 50 ml, 1× API Beta 50 ml → 2× API Alpha 50 ml, 1× API Gamma 50 ml', $note);
        $this->assertStringContainsString('total Rp 400.000 → Rp 480.000 · kurang bayar Rp 80.000 · Stok Beta habis', $note);
        $this->get(route('admin.orders.show', $order))->assertSee('Pesanan diubah.')->assertSee('Kurang bayar');
        $this->get(route('orders.customer.show', $order->customer_token_encrypted))->assertSee('Sisa yang perlu dibayar Rp 80.000');

        // The rest is paid as usual; then the order is paid again.
        $this->assertSame('paid', $this->payInFull($order)->payment_status);
    }

    public function test_a_cheaper_change_is_returned_by_staff_and_a_later_increase_asks_again(): void
    {
        $order = $this->paidV2Order('pickup');
        $alpha = $order->items->firstWhere('product_name', 'API Alpha');
        $lines = fn (int $alphaQuantity) => $order->items->map(fn ($item) => ['line_id' => $item->line_id, 'variant_id' => $item->variant_id, 'quantity' => $item->is($alpha) ? $alphaQuantity : $item->quantity])->all();
        $this->actingAs($this->staff);

        $this->post(route('admin.orders.v2.change', $order), ['revision' => $order->revision, 'fulfillment' => 'pickup', 'items' => $lines(1)])
            ->assertSessionHas('order_change.message', fn ($message) => str_contains($message, 'Kelebihan bayar *Rp 150.000*'));
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->get(route('admin.orders.show', $order))->assertSee('Customer lebih bayar Rp 150.000');

        $this->post(route('admin.orders.v2.overpayment', $order), ['revision' => $order->revision, 'refund_method' => 'transfer', 'reference' => 'BCA customer'])
            ->assertSessionHas('order_notice.message', 'Pengembalian kelebihan bayar dicatat.');
        $order->refresh();
        $this->assertSame(['received' => 40000000, 'refunded' => 15000000], OnlineOrderMoney::totals($order));
        $this->assertSame(['paid', null], [$order->payment_status, $order->refund_status]);
        $this->get(route('admin.orders.show', $order))->assertDontSee('Customer lebih bayar');
        $this->post(route('admin.orders.v2.overpayment', $order), ['revision' => $order->revision, 'refund_method' => 'transfer'])->assertSessionHas('order_notice.type', 'error');

        // Back to 2 bottles: the 150.000 returned is owed again.
        $this->post(route('admin.orders.v2.change', $order), ['revision' => $order->revision, 'fulfillment' => 'pickup', 'items' => $lines(2)]);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_a_website_jnt_order_switches_to_an_instant_courier_and_a_packed_order_is_packed_again(): void
    {
        $order = $this->v2Order('intercity', ['address' => 'Jl. Sintetis 1, Kota Palu, Sulawesi Tengah']);
        $order->forceFill(['shipping_fee' => '20000.00', 'shipping_payer' => 'added_to_transfer', 'source' => 'website',
            'shipping_quote' => ['kind' => 'jnt', 'provider' => 'jnt', 'label' => 'J&T', 'charged' => 20000, 'price' => 28000, 'destination' => ['postal_code' => '90111']]])->saveQuietly();
        $order = $this->payInFull($order);
        app(OnlineOrderFulfillment::class)->startPreparation($order->fresh(), $order->revision, OrderActor::user($this->orderOwner));
        $order = $order->fresh(['items']);
        $this->actingAs($this->staff);

        $lines = $order->items->map(fn ($item) => ['line_id' => $item->line_id, 'variant_id' => $item->variant_id, 'quantity' => $item->quantity])->all();
        // Only the delivery changes: the products stay packed as they are.
        $this->post(route('admin.orders.v2.change', $order), [
            'revision' => $order->revision, 'items' => $lines, 'fulfillment' => 'local_delivery', 'address' => 'Jl. Sintetis 1, Palu',
            'location_url' => '-0.9012345, 119.8701234', 'shipping_fee' => '15.000', 'shipping_payer' => 'added_to_transfer',
        ])->assertSessionHas('order_change.message', fn ($message) => str_contains($message, 'Pengiriman: Kurir dalam kota · ongkir Rp 15.000') && str_contains($message, 'Kelebihan bayar *Rp 5.000*'));
        $order->refresh();
        $this->assertSame(['local_delivery', '15000.00', null, 'unassigned', null], [$order->fulfillment, $order->shipping_fee, $order->shipping_quote, $order->courier_status, $order->postcode]);
        $this->assertSame(['latitude' => -0.901235, 'longitude' => 119.870123], $order->deliveryPin());
        $this->assertNotSame('not_started', $order->preparation_status);
        $this->assertStringContainsString('Pengiriman: J&T → Kurir dalam kota', $order->events()->where('kind', 'order_changed')->value('note'));

        // A changed product: packed again.
        $lines[0]['quantity']++;
        $this->post(route('admin.orders.v2.change', $order), ['revision' => $order->revision, 'items' => $lines, 'fulfillment' => 'local_delivery', 'address' => 'Jl. Sintetis 1, Palu',
            'location_url' => 'https://www.google.com/maps?q=-0.9012345,119.8701234', 'shipping_fee' => '15000', 'shipping_payer' => 'added_to_transfer']);
        $this->assertSame('not_started', $order->fresh()->preparation_status);
    }

    public function test_staff_price_the_changed_order_with_biteship_and_the_price_is_the_servers(): void
    {
        config(['orders.shipping_quotes.enabled' => true, 'services.biteship.key' => 'biteship_test.fake', 'services.biteship.base_url' => 'https://api.biteship.com']);
        Http::fake(['*/v1/rates/couriers' => Http::response(['success' => true, 'pricing' => [
            ['courier_code' => 'jnt', 'courier_service_code' => 'ez', 'price' => 30000, 'shipment_duration_range' => '2 - 3', 'shipment_duration_unit' => 'days'],
        ]])]);
        $order = $this->v2Order('pickup');
        $items = $order->items->map(fn ($item) => ['line_id' => $item->line_id, 'variant_id' => $item->variant_id, 'quantity' => $item->quantity])->all();
        $this->actingAs($this->staff);

        $options = $this->postJson(route('admin.orders.v2.change.quotes', $order), ['items' => $items, 'mode' => 'jnt', 'postcode' => '90111'])->assertOk()->json('options');
        $this->assertSame(['J&T', 21000], [$options[0]['label'], $options[0]['charged']]);
        // An option priced for other products is refused.
        $other = $items;
        $other[0]['quantity']++;
        $this->post(route('admin.orders.v2.change', $order), ['revision' => $order->revision, 'items' => $other, 'fulfillment' => 'intercity',
            'address' => 'Jl. Sintetis 1, Makassar Sulawesi Selatan', 'postcode' => '90111', 'shipping_fee' => '1', 'shipping_option' => $options[0]['id']])
            ->assertSessionHasErrors('shipping_option');

        $this->post(route('admin.orders.v2.change', $order), ['revision' => $order->revision, 'items' => $items, 'fulfillment' => 'intercity',
            'address' => 'Jl. Sintetis 1, Makassar Sulawesi Selatan', 'postcode' => '90111', 'shipping_fee' => '1', 'shipping_option' => $options[0]['id']])
            ->assertSessionHas('order_change');
        $order->refresh();
        $this->assertSame(['intercity', '21000.00', 'added_to_transfer', 'staff'], [$order->fulfillment, $order->shipping_fee, $order->shipping_payer, $order->shipping_quote['chosen_by']]);
    }

    public function test_changes_are_refused_after_handover_for_other_orders_lines_and_without_products(): void
    {
        $order = $this->v2Order('pickup');
        $other = $this->v2Order('pickup');
        $this->actingAs($this->staff);
        $line = fn ($item) => ['line_id' => $item->line_id, 'variant_id' => $item->variant_id, 'quantity' => 1];

        $this->post(route('admin.orders.v2.change', $order), ['revision' => $order->revision, 'fulfillment' => 'pickup', 'items' => [$line($other->items->first())]])
            ->assertRedirect(route('admin.orders.v2.edit', $order))->assertSessionHas('change_error', fn ($message) => str_contains($message, 'Muat ulang'));
        $this->post(route('admin.orders.v2.change', $order), ['revision' => $order->revision, 'fulfillment' => 'pickup', 'items' => []])->assertSessionHasErrors('items');
        $this->assertSame(2, $order->items()->count());

        $order->forceFill(['handover_status' => 'handed_over'])->saveQuietly();
        $this->get(route('admin.orders.v2.edit', $order))->assertOk()->assertSee('Pesanan ini tidak dapat diubah lagi.');
        $this->get(route('admin.orders.show', $order))->assertDontSee('data-change-order', false);
        $this->post(route('admin.orders.v2.change', $order), ['revision' => $order->revision, 'fulfillment' => 'pickup', 'items' => $order->items->map($line)->all()])
            ->assertSessionHas('change_error', 'Barang sudah diserahkan, jadi pesanan tidak dapat diubah lagi.');
    }

    public function test_staff_cancel_a_paid_order_with_the_amount_to_return_and_record_the_refund(): void
    {
        // Owner 2026-11-17: no Staff Order limits that leave work for the admin.
        $order = $this->paidV2Order('pickup');
        $this->actingAs($this->staff);
        $this->get(route('admin.orders.show', $order))->assertSee('Ya, batalkan pesanan')->assertSee('Dikembalikan ke customer (Rp)');
        $this->post(route('admin.orders.v2.cancel', $order), ['revision' => $order->revision, 'reason' => 'Stok habis semua', 'refund_due' => '400000'])
            ->assertSessionHas('order_notice.type', 'success');
        $order->refresh();
        $this->assertSame(['cancelled', 'refund_pending'], [$order->lifecycle, $order->payment_status]);

        $this->post(route('admin.orders.money.refunds', $order), ['revision' => $order->revision, 'amount' => '400000', 'refund_method' => 'transfer'])
            ->assertSessionHas('order_notice.type', 'success');
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    private function variant(string $name, int $price): int
    {
        $template = Product::where('name', 'API Alpha')->firstOrFail();
        $product = Product::create(['name' => $name, 'brand_id' => $template->brand_id, 'category_id' => $template->category_id, 'description' => 'Synthetic',
            'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []], 'gender' => 'Unisex', 'base_price' => $price,
            'publication_status' => 'published', 'is_active' => true, 'availability_source' => 'manual', 'availability_status' => 'available', 'availability_checked_at' => now()]);

        return $product->variants()->create(['volume' => 50, 'price' => $price, 'stock' => 0, 'is_active' => true])->id;
    }
}
