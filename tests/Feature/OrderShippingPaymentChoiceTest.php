<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\InquiryWhatsApp;
use App\Support\OrderApi\OrderApiSchema;
use App\Support\OrderApi\OrderSerializer;
use App\Support\Rupiah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/** ORD-10: the customer picks "Metode pembayaran ongkir"; staff only enter the amount. */
class OrderShippingPaymentChoiceTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        RateLimiter::clear('checkout-min|127.0.0.1');
        config(['orders.v2_enabled' => true, 'orders.website_checkout' => true, 'orders.simple_ux' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
    }

    public function test_checkout_asks_the_local_courier_payment_and_stores_it_as_the_shipping_payer(): void
    {
        $variant = $this->variant(120000);

        $this->review($variant);
        $this->post(route('cart.checkout'), $this->checkoutForm('local_delivery'))->assertSessionHasErrors('shipping_payment');
        $this->post(route('cart.checkout'), $this->checkoutForm('local_delivery', ['shipping_payment' => 'store_free']))->assertSessionHasErrors('shipping_payment');
        $this->assertSame(0, OnlineOrder::count());

        $this->post(route('cart.checkout'), $this->checkoutForm('local_delivery', ['shipping_payment' => 'cash_to_driver']))->assertSessionHasNoErrors();
        $driver = OnlineOrder::sole();
        $this->assertSame('cash_to_driver', $driver->shipping_payer);
        $this->get(route('orders.customer.show', $driver->customer_token_encrypted))->assertSee('Dibayar langsung ke driver')
            ->assertSee('Metode pembayaran ongkir')->assertSee('Bayar langsung ke driver');
        $this->assertStringContainsString('Ongkir: bayar langsung ke driver', app(InquiryWhatsApp::class)->websiteOrderMessage($driver));

        // J&T has no driver to pay, so its ongkir is part of the payment; pickup has none and is not asked.
        $this->review($variant);
        $this->post(route('cart.checkout'), $this->checkoutForm('intercity', ['shipping_payment' => 'cash_to_driver']))->assertSessionHasNoErrors();
        $this->assertSame('added_to_transfer', OnlineOrder::latest('id')->first()->shipping_payer);
        $this->review($variant);
        $this->post(route('cart.checkout'), $this->checkoutForm('pickup'))->assertSessionHasNoErrors();
        $this->assertNull(OnlineOrder::latest('id')->first()->shipping_payer);
    }

    public function test_customer_link_choice_fills_the_staff_ongkir_step_and_paying_the_driver_keeps_ongkir_out_of_the_total(): void
    {
        $order = $this->v2Order(null);
        $token = $order->customer_token_encrypted;
        $this->get(route('orders.customer.show', $token))->assertSee('Metode pembayaran ongkir')->assertSee('name="shipping_payment"', false);

        $this->post(route('orders.customer.submit', $token), $this->linkForm('local_delivery'))->assertSessionHasErrors('shipping_payment');
        $this->post(route('orders.customer.submit', $token), $this->linkForm('local_delivery', ['shipping_payment' => 'cash_to_driver']))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(['cash_to_driver', null, 'details_received'], [$order->shipping_payer, $order->shipping_fee, $order->stage]);

        // Paid to the driver, so the transfer total is final before any ongkir amount: payment can be requested now.
        $this->actingAs($this->staff)->get(route('admin.orders.show', $order))
            ->assertSee('<option value="cash_to_driver" selected>', false)->assertSee('jadi total sudah final', false)
            ->assertSee('data-payment-instructions', false)->assertSee('Ongkir: dibayar langsung ke driver');
        $this->get(route('orders.customer.show', $token))->assertSee('Total pembayaran')->assertSee('Dibayar langsung ke driver');
        $this->post(route('admin.orders.v2.shipping', $order), ['revision' => $order->revision, 'shipping_fee' => '15.000', 'shipping_payer' => 'cash_to_driver'])
            ->assertSessionHas('order_notice.type', 'success');
        $order->refresh();
        $this->assertSame('15000.00', $order->shipping_fee);
        $this->assertSame(Rupiah::minorUnits($order->subtotal()) + Rupiah::minorUnits($order->packagingFee()), Rupiah::minorUnits($order->customerTotal()), 'paid to the driver, not in the transfer');
        $this->assertSame([], OrderApiSchema::errors(json_decode(json_encode(OrderSerializer::order($order))), 'Order'), 'API v1 unchanged');
        $this->get(route('orders.customer.show', $token))->assertSee('Rp 15.000, dibayar langsung ke driver');
    }

    public function test_switching_to_jnt_puts_the_ongkir_in_the_payment_and_pickup_is_not_asked(): void
    {
        $order = $this->v2Order(null);
        $token = $order->customer_token_encrypted;
        $this->post(route('orders.customer.submit', $token), $this->linkForm('local_delivery', ['shipping_payment' => 'cash_to_driver']));
        $this->post(route('orders.customer.submit', $token), $this->linkForm('intercity', ['address' => 'Jl. Sintetis 12, Kel. Uji, Makassar']))->assertSessionHasNoErrors();
        $this->assertSame('added_to_transfer', $order->fresh()->shipping_payer);

        $pickup = $this->v2Order(null);
        $this->post(route('orders.customer.submit', $pickup->customer_token_encrypted), $this->linkForm('pickup'))->assertSessionHasNoErrors();
        $this->assertNull($pickup->fresh()->shipping_payer);
    }

    public function test_free_ongkir_and_any_payment_stay_store_decisions(): void
    {
        // Free ongkir chosen by the store: the customer is no longer asked and cannot change it.
        $free = $this->v2Order(null);
        $free->forceFill(['shipping_payer' => 'store_free', 'shipping_fee' => 0])->save();
        $this->get(route('orders.customer.show', $free->customer_token_encrypted))->assertDontSee('name="shipping_payment"', false);
        $this->post(route('orders.customer.submit', $free->customer_token_encrypted), $this->linkForm('local_delivery', ['shipping_payment' => 'cash_to_driver']))->assertSessionHasNoErrors();
        $this->assertSame('store_free', $free->fresh()->shipping_payer);
        $this->get(route('orders.customer.show', $free->customer_token_encrypted))->assertSee('Gratis ongkir');

        // After money came in, the total is fixed: the link no longer asks or changes how the ongkir is paid.
        $paid = $this->v2Order();
        $paid->forceFill(['shipping_payer' => 'added_to_transfer', 'shipping_fee' => 10000])->save();
        $this->actingAs($this->staff)->post(route('admin.orders.v2.payments', $paid), ['revision' => $paid->fresh()->revision, 'amount' => '50000', 'payment_method' => 'transfer']);
        $this->assertFalse($paid->fresh()->customerSetsShippingPayment());
        $this->post(route('orders.customer.submit', $paid->customer_token_encrypted), $this->linkForm('local_delivery', ['shipping_payment' => 'cash_to_driver']));
        $this->assertSame('added_to_transfer', $paid->fresh()->shipping_payer);
    }

    private function linkForm(string $fulfillment, array $overrides = []): array
    {
        return array_replace([
            'customer_name' => 'E2E Rina Sintetis', 'customer_phone' => '081200000099', 'fulfillment' => $fulfillment,
            'address' => $fulfillment === 'pickup' ? null : 'Depan masjid', 'packaging' => 'paperbag', 'payment_preference' => 'transfer',
        ], $overrides);
    }

    private function review(ProductVariant $variant): void
    {
        $this->withSession(['cart' => [$variant->id => ['variant_id' => $variant->id, 'quantity' => 1]]])
            ->get(route('cart.checkout.show'))->assertOk()->assertSee('Metode pembayaran ongkir');
    }

    private function checkoutForm(string $delivery, array $overrides = []): array
    {
        return array_replace([
            'customer_name' => 'E2E Pembeli Sintetis', 'customer_phone' => '080000000101', 'delivery' => $delivery,
            'customer_address' => 'Jl. Sintetis No. 1, patokan uji', 'packaging' => 'paperbag', 'payment_preference' => 'transfer',
            'checkout_quote' => session('checkout_quote'), 'checkout_key' => session('checkout_key'),
        ], $overrides);
    }

    private function variant(int $price): ProductVariant
    {
        $brand = Brand::firstOrCreate(['name' => 'E2E Brand Sintetis'], ['is_active' => true]);
        $category = Category::firstOrCreate(['name' => 'EDP']);
        $product = Product::create([
            'brand_id' => $brand->id, 'category_id' => $category->id, 'name' => 'Ongkir Sintetis', 'description' => 'Sintetis.',
            'base_price' => $price, 'gender' => 'Unisex', 'is_active' => true,
            'availability_status' => 'available', 'availability_source' => 'qammaris_app',
        ]);

        return ProductVariant::create(['product_id' => $product->id, 'volume' => 50, 'price' => $price, 'sku' => 'ORD10-'.uniqid(), 'stock' => 5, 'is_active' => true]);
    }
}
