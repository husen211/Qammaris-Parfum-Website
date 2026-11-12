<?php

namespace Tests\Feature;

use App\Actions\Orders\ShippingQuotes;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreInfo;
use App\Models\StorePaymentAccount;
use App\Models\User;
use App\Support\InquiryWhatsApp;
use App\Support\OnlineOrderStepper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('Kurir instan')->assertSee('Pilih titik sendiri di peta')
            // ORD-15: courier logos beside the delivery names, and the logo map for the script's option cards.
            ->assertSee('data-partner-logo="gosend"', false)->assertSee('data-partner-logo="grab"', false)->assertSee('data-partner-logo="jnt"', false)
            ->assertSee('images\/partners\/gojek.svg', false)
            // ORD-16: marketplace order: how to receive, the address, then the options grouped Instan and Reguler.
            ->assertSeeInOrder(['Metode pengiriman', 'Dikirim ke alamat', 'Ambil di toko', 'Alamat pengiriman', 'Titik antar', 'Kecamatan terisi otomatis', 'Kecamatan atau kota', 'Alamat lengkap', 'Pilih pengiriman', 'Instan', 'Reguler', 'Paperbag'])
            ->assertSee('data-delivery-value', false)->assertDontSee('name="delivery" value="local_delivery"', false)
            // ORD-17: the area field opens our own search screen; the pin check has its own endpoint.
            ->assertSee('data-area-open', false)->assertSee('data-area-dialog', false)->assertSee('data-pin-preview', false)
            ->assertSee(json_encode(route('cart.checkout.pin')), false);
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

        session(['shipping_areas' => ['IDNP28PALUBARAT94221' => 'Palu Barat, Palu, Sulawesi Tengah. 94221']]);
        $this->post(route('cart.checkout'), $this->form('local_delivery', ['shipping_option' => $options['gosend']['id'],
            'delivery_area_id' => 'IDNP28PALUBARAT94221', 'delivery_area_label' => 'Kota palsu, 99999']))->assertRedirect();
        $order = OnlineOrder::sole();
        $this->assertSame(['Palu Barat', '94221'], [$order->district, $order->postcode]);
        $this->assertSame(['17000.00', 'added_to_transfer', '20500.00', 'api'], [$order->shipping_fee, $order->shipping_payer, $order->shipping_estimate, $order->shipping_estimate_source]);
        $this->assertSame(['instant', 'gosend', 17000, 3500, 2400], [$order->shipping_quote['kind'], $order->shipping_quote['provider'], $order->shipping_quote['charged'], $order->shipping_quote['discount'], $order->shipping_quote['weight_grams']]);
        $this->assertSame(-0.916, $order->shipping_quote['destination']['latitude']);
        $this->assertSame('https://www.google.com/maps?q=-0.916,119.886', $order->location_url, 'the pin is the Sharelok for staff and the App');
        $this->assertSame('824000.00', $order->customerTotal(), '803.000 + paperbag 4.000 + ongkir 17.000');
        $this->assertTrue(OnlineOrderStepper::totalIsFinal($order));
        $this->assertFalse($order->customerSetsShippingPayment(), 'a locked ongkir cannot be switched to "bayar ke driver"');
        $this->assertArrayNotHasKey('shipping_quote', $order->toArray());

        // Two pieces of one product: staff check the stock before the customer can pay.
        $this->get(route('orders.customer.show', $order->customer_token_encrypted))->assertOk()
            ->assertSee('Ongkir GoSend')->assertSee('Potongan ongkir 15% dari Qammaris')->assertSee('Rp 20.500')->assertSee('Rp 17.000')
            ->assertSee('Menunggu admin cek stok')->assertSee('Musk Sintetis × 2')->assertDontSee('Kirim bukti bayar ke WhatsApp');
        $message = app(InquiryWhatsApp::class)->websiteOrderMessage($order);
        $this->assertStringContainsString('Ongkir GoSend: Rp 17.000 (potongan ongkir 15% dari Qammaris)', $message);
        $this->assertStringContainsString('Total: Rp 824.000', $message);
        $this->assertStringContainsString('Mohon konfirmasi stok dan pembayarannya.', $message);
        $customerLink = route('orders.customer.show', $order->customer_token_encrypted);
        $this->assertStringContainsString('Cek status pesanan saya: '.$customerLink, $message, 'the customer keeps their own link in the chat');
        $this->assertStringContainsString('Cek status pesanan saya: '.$customerLink, app(InquiryWhatsApp::class)->paymentProofMessage($order, 'transfer'));
        $this->get($customerLink)->assertSee('data-order-link', false)->assertSee('Link pesanan Anda')->assertSee('data-copy-account="'.$customerLink.'"', false);
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
        // ORD-17: the same search (any spacing or case) answers from the cache, without another paid Biteship call.
        $this->getJson(route('cart.checkout.areas', ['q' => '  makassar ']))->assertOk()->assertJsonPath('areas.0.label', 'Makassar, Makassar, Sulawesi Selatan. 90141');
        Http::assertSentCount(1);
        // Review 2026-11-11: an area this session never searched is not priced, and the label always comes from Biteship.
        $this->postJson(route('cart.checkout.shipping'), ['mode' => 'jnt', 'area_id' => 'IDNP99UNSEARCHED', 'area_label' => 'Makassar'])
            ->assertOk()->assertJson(['options' => [], 'reason' => 'unavailable']);
        $option = $this->postJson(route('cart.checkout.shipping'), ['mode' => 'jnt', 'area_id' => 'IDNP28IDNC248IDND2564IDZ90141', 'area_label' => "Jayapura\nTotal: Rp0"])
            ->assertOk()->json('options.0');
        $this->assertSame('Makassar, Makassar, Sulawesi Selatan. 90141', $option['area_label']);

        // Rp69.000 − 30% (Rp20.700) = Rp48.300, rounded down to Rp48.000.
        $this->assertSame(['jnt', 'J&T', 69000, 48000, 21000, 30, 'Tiba 2–3 hari'], [$option['provider'], $option['label'], $option['price'], $option['charged'], $option['discount'], $option['percent'], $option['eta']]);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://'.self::RATES && $request['origin_postal_code'] === 94221
            && $request['destination_area_id'] === 'IDNP28IDNC248IDND2564IDZ90141' && $request['couriers'] === 'jnt');

        $this->post(route('cart.checkout'), $this->form('intercity', ['shipping_option' => $option['id']]))->assertRedirect();
        $order = OnlineOrder::sole();
        $this->assertSame(['48000.00', 'added_to_transfer', 'jnt', 'Makassar, Makassar, Sulawesi Selatan. 90141'], [$order->shipping_fee, $order->shipping_payer, $order->shipping_quote['provider'], $order->shipping_quote['destination']['area_label']]);
        $this->assertSame(['Makassar', '90141'], [$order->district, $order->postcode], 'the J&T destination fills kecamatan and postcode');
    }

    public function test_single_pieces_go_straight_to_payment_and_the_choice_opens_whatsapp(): void
    {
        Http::fake([self::RATES => Http::response($this->pricing([['grab', 'instant', 15000, '1 - 3', 'hours']]))]);
        $this->review([$this->variant('Oud Sintetis', 425000)->id => 1, $this->variant('Musk Sintetis', 189000)->id => 1]);
        $this->get(route('cart.checkout.show'))->assertOk()->assertSee('Lanjut ke pembayaran')
            ->assertDontSee('name="district"', false)->assertDontSee('name="subdistrict"', false)->assertDontSee('name="payment_preference"', false);
        $id = $this->postJson(route('cart.checkout.shipping'), ['mode' => 'instant', 'latitude' => -0.88, 'longitude' => 119.87])->json('options.0.id');
        $placed = $this->post(route('cart.checkout'), $this->form('local_delivery', ['shipping_option' => $id]));
        $order = OnlineOrder::sole();
        $this->assertNull($order->payment_preference, 'the customer picks it on the payment page');
        $this->assertTrue($order->payableBeforeConfirmation());

        $page = $this->followRedirects($placed)->assertOk()->assertSee('Langkah berikutnya: pembayaran')->assertSee('Transfer bank')
            ->assertSee('Segera tersedia')->assertSee('Kirim bukti bayar ke WhatsApp')->assertDontSee('Menunggu admin cek stok')
            ->assertSee('Nomor rekening resmi dikirim admin lewat WhatsApp');
        // A verified account is shown at once with its bank mark and a copy button (digits only).
        StorePaymentAccount::create(['bank_name' => 'BRI', 'account_number' => '0123-01-000000-50-1', 'account_holder' => 'Qammaris Perfumes', 'created_by' => User::factory()->create()->id])
            ->forceFill(['verified_at' => now()])->save();
        $this->get(route('orders.customer.show', $order->customer_token_encrypted))->assertSee('data-bank-mark="bri"', false)->assertSee('images/partners/bri.svg', false)
            ->assertSee('0123-01-000000-50-1')->assertSee('data-copy-account="012301000000501"', false)->assertSee('Salin nomor rekening')
            ->assertDontSee('Nomor rekening resmi dikirim admin lewat WhatsApp');
        $this->assertStringNotContainsString('data-auto-open="1"', $page->getContent(), 'WhatsApp opens with the proof, not before paying');

        $token = $order->customer_token_encrypted;
        $this->post(route('orders.customer.pay', $token), ['payment_preference' => 'qris'])->assertSessionHasErrors('payment_preference');
        $to = $this->post(route('orders.customer.pay', $token), ['payment_preference' => 'transfer'])->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/', $to);
        parse_str((string) parse_url($to, PHP_URL_QUERY), $query);
        $this->assertStringContainsString('Nomor pesanan: *'.$order->code.'*', $query['text']);
        $this->assertStringContainsString('Dibayar lewat: Transfer bank', $query['text']);
        $this->assertSame(['transfer', 'unpaid', 'draft'], [$order->fresh()->payment_preference, $order->fresh()->payment_status, $order->fresh()->lifecycle]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh());
        $this->get(route('admin.orders.show', $order))->assertSee('data-pay-now-note', false)->assertSee('Transfer bank');
    }

    public function test_two_pieces_or_staff_priced_ongkir_wait_and_payment_opens_after_confirmation(): void
    {
        Http::fake([self::RATES => Http::response($this->pricing([['grab', 'instant', 15000, '1 - 3', 'hours']]))]);
        $variant = $this->variant('Oud Sintetis', 425000);
        $this->review([$variant->id => 2]);
        $this->get(route('cart.checkout.show'))->assertSee('Pesan &amp; minta cek stok', false);
        $id = $this->postJson(route('cart.checkout.shipping'), ['mode' => 'instant', 'latitude' => -0.88, 'longitude' => 119.87])->json('options.0.id');
        $this->post(route('cart.checkout'), $this->form('local_delivery', ['shipping_option' => $id]));
        $order = OnlineOrder::sole();
        $token = $order->customer_token_encrypted;

        $this->get(route('orders.customer.show', $token))->assertSee('Menunggu admin cek stok')->assertSee('Oud Sintetis × 2')
            ->assertSee('Kirim pesanan ke WhatsApp')->assertDontSee('Kirim bukti bayar ke WhatsApp');
        $this->post(route('orders.customer.pay', $token), ['payment_preference' => 'transfer'])->assertSessionHas('error');
        $this->assertNull($order->fresh()->payment_preference);

        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $this->actingAs($staff)->get(route('admin.orders.show', $order))->assertSee('data-stock-check-note', false)->assertSee('Perlu cek stok');
        $this->post(route('admin.orders.v2.confirm-website', $order), ['revision' => $order->fresh()->revision])->assertSessionHas('order_notice.type', 'success');
        auth()->logout();
        $this->get(route('orders.customer.show', $token))->assertSee('Kirim bukti bayar ke WhatsApp')->assertDontSee('Menunggu admin cek stok');
    }

    public function test_ongkir_confirmed_by_staff_waits_too(): void
    {
        $this->review([$this->variant('Oud Sintetis', 425000)->id => 1]);
        $this->post(route('cart.checkout'), $this->form('local_delivery', ['shipping_option' => 'manual', 'shipping_payment' => 'added_to_transfer']));
        $this->get(route('orders.customer.show', OnlineOrder::sole()->customer_token_encrypted))
            ->assertSee('Menunggu admin menghitung ongkir')->assertDontSee('Kirim bukti bayar ke WhatsApp');
    }

    public function test_super_admin_uploads_the_store_qris_and_customers_can_pay_with_it(): void
    {
        Storage::fake('public');
        config(['media.product_disk' => 'public']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh())
            ->post(route('admin.orders.payment-accounts.qris'), ['qris' => UploadedFile::fake()->createWithContent('qris.png', $this->png(600, 600))])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh());
        $this->post(route('admin.orders.payment-accounts.qris'), ['qris' => UploadedFile::fake()->createWithContent('kecil.png', $this->png(120, 120))])->assertSessionHasErrors('qris');
        $this->post(route('admin.orders.payment-accounts.qris'), ['qris' => UploadedFile::fake()->createWithContent('qris.png', $this->png(600, 600))])->assertSessionHas('success');
        $path = StoreInfo::query()->value('qris_image_path');
        Storage::disk('public')->assertExists($path);
        $this->get(route('admin.orders.payment-accounts.index'))->assertSee('data-qris-settings', false)->assertSee('Ganti gambar QRIS');
        auth()->logout();

        $this->review([$this->variant('Oud Sintetis', 425000)->id => 1]);
        $this->post(route('cart.checkout'), $this->form('pickup'));
        $token = OnlineOrder::sole()->customer_token_encrypted;
        $this->get(route('orders.customer.show', $token))->assertSee('alt="QRIS Qammaris Perfumes"', false)->assertDontSee('Segera tersedia');
        $to = $this->post(route('orders.customer.pay', $token), ['payment_preference' => 'qris'])->headers->get('Location');
        parse_str((string) parse_url($to, PHP_URL_QUERY), $query);
        $this->assertStringContainsString('Dibayar lewat: QRIS', $query['text']);

        $this->actingAs(User::where('role', User::ROLE_SUPER_ADMIN)->first())->post(route('admin.orders.payment-accounts.qris.remove'))->assertSessionHas('success');
        $this->assertNull(StoreInfo::query()->value('qris_image_path'));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_palu_pin_fills_its_kecamatan_and_offers_two_where_openstreetmap_and_the_centre_disagree(): void
    {
        $osm = ['-0.9160,119.8860' => 'Kecamatan Palu Selatan', '-0.8950,119.8560' => 'Kecamatan Ulujadi', '-1.0500,119.9500' => 'Kecamatan Sigi Biromaru'];
        $nominatim = 0;
        Http::fake(function (HttpRequest $request) use ($osm, &$nominatim) {
            if (str_contains($request->url(), 'nominatim.openstreetmap.org')) {
                $nominatim++;
                $name = $osm[sprintf('%.4f,%.4f', $request['lat'], $request['lon'])] ?? null;

                return $name ? Http::response(['address' => ['village' => 'Tatura Utara', 'municipality' => $name]]) : Http::response([], 500);
            }
            if (str_contains($request->url(), '/v1/maps/areas')) {
                $name = ucwords((string) $request['input']);
                $city = str_starts_with($name, 'Sigi') ? 'Sigi' : 'Palu';

                return Http::response(['success' => true, 'areas' => [
                    ['id' => 'IDOTHER'.str_replace(' ', '', $name), 'name' => $name.' Raya, Makassar, Sulawesi Selatan. 90111'],
                    ['id' => 'ID'.str_replace(' ', '', $name), 'name' => "{$name}, {$city}, Sulawesi Tengah. 94111"],
                ]]);
            }

            return Http::response([], 404);
        });
        $this->review([$this->variant('Oud Sintetis', 425000)->id => 1]);
        $area = fn (string $name, string $city = 'Palu') => ['id' => 'ID'.str_replace(' ', '', $name), 'label' => "{$name}, {$city}, Sulawesi Tengah. 94111"];

        // OpenStreetMap and the nearest centre agree: that kecamatan, in Palu (not the Makassar namesake), fills itself.
        $this->getJson(route('cart.checkout.pin', ['latitude' => -0.916, 'longitude' => 119.886]))->assertOk()
            ->assertExactJson(['kecamatan' => 'Palu Selatan', 'areas' => [$area('Palu Selatan')]]);
        $this->getJson(route('cart.checkout.pin', ['latitude' => -0.91601, 'longitude' => 119.88602]))->assertOk()->assertJsonPath('kecamatan', 'Palu Selatan');
        $this->assertSame(1, $nominatim, 'a pin is looked up once');
        Http::assertSent(fn (HttpRequest $request) => ! str_contains($request->url(), 'nominatim') || str_starts_with($request->header('User-Agent')[0] ?? '', 'QammarisPerfumes/'));
        $this->assertSame('Palu Selatan, Palu, Sulawesi Tengah. 94111', session('shipping_areas')['IDPaluSelatan'], 'the filled area prices and is saved like a searched one');
        // They disagree (OpenStreetMap reads Balaroa as Ulujadi): both, for the customer to choose.
        $this->getJson(route('cart.checkout.pin', ['latitude' => -0.895, 'longitude' => 119.856]))->assertOk()
            ->assertExactJson(['kecamatan' => 'Ulujadi', 'areas' => [$area('Ulujadi'), $area('Palu Barat')]]);
        // No OpenStreetMap answer: the nearest Palu centre.
        $this->getJson(route('cart.checkout.pin', ['latitude' => -0.923, 'longitude' => 119.85]))->assertOk()
            ->assertExactJson(['kecamatan' => null, 'areas' => [$area('Tatanga')]]);
        // Outside Palu city (Sigi): OpenStreetMap's kecamatan; with no answer and no centre within 5 km, nothing.
        $this->getJson(route('cart.checkout.pin', ['latitude' => -1.05, 'longitude' => 119.95]))->assertOk()
            ->assertExactJson(['kecamatan' => 'Sigi Biromaru', 'areas' => [$area('Sigi Biromaru', 'Sigi')]]);
        $this->getJson(route('cart.checkout.pin', ['latitude' => -1.1, 'longitude' => 120.05]))->assertOk()->assertExactJson(['kecamatan' => null, 'areas' => []]);
        // Outside Palu and its surroundings nothing is asked.
        $this->getJson(route('cart.checkout.pin', ['latitude' => -5.14, 'longitude' => 119.42]))->assertUnprocessable();
    }

    public function test_an_area_from_an_earlier_search_still_prices_and_a_dropped_connection_is_retried(): void
    {
        $calls = 0;
        Http::fake(function (HttpRequest $request) use (&$calls) {
            if (str_contains($request->url(), '/v1/maps/areas')) {
                return Http::response(['success' => true, 'areas' => [['id' => 'IDNP28IDNC248IDND2564IDZ90141', 'name' => 'Makassar, Makassar, Sulawesi Selatan. 90141']]]);
            }
            // ORD-18: the first price request loses its connection; the retry answers.
            if (++$calls === 1) {
                throw new ConnectionException('reset');
            }

            return Http::response($this->pricing([['jnt', 'ez', 69000, '2 - 3', 'days']]));
        });
        $this->review([$this->variant('Oud Sintetis', 425000)->id => 1]);
        $this->getJson(route('cart.checkout.areas', ['q' => 'Makassar']))->assertOk();
        // The session forgot it (many searches, or another tab): the site-wide memory of Biteship's label still prices it.
        session()->forget('shipping_areas');
        $this->postJson(route('cart.checkout.shipping'), ['mode' => 'jnt', 'area_id' => 'IDNP28IDNC248IDND2564IDZ90141'])
            ->assertOk()->assertJsonPath('options.0.area_label', 'Makassar, Makassar, Sulawesi Selatan. 90141')->assertJsonPath('options.0.charged', 48000);
        $this->assertSame(2, $calls);
    }

    public function test_an_empty_area_answer_is_not_remembered(): void
    {
        $answers = [['success' => true, 'areas' => []], ['success' => true, 'areas' => [['id' => 'IDNP27IDNC350IDND4127IDZ94221', 'name' => 'Palu Barat, Palu, Sulawesi Tengah. 94221']]]];
        Http::fake(function () use (&$answers) {
            return Http::response(array_shift($answers));
        });
        $this->review([$this->variant('Oud Sintetis', 425000)->id => 1]);
        $this->getJson(route('cart.checkout.areas', ['q' => 'Palu Barat']))->assertOk();
        // One odd empty answer must not hide the kecamatan for a month: the next search asks Biteship again.
        $this->getJson(route('cart.checkout.areas', ['q' => 'Palu Barat']))->assertOk()->assertJsonPath('areas.0.label', 'Palu Barat, Palu, Sulawesi Tengah. 94221');
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

    /** A plain white PNG built without GD (not installed everywhere tests run). */
    private function png(int $width, int $height): string
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress(str_repeat("\0".str_repeat("\xff", $width * 3), $height))).$chunk('IEND', '');
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
