<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOnlineOrder;
use App\Exceptions\CheckoutChanged;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\IntegrationOutbox;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\OnlineOrderState;
use App\Support\OrderApi\OrderApiSchema;
use App\Support\OrderApi\OrderSerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Concerns\SignsOrderApiRequests;
use Tests\TestCase;

/** ORD-04: the website cart checkout saves a guest order that waits for Staff Order confirmation. */
class WebsiteCheckoutOrderTest extends TestCase
{
    use RefreshDatabase, SignsOrderApiRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        RateLimiter::clear('checkout-min|127.0.0.1');
        config(['orders.v2_enabled' => true, 'orders.website_checkout' => true, 'orders.simple_ux' => true, 'orders_api.webhook_enabled' => true]);
    }

    public function test_guest_pickup_checkout_saves_a_draft_order_without_an_admin_and_opens_whatsapp_once(): void
    {
        $variant = $this->variant('Citrus Sintetis', 150000);
        $usersBefore = User::count();
        $this->review([$variant->id => 2]);

        $response = $this->post(route('cart.checkout'), $this->form('pickup', ['customer_address' => 'Tidak dipakai untuk ambil di toko']));
        $order = OnlineOrder::sole();
        $response->assertRedirect(route('orders.customer.show', $order->customer_token_encrypted))
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');

        $this->assertSame(['website', 'draft', null, 'v2', 'awaiting_customer'], [$order->source, $order->lifecycle, $order->created_by, $order->state_model, $order->stage]);
        $this->assertSame(['E2E Pembeli Sintetis', '080000000101', 'pickup', null, 'paperbag', 'qris'], [$order->customer_name, $order->customer_phone, $order->fulfillment, $order->address, $order->packaging, $order->payment_preference]);
        $this->assertSame(['unpaid', null, null], [$order->payment_status, $order->shipping_fee, $order->shipping_estimate]);
        $this->assertSame([2, '150000.00', null], [$order->items->sole()->quantity, $order->items->sole()->unit_price, $order->items->sole()->weight_grams]);
        $event = $order->events()->sole();
        $this->assertSame(['created', 'customer', null], [$event->kind, $event->actor_type, $event->actor_user_id]);
        $this->assertSame($usersBefore, User::count(), 'no stand-in admin account');
        $this->assertSame(0, Customer::count(), 'a phone number never creates a repeat customer');
        $this->assertNull(session('cart'), 'the cart is emptied once the order exists');
        $this->assertNull(OnlineOrderState::queue($order), 'draft is not App work');

        // Success page = the customer link: order number, WhatsApp opened once by script, data locked.
        $page = $this->followRedirects($response)->assertOk()->assertSee('Pesanan tersimpan')->assertSee($order->code)
            ->assertSee('data-auto-open="1"', false)->assertDontSee('Ubah data')->assertSee('Sampaikan lewat WhatsApp', false);
        preg_match('/href="(https:\/\/wa\.me\/[^"]+)" rel="noopener noreferrer" data-checkout-whatsapp/', $page->getContent(), $match);
        parse_str((string) parse_url(html_entity_decode($match[1]), PHP_URL_QUERY), $query);
        $this->assertStringContainsString('Nomor pesanan: *'.$order->code.'*', $query['text']);
        $this->assertStringContainsString('Pengiriman: Ambil di toko', $query['text']);
        $this->assertStringNotContainsString('080000000101', $query['text'], 'the chat names the order; contact data stays in the order');
    }

    public function test_whatsapp_not_sent_keeps_the_order_and_the_page_offers_whatsapp_again(): void
    {
        $this->review([$this->variant('Oud Sintetis', 90000)->id => 1]);
        $this->post(route('cart.checkout'), $this->form('intercity'));
        $order = OnlineOrder::sole();

        $this->get(route('orders.customer.show', $order->customer_token_encrypted))->assertSee('data-auto-open="1"', false);
        // Later visit (WhatsApp closed, nothing sent): no automatic reopen, a retry button instead.
        $this->get(route('orders.customer.show', $order->customer_token_encrypted))->assertOk()
            ->assertSee('Buka WhatsApp lagi')->assertDontSee('data-auto-open="1"', false)->assertSee('Pesanan Anda tetap tersimpan')
            ->assertSee('Menunggu konfirmasi staf');
        $this->assertSame('draft', $order->fresh()->lifecycle, 'opening or not opening WhatsApp changes nothing');
    }

    public function test_many_products_and_the_three_delivery_types_store_the_right_address_fields(): void
    {
        $a = $this->variant('Amber Sintetis', 100000);
        $b = $this->variant('Musk Sintetis', 250000);

        $this->review([$a->id => 1, $b->id => 3]);
        $this->post(route('cart.checkout'), $this->form('local_delivery', ['district' => 'Palu Barat', 'subdistrict' => 'Ujuna']));
        $local = OnlineOrder::latest('id')->first();
        $this->assertSame(['local_delivery', 'Jl. Sintetis No. 1, patokan uji', 'Palu Barat', 'Ujuna', 'store'], [$local->fulfillment, $local->address, $local->district, $local->subdistrict, $local->courier_booking_responsibility]);
        $this->assertSame([1, 3], $local->items->pluck('quantity')->all());
        $this->assertSame('850000.00', $local->subtotal());

        $this->review([$a->id => 1]);
        $this->post(route('cart.checkout'), $this->form('intercity', ['customer_postcode' => '94111']));
        $intercity = OnlineOrder::latest('id')->first();
        $this->assertSame(['intercity', '94111', 'not_requested', null], [$intercity->fulfillment, $intercity->postcode, $intercity->jnt_status, $intercity->district]);

        // Delivery needs an address; pickup does not.
        $this->review([$a->id => 1]);
        $this->post(route('cart.checkout'), $this->form('intercity', ['customer_address' => '']))->assertSessionHasErrors('customer_address');
        $this->post(route('cart.checkout'), array_diff_key($this->form('pickup'), ['customer_address' => 1]))->assertSessionDoesntHaveErrors();
        $this->assertSame(3, OnlineOrder::count());
    }

    public function test_choices_are_required_and_validated_on_the_server(): void
    {
        $this->review([$this->variant('Vetiver Sintetis', 80000)->id => 1]);
        $this->post(route('cart.checkout'), array_diff_key($this->form('pickup'), array_flip(['delivery', 'packaging', 'payment_preference'])))
            ->assertSessionHasErrors(['delivery', 'packaging', 'payment_preference']);
        $this->post(route('cart.checkout'), $this->form('maxim', ['payment_preference' => 'cash', 'packaging' => 'box']))
            ->assertSessionHasErrors(['delivery', 'payment_preference', 'packaging']);
        $this->post(route('cart.checkout'), $this->form('local_delivery', ['district' => str_repeat('x', 81), 'customer_phone' => 'abc']))
            ->assertSessionHasErrors(['district', 'customer_phone']);
        $this->assertSame(0, OnlineOrder::count());
    }

    public function test_price_change_or_sold_out_after_review_creates_no_order(): void
    {
        $variant = $this->variant('Rose Sintetis', 120000);
        $this->review([$variant->id => 1]);
        $variant->update(['price' => 125000]);
        $this->post(route('cart.checkout'), $this->form('pickup'))->assertRedirect(route('cart.checkout.show'))
            ->assertSessionHas('error', 'Harga atau isi keranjang berubah. Periksa ringkasan terbaru, lalu lanjutkan kembali.');

        $this->review([$variant->id => 1]);
        $variant->product->update(['availability_status' => 'sold_out']);
        $this->post(route('cart.checkout'), $this->form('pickup'))->assertRedirect(route('cart.index'));
        $this->assertSame(0, OnlineOrder::count());

        // The operation itself re-checks the catalog (price moved between review and save).
        $variant->product->update(['availability_status' => 'available']);
        $this->expectException(CheckoutChanged::class);
        try {
            app(CreateOnlineOrder::class)->fromWebsiteCheckout([$variant->id => ['quantity' => 1, 'price' => '120000.00']], $this->details(), (string) Str::uuid());
        } finally {
            $this->assertSame(0, OnlineOrder::count());
        }
    }

    public function test_double_tap_refresh_and_retry_return_the_same_order(): void
    {
        $this->review([$this->variant('Lavender Sintetis', 70000)->id => 1]);
        $form = $this->form('pickup');
        $first = $this->post(route('cart.checkout'), $form);
        $second = $this->post(route('cart.checkout'), $form);
        $this->assertSame(1, OnlineOrder::count());
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));

        // A concurrent loser reaching the operation after the winner gets the same order back.
        $order = OnlineOrder::sole();
        [$again, $token, $created] = app(CreateOnlineOrder::class)->fromWebsiteCheckout([1 => ['quantity' => 1, 'price' => '1']], $this->details(), $order->checkout_key);
        $this->assertSame([$order->id, $order->customer_token_encrypted, false], [$again->id, $token, $created]);
    }

    public function test_a_checkout_key_from_another_session_is_refused(): void
    {
        $this->review([$this->variant('Sandal Sintetis', 70000)->id => 1]);
        $this->post(route('cart.checkout'), array_replace($this->form('pickup'), ['checkout_key' => (string) Str::uuid()]))
            ->assertRedirect(route('cart.checkout.show'))->assertSessionHas('error');
        $this->assertSame(0, OnlineOrder::count());
    }

    public function test_draft_is_not_payable_not_editable_by_the_customer_and_staff_confirms_it_into_the_normal_flow(): void
    {
        $this->review([$this->variant('Cedar Sintetis', 200000)->id => 1]);
        $this->post(route('cart.checkout'), $this->form('local_delivery', ['district' => 'Palu Barat']));
        $order = OnlineOrder::sole();
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();

        // Customer link cannot change a draft; the chat does.
        $this->post(route('orders.customer.submit', $order->customer_token_encrypted), ['customer_name' => 'Ganti', 'customer_phone' => '080000000102', 'fulfillment' => 'pickup', 'packaging' => 'paperbag', 'payment_preference' => 'transfer'])
            ->assertSessionHas('error');
        $this->assertSame('E2E Pembeli Sintetis', $order->fresh()->customer_name);

        $this->actingAs($staff);
        $this->get(route('admin.orders.index'))->assertSee('1 pesanan website menunggu konfirmasi');
        $this->get(route('admin.orders.index', ['status' => 'website']))->assertSee($order->code);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Konfirmasi pesanan website')->assertSee('Menunggu konfirmasi website')
            ->assertSee('Pembayaran dicatat setelah pesanan website dikonfirmasi.')->assertSee('Palu Barat');

        // Unpaid draft: no payment can be recorded before confirmation; ongkir can be set.
        $this->post(route('admin.orders.v2.payments', $order), ['revision' => $order->revision, 'amount' => '200000', 'payment_method' => 'qris'])
            ->assertSessionHas('order_notice.type', 'error');
        $this->post(route('admin.orders.v2.shipping', $order), ['revision' => $order->fresh()->revision, 'shipping_fee' => '15000', 'shipping_payer' => 'added_to_transfer'])
            ->assertSessionHas('order_notice.type', 'success');
        $this->assertSame(0, $order->payments()->count());

        $this->post(route('admin.orders.v2.confirm-website', $order), ['revision' => $order->fresh()->revision])->assertSessionHas('order_notice.type', 'success');
        $order->refresh();
        $this->assertSame(['active', 'details_received', 'needs_handling'], [$order->lifecycle, $order->stage, OnlineOrderState::queue($order)]);
        $event = $order->events()->reorder('id', 'desc')->first();
        $this->assertSame(['website_confirmed', 'admin', $staff->id], [$event->kind, $event->actor_type, $event->actor_user_id]);
        $this->post(route('admin.orders.v2.confirm-website', $order), ['revision' => $order->revision])->assertSessionHas('order_notice.type', 'error');

        // Then the existing payment flow: total includes the confirmed ongkir.
        $this->post(route('admin.orders.v2.payments', $order), ['revision' => $order->revision, 'amount' => '215000', 'payment_method' => 'qris'])->assertSessionHas('order_notice.type', 'success');
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->get(route('orders.customer.show', $order->customer_token_encrypted))->assertSee('Dikonfirmasi toko')->assertDontSee('Pesanan tersimpan');
    }

    public function test_app_sees_a_valid_draft_with_no_queue_then_an_active_order_after_confirmation(): void
    {
        $this->review([$this->variant('Pine Sintetis', 60000)->id => 1]);
        $this->post(route('cart.checkout'), $this->form('intercity', ['district' => 'Kecamatan Uji']));
        $order = OnlineOrder::sole();

        $draft = json_decode(json_encode(OrderSerializer::order($order)));
        $this->assertSame([], OrderApiSchema::errors($draft, 'Order'), 'r4.2 schema unchanged');
        $this->assertSame(['draft', null, 'website'], [$draft->lifecycle, $draft->queue, $draft->source]);
        $this->assertStringNotContainsString('Kecamatan Uji', json_encode($draft), 'internal phase-2 fields are not in the contract');
        $this->assertSame(1, IntegrationOutbox::count());

        // App condition: the signed read API serves drafts with 200 and queue null (contract §queue row 1), never 404.
        $this->enableOrderApi();
        $read = $this->assertMatchesApiSchema($this->orderApi('GET', '/orders/'.$order->public_id)->assertOk(), 'Order');
        $this->assertSame(['draft', null], [$read->lifecycle, $read->queue]);
        $list = $this->assertMatchesApiSchema($this->orderApi('GET', '/orders?lifecycle=draft')->assertOk(), 'OrderListPage');
        $this->assertSame([$order->public_id], array_map(fn ($row) => $row->id, $list->data));

        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $this->actingAs($staff)->post(route('admin.orders.v2.confirm-website', $order), ['revision' => $order->revision]);
        $active = json_decode(json_encode(OrderSerializer::order($order->fresh())));
        $this->assertSame([], OrderApiSchema::errors($active, 'Order'));
        $this->assertSame(['active', 'needs_handling', 2], [$active->lifecycle, $active->queue, $active->revision]);
        $this->assertSame(2, IntegrationOutbox::count());
    }

    public function test_staff_can_cancel_an_unanswered_website_order(): void
    {
        $this->review([$this->variant('Iris Sintetis', 60000)->id => 1]);
        $this->post(route('cart.checkout'), $this->form('pickup'));
        $order = OnlineOrder::sole();
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $this->actingAs($staff)->post(route('admin.orders.v2.cancel', $order), ['revision' => $order->revision, 'reason' => 'Customer tidak membalas chat'])
            ->assertSessionHas('order_notice.type', 'success');
        $this->assertSame('cancelled', $order->fresh()->lifecycle);
    }

    public function test_flag_off_keeps_the_whatsapp_only_checkout_without_storing_anything(): void
    {
        config(['orders.website_checkout' => false]);
        $variant = $this->variant('Legacy Sintetis', 50000);
        $this->review([$variant->id => 1], false);
        $response = $this->post(route('cart.checkout'), [
            'customer_name' => 'Test Recipient', 'customer_phone' => '081234567890',
            'customer_address' => 'Alamat sintetis untuk pengujian checkout', 'checkout_quote' => session('checkout_quote'),
        ]);
        $this->assertStringStartsWith('https://wa.me/', $response->headers->get('Location'));
        $this->assertSame(0, OnlineOrder::count());
        $this->get(route('cart.checkout.show'))->assertDontSee('checkout_key')->assertDontSee('Cara pengiriman');
    }

    public function test_checkout_creation_is_rate_limited_per_client(): void
    {
        $this->review([$this->variant('Limit Sintetis', 50000)->id => 1]);
        $form = array_replace($this->form('pickup'), ['customer_phone' => 'x']);
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('cart.checkout'), $form)->assertSessionHasErrors('customer_phone');
        }
        $this->post(route('cart.checkout'), $form)->assertStatus(429);
    }

    /** Puts the lines into the cart and opens the checkout like a customer (quote + checkout key in the session). */
    private function review(array $lines, bool $savesOrder = true): void
    {
        $cart = [];
        foreach ($lines as $id => $quantity) {
            $cart[$id] = ['variant_id' => $id, 'quantity' => $quantity];
        }
        $page = $this->withSession(['cart' => $cart])->get(route('cart.checkout.show'))->assertOk();
        if (! $savesOrder) {
            return;
        }
        $page->assertSee('Pengiriman Instan — Kota Palu')->assertSee('Menunggu konfirmasi staf');
    }

    private function form(string $delivery, array $overrides = []): array
    {
        return array_replace([
            'customer_name' => 'E2E Pembeli Sintetis', 'customer_phone' => '080000000101', 'delivery' => $delivery,
            'customer_address' => 'Jl. Sintetis No. 1, patokan uji', 'packaging' => 'paperbag', 'payment_preference' => 'qris',
            'customer_note' => 'Catatan sintetis', 'checkout_quote' => session('checkout_quote'), 'checkout_key' => session('checkout_key'),
        ], $overrides);
    }

    private function details(): array
    {
        return ['customer_name' => 'E2E Pembeli Sintetis', 'customer_phone' => '080000000101', 'fulfillment' => 'pickup', 'packaging' => 'paperbag', 'payment_preference' => 'qris'];
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

        return ProductVariant::create(['product_id' => $product->id, 'volume' => 50, 'price' => $price, 'sku' => 'ORD04-'.uniqid(), 'stock' => 5, 'is_active' => true]);
    }
}
