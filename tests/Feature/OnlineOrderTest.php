<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OnlineOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_creates_order_with_price_snapshot_and_private_links(): void
    {
        $variant = $this->offer('Mykonos Reflection', 310000);
        $response = $this->actingAs($this->admin)->post(route('admin.orders.store'), [
            'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
        ]);

        $order = OnlineOrder::sole();
        $response->assertRedirect(route('admin.orders.show', $order));
        $this->assertSame('QAM-0001', $order->code);
        $this->assertSame(OnlineOrder::STAGE_AWAITING_CUSTOMER, $order->stage);
        $this->assertSame('620000.00', $order->subtotal());
        $token = $order->customer_token_encrypted;
        $this->assertSame(40, strlen($token));
        $this->assertSame(hash('sha256', $token), $order->customer_token_hash);
        $this->assertStringNotContainsString($token, json_encode(DB::table('online_orders')->first()));

        $variant->update(['price' => 999000]);
        $this->assertSame('620000.00', $order->fresh()->subtotal(), 'Catalog changes never rewrite the agreed order.');
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee(route('orders.customer.show', $token))->assertSee('Rp 620.000');
    }

    public function test_create_rejects_unpublished_or_unpriced_offers_and_non_admins(): void
    {
        $draft = $this->offer('Draft Scent', 100000, ['publication_status' => 'draft']);
        $free = $this->offer('Zero Scent', 0);
        foreach ([$draft, $free] as $variant) {
            $this->actingAs($this->admin)->post(route('admin.orders.store'), ['items' => [['variant_id' => $variant->id, 'quantity' => 1]]])
                ->assertSessionHas('error');
        }
        $this->assertSame(0, OnlineOrder::count());

        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.orders.store'), ['items' => [['variant_id' => $draft->id, 'quantity' => 1]]])->assertForbidden();
    }

    public function test_customer_completes_details_and_sees_private_status(): void
    {
        [$order, $token] = $this->order();
        $page = $this->get(route('orders.customer.show', $token));
        $page->assertOk()->assertSee('Lengkapi data pesanan')->assertSee('Mykonos Reflection');
        $this->assertSame('no-store, private', $page->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $page->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('noindex', $page->headers->get('X-Robots-Tag'));

        $this->post(route('orders.customer.submit', $token), [
            'customer_name' => '  Bondan   Dwi ', 'customer_phone' => '0812-3456-7890', 'fulfillment' => 'local_delivery',
            'address' => 'Dekat masjid', 'postcode' => '12345', 'packaging' => 'no_paperbag',
        ])->assertRedirect(route('orders.customer.show', $token));

        $order->refresh();
        $this->assertSame(OnlineOrder::STAGE_DETAILS_RECEIVED, $order->stage);
        $this->assertSame('Bondan Dwi', $order->customer_name);
        $this->assertSame('081234567890', $order->customer_phone);
        $this->assertNull($order->postcode, 'Local delivery does not keep an intercity postcode.');
        $this->get(route('orders.customer.show', $token))->assertSee('Kirim lokasi lewat WhatsApp')->assertSee('Perjalanan pesanan')
            ->assertSee('https://wa.me/', false);
    }

    public function test_intercity_requires_full_address_and_pickup_drops_address(): void
    {
        [$order, $token] = $this->order();
        $this->post(route('orders.customer.submit', $token), $this->details(['fulfillment' => 'intercity', 'address' => '']))
            ->assertSessionHasErrors('address');
        $this->post(route('orders.customer.submit', $token), $this->details(['fulfillment' => 'intercity', 'address' => 'Pendek']))
            ->assertSessionHasErrors('address');
        $this->assertSame(OnlineOrder::STAGE_AWAITING_CUSTOMER, $order->fresh()->stage);

        $this->post(route('orders.customer.submit', $token), $this->details(['fulfillment' => 'pickup', 'address' => 'Should be ignored']))->assertRedirect();
        $this->assertNull($order->fresh()->address);
        $this->assertSame(['awaiting_customer', 'details_received', 'paid', 'courier_booked', 'completed'], $order->fresh()->steps());
    }

    public function test_customer_may_correct_details_until_paid_then_link_is_read_only(): void
    {
        [$order, $token] = $this->order();
        $this->post(route('orders.customer.submit', $token), $this->details());
        $this->post(route('orders.customer.submit', $token), $this->details(['customer_name' => 'Nama Koreksi']))->assertRedirect();
        $this->assertSame('Nama Koreksi', $order->fresh()->customer_name);

        $this->advance($order, 'details_received', 'paid', ['payment_method' => 'transfer'])->assertSessionHas('success');
        $this->post(route('orders.customer.submit', $token), $this->details(['customer_name' => 'Setelah Bayar']))->assertSessionHas('error');
        $this->assertSame('Nama Koreksi', $order->fresh()->customer_name);
        $this->get(route('orders.customer.show', ['token' => $token, 'ubah' => 1]))->assertOk()
            ->assertDontSee('Simpan perubahan')->assertSee('Data sudah dikunci');
    }

    public function test_unknown_expired_and_replaced_links_are_rejected_without_details(): void
    {
        [$order, $token] = $this->order();
        $this->get(route('orders.customer.show', str_repeat('x', 40)))->assertNotFound()->assertSee('Link tidak berlaku');
        $this->post(route('orders.customer.submit', str_repeat('x', 40)), $this->details())->assertNotFound();

        $this->travel(8)->days();
        $this->get(route('orders.customer.show', $token))->assertNotFound()->assertDontSee('Mykonos');
        $this->travelBack();

        $this->actingAs($this->admin)->post(route('admin.orders.regenerate-link', $order), ['audience' => 'customer'])->assertRedirect();
        $this->get(route('orders.customer.show', $token))->assertNotFound();
        $this->get(route('orders.customer.show', $order->fresh()->customer_token_encrypted))->assertOk();

        $staffToken = $order->staff_token_encrypted;
        $this->actingAs($this->admin)->post(route('admin.orders.regenerate-link', $order), ['audience' => 'staff']);
        $this->get(route('orders.staff.show', $staffToken))->assertNotFound();
    }

    public function test_steps_are_validated_and_replays_are_idempotent(): void
    {
        [$order, $token] = $this->order();
        $this->advance($order, 'awaiting_customer', 'paid', ['payment_method' => 'qris'])->assertSessionHas('error');
        $this->post(route('orders.customer.submit', $token), $this->details());
        $this->advance($order, 'details_received', 'paid')->assertSessionHas('error');
        $this->assertSame('details_received', $order->fresh()->stage);

        $this->advance($order, 'details_received', 'paid', ['payment_method' => 'qris'])->assertSessionHas('success');
        $this->advance($order, 'details_received', 'paid', ['payment_method' => 'qris'])->assertSessionHas('success');
        $this->assertSame(1, $order->events()->where('kind', 'advance')->where('stage', 'paid')->count());
        $this->advance($order, 'paid', 'courier_booked')->assertSessionHas('error', 'Pilih kurir sebelum menandai driver/J&T dipesan.');
    }

    public function test_staff_link_moves_shipping_steps_only_and_records_advance(): void
    {
        [$order, $token] = $this->order();
        $staffToken = $order->staff_token_encrypted;
        $this->post(route('orders.customer.submit', $token), $this->details(['fulfillment' => 'intercity', 'address' => 'Jl. Contoh No. 1, Kel. Sintetis, Jakarta']));

        $this->post(route('orders.staff.advance', $staffToken), ['from' => 'details_received', 'to' => 'paid', 'staff_name' => 'Andi'])
            ->assertSessionHas('error', 'Langkah ini hanya dapat ditandai oleh admin.');
        $this->get(route('orders.staff.show', $staffToken))->assertOk()->assertSee('Menunggu admin mengonfirmasi pembayaran');

        $this->advance($order, 'details_received', 'paid', ['payment_method' => 'transfer']);
        $this->post(route('orders.staff.advance', $staffToken), ['from' => 'paid', 'to' => 'courier_booked', 'staff_name' => 'Andi', 'courier' => 'jnt'])
            ->assertSessionHas('success');
        $this->post(route('orders.staff.advance', $staffToken), ['from' => 'courier_booked', 'to' => 'shipped', 'staff_name' => 'Andi'])
            ->assertSessionHas('error', 'Isi nomor resi J&T sebelum menandai dikirim.');
        $this->post(route('orders.staff.advance', $staffToken), ['from' => 'courier_booked', 'to' => 'shipped', 'staff_name' => 'Andi', 'tracking_number' => 'JX123456'])
            ->assertSessionHas('success');

        $this->post(route('orders.staff.advance-cost', $staffToken), ['staff_name' => 'Ikrar', 'amount' => '11.500'])->assertSessionHas('success');
        $order->refresh();
        $this->assertSame('shipped', $order->stage);
        $this->assertSame('JX123456', $order->tracking_number);
        $this->assertSame('11500.00', $order->staff_advance_amount);
        $this->assertTrue($order->needsReimbursement());
        $this->assertSame('Andi', $order->events()->where('stage', 'shipped')->value('staff_name'));
        $this->assertSame('transfer', $order->payment_method, 'Staff cannot change payment.');

        $this->actingAs($this->admin)->patch(route('admin.orders.reimburse', $order))->assertSessionHas('success');
        $this->assertFalse($order->fresh()->needsReimbursement());
        $this->post(route('orders.staff.advance-cost', $staffToken), ['staff_name' => 'Ikrar', 'amount' => '20000'])->assertSessionHas('error');

        $customer = $this->get(route('orders.customer.show', $token));
        $customer->assertSee('No. resi: JX123456')->assertDontSee('Andi')->assertDontSee('Ikrar')->assertDontSee('11.500');
    }

    public function test_admin_revision_conflict_revert_and_cancel(): void
    {
        [$order, $token] = $this->order();
        $staleRevision = $order->revision;
        $this->post(route('orders.customer.submit', $token), $this->details());
        $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), $this->adminDetails($staleRevision, ['staff_note' => 'Stale']))
            ->assertSessionHas('error');
        $this->assertNull($order->fresh()->staff_note);

        $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), $this->adminDetails($order->fresh()->revision, [
            'shipping_fee' => '11.500', 'shipping_payer' => 'added_to_transfer', 'staff_note' => 'Titip satpam',
        ]))->assertSessionHas('success');
        $order->refresh();
        $this->assertSame('11500.00', $order->shipping_fee);
        $this->assertSame('321500.00', $order->load('items')->customerTotal());

        $this->advance($order, 'details_received', 'paid', ['payment_method' => 'cash']);
        $this->actingAs($this->admin)->patch(route('admin.orders.revert', $order), ['from' => 'paid'])->assertSessionHas('success');
        $this->assertSame('details_received', $order->fresh()->stage);

        $this->actingAs($this->admin)->patch(route('admin.orders.cancel', $order), ['cancel_reason' => 'Customer batal'])->assertSessionHas('success');
        $this->assertSame('cancelled', $order->fresh()->stage);
        $this->get(route('orders.customer.show', $token))->assertSee('Dibatalkan')->assertDontSee('Customer batal');
        $this->actingAs($this->admin)->patch(route('admin.orders.revert', $order), ['from' => 'cancelled'])->assertSessionHas('success');
        $this->assertSame('details_received', $order->fresh()->stage);
    }

    public function test_group_message_variants_follow_who_books_the_courier(): void
    {
        [$order, $token] = $this->order();
        $this->post(route('orders.customer.submit', $token), $this->details());
        $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), $this->adminDetails($order->fresh()->revision, [
            'courier_booked_by' => 'staff', 'courier' => 'maxim', 'location_url' => 'https://maps.app.goo.gl/abc123',
            'shipping_fee' => '20000', 'shipping_payer' => 'added_to_transfer', 'driver_funding' => 'cashier_cash',
        ]))->assertSessionHas('success');
        $this->advance($order, 'details_received', 'paid', ['payment_method' => 'qris']);

        $show = $this->actingAs($this->admin)->get(route('admin.orders.show', $order));
        $message = $show->viewData('groupMessage');
        foreach (['*MOHON DIPESANKAN MAXIM — QAM-0001*', 'Nama: Test Customer', 'Pesanan: Mykonos Reflection 100 ml 1x', 'Kemasan: Tanpa paperbag',
            'HP penerima: 081234567890', 'Lokasi: https://maps.app.goo.gl/abc123', 'sudah dibayar (qris)', 'bayar driver pakai uang kasir',
            route('orders.staff.show', $order->fresh()->staff_token_encrypted)] as $text) {
            $this->assertStringContainsString($text, $message);
        }

        $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), $this->adminDetails($order->fresh()->revision, [
            'courier_booked_by' => 'admin', 'courier' => 'maxim', 'shipping_payer' => 'cash_to_driver', 'shipping_fee' => '20000',
        ]));
        $message = $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->viewData('groupMessage');
        $this->assertStringContainsString('Pengiriman: Maxim (dipesan admin)', $message);
        $this->assertStringContainsString('ongkir Rp 20.000 dibayar customer langsung ke driver', $message);
        $this->assertStringNotContainsString('HP penerima', $message);

        $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), $this->adminDetails($order->fresh()->revision, ['location_url' => 'https://evil.example/maps']))
            ->assertSessionHasErrors('location_url');
    }

    public function test_untrusted_text_is_escaped_and_flattened(): void
    {
        [$order, $token] = $this->order();
        $this->post(route('orders.customer.submit', $token), $this->details(['customer_name' => '<script>alert(1)</script>', 'customer_note' => "Baris\nbaru *tebal*"]));
        $this->get(route('orders.customer.show', $token))->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $message = $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertDontSee('<script>alert(1)</script>', false)->viewData('groupMessage');
        $this->assertStringContainsString('Catatan customer: Baris baru *tebal*', $message);
    }

    public function test_cart_checkout_still_does_not_store_orders(): void
    {
        $variant = $this->offer('Cart Scent', 150000);
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1])->assertOk();
        $this->get(route('cart.checkout.show'))->assertOk();
        $this->post(route('cart.checkout'), [
            'customer_name' => 'Cart Customer', 'customer_phone' => '081234567890',
            'customer_address' => 'Alamat sintetis untuk pengujian checkout', 'checkout_quote' => session('checkout_quote'),
        ])->assertRedirect();
        $this->assertSame(0, OnlineOrder::count());
        $this->assertSame(0, DB::table('online_order_events')->count());
    }

    private function order(): array
    {
        $variant = ProductVariant::query()->first() ?? $this->offer('Mykonos Reflection', 310000);
        $this->actingAs($this->admin)->post(route('admin.orders.store'), ['items' => [['variant_id' => $variant->id, 'quantity' => 1]]]);
        auth()->logout();
        $order = OnlineOrder::query()->latest('id')->firstOrFail();

        return [$order, $order->customer_token_encrypted];
    }

    private function advance(OnlineOrder $order, string $from, string $to, array $extra = [])
    {
        return $this->actingAs($this->admin)->patch(route('admin.orders.advance', $order), ['from' => $from, 'to' => $to] + $extra);
    }

    private function details(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Test Customer', 'customer_phone' => '081234567890', 'fulfillment' => 'local_delivery',
            'address' => '', 'packaging' => 'no_paperbag', 'customer_note' => '',
        ], $overrides);
    }

    private function adminDetails(int $revision, array $overrides = []): array
    {
        return array_merge($this->details(), ['revision' => $revision, 'courier_booked_by' => 'admin'], $overrides);
    }

    private function offer(string $name, int $price, array $product = []): ProductVariant
    {
        $brand = Brand::firstOrCreate(['name' => 'Order Brand'], ['is_active' => true]);
        $category = Category::firstOrCreate(['name' => 'EDP']);
        $model = Product::create(array_merge([
            'name' => $name, 'brand_id' => $brand->id, 'category_id' => $category->id, 'description' => 'Synthetic',
            'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []], 'gender' => 'Unisex', 'base_price' => $price,
            'publication_status' => 'published', 'is_active' => true, 'availability_source' => 'manual',
            'availability_status' => 'available', 'availability_checked_at' => now(),
        ], $product));

        return $model->variants()->create(['volume' => 100, 'price' => $price, 'stock' => 0, 'is_active' => true]);
    }
}
