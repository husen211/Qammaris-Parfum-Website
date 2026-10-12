<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreInfo;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** ORD-07: UAT/staging/local never open a chat with the real store; production keeps its number unchanged. */
class StoreWhatsappEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    private const STORE_NUMBER = '6285144924931';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        StoreInfo::create(['store_name' => 'Qammaris', 'whatsapp_number' => self::STORE_NUMBER]);
        config(['orders.v2_enabled' => true, 'orders.website_checkout' => true, 'orders.simple_ux' => true]);
    }

    public function test_uat_without_a_test_number_saves_the_order_and_offers_a_copyable_message_instead_of_the_store_chat(): void
    {
        $this->asEnvironment('uat', null);
        $variant = $this->variant();
        $this->assertSame('', StoreInfo::first()->whatsapp_number);

        $this->withSession(['cart' => [$variant->id => ['variant_id' => $variant->id, 'quantity' => 1]]]);
        $this->get(route('cart.index'))->assertOk()->assertDontSee(self::STORE_NUMBER);
        $checkout = $this->get(route('cart.checkout.show'))->assertOk()->assertSee('Pesan sekarang')->assertDontSee(self::STORE_NUMBER);
        $this->assertDoesNotMatchRegularExpression('/\sdisabled[\s>=]/', $this->submitButton($checkout->getContent()));

        $response = $this->post(route('cart.checkout'), $this->form());
        $order = OnlineOrder::sole();
        $page = $this->followRedirects($response)->assertOk()->assertSee('Salin pesan')->assertSee('Nomor pesanan: *'.$order->code.'*', false)
            ->assertDontSee('noreferrer" data-checkout-whatsapp', false)->assertDontSee('wa.me/'.self::STORE_NUMBER, false);
        $this->assertStringNotContainsString(self::STORE_NUMBER, $page->getContent());

        // Other public pages: no link to the real store either.
        foreach ([route('products.show', $variant->product), route('store.location'), route('orders.customer.show', $order->customer_token_encrypted)] as $url) {
            $this->assertStringNotContainsString(self::STORE_NUMBER, $this->get($url)->assertOk()->getContent(), $url);
        }
    }

    public function test_uat_with_a_test_number_opens_only_that_number(): void
    {
        $this->asEnvironment('uat', '0800-0000-9999');
        $variant = $this->variant();
        $this->withSession(['cart' => [$variant->id => ['variant_id' => $variant->id, 'quantity' => 1]]]);
        $this->get(route('cart.checkout.show'))->assertSee('Pesan &amp; lanjut ke WhatsApp', false);
        $response = $this->post(route('cart.checkout'), $this->form());
        $page = $this->followRedirects($response)->getContent();
        $this->assertStringContainsString('https://wa.me/6280000009999?text=', $page);
        $this->assertStringNotContainsString(self::STORE_NUMBER, $page);
    }

    public function test_the_store_number_is_refused_as_a_test_number_and_every_test_environment_is_covered(): void
    {
        foreach (['local', 'development', 'testing', 'uat', 'staging'] as $environment) {
            $this->asEnvironment($environment, '0851-4492-4931');
            $this->assertSame('', StoreInfo::first()->whatsapp_number, $environment);
            $this->assertSame('', StoreInfo::first()->whatsapp_link, $environment);
        }
    }

    public function test_production_checkout_keeps_the_store_number_and_the_whatsapp_only_flow(): void
    {
        $this->asEnvironment('production', '6280000009999');
        config(['orders.website_checkout' => false]);
        $this->assertSame(self::STORE_NUMBER, StoreInfo::first()->whatsapp_number);
        $variant = $this->variant();
        $this->withSession(['cart' => [$variant->id => ['variant_id' => $variant->id, 'quantity' => 1]]])->get(route('cart.checkout.show'))->assertSee('Lanjut ke WhatsApp');
        $response = $this->post(route('cart.checkout'), [
            'customer_name' => 'E2E Pembeli Sintetis', 'customer_phone' => '080000000101',
            'customer_address' => 'Alamat sintetis untuk pengujian checkout', 'checkout_quote' => session('checkout_quote'),
        ]);
        $this->assertStringStartsWith('https://wa.me/'.self::STORE_NUMBER.'?text=', $response->headers->get('Location'));
        $this->assertSame(0, OnlineOrder::count());
    }

    private function asEnvironment(string $environment, ?string $testNumber): void
    {
        $this->app['env'] = $environment;
        // CSRF is skipped automatically only while APP_ENV is "testing"; these requests run as other environments.
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['store.whatsapp_test_number' => $testNumber]);
    }

    private function submitButton(string $html): string
    {
        preg_match('/<button type="submit"[^>]*>/', $html, $match);

        return $match[0] ?? '';
    }

    private function form(): array
    {
        return [
            'customer_name' => 'E2E Pembeli Sintetis', 'customer_phone' => '080000000101', 'delivery' => 'pickup',
            'packaging' => 'paperbag', 'payment_preference' => 'transfer',
            'checkout_quote' => session('checkout_quote'), 'checkout_key' => session('checkout_key'),
        ];
    }

    private function variant(): ProductVariant
    {
        $product = Product::create([
            'brand_id' => Brand::create(['name' => 'E2E Brand Sintetis', 'is_active' => true])->id, 'category_id' => Category::create(['name' => 'EDP'])->id,
            'name' => 'Nomor Sintetis', 'description' => 'Sintetis.', 'base_price' => 50000, 'gender' => 'Unisex', 'is_active' => true,
            'availability_status' => 'available', 'availability_source' => 'qammaris_app',
        ]);

        return ProductVariant::create(['product_id' => $product->id, 'volume' => 50, 'price' => 50000, 'sku' => 'ORD07-'.uniqid(), 'stock' => 1, 'is_active' => true]);
    }
}
