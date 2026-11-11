<?php

namespace Tests\Feature;

use App\Models\OnlineOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/** ORD-14: instant courier booked through Biteship from the admin order page; statuses follow Biteship. */
class BiteshipCourierBookingTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase;

    private const SECRET = 'whsec_0123456789abcdefghijklmnopqrstuv';

    private User $staff;

    /** @var array<string, list<array{0: int, 1: array}>> queued Biteship answers per "METHOD path" */
    private array $answers = [];

    /** @var list<Request> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'orders.simple_ux' => true, 'orders.shipping_quotes.enabled' => true,
            'services.biteship.key' => 'biteship_test.fake', 'services.biteship.base_url' => 'https://api.biteship.com',
            'services.biteship.webhook_secret' => self::SECRET,
        ]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        Http::fake(function (Request $request) {
            $this->sent[] = $request;
            $key = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);
            [$status, $body] = array_shift($this->answers[$key]) ?? [500, ['success' => false]];

            return Http::response($body, $status);
        });
    }

    public function test_staff_book_the_customers_courier_and_every_later_status_comes_from_biteship(): void
    {
        $order = $this->packed($this->instantOrder());
        $page = $this->actingAs($this->staff)->get(route('admin.orders.show', $order))->assertOk();
        $page->assertSee('data-biteship-book', false)->assertSee('Pesan kurir sekarang')->assertSee('Customer memilih GoSend')
            ->assertSee('Pesan manual di aplikasi kurir')->assertSee('value="gojek" checked', false);

        $this->answer('POST /v1/orders', $this->biteshipOrder('confirmed'));
        $this->bookCourier($order, 'gojek')->assertSessionHas('order_notice.type', 'success');
        $order->refresh();
        $this->assertSame(['requested', 'gosend', 'bs_order_1', 'active', 1], [$order->courier_status, $order->courier_provider, $order->courier_reference, $order->courier_booking['state'], $order->courier_booking['attempt']]);
        $this->assertSame('courier_requested', $order->events()->reorder('id', 'desc')->value('kind'));

        $booking = $this->sent[0]->data();
        $this->assertSame(['gojek', 'instant', 'now', $order->code.'-1'], [$booking['courier_company'], $booking['courier_type'], $booking['delivery_type'], $booking['reference_id']]);
        $this->assertSame(['latitude' => -0.9012345, 'longitude' => 119.8701234], $booking['destination_coordinate']);
        $this->assertSame([800, 800], array_column($booking['items'], 'weight'));
        $this->assertSame('081234567890', $booking['destination_contact_phone']);
        $this->assertSame('biteship_test.fake', $this->sent[0]->header('Authorization')[0]);

        // A second click books nothing.
        $this->bookCourier($order->fresh(), 'gojek')->assertSessionHas('order_notice.message', 'Kurir sudah dipesan. Batalkan dulu bila perlu memesan ulang.');
        $this->assertCount(1, $this->sent);

        // Driver found: saved quietly (no new revision for staff working on the page), shown to staff and the customer.
        $revision = $order->revision;
        $this->answer('GET /v1/orders/bs_order_1', $this->biteshipOrder('allocated', ['driver_name' => 'Budi Kurir', 'driver_phone' => '081200000000', 'driver_plate_number' => 'DN 1234 AB', 'link' => 'https://track.example.test/bs_order_1']));
        $this->webhook(['event' => 'order.status', 'order_id' => 'bs_order_1', 'status' => 'allocated'])->assertOk();
        $order->refresh();
        $this->assertSame([$revision, 'allocated', 'Budi Kurir'], [$order->revision, $order->courier_booking['status'], $order->courier_booking['driver_name']]);
        $this->get(route('admin.orders.show', $order))->assertSee('Driver ditemukan')->assertSee('Budi Kurir')->assertSee('DN 1234 AB')
            ->assertSee('data-biteship-track', false)->assertSee('Batalkan kurir')->assertDontSee('data-biteship-book', false);
        $this->get(route('orders.customer.show', $order->customer_token_encrypted))->assertOk()
            ->assertSee('data-customer-courier="allocated"', false)->assertSee('Driver menuju toko untuk mengambil pesanan')->assertSee('Budi Kurir')
            ->assertSee('https://track.example.test/bs_order_1', false)->assertDontSee('081200000000')
            // The checkout pin is the location: no Sharelok is asked again.
            ->assertDontSee('Kirim Sharelok')->assertDontSee('data-location-test-note', false);

        // Picked: the handover, with what the store paid from the Owner's Biteship balance (no reimbursement).
        $this->answer('GET /v1/orders/bs_order_1', $this->biteshipOrder('picked', ['driver_name' => 'Budi Kurir']));
        $this->webhook(['order_id' => 'bs_order_1', 'status' => 'picked'])->assertOk();
        $order->refresh();
        $this->assertSame(['handed_over', 'courier'], [$order->handover_status, $order->handed_to]);
        $cost = $order->costs()->sole();
        $this->assertSame([[['source' => 'owner_fund', 'amount' => 21000]], 'not_applicable', 'Biteship'], [$cost->funding, $cost->reimbursement_status, $cost->reported_by_name]);
        $event = $order->events()->reorder('id', 'desc')->first();
        $this->assertSame(['handed_over', 'system', 'Biteship (otomatis)'], [$event->kind, $event->actor_type, $event->actorLabel()]);
        $this->assertStringContainsString('Diambil driver GoSend · ongkir kurir Rp21.000 · saldo Biteship (otomatis)', $event->note);
        $this->get(route('admin.orders.show', $order))->assertSee('saldo Biteship (Owner)')->assertSee('Paket diambil driver')->assertDontSee('Batalkan kurir');

        $this->answer('GET /v1/orders/bs_order_1', $this->biteshipOrder('delivered'));
        $this->webhook(['order_id' => 'bs_order_1', 'status' => 'delivered'])->assertOk();
        $this->assertSame(['delivered', 'website'], [$order->fresh()->delivery_status, $order->fresh()->delivery_confirmed_by]);
        $this->assertSame(1, $order->costs()->count());
    }

    public function test_a_refused_booking_shows_biteships_reason_and_a_retry_is_a_new_attempt(): void
    {
        $order = $this->packed($this->instantOrder());
        $this->answer('POST /v1/orders', ['success' => false, 'error' => 'Saldo Anda tidak mencukupi', 'code' => 40002001], 400);
        $this->actingAs($this->staff);
        $this->bookCourier($order, 'gojek')->assertSessionHas('order_notice.message', 'Kurir belum terpesan: Saldo Anda tidak mencukupi');
        $order->refresh();
        $this->assertSame(['failed', 'unassigned'], [$order->courier_booking['state'], $order->courier_status]);
        $this->assertSame('courier_booking_failed', $order->events()->reorder('id', 'desc')->value('kind'));
        $this->get(route('admin.orders.show', $order))->assertSee('data-biteship-error', false)->assertSee('Saldo Anda tidak mencukupi');

        // Grab instead, after topping up: a new attempt with its own reference.
        $this->answer('POST /v1/orders', $this->biteshipOrder('confirmed', ['company' => 'grab']));
        $this->bookCourier($order->fresh(), 'grab')->assertSessionHas('order_notice.type', 'success');
        $this->assertSame([$order->code.'-2', 'grab'], [end($this->sent)->data()['reference_id'], $order->fresh()->courier_provider]);
    }

    public function test_driver_not_found_frees_the_step_and_staff_can_cancel_a_driver_before_pickup(): void
    {
        $order = $this->packed($this->instantOrder());
        $this->actingAs($this->staff);
        $this->answer('POST /v1/orders', $this->biteshipOrder('confirmed'));
        $this->bookCourier($order, 'gojek')->assertSessionHas('order_notice.type', 'success');

        $this->answer('GET /v1/orders/bs_order_1', $this->biteshipOrder('courier_not_found'));
        $this->webhook(['order_id' => 'bs_order_1', 'status' => 'courierNotFound'])->assertOk();
        $order->refresh();
        $this->assertSame(['ended', 'unassigned', null, 'pending'], [$order->courier_booking['state'], $order->courier_status, $order->courier_reference, $order->handover_status]);
        $this->assertSame('courier_failed', $order->events()->reorder('id', 'desc')->value('kind'));
        $this->get(route('admin.orders.show', $order))->assertSee('data-biteship-ended', false)->assertSee('Driver tidak ditemukan')->assertSee('Pesan kurir sekarang');

        $this->answer('POST /v1/orders', $this->biteshipOrder('allocated', ['id' => 'bs_order_2']));
        $this->bookCourier($order->fresh(), 'gojek')->assertSessionHas('order_notice.type', 'success');
        $this->answer('POST /v1/orders/bs_order_2/cancel', ['success' => true, 'id' => 'bs_order_2', 'status' => 'cancelled']);
        $this->post(route('admin.orders.v2.biteship.cancel', $order), ['reason' => 'Customer minta kirim besok'])->assertSessionHas('order_notice.type', 'success');
        $this->assertSame(['reason' => 'Customer minta kirim besok', 'code' => 'others'], ['reason' => end($this->sent)->data()['cancellation_reason'], 'code' => end($this->sent)->data()['cancellation_reason_code']]);
        $order->refresh();
        $this->assertSame(['ended', 'unassigned'], [$order->courier_booking['state'], $order->courier_status]);
        $event = $order->events()->reorder('id', 'desc')->first();
        $this->assertSame(['courier_cancelled', 'admin'], [$event->kind, $event->actor_type]);
        $this->assertSame(0, $order->costs()->count());
    }

    public function test_pickup_after_the_booking_admin_was_removed_still_records_the_cost(): void
    {
        $order = $this->packed($this->instantOrder());
        $booker = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $this->answer('POST /v1/orders', $this->biteshipOrder('confirmed'));
        $this->actingAs($booker)->post(route('admin.orders.v2.biteship.book', $order), ['revision' => $order->fresh()->revision, 'courier' => 'gojek'])
            ->assertSessionHas('order_notice.type', 'success');
        $booker->delete();

        $this->answer('GET /v1/orders/bs_order_1', $this->biteshipOrder('picked'));
        $this->webhook(['order_id' => 'bs_order_1'])->assertOk();
        $cost = $order->costs()->sole();
        $this->assertSame([null, 'Biteship', 'handed_over'], [$cost->recorded_by_user_id, $cost->reported_by_name, $order->fresh()->handover_status]);
    }

    public function test_biteship_is_offered_only_for_a_packed_instant_order_with_the_checkout_pin(): void
    {
        $this->actingAs($this->staff);
        // Not packed yet: the courier step is not open and the server refuses; once packed, the customer's Grab is preselected.
        $unpacked = $this->instantOrder('grab');
        $this->get(route('admin.orders.show', $unpacked))->assertDontSee('data-biteship-book', false);
        $this->bookCourier($unpacked, 'grab')->assertSessionHas('order_notice.message', 'Konfirmasi packing dulu sebelum menyerahkan pesanan.');
        $this->get(route('admin.orders.show', $this->packed($unpacked)))->assertSee('data-biteship-book', false)->assertSee('value="grab" checked', false);

        // An order made by staff has no pin: only the manual booking.
        $manual = $this->packed($this->paidV2Order('local_delivery'));
        $this->get(route('admin.orders.show', $manual))->assertDontSee('data-biteship-book', false)->assertSee('data-courier-booking', false)
            ->assertDontSee('Pesan manual di aplikasi kurir');
        $this->bookCourier($manual, 'gojek')->assertSessionHas('order_notice.type', 'error');

        // Switched off: the manual form only, and nothing is sent to Biteship.
        config(['orders.shipping_quotes.enabled' => false]);
        $order = $this->packed($this->instantOrder());
        $this->get(route('admin.orders.show', $order))->assertDontSee('data-biteship-book', false)->assertSee('data-courier-booking', false);
        $this->bookCourier($order, 'gojek')->assertSessionHas('order_notice.type', 'error');
        $this->assertSame([], $this->sent);
    }

    public function test_the_webhook_needs_the_secret_and_only_acts_on_a_booked_order(): void
    {
        $this->post(route('integrations.biteship.webhook', 'wrong_secret_0123456789abcdefghijklmn'), ['order_id' => 'bs_order_1'])->assertNotFound();
        // Biteship's own URL check sends an empty body.
        $this->postJson(route('integrations.biteship.webhook', self::SECRET), [])->assertOk();
        $this->webhook(['order_id' => 'bs_unknown'])->assertOk();
        $this->assertSame([], $this->sent);

        config(['services.biteship.webhook_secret' => null]);
        $this->webhook(['order_id' => 'bs_order_1'])->assertNotFound();
    }

    public function test_a_website_order_payment_card_skips_the_instructions_the_customer_already_has(): void
    {
        $order = $this->instantOrder(paid: false);
        $order->forceFill(['payment_preference' => 'qris'])->saveQuietly();
        $this->actingAs($this->staff)->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('data-payment-website', false)->assertSee('Cara bayar: <strong>QRIS</strong>', false)
            ->assertSee('Rekening dan QRIS sudah tampil di halaman pesanan customer')->assertSee('Tandai Lunas')
            ->assertDontSee('Salin Total & Instruksi Pembayaran', false);
    }

    private function instantOrder(string $courier = 'gojek', bool $paid = true): OnlineOrder
    {
        $order = $this->v2Order('local_delivery');
        $order->forceFill([
            'source' => 'website', 'shipping_fee' => '17000.00', 'shipping_payer' => 'added_to_transfer', 'location_url' => 'https://www.google.com/maps?q=-0.9012345,119.8701234',
            'shipping_quote' => [
                'kind' => 'instant', 'provider' => $courier === 'grab' ? 'grab' : 'gosend', 'label' => $courier === 'grab' ? 'GrabExpress' : 'GoSend',
                'service' => 'instant', 'eta' => 'Tiba 1–2 jam', 'price' => 20000, 'charged' => 17000, 'discount' => 3000, 'percent' => 15,
                'destination' => ['latitude' => -0.9012345, 'longitude' => 119.8701234, 'distance_km' => 1.4],
            ],
        ])->saveQuietly();

        return $paid ? $this->payInFull($order) : $order->fresh(['items']);
    }

    private function packed(OnlineOrder $order): OnlineOrder
    {
        $this->actingAs($this->staff)->post(route('admin.orders.v2.pack', $order), ['revision' => $order->fresh()->revision,
            'packed' => $order->items->mapWithKeys(fn ($item) => [$item->line_id => $item->quantity])->all()])->assertSessionHas('order_notice.type', 'success');

        return $order->fresh(['items']);
    }

    private function bookCourier(OnlineOrder $order, string $courier): TestResponse
    {
        return $this->post(route('admin.orders.v2.biteship.book', $order), ['revision' => $order->fresh()->revision, 'courier' => $courier]);
    }

    private function webhook(array $body): TestResponse
    {
        return $this->postJson(route('integrations.biteship.webhook', self::SECRET), $body);
    }

    private function answer(string $endpoint, array $body, int $status = 200): void
    {
        $this->answers[$endpoint][] = [$status, $body];
    }

    private function biteshipOrder(string $status, array $courier = []): array
    {
        return ['success' => true, 'id' => $courier['id'] ?? 'bs_order_1', 'status' => $status, 'price' => 21000,
            'courier' => ['company' => $courier['company'] ?? 'gojek', 'type' => 'instant'] + array_diff_key($courier, ['id' => 1, 'company' => 1])];
    }
}
