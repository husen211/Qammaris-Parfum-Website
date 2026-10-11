<?php

namespace Tests\Feature;

use App\Actions\Orders\OnlineOrderAdjustments;
use App\Actions\Orders\OnlineOrderDetails;
use App\Actions\Orders\RecordOnlineOrderMoney;
use App\Exceptions\OrderActionNotAllowed;
use App\Models\Brand;
use App\Models\Category;
use App\Models\GuestOrderHistory;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreInfo;
use App\Models\User;
use App\Support\CustomerOrderHistory;
use App\Support\OnlineOrderMessages;
use App\Support\OnlineOrderMoney;
use App\Support\OrderApi\OrderApiSchema;
use App\Support\OrderApi\OrderSerializer;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/** ORD-04 polish (Owner 2026-10-11): Pesanan Saya 10 days, paperbag Rp4.000, WhatsApp, status copy, task links. */
class OrderPolishTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase;

    private const STORE_NUMBER = '6285144924931';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        config(['orders.v2_enabled' => true, 'orders.website_checkout' => true, 'orders.simple_ux' => true]);
    }

    public function test_pesanan_saya_keeps_twenty_orders_for_ten_days_on_this_browser_only(): void
    {
        $orders = collect(range(1, 22))->map(fn () => $this->v2Order('pickup'));
        $cookie = null;
        foreach ($orders as $order) {
            $request = $cookie ? $this->withCookie(CustomerOrderHistory::COOKIE, $cookie) : $this;
            $response = $request->get(route('orders.customer.show', $order->customer_token_encrypted))->assertOk();
            $cookie ??= $response->getCookie(CustomerOrderHistory::COOKIE)?->getValue();
            $this->travel(1)->minutes();
        }
        $this->assertSame(64, strlen($cookie));
        $history = GuestOrderHistory::sole();
        $this->assertSame(hash('sha256', $cookie), $history->key_hash, 'Only the hash is stored');
        $this->assertSame(20, $history->entries()->count(), 'At most 20 newest');
        foreach ($orders as $order) {
            $this->assertStringNotContainsString($order->customer_token_encrypted, $cookie);
        }

        $list = $this->withCookie(CustomerOrderHistory::COOKIE, $cookie)->get(route('orders.customer.mine'))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee($orders->last()->code)->assertDontSee($orders->first()->code.'<', false);
        $this->assertSame(20, substr_count($list->getContent(), 'data-my-order>'));

        // Another browser (no cookie) sees nothing; a forged key finds nothing.
        $this->defaultCookies = [];
        $this->get(route('orders.customer.mine'))->assertOk()->assertSee('Belum ada pesanan di browser ini');
        $this->withCookie(CustomerOrderHistory::COOKIE, str_repeat('a', 64))->get(route('orders.customer.mine'))->assertSee('Belum ada pesanan di browser ini');

        // After 10 days without a new order the list is gone.
        $this->defaultCookies = [];
        $this->travel(11)->days();
        $this->withCookie(CustomerOrderHistory::COOKIE, $cookie)->get(route('orders.customer.mine'))->assertSee('Belum ada pesanan di browser ini');
    }

    public function test_history_can_be_deleted_from_the_device_and_needs_no_session(): void
    {
        $order = $this->v2Order('pickup');
        $cookie = $this->get(route('orders.customer.show', $order->customer_token_encrypted))->getCookie(CustomerOrderHistory::COOKIE)->getValue();
        $this->assertSame(1, GuestOrderHistory::count());
        $this->withCookie(CustomerOrderHistory::COOKIE, $cookie)->post(route('orders.customer.forget'))
            ->assertRedirect(route('orders.customer.mine'))->assertCookieExpired(CustomerOrderHistory::COOKIE);
        $this->assertSame(0, GuestOrderHistory::count());
        // The 10-day list never touches the Laravel session (admin keeps its own lifetime).
        $this->assertNull(session(CustomerOrderHistory::COOKIE));
    }

    public function test_status_page_links_back_and_catalog_navigation_offers_pesanan_saya(): void
    {
        $order = $this->v2Order('pickup');
        $this->get(route('orders.customer.show', $order->customer_token_encrypted))
            ->assertSee('Kembali ke Katalog')->assertSee(route('orders.customer.mine'), false);
        $this->get(route('products.index'))->assertOk()->assertSee('data-orders-href="'.route('orders.customer.mine').'"', false)
            ->assertSee('Pesanan Saya');
    }

    public function test_paperbag_is_four_thousand_once_per_order_and_part_of_every_total(): void
    {
        $with = $this->v2Order('local_delivery', ['packaging' => 'paperbag']);
        $without = $this->v2Order('local_delivery', ['packaging' => 'no_paperbag']);
        $this->assertSame(['4000.00', '404000.00'], [$with->packagingFee(), $with->customerTotal()]);
        $this->assertSame(['0.00', '400000.00'], [$without->packagingFee(), $without->customerTotal()]);

        // Saving the same choice again never adds it twice; switching recomputes the snapshot.
        $with->forceFill(['customer_note' => 'Catatan baru'])->save();
        $with->forceFill(['packaging' => 'paperbag'])->save();
        $this->assertSame('4000.00', $with->fresh()->packagingFee());
        config(['orders.paperbag_fee' => 5000]);
        $this->assertSame('4000.00', $with->fresh()->packagingFee(), 'Snapshot: a later price change never rewrites an order');
        $with->forceFill(['packaging' => 'no_paperbag'])->save();
        $this->assertSame('0.00', $with->fresh()->packagingFee());
        config(['orders.paperbag_fee' => 4000]);

        // API r4.4 draft, payment instructions and the admin page show the same component.
        $api = json_decode(json_encode(OrderSerializer::order($without->fresh())));
        $this->assertSame([], OrderApiSchema::errors($api, 'Order'));
        $paperbag = $this->v2Order('pickup', ['packaging' => 'paperbag']);
        $totals = OrderSerializer::order($paperbag->fresh())['totals'];
        $this->assertSame([400000, 0, 4000, 404000], [$totals['subtotal'], $totals['shipping_charge'], $totals['packaging_fee'], $totals['total']]);
        $this->assertStringContainsString('Paperbag: Rp 4.000', app(OnlineOrderMessages::class)->paymentInstructions($paperbag->fresh()));
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $this->actingAs($staff)->get(route('admin.orders.show', $paperbag))->assertSee('Paperbag')->assertSee('Rp 404.000');
        $this->get(route('orders.customer.show', $paperbag->customer_token_encrypted))->assertSee('data-paperbag-row', false)->assertSee('Rp 404.000');
    }

    public function test_paperbag_change_after_payment_is_a_super_admin_correction_with_refund_rules(): void
    {
        $order = $this->payInFull($this->v2Order('pickup', ['packaging' => 'paperbag']));
        $this->assertSame(['paid', 40400000], [$order->payment_status, OnlineOrderMoney::totals($order)['received']]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $details = app(OnlineOrderDetails::class);
        $this->assertThrows(fn () => $details->updateByAdmin($order, $order->revision, $staff, ['packaging' => 'no_paperbag']), OrderActionNotAllowed::class);

        $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $order = $details->updateByAdmin($order->fresh(), $order->fresh()->revision, $owner, ['packaging' => 'no_paperbag']);
        $this->assertSame(['400000.00', 'paid'], [$order->customerTotal(), $order->payment_status]);
        $this->assertSame(400000, OnlineOrderAdjustments::overpaidCents($order), 'Rp4.000 overpaid, shown for a refund decision');
        $order = app(RecordOnlineOrderMoney::class)->decideRefund($order, $order->revision, $owner, '4000', 'Paperbag dibatalkan');
        $this->assertSame(['pending', 'refund_pending'], [$order->refund_status, $order->payment_status]);
    }

    public function test_checkout_charges_paperbag_once_even_when_replayed(): void
    {
        $variants = [$this->variant('Polish Satu', 100000), $this->variant('Polish Dua', 50000)];
        $this->withSession(['cart' => [$variants[0]->id => ['variant_id' => $variants[0]->id, 'quantity' => 2], $variants[1]->id => ['variant_id' => $variants[1]->id, 'quantity' => 1]]])
            ->get(route('cart.checkout.show'))->assertSee('Total sementara')->assertSee('+ Rp 4.000');
        $form = ['customer_name' => 'E2E Polish', 'customer_phone' => '080000000301', 'delivery' => 'pickup', 'packaging' => 'paperbag', 'payment_preference' => 'transfer',
            'checkout_quote' => session('checkout_quote'), 'checkout_key' => session('checkout_key')];
        $this->post(route('cart.checkout'), $form);
        $this->post(route('cart.checkout'), $form);
        $order = OnlineOrder::sole();
        $this->assertSame(['250000.00', '4000.00', '254000.00'], [$order->subtotal(), $order->packagingFee(), $order->customerTotal()]);
    }

    public function test_production_success_page_offers_kirim_ke_whatsapp_to_the_official_number(): void
    {
        $this->app['env'] = 'production';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        StoreInfo::create(['store_name' => 'Qammaris', 'whatsapp_number' => self::STORE_NUMBER]);
        $variant = $this->variant('Polish Prod', 100000);
        $this->withSession(['cart' => [$variant->id => ['variant_id' => $variant->id, 'quantity' => 1]]])->get(route('cart.checkout.show'));
        $response = $this->post(route('cart.checkout'), ['customer_name' => 'E2E Prod', 'customer_phone' => '080000000302', 'delivery' => 'pickup', 'packaging' => 'no_paperbag',
            'payment_preference' => 'qris', 'checkout_quote' => session('checkout_quote'), 'checkout_key' => session('checkout_key')]);
        $first = $this->followRedirects($response)->assertSee('Kirim ke WhatsApp')->assertSee('data-auto-open="1"', false)->getContent();
        $this->assertStringContainsString('https://wa.me/'.self::STORE_NUMBER.'?text=Halo%20Qammaris', $first, 'Prefilled chat to the official number, no [UAT]');
        $this->assertStringNotContainsString('id="checkout-message"', $first, 'Production never asks to copy the message');
        $order = OnlineOrder::sole();
        $this->get(route('orders.customer.show', $order->customer_token_encrypted))->assertSee('Kirim ke WhatsApp')->assertDontSee('data-auto-open="1"', false);
    }

    public function test_uat_opt_in_is_per_browser_super_admin_only_and_prefixes_uat(): void
    {
        $this->app['env'] = 'uat';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['store.whatsapp_test_number' => null]);
        StoreInfo::create(['store_name' => 'Qammaris', 'whatsapp_number' => self::STORE_NUMBER]);
        $order = $this->v2Order('pickup');
        $order->forceFill(['lifecycle' => 'draft', 'source' => 'website'])->save();
        $page = fn ($request) => $request->get(route('orders.customer.show', $order->customer_token_encrypted))->getContent();
        $this->assertStringNotContainsString(self::STORE_NUMBER, $page($this), 'Default UAT visitor: protected');

        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $this->actingAs($staff)->post(route('admin.orders.uat-whatsapp'), ['on' => 1])->assertForbidden();
        $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $cookie = $this->actingAs($owner)->post(route('admin.orders.uat-whatsapp'), ['on' => 1])->getCookie(config('store.uat_official_cookie'));
        $this->assertSame('1', $cookie->getValue());
        $optedIn = $page($this->withCookie(config('store.uat_official_cookie'), '1'));
        $this->assertStringContainsString('https://wa.me/'.self::STORE_NUMBER.'?text=%5BUAT%5D%20Halo%20Qammaris', $optedIn);

        $this->app['env'] = 'production';
        $this->actingAs($owner)->post(route('admin.orders.uat-whatsapp'), ['on' => 1])->assertNotFound();
    }

    public function test_receipt_panel_and_timeline_copy_with_vehicle_icons(): void
    {
        $local = $this->handedOver('local_delivery');
        $page = $this->get(route('orders.customer.show', $local->customer_token_encrypted))->assertSee('Pesanan sudah saya terima')
            ->assertSee('data-shipping-icon="motor"', false)->assertSee('Dalam pengiriman kurir dalam kota')
            ->assertSee('Menunggu konfirmasi penerimaan dari Anda.')->assertDontSee('tombol di bawah');
        $this->assertLessThan(strpos($page->getContent(), 'Produk yang dipesan'), strpos($page->getContent(), 'data-received-form'), 'Receipt button above the items');
        $jnt = $this->handedOver('intercity');
        $this->get(route('orders.customer.show', $jnt->customer_token_encrypted))->assertSee('data-shipping-icon="jnt"', false)->assertSee('Dikirim lewat J&amp;T', false);
        $this->get(route('orders.customer.show', $local->customer_token_encrypted))->assertSee('Pengiriman Dalam Kota')->assertDontSee('Antar Lokal');
    }

    public function test_task_link_goes_straight_to_the_app_of_the_same_environment(): void
    {
        $order = $this->v2Order('local_delivery');
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $this->app['env'] = 'uat';
        config(['orders_api.app_task_links' => true, 'orders_api.app_orders_url' => 'https://uat-app.trycloudflare.com/orders']);
        $this->actingAs($staff)->get(route('admin.orders.show', $order))->assertSee('Buka di Qammaris App: https://uat-app.trycloudflare.com/orders/'.$order->public_id);
        config(['orders_api.app_orders_url' => 'https://qammarisapp.com/orders']);
        $this->get(route('admin.orders.show', $order))->assertDontSee('qammarisapp.com/orders/'.$order->public_id)
            ->assertSee(route('admin.orders.task', $order->public_id), false);
        $this->app['env'] = 'production';
        $this->get(route('admin.orders.show', $order))->assertSee('Buka di Qammaris App: https://qammarisapp.com/orders/'.$order->public_id);
        config(['orders_api.app_orders_url' => 'https://uat-app.trycloudflare.com/orders']);
        $this->get(route('admin.orders.show', $order))->assertDontSee('trycloudflare.com/orders/'.$order->public_id);
    }

    private function handedOver(string $fulfillment): OnlineOrder
    {
        $order = $this->payInFull($this->v2Order($fulfillment));
        $order->forceFill(['preparation_status' => 'packed', 'handover_status' => 'handed_over', 'handed_to' => $fulfillment === 'intercity' ? 'jnt' : 'courier',
            'handed_over_at' => now(), 'jnt_status' => $fulfillment === 'intercity' ? 'picked_up' : null])->save();

        return $order->fresh();
    }

    private function variant(string $name, int $price): ProductVariant
    {
        $product = Product::create([
            'brand_id' => Brand::firstOrCreate(['name' => 'E2E Brand Sintetis'], ['is_active' => true])->id, 'category_id' => Category::firstOrCreate(['name' => 'EDP'])->id,
            'name' => $name, 'description' => 'Sintetis.', 'base_price' => $price, 'gender' => 'Unisex', 'is_active' => true,
            'availability_status' => 'available', 'availability_source' => 'qammaris_app',
        ]);

        return ProductVariant::create(['product_id' => $product->id, 'volume' => 50, 'price' => $price, 'sku' => 'POL-'.uniqid(), 'stock' => 1, 'is_active' => true]);
    }
}
