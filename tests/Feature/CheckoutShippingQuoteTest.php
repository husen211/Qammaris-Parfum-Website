<?php

namespace Tests\Feature;

use App\Actions\Orders\ShippingQuotes;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\InquiryWhatsApp;
use App\Support\OnlineOrderStepper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/** ORD-13: checkout prices GoSend, GrabExpress and J&T through Biteship and locks the discounted ongkir on the order. */
class CheckoutShippingQuoteTest extends TestCase
{
    use RefreshDatabase;

    private const RATES = 'api.biteship.com/v1/rates/couriers';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        foreach (['checkout-min', 'shipping-quote-min', 'shipping-area-min'] as $limit) {
            RateLimiter::clear($limit.'|127.0.0.1');
        }
        config([
            'orders.v2_enabled' => true, 'orders.website_checkout' => true, 'orders.simple_ux' => true,
            'orders.shipping_quotes.enabled' => true,
            'services.biteship.key' => 'biteship_test.synthetic', 'services.biteship.base_url' => 'https://api.biteship.com',
        ]);
    }

    public function test_instant_couriers_are_priced_with_the_store_discount_and_the_order_locks_that_ongkir(): void
    {
        Http::fake([self::RATES => Http::response($this->pricing([['gojek', 'instant', 20500, '1 - 2', 'hours'], ['grab', 'instant', 18000, '1 - 3', 'hours']]))]);
        $this->review([$this->variant('Oud Sintetis', 425000)->id => 1, $this->variant('Musk Sintetis', 189000)->id => 2]);

        $this->get(route('cart.checkout.show'))->assertOk()->assertSee('data-shipping-quotes', false)->assertSee('Antar ke lokasimu sekarang?')
            ->assertSee('Kurir instan')->assertSee('Pilih titik sendiri di peta');
        $response = $this->postJson(route('cart.checkout.shipping'), ['mode' => 'instant', 'latitude' => -0.916, 'longitude' => 119.886])->assertOk();
        $options = collect($response->json('options'))->keyBy('provider');

        $this->assertSame([18000, 18000, 0, 0, 'Tiba 1–3 jam'], [$options['grab']['price'], $options['grab']['charged'], $options['grab']['discount'], $options['grab']['percent'], $options['grab']['eta']]);
        // Rp20.500 is 15% off from Rp20.000 up: Rp17.425, rounded down to Rp17.000.
        $this->assertSame([20500, 17000, 3500, 15, 'GoSend'], [$options['gosend']['price'], $options['gosend']['charged'], $options['gosend']['discount'], $options['gosend']['percent'], $options['gosend']['label']]);
        $this->assertStringNotContainsString('latitude', $response->getContent(), 'the pin stays on the server');
        $this->assertStringNotContainsString('cart', json_encode($response->json('options')));
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://'.self::RATES
            && $request->header('Authorization') === ['biteship_test.synthetic']
            && $request['couriers'] === 'grab,gojek' && $request['destination_latitude'] === -0.916
            && $request['origin_latitude'] === config('orders.shipping_quotes.origin.latitude')
            && array_column($request['items'], 'weight') === [800, 800] && array_column($request['items'], 'quantity') === [1, 2]);

        $this->post(route('cart.checkout'), $this->form('local_delivery', ['shipping_option' => $options['gosend']['id']]))->assertRedirect();
        $order = OnlineOrder::sole();
        $this->assertSame(['17000.00', 'added_to_transfer', '20500.00', 'api'], [$order->shipping_fee, $order->shipping_payer, $order->shipping_estimate, $order->shipping_estimate_source]);
        $this->assertSame(['instant', 'gosend', 17000, 3500, 2400], [$order->shipping_quote['kind'], $order->shipping_quote['provider'], $order->shipping_quote['charged'], $order->shipping_quote['discount'], $order->shipping_quote['weight_grams']]);
        $this->assertSame(-0.916, $order->shipping_quote['destination']['latitude']);
        $this->assertSame('https://www.google.com/maps?q=-0.916,119.886', $order->location_url, 'the pin is the Sharelok for staff and the App');
        $this->assertSame('824000.00', $order->customerTotal(), '803.000 + paperbag 4.000 + ongkir 17.000');
        $this->assertTrue(OnlineOrderStepper::totalIsFinal($order));
        $this->assertFalse($order->customerSetsShippingPayment(), 'a locked ongkir cannot be switched to "bayar ke driver"');
        $this->assertArrayNotHasKey('shipping_quote', $order->toArray());

        $this->get(route('orders.customer.show', $order->customer_token_encrypted))->assertOk()
            ->assertSee('Ongkir GoSend')->assertSee('Potongan ongkir 15% dari Qammaris')->assertSee('Rp 20.500')->assertSee('Rp 17.000')
            ->assertSee('mengonfirmasi stok dan pembayaran');
        $message = app(InquiryWhatsApp::class)->websiteOrderMessage($order);
        $this->assertStringContainsString('Ongkir GoSend: Rp 17.000 (potongan ongkir 15% dari Qammaris)', $message);
        $this->assertStringContainsString('Total: Rp 824.000', $message);
        $this->assertStringContainsString('Mohon konfirmasi stok dan pembayarannya.', $message);
    }

    public function test_jnt_is_priced_from_the_searched_area_with_30_percent_off_capped_at_30000(): void
    {
        Http::fake([
            'api.biteship.com/v1/maps/areas*' => Http::response(['success' => true, 'areas' => [
                ['id' => 'IDNP28IDNC248IDND2564IDZ90141', 'name' => 'Makassar, Makassar, Sulawesi Selatan. 90141', 'postal_code' => 90141],
                ['id' => 'bad id!', 'name' => 'Dibuang'],
            ]]),
            self::RATES => Http::response($this->pricing([['jnt', 'ez', 69000, '2 - 3', 'days']])),
        ]);
        $this->review([$this->variant('Oud Sintetis', 425000)->id => 3]);

        $this->getJson(route('cart.checkout.areas', ['q' => 'Makassar']))->assertOk()
            ->assertExactJson(['areas' => [['id' => 'IDNP28IDNC248IDND2564IDZ90141', 'label' => 'Makassar, Makassar, Sulawesi Selatan. 90141']]]);
        $this->getJson(route('cart.checkout.areas', ['q' => 'Ma']))->assertUnprocessable();
        $option = $this->postJson(route('cart.checkout.shipping'), ['mode' => 'jnt', 'area_id' => 'IDNP28IDNC248IDND2564IDZ90141', 'area_label' => 'Makassar, Makassar, Sulawesi Selatan. 90141'])
            ->assertOk()->json('options.0');

        // Rp69.000 − 30% (Rp20.700) = Rp48.300, rounded down to Rp48.000.
        $this->assertSame(['jnt', 'J&T', 69000, 48000, 21000, 30, 'Tiba 2–3 hari'], [$option['provider'], $option['label'], $option['price'], $option['charged'], $option['discount'], $option['percent'], $option['eta']]);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://'.self::RATES && $request['origin_postal_code'] === 94221
            && $request['destination_area_id'] === 'IDNP28IDNC248IDND2564IDZ90141' && $request['couriers'] === 'jnt');

        $this->post(route('cart.checkout'), $this->form('intercity', ['shipping_option' => $option['id']]))->assertRedirect();
        $order = OnlineOrder::sole();
        $this->assertSame(['48000.00', 'added_to_transfer', 'jnt', 'Makassar, Makassar, Sulawesi Selatan. 90141'], [$order->shipping_fee, $order->shipping_payer, $order->shipping_quote['provider'], $order->shipping_quote['destination']['area_label']]);
    }

    public function test_discount_rules_round_down_and_respect_the_threshold_and_cap(): void
    {
        $this->assertSame(['charged' => 19999, 'discount' => 0, 'percent' => 0], ShippingQuotes::discounted('instant', 19999));
        $this->assertSame(['charged' => 17000, 'discount' => 3000, 'percent' => 15], ShippingQuotes::discounted('instant', 20000));
        $this->assertSame(['charged' => 59500, 'discount' => 11000, 'percent' => 15], ShippingQuotes::discounted('instant', 70500));
        $this->assertSame(['charged' => 5500, 'discount' => 2500, 'percent' => 30], ShippingQuotes::discounted('jnt', 8000));
        $this->assertSame(['charged' => 90000, 'discount' => 30000, 'percent' => 30], ShippingQuotes::discounted('jnt', 120000), 'at most Rp30.000 off');
    }

    public function test_an_option_of_another_cart_delivery_or_time_is_refused_and_nothing_is_ordered(): void
    {
        Http::fake([self::RATES => Http::response($this->pricing([['grab', 'instant', 15000, '1 - 3', 'hours']]))]);
        $first = $this->variant('Oud Sintetis', 425000);
        $this->review([$first->id => 1]);
        $id = $this->postJson(route('cart.checkout.shipping'), ['mode' => 'instant', 'latitude' => -0.88, 'longitude' => 119.87])->json('options.0.id');

        $refused = 'Ongkir perlu dihitung ulang';
        $this->post(route('cart.checkout'), $this->form('local_delivery', ['shipping_option' => Str::random(20)]))->assertSessionHas('error', fn ($error) => str_contains($error, $refused));
        $this->post(route('cart.checkout'), $this->form('intercity', ['shipping_option' => $id]))->assertSessionHas('error', fn ($error) => str_contains($error, $refused));
        $this->post(route('cart.checkout'), $this->form('local_delivery', ['shipping_option' => '17000']))->assertSessionHasErrors('shipping_option');
        $this->post(route('cart.checkout'), $this->form('local_delivery'))->assertSessionHasErrors('shipping_option');
        $this->travel(31)->minutes();
        $this->post(route('cart.checkout'), $this->form('local_delivery', ['shipping_option' => $id]))->assertSessionHas('error', fn ($error) => str_contains($error, $refused));
        $this->travelBack();

        // Same session, different cart: the price was for the old basket.
        $id = $this->postJson(route('cart.checkout.shipping'), ['mode' => 'instant', 'latitude' => -0.88, 'longitude' => 119.87])->json('options.0.id');
        $this->review([$first->id => 1, $this->variant('Musk Sintetis', 189000)->id => 1]);
        $this->post(route('cart.checkout'), $this->form('local_delivery', ['shipping_option' => $id]))->assertSessionHas('error', fn ($error) => str_contains($error, $refused));
        $this->assertSame(0, OnlineOrder::count());
    }

    public function test_without_a_courier_price_the_customer_still_orders_and_staff_confirm_ongkir(): void
    {
        $this->review([$this->variant('Oud Sintetis', 425000)->id => 1]);
        $quote = fn () => $this->postJson(route('cart.checkout.shipping'), ['mode' => 'instant', 'latitude' => -0.88, 'longitude' => 119.87])->assertOk();

        Http::fake([self::RATES => Http::sequence()
            ->push(['success' => false, 'code' => 40001010, 'error' => 'No courier available for requested location.'], 400)
            ->push(['error' => 'down'], 500)]);
        $quote()->assertExactJson(['options' => [], 'reason' => 'no_courier']);
        $this->assertSame('unavailable', $quote()->json('reason'));
        $this->postJson(route('cart.checkout.shipping'), ['mode' => 'instant', 'latitude' => 3.59, 'longitude' => 98.67])->assertExactJson(['options' => [], 'reason' => 'outside_area']);
        Http::assertSentCount(2);

        $this->post(route('cart.checkout'), $this->form('local_delivery', ['shipping_option' => 'manual']))->assertSessionHasErrors('shipping_payment');
        $this->post(route('cart.checkout'), $this->form('local_delivery', ['shipping_option' => 'manual', 'shipping_payment' => 'cash_to_driver']))->assertRedirect();
        $order = OnlineOrder::sole();
        $this->assertSame([null, 'cash_to_driver', null], [$order->shipping_fee, $order->shipping_payer, $order->shipping_quote]);
    }

    public function test_staff_may_lower_a_locked_ongkir_but_never_raise_it_or_send_it_to_the_driver(): void
    {
        Http::fake([self::RATES => Http::response($this->pricing([['gojek', 'instant', 20500, '1 - 2', 'hours']]))]);
        $this->review([$this->variant('Oud Sintetis', 425000)->id => 1]);
        $id = $this->postJson(route('cart.checkout.shipping'), ['mode' => 'instant', 'latitude' => -0.916, 'longitude' => 119.886])->json('options.0.id');
        $this->post(route('cart.checkout'), $this->form('local_delivery', ['shipping_option' => $id]));
        $order = OnlineOrder::sole();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh());
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('data-ongkir-quote', false)
            ->assertSee('Harga kurir Rp 20.500')->assertSee('potongan Rp 3.500')->assertSee('ditagih Rp 17.000')
            ->assertSee('https://www.google.com/maps?q=-0.916,119.886', false)->assertSee('ongkir GoSend sudah dihitung otomatis');

        $shipping = fn (array $data) => $this->post(route('admin.orders.v2.shipping', $order), ['revision' => $order->fresh()->revision, ...$data]);
        $shipping(['shipping_fee' => '20500', 'shipping_payer' => 'added_to_transfer'])->assertSessionHas('order_notice.type', 'error');
        $shipping(['shipping_fee' => '17000', 'shipping_payer' => 'cash_to_driver'])->assertSessionHas('order_notice.type', 'error');
        $this->assertSame('17000.00', $order->fresh()->shipping_fee);
        $shipping(['shipping_fee' => '15000', 'shipping_payer' => 'added_to_transfer'])->assertSessionHas('order_notice.type', 'success');
        $this->assertSame('15000.00', $order->fresh()->shipping_fee);
    }

    public function test_switched_off_or_without_a_key_the_checkout_stays_as_before(): void
    {
        $this->review([$this->variant('Oud Sintetis', 425000)->id => 1]);
        foreach ([['orders.shipping_quotes.enabled' => false], ['services.biteship.key' => null]] as $off) {
            config($off);
            $this->get(route('cart.checkout.show'))->assertOk()->assertDontSee('data-shipping-quotes', false)->assertSee('Pengiriman Dalam Kota');
            $this->postJson(route('cart.checkout.shipping'), ['mode' => 'instant', 'latitude' => -0.88, 'longitude' => 119.87])->assertNotFound();
            $this->getJson(route('cart.checkout.areas', ['q' => 'Palu']))->assertNotFound();
        }
        $this->post(route('cart.checkout'), $this->form('local_delivery', ['shipping_payment' => 'added_to_transfer']))->assertRedirect();
        $this->assertNull(OnlineOrder::sole()->shipping_quote);
    }

    private function pricing(array $rates): array
    {
        return ['success' => true, 'pricing' => array_map(fn ($rate) => [
            'courier_code' => $rate[0], 'courier_service_code' => $rate[1], 'price' => $rate[2],
            'shipment_duration_range' => $rate[3], 'shipment_duration_unit' => $rate[4],
        ], $rates)];
    }

    private function review(array $lines): void
    {
        $cart = [];
        foreach ($lines as $id => $quantity) {
            $cart[$id] = ['variant_id' => $id, 'quantity' => $quantity];
        }
        $this->withSession(['cart' => $cart])->get(route('cart.checkout.show'))->assertOk();
    }

    private function form(string $delivery, array $overrides = []): array
    {
        return array_replace([
            'customer_name' => 'E2E Pembeli Sintetis', 'customer_phone' => '080000000101', 'delivery' => $delivery,
            'customer_address' => 'Jl. Sintetis No. 1, patokan uji', 'packaging' => 'paperbag', 'payment_preference' => 'qris',
            'checkout_quote' => session('checkout_quote'), 'checkout_key' => session('checkout_key'),
        ], $overrides);
    }

    private function variant(string $name, int $price): ProductVariant
    {
        $brand = Brand::firstOrCreate(['name' => 'E2E Brand Sintetis'], ['is_active' => true]);
        $category = Category::firstOrCreate(['name' => 'EDP']);
        $product = Product::create([
            'brand_id' => $brand->id, 'category_id' => $category->id, 'name' => $name, 'description' => 'Sintetis.',
            'base_price' => $price, 'gender' => 'Unisex', 'is_active' => true,
            'availability_status' => 'available', 'availability_source' => 'qammaris_app',
        ]);

        return ProductVariant::create(['product_id' => $product->id, 'volume' => 50, 'price' => $price, 'sku' => 'ORD13-'.uniqid(), 'stock' => 5, 'is_active' => true]);
    }
}
