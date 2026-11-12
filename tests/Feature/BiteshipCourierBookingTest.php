<?php

namespace Tests\Feature;

use App\Models\OnlineOrder;
use App\Models\StoreInfo;
use App\Models\User;
use App\Services\BiteshipClient;
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
        StoreInfo::getSetting()->forceFill(['phone_number' => '082200001111'])->save();
        $order = $this->packed($this->instantOrder());
        $page = $this->actingAs($this->staff)->get(route('admin.orders.show', $order))->assertOk();
        $page->assertSee('data-biteship-book', false)->assertSee('Pesan kurir sekarang')->assertSee('Customer memilih GoSend')
            ->assertSee('Pesan manual di aplikasi kurir')->assertSee('value="gojek" checked', false)
            // Owner 2026-11-11: no manual handover while staff have not chosen the manual path.
            ->assertDontSee('data-handover-form', false);

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
        $this->assertSame(['082200001111', 'Pesanan '.$order->code.'. Ada kendala? WhatsApp admin toko 082200001111.'], [$booking['origin_contact_phone'], $booking['origin_note']]);
        $this->assertSame('biteship_test.fake', $this->sent[0]->header('Authorization')[0]);

        // A second click books nothing.
        $this->bookCourier($order->fresh(), 'gojek')->assertSessionHas('order_notice.message', 'Kurir sudah dipesan. Batalkan dulu bila perlu memesan ulang.');
        $this->assertCount(1, $this->sent);
        $this->get(route('admin.orders.show', $order))->assertDontSee('data-biteship-chat-driver', false)->assertDontSee('data-biteship-tell-customer', false);

        // Driver found: saved quietly (no new revision for staff working on the page), shown to staff and the customer.
        $revision = $order->revision;
        $this->answer('GET /v1/orders/bs_order_1', $this->biteshipOrder('allocated', ['driver_name' => 'Budi Kurir', 'driver_phone' => '081200000000', 'driver_plate_number' => 'DN 1234 AB', 'link' => 'https://track.example.test/bs_order_1']));
        $this->webhook(['event' => 'order.status', 'order_id' => 'bs_order_1', 'status' => 'allocated'])->assertOk();
        $order->refresh();
        $this->assertSame([$revision, 'allocated', 'Budi Kurir'], [$order->revision, $order->courier_booking['status'], $order->courier_booking['driver_name']]);
        $this->get(route('admin.orders.show', $order))->assertSee('Driver ditemukan')->assertSee('Budi Kurir')->assertSee('DN 1234 AB')
            ->assertSee('data-biteship-track', false)->assertSee('Batalkan kurir')->assertDontSee('data-biteship-book', false)
            // The driver's pickup is the handover: no manual button, only the note.
            ->assertDontSee('data-handover-form', false)->assertSee('Saat driver mengambil paket, pesanan otomatis tercatat sudah diserahkan.')
            // Owner 2026-11-11: WhatsApp the driver (with the customer's number, note and pin) and tell the customer with the tracking link.
            ->assertSee('href="https://wa.me/6281200000000?text=', false)->assertSee(rawurlencode('No. HP/WA penerima: 081234567890'), false)
            ->assertSee(rawurlencode('Titik antar: https://www.google.com/maps?q=-0.9012345,119.8701234'), false)
            ->assertSee('href="tel:081200000000"', false)
            ->assertSee('href="https://wa.me/6281234567890?text=', false)->assertSee(rawurlencode('Lacak driver: https://track.example.test/bs_order_1'), false)
            ->assertSee(rawurlencode('sudah ditemukan dan sedang menuju toko'), false);
        $this->get(route('orders.customer.show', $order->customer_token_encrypted))->assertOk()
            ->assertSee('data-customer-courier="allocated"', false)->assertSee('Driver menuju toko untuk mengambil pesanan')->assertSee('Budi Kurir')
            ->assertSee('https://track.example.test/bs_order_1', false)->assertDontSee('081200000000')
            ->assertSee('Driver akan menghubungi nomor Anda, <span class="tabular-nums">081234567890</span>', false)
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
        $this->get(route('admin.orders.show', $order))->assertSee('saldo Biteship (Owner)')->assertSee('Paket diambil driver')->assertDontSee('Batalkan kurir')
            // Received comes from Biteship; "Tandai diterima customer" stays as the fallback when its status lags (live 2026-11-12).
            ->assertSee('data-delivery-form', false)->assertSee('Saat paket sampai, pesanan otomatis tercatat diterima customer.');

        $this->answer('GET /v1/orders/bs_order_1', $this->biteshipOrder('delivered'));
        $this->webhook(['order_id' => 'bs_order_1', 'status' => 'delivered'])->assertOk();
        $this->assertSame(['delivered', 'website'], [$order->fresh()->delivery_status, $order->fresh()->delivery_confirmed_by]);
        $this->assertSame(1, $order->costs()->count());
    }

    public function test_a_confirmed_order_with_a_driver_shows_the_driver_found_not_searching(): void
    {
        // Live 2026-11-12: Biteship kept "confirmed" while the driver was already on the way.
        $order = $this->packed($this->instantOrder());
        $this->answer('POST /v1/orders', $this->biteshipOrder('confirmed', ['driver_name' => 'Driver Uji', 'driver_phone' => '+6281200000001', 'link' => 'https://track.biteship.com/trk1']));
        $this->actingAs($this->staff);
        $this->bookCourier($order, 'gojek')->assertSessionHas('order_notice.type', 'success');
        $this->assertSame('allocated', $order->fresh()->courier_booking['status']);
        $this->get(route('admin.orders.show', $order))->assertSee('Driver ditemukan')->assertDontSee('Mencari driver');

        // Without a driver yet, "confirmed" still reads as searching.
        $this->assertSame('confirmed', $this->biteshipStatusOf($this->biteshipOrder('confirmed')));
    }

    public function test_staff_record_the_pickup_and_delivery_themselves_when_biteships_status_lags(): void
    {
        // Live 2026-11-12: Biteship stayed "confirmed" while the driver was on the way; nothing may wait on it forever.
        $order = $this->packed($this->instantOrder());
        $this->answer('POST /v1/orders', $this->biteshipOrder('confirmed'));
        $this->actingAs($this->staff);
        $this->bookCourier($order, 'gojek');
        // No driver yet: no manual pickup.
        $this->get(route('admin.orders.show', $order))->assertDontSee('data-biteship-manual-pickup', false);

        $this->answer('GET /v1/orders/bs_order_1', $this->biteshipOrder('confirmed', ['driver_name' => 'Budi Kurir', 'link' => 'https://track.example.test/1']));
        $this->post(route('admin.orders.v2.biteship.refresh', $order), ['_section' => 'pengiriman']);
        $this->get(route('admin.orders.show', $order))->assertSee('data-biteship-manual-pickup', false)->assertSee('Driver sudah ambil paket');

        $this->post(route('admin.orders.v2.handover', $order), ['revision' => $order->fresh()->revision, 'handed_to' => 'courier'])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(['handed_over', 'courier', 'active'], [$order->handover_status, $order->handed_to, $order->courier_booking['state']]);
        $this->get(route('admin.orders.show', $order))->assertSee('Paket diambil driver')->assertDontSee('Batalkan kurir')
            ->assertDontSee('data-biteship-manual-pickup', false)->assertSee('data-delivery-form', false);
        $this->get(route('orders.customer.show', $order->customer_token_encrypted))->assertSee('Pesanan dalam perjalanan ke alamat Anda');

        // Biteship catches up later: no second handover, and the courier cost is still recorded once.
        $this->answer('GET /v1/orders/bs_order_1', $this->biteshipOrder('picked', ['driver_name' => 'Budi Kurir']));
        $this->webhook(['event' => 'order.status', 'order_id' => 'bs_order_1', 'status' => 'picked'])->assertOk();
        $this->assertSame(1, $order->events()->where('kind', 'handed_over')->count());
        $this->assertSame(1, $order->costs()->where('origin', 'website')->count());

        // Delivery Biteship never reports: staff mark it received.
        $this->post(route('admin.orders.v2.delivery', $order), ['revision' => $order->fresh()->revision])->assertSessionHasNoErrors();
        $this->assertSame('delivered', $order->fresh()->delivery_status);
    }

    public function test_biteships_english_refusals_read_in_indonesian_and_no_courier_is_a_refusal_not_unsure(): void
    {
        $order = $this->packed($this->instantOrder());
        $this->actingAs($this->staff);
        $this->answer('POST /v1/orders', ['success' => false, 'error' => 'Key has not been activated', 'code' => 40101001], 400);
        $this->bookCourier($order, 'gojek')->assertSessionHas('order_notice.message',
            'Kurir belum terpesan: Order API kunci Biteship belum aktif. Aktifkan di dashboard Biteship: Integrasi → Kunci API → Aktivasi Order API. (Biteship: Key has not been activated)');

        $this->answer('POST /v1/orders', ['success' => false, 'error' => 'No courier available for this route', 'code' => 40001010], 400);
        $this->bookCourier($order->fresh(), 'gojek')->assertSessionHas('order_notice.message',
            'Kurir belum terpesan: Kurir ini sedang tidak melayani titik atau jam ini. Coba kurir lain atau pesan manual. (Biteship: No courier available for this route)');
        $this->assertSame('failed', $order->fresh()->courier_booking['state']);
    }

    public function test_history_names_website_confirmation_and_claims(): void
    {
        $order = $this->instantOrder();
        foreach (['website_confirmed', 'claimed', 'claim_released'] as $kind) {
            $order->events()->create(['kind' => $kind, 'note' => 'x', 'stage' => $order->stage, 'actor_type' => 'user', 'actor_user_id' => $this->staff->id]);
        }
        $this->actingAs($this->staff)->get(route('admin.orders.show', $order))
            ->assertSee('mengonfirmasi pesanan website')->assertSee('mengambil tugas')->assertSee('melepas tugas')
            ->assertDontSee('website_confirmed');
    }

    private function biteshipStatusOf(array $answer): string
    {
        return (fn () => $this->normalise($answer))->call(app(BiteshipClient::class))['status'];
    }

    public function test_an_empty_info_toko_still_books_with_the_published_store_profile(): void
    {
        // Live 2026-11-12: Info Toko had no phone or address; Biteship refused "origin contact phone/address is filled".
        $store = StoreInfo::getSetting();
        $this->assertSame([null, null], [$store->getRawOriginal('phone_number'), $store->getRawOriginal('address')]);
        $order = $this->packed($this->instantOrder());
        $this->answer('POST /v1/orders', $this->biteshipOrder('confirmed'));
        $this->actingAs($this->staff);
        $this->bookCourier($order, 'gojek')->assertSessionHas('order_notice.type', 'success');
        $booking = $this->sent[0]->data();
        $this->assertSame(['085144924931', '085144924931'], [$booking['origin_contact_phone'], $booking['shipper_contact_phone']]);
        $this->assertSame('Jl. Sis Aljufri, Siranindi, Kec. Palu Barat, Kota Palu, Sulawesi Tengah 94111', $booking['origin_address']);
        $this->assertSame('Qammaris Perfumes', $booking['origin_contact_name']);
        $this->assertStringContainsString('WhatsApp admin toko 085144924931.', $booking['origin_note']);
    }

    public function test_a_booking_with_an_empty_required_field_stops_before_biteship_with_a_clear_reason(): void
    {
        $order = $this->packed($this->instantOrder());
        $order->forceFill(['address' => ''])->save();
        $this->actingAs($this->staff);
        $this->bookCourier($order->fresh(), 'gojek')
            ->assertSessionHas('order_notice.message', 'Kurir belum terpesan: Data untuk kurir belum lengkap: alamat penerima. Lengkapi dulu, lalu pesan lagi.');
        $this->assertSame([], $this->sent);
        $this->assertSame(['failed', 'unassigned'], [$order->fresh()->courier_booking['state'], $order->fresh()->courier_status]);
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

    public function test_a_booked_driver_blocks_cancelling_the_order_and_a_second_courier(): void
    {
        $order = $this->packed($this->instantOrder());
        $this->answer('POST /v1/orders', $this->biteshipOrder('allocated'));
        $this->bookCourier($order, 'gojek')->assertSessionHas('order_notice.type', 'success');

        $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $this->actingAs($owner)->post(route('admin.orders.v2.cancel', $order), ['revision' => $order->fresh()->revision, 'reason' => 'Customer batal pesan', 'refund_due' => '10000'])
            ->assertSessionHas('order_notice.message', 'Kurir Biteship masih dipesan. Batalkan kurir dulu di bagian Pengiriman, lalu batalkan pesanan.');
        $this->post(route('admin.orders.v2.courier', $order), ['revision' => $order->fresh()->revision, 'provider' => 'maxim', 'status' => 'requested',
            'cost_amount' => '15000', 'cost_payer' => 'cashier'])
            ->assertSessionHas('order_notice.message', 'Kurir sedang atau sudah dipesan lewat Biteship. Batalkan kurir Biteship dulu bila ingin memesan manual.');
        $this->assertSame(['active', 'gosend', 0], [$order->fresh()->lifecycle, $order->fresh()->courier_provider, $order->costs()->count()]);
    }

    public function test_an_unanswered_booking_is_unsure_and_booking_again_needs_the_dashboard_check(): void
    {
        $order = $this->packed($this->instantOrder());
        // No readable answer: Biteship may still have booked the driver.
        $this->answer('POST /v1/orders', ['message' => 'Gateway Timeout'], 504);
        $this->bookCourier($order, 'gojek')->assertSessionHas('order_notice.type', 'error');
        $this->assertSame(['unsure', 'unassigned'], [$order->fresh()->courier_booking['state'], $order->fresh()->courier_status]);
        $this->get(route('admin.orders.show', $order))->assertSee('data-biteship-unsure', false)->assertSee($order->code.'-1')
            ->assertSee('Sudah saya cek, belum ada pesanan di Biteship');

        $this->bookCourier($order, 'gojek')->assertSessionHasErrors('checked_biteship');
        $this->assertCount(1, $this->sent);
        $this->answer('POST /v1/orders', $this->biteshipOrder('confirmed'));
        $this->post(route('admin.orders.v2.biteship.book', $order), ['revision' => $order->fresh()->revision, 'courier' => 'gojek', 'checked_biteship' => '1'])
            ->assertSessionHas('order_notice.type', 'success');
        $this->assertSame([$order->code.'-2', 'active'], [end($this->sent)->data()['reference_id'], $order->fresh()->courier_booking['state']]);
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

        // An order made by staff without a pin yet: the manual booking and a hint to get the pin from the order link.
        $manual = $this->packed($this->paidV2Order('local_delivery'));
        $this->get(route('admin.orders.show', $manual))->assertDontSee('data-biteship-book', false)->assertSee('data-courier-booking', false)
            ->assertSee('data-biteship-needs-pin', false)
            ->assertDontSee('Pesan manual di aplikasi kurir')->assertSee('data-handover-form', false);
        $this->bookCourier($manual, 'gojek')->assertSessionHas('order_notice.type', 'error');

        // Booked by hand instead (the "Pesan manual" path): the handover button is back for that courier.
        $byHand = $this->packed($this->instantOrder());
        $this->post(route('admin.orders.v2.courier', $byHand), ['revision' => $byHand->fresh()->revision, 'provider' => 'gosend', 'status' => 'requested',
            'cost_amount' => '15000', 'cost_payer' => 'cashier'])->assertSessionHas('order_notice.type', 'success');
        $this->get(route('admin.orders.show', $byHand))->assertSee('data-handover-form', false)->assertDontSee('data-biteship-book', false);

        // Switched off: the manual form only, and nothing is sent to Biteship.
        config(['orders.shipping_quotes.enabled' => false]);
        $order = $this->packed($this->instantOrder());
        $this->get(route('admin.orders.show', $order))->assertDontSee('data-biteship-book', false)->assertSee('data-courier-booking', false);
        $this->bookCourier($order, 'gojek')->assertSessionHas('order_notice.type', 'error');
        $this->assertSame([], $this->sent);
    }

    public function test_an_admin_made_order_gets_its_pin_on_the_order_link_and_staff_book_the_courier(): void
    {
        $order = $this->packed($this->paidV2Order('local_delivery'));
        $token = $order->customer_token_encrypted;
        $this->get(route('admin.orders.show', $order))->assertSee('data-biteship-needs-pin', false)->assertDontSee('data-biteship-book', false);

        // The customer places the pin on the order link; outside Palu it is refused.
        $this->get(route('orders.customer.show', $token))->assertOk()->assertSee('data-order-pin', false)->assertSee('Tentukan titik di peta');
        $this->post(route('orders.customer.pin', $token), ['latitude' => -5.14, 'longitude' => 119.42])->assertSessionHasErrors('latitude');
        $this->post(route('orders.customer.pin', $token), ['latitude' => -0.8951234, 'longitude' => 119.8561234])
            ->assertSessionHas('success', 'Titik antar tersimpan. Terima kasih.');
        $order->refresh();
        $this->assertSame('https://www.google.com/maps?q=-0.895123,119.856123', $order->location_url, 'staff group, App and Maps use the same link');
        $this->assertSame('Titik antar diisi customer', $order->events()->reorder('id', 'desc')->value('note'));
        $this->get(route('orders.customer.show', $token))->assertSee('Titik antar tersimpan')->assertSee('Ubah titik');

        // Staff now book through Biteship to that pin.
        $this->get(route('admin.orders.show', $order))->assertSee('data-biteship-book', false)->assertSee('Titik antar dari link pesanan')
            ->assertDontSee('data-biteship-needs-pin', false);
        $this->answer('POST /v1/orders', $this->biteshipOrder('confirmed'));
        $this->bookCourier($order, 'grab')->assertSessionHas('order_notice.type', 'success');
        $this->assertSame(['latitude' => -0.895123, 'longitude' => 119.856123], $this->sent[0]->data()['destination_coordinate']);

        // A courier is on the way: the pin is locked on the link.
        $this->get(route('orders.customer.show', $token))->assertDontSee('data-order-pin', false);
        $this->post(route('orders.customer.pin', $token), ['latitude' => -0.9, 'longitude' => 119.87])
            ->assertSessionHas('error', 'Titik antar tidak bisa diubah lagi. Hubungi kami lewat WhatsApp.');
        $this->assertSame('https://www.google.com/maps?q=-0.895123,119.856123', $order->fresh()->location_url);

        // A Google Maps link with coordinates saved by staff is a pin too; a short Sharelok link or a point outside Palu is not.
        $this->assertSame(['latitude' => -0.9, 'longitude' => 119.87], OnlineOrder::pinFromUrl('https://maps.google.com/?q=-0.9,119.87'));
        $this->assertNull(OnlineOrder::pinFromUrl('https://maps.app.goo.gl/AbCdEf'));
        $this->assertNull(OnlineOrder::pinFromUrl('https://www.google.com/maps?q=-5.14,119.42'));
        // A website instant order keeps its checkout pin: its ongkir was priced from it.
        $this->assertFalse($this->instantOrder()->customerCanSetPin());
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

    public function test_an_early_webhook_during_a_booking_is_retried_not_dropped(): void
    {
        $order = $this->instantOrder();
        // Biteship has the order already, but its booking answer is not saved here yet.
        $order->forceFill(['courier_booking' => ['state' => 'booking', 'attempt' => 1, 'courier' => 'gojek', 'started_at' => now()->getTimestamp()]])->saveQuietly();
        $this->webhook(['order_id' => 'bs_order_1', 'status' => 'allocated'])->assertStatus(503);

        // A booking stuck for over two minutes no longer holds back unknown events.
        $this->travel(3)->minutes();
        $this->webhook(['order_id' => 'bs_order_1', 'status' => 'allocated'])->assertOk();
        $this->assertSame([], $this->sent);
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
