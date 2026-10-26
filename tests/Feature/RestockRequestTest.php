<?php

namespace Tests\Feature;

use App\Jobs\DeliverRestockRequest;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\ProductVariant;
use App\Models\RestockRequest;
use App\Models\StoreInfo;
use App\Support\OrderApi\OrderApiSignature;
use App\Support\RestockRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** WAIT-01: "Kabari saya jika sudah ready" → Qammaris App Daftar Tunggu. */
class RestockRequestTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://api.example.test/api/integrations/website/waitlist/events';

    private const UUID = '5f0c7a8e-1b2c-4d3e-8f90-123456789abc';

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'orders_api.waitlist_enabled' => true, 'orders_api.waitlist_url' => self::URL,
            'orders_api.webhook_client_id' => 'qammaris-website', 'orders_api.webhook_secret' => 'rahasia-uji',
        ]);
        StoreInfo::create(['whatsapp_number' => '6281234567890', 'address' => 'Palu']);
        $brand = Brand::create(['name' => 'Rasasi', 'is_active' => true]);
        $this->product = Product::create([
            'brand_id' => $brand->id, 'name' => 'Rasasi Hawas Ice', 'description' => 'Segar', 'base_price' => 450000,
            'gender' => 'Pria', 'is_active' => true, 'publication_status' => Product::PUBLICATION_PUBLISHED,
            'published_at' => now(), 'availability_status' => Product::AVAILABILITY_SOLD_OUT,
        ]);
        ProductVariant::create(['product_id' => $this->product->id, 'volume' => 100, 'price' => 450000, 'stock' => 0, 'is_active' => true]);
        $this->product->externalIdentities()->create(['provider' => ProductExternalIdentity::PROVIDER_QAMMARIS_APP, 'external_product_id' => self::UUID]);
    }

    public function test_sold_out_page_offers_the_form_instead_of_whatsapp(): void
    {
        $this->get(route('products.show', $this->product->slug))->assertOk()
            ->assertSee('Kabari saya jika sudah ready')
            ->assertSee('action="'.route('products.restock-request', $this->product->slug).'"', false)
            ->assertSee('autocomplete="tel"', false)
            ->assertDontSee('Tanya restock via WhatsApp');

        config(['orders_api.waitlist_enabled' => false]);
        $this->get(route('products.show', $this->product->slug))->assertOk()
            ->assertSee('Tanya restock via WhatsApp')->assertDontSee('Kabari saya jika sudah ready');
        $this->post(route('products.restock-request', $this->product->slug), ['name' => 'Sandi', 'whatsapp' => '081248349772'])->assertNotFound();
    }

    public function test_request_is_saved_once_and_sent_signed_to_the_app(): void
    {
        Queue::fake();
        $this->post(route('products.restock-request', $this->product->slug), ['name' => '  Sandi  ', 'whatsapp' => '0812-4834-9772'])
            ->assertRedirect(route('products.show', $this->product->slug).'#kabari')->assertSessionHas('restock_status', 'saved');
        // Same product and number again: no second entry or event.
        $this->post(route('products.restock-request', $this->product->slug), ['name' => 'Sandi', 'whatsapp' => '+62 812 4834 9772'])
            ->assertSessionHas('restock_status', 'saved');

        $row = RestockRequest::sole();
        $this->assertSame(['Sandi', '6281248349772'], [$row->customer_name, $row->whatsapp]);
        Queue::assertPushed(DeliverRestockRequest::class, 1);
        $body = json_decode($row->body, true);
        $this->assertSame('waitlist.requested', $body['type']);
        $this->assertSame($row->request_id, $body['event_id']);
        $this->assertSame($row->request_id, $body['data']['request_id']);
        $this->assertSame(['app_product_id' => self::UUID, 'name' => 'Rasasi Hawas Ice', 'brand' => 'Rasasi', 'volume_ml' => 100,
            'url' => route('products.show', $this->product->slug)], $body['data']['product']);
        $this->assertSame(['name' => 'Sandi', 'whatsapp' => '6281248349772'], $body['data']['customer']);

        Http::fake([self::URL => Http::response(['ok' => true, 'duplicate' => false, 'entry_id' => 7])]);
        $this->assertTrue(RestockRequests::attempt($row->id));
        Http::assertSent(function (HttpRequest $request) use ($row) {
            $timestamp = (int) $request->header('X-Qammaris-Timestamp')[0];

            return $request->url() === self::URL && $request->body() === $row->body
                && $request->header('X-Qammaris-Client')[0] === 'qammaris-website'
                && $request->header('Idempotency-Key')[0] === $row->request_id
                && $request->header('X-Qammaris-Signature')[0] === OrderApiSignature::sign('rahasia-uji', $timestamp, 'POST', '/api/integrations/website/waitlist/events', $row->body);
        });

        // Delivered: the App holds the contact data now; only the repeat-check hash stays.
        $row->refresh();
        $this->assertNotNull($row->delivered_at);
        $this->assertSame([null, null, null], [$row->customer_name, $row->whatsapp, $row->body]);
        $this->post(route('products.restock-request', $this->product->slug), ['name' => 'Sandi', 'whatsapp' => '081248349772']);
        $this->assertSame(1, RestockRequest::count());
    }

    public function test_app_rejection_stops_retries_and_outage_retries(): void
    {
        Queue::fake();
        $this->post(route('products.restock-request', $this->product->slug), ['name' => 'Kak Nabila', 'whatsapp' => '089535239134']);
        $row = RestockRequest::sole();

        Http::fake([self::URL => Http::sequence()->push(['error' => 'unavailable'], 503)->push(['error' => 'invalid'], 422)]);
        $this->assertFalse(RestockRequests::attempt($row->id));
        $row->refresh();
        $this->assertSame([1, 503], [$row->attempts, $row->last_status]);
        $this->assertNull($row->failed_at);
        $this->assertNotNull($row->next_attempt_at);

        $this->travel(5)->minutes();
        $this->assertSame(0, RestockRequests::deliverDue());
        $row->refresh();
        $this->assertNotNull($row->failed_at, '422 is final');
        $this->assertSame('Kak Nabila', $row->customer_name, 'kept for a manual retry');
        $this->assertSame(1, RestockRequests::retryFailed());
    }

    public function test_invalid_input_is_explained_and_nothing_is_saved(): void
    {
        $this->post(route('products.restock-request', $this->product->slug), ['name' => '', 'whatsapp' => '12345'])
            ->assertSessionHasErrorsIn('restock', ['name', 'whatsapp']);
        $this->post(route('products.restock-request', $this->product->slug), ['name' => 'Sandi', 'whatsapp' => '021555'])
            ->assertSessionHasErrorsIn('restock', ['whatsapp']);
        $this->post(route('products.restock-request', $this->product->slug), ['name' => 'Bot', 'whatsapp' => '081248349772', 'website' => 'http://spam'])
            ->assertSessionHasErrorsIn('restock', ['website']);
        $this->assertSame(0, RestockRequest::count());

        $this->product->update(['availability_status' => Product::AVAILABILITY_AVAILABLE, 'availability_source' => 'qammaris_app']);
        $this->post(route('products.restock-request', $this->product->slug), ['name' => 'Sandi', 'whatsapp' => '081248349772'])
            ->assertSessionHas('restock_status', 'available');
        $this->assertSame(0, RestockRequest::count());
    }

    public function test_sold_out_dot_is_red_on_card_and_detail(): void
    {
        $this->get(route('products.show', $this->product->slug))->assertSee('bg-[#c62828]', false);
        $this->get(route('products.index'))->assertSee('data-status="sold_out"', false);
        $this->assertStringContainsString('[data-status="sold_out"] .catalog-card__dot {'."\n".'    background: #c62828;',
            file_get_contents(resource_path('css/catalog.css')));
    }
}
