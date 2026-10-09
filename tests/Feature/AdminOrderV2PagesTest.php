<?php

namespace Tests\Feature;

use App\Actions\Orders\OnlineOrderWorkflow;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderPayment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\OnlineOrderState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/** ORD-02d: Admin PWA pages for V2 orders, through real routes and permissions. */
class AdminOrderV2PagesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    private ProductVariant $alpha;

    private ProductVariant $beta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['orders.v2_enabled' => true]);
        $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'name' => 'Pemilik'])->fresh();
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER, 'name' => 'Andi Staf'])->fresh();
        $brand = Brand::create(['name' => 'Merek Uji', 'is_active' => true]);
        $category = Category::create(['name' => 'EDP']);
        [$this->alpha, $this->beta] = array_map(function (array $spec) use ($brand, $category) {
            [$name, $price] = $spec;
            $product = Product::create([
                'name' => $name, 'brand_id' => $brand->id, 'category_id' => $category->id, 'description' => 'Synthetic',
                'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []], 'gender' => 'Unisex', 'base_price' => $price,
                'publication_status' => 'published', 'is_active' => true, 'availability_source' => 'manual',
                'availability_status' => 'available', 'availability_checked_at' => now(),
            ]);

            return $product->variants()->create(['volume' => 50, 'price' => $price, 'stock' => 0, 'is_active' => true]);
        }, [['Aroma Alpha', 150000], ['Aroma Beta', 100000]]);
    }

    public function test_staff_creates_an_instagram_order_with_complete_data_and_a_new_repeat_customer(): void
    {
        $this->actingAs($this->staff)->get('/admin/orders/create')->assertOk()
            ->assertSee('Pesanan dari')->assertSee('Pelanggan lama? Cari nama atau nomor WA')
            ->assertSee('Data sudah lengkap')->assertSee('Kirim link ke customer');

        $response = $this->post('/admin/orders', $this->orderPayload([
            'source' => 'instagram', 'fill_customer' => '1', 'new_customer' => '1',
        ] + $this->recipient('local_delivery')));
        $order = OnlineOrder::sole();
        $response->assertRedirect(route('admin.orders.show', $order))->assertSessionHas('success', fn ($message) => str_contains($message, 'Customer tidak perlu mengisi form'));
        $this->assertSame([OnlineOrder::STATE_V2, 'instagram', 'active', 'needs_handling'], [$order->state_model, $order->source, $order->lifecycle, OnlineOrderState::queue($order)]);
        $this->assertSame(['Siti Sintetis', '6281234567890', $order->id], [Customer::sole()->name, Customer::sole()->phone, $order->customer->orders()->value('id')]);

        $page = $this->get(route('admin.orders.show', $order))->assertOk();
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $page->assertSee('data-order-v2', false)->assertSee('Berikutnya: Mulai siapkan pesanan.')
            ->assertSee('Salin link')->assertSee('Salin untuk grup')->assertSee('Konfirmasi packing')
            ->assertSee(route('admin.orders.show', $order), false)
            ->assertDontSee('/tugas-pesanan/', false)
            ->assertDontSee('Keuangan · Super Admin');
    }

    public function test_repeat_customer_and_saved_address_are_chosen_explicitly_and_the_link_mode_waits_for_the_customer(): void
    {
        $customer = Customer::create(['name' => 'Budi Langganan', 'phone' => '6285200000000', 'created_by' => $this->owner->id]);
        $address = CustomerAddress::forceCreate(['customer_id' => $customer->id, 'label' => 'Rumah', 'type' => 'intercity', 'address' => 'Jl. Makassar 10',
            'postcode' => '90111', 'confirmed_by' => $this->owner->id]);

        $search = $this->actingAs($this->staff)->getJson('/admin/orders/customer-search?q=0852 0000 0000')->assertOk()->json('items');
        $this->assertSame([['id' => $customer->id, 'name' => 'Budi Langganan', 'phone' => '085200000000', 'addresses' => [
            ['id' => $address->id, 'label' => 'Rumah', 'type' => 'intercity', 'address' => 'Jl. Makassar 10', 'postcode' => '90111']]]], $search);
        $this->assertSame([], $this->getJson('/admin/orders/customer-search?q=ab')->json('items'), 'Short terms return nothing');

        $this->post('/admin/orders', $this->orderPayload(['customer_id' => $customer->id, 'customer_address_id' => $address->id, 'fill_customer' => '0']));
        $order = OnlineOrder::sole();
        $this->assertSame(['awaiting_customer', $customer->id, $address->id, 'intercity', 'Jl. Makassar 10', '90111'],
            [$order->lifecycle, $order->customer_id, $order->customer_address_id, $order->fulfillment, $order->address, $order->postcode]);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Kirim link ini agar customer mengisi data')->assertSee('Pesan untuk customer');

        $stranger = CustomerAddress::forceCreate(['customer_id' => Customer::create(['name' => 'Lain', 'phone' => '6281111111111', 'created_by' => $this->owner->id])->id,
            'label' => 'X', 'type' => 'local', 'address' => 'Jl. Lain', 'confirmed_by' => $this->owner->id]);
        $this->post('/admin/orders', $this->orderPayload(['customer_id' => $customer->id, 'customer_address_id' => $stranger->id]))
            ->assertSessionHasErrors('customer_address_id');
        $this->assertSame(1, OnlineOrder::count());
    }

    public function test_payment_packing_courier_handover_and_delivery_through_the_pages(): void
    {
        $order = $this->activeOrder('local_delivery');
        $this->actingAs($this->staff);

        $this->post(route('admin.orders.v2.payments', $order), ['revision' => $order->revision, 'amount' => '400.000', 'payment_method' => 'qris', 'confirmation_source' => 'majoo', 'recorded_in_majoo' => '1'])
            ->assertRedirect(route('admin.orders.show', $order).'#pembayaran')->assertSessionHas('order_notice.type', 'success');
        $order->refresh();
        $this->assertSame(['paid', true], [$order->payment_status, $order->recorded_in_majoo]);

        // A second tap with the old revision is a conflict, not a second payment.
        $this->post(route('admin.orders.v2.payments', $order), ['revision' => 1, 'amount' => '400000', 'payment_method' => 'qris', 'confirmation_source' => 'majoo'])
            ->assertSessionHas('order_notice.type', 'conflict');
        $this->assertSame(1, OnlineOrderPayment::count());
        $this->get(route('admin.orders.show', $order))->assertSee('Lunas.')->assertSee('Majoo');

        [$alpha, $beta] = $order->items->pluck('line_id')->all();
        $this->post(route('admin.orders.v2.pack', $order), ['revision' => $order->revision, '_section' => 'packing', 'packed' => [$alpha => 1, $beta => 1]])
            ->assertSessionHas('order_notice', fn ($notice) => $notice['section'] === 'packing' && $notice['type'] === 'error')
            ->assertSessionHasErrors('packed_items.0.quantity');
        $this->followingRedirects()->post(route('admin.orders.v2.pack', $order), ['revision' => $order->revision, '_section' => 'packing', 'packed' => [$alpha => 1, $beta => 1]])
            ->assertSee('Jumlah packing harus sama persis')->assertSee('Harus 2 sesuai pesanan');
        $this->post(route('admin.orders.v2.pack', $order->refresh()), ['revision' => $order->revision, 'packed' => [$alpha => 2, $beta => 1]])->assertSessionHas('order_notice.type', 'success');
        $this->assertSame('packed', $order->refresh()->preparation_status);

        $this->post(route('admin.orders.v2.courier', $order), ['revision' => $order->revision, 'provider' => 'maxim', 'status' => 'requested', 'reference' => 'MX-1']);
        $this->post(route('admin.orders.v2.handover', $order->refresh()), ['revision' => $order->revision, 'handed_to' => 'courier']);
        $order->refresh();
        $this->assertSame(['handed_over', 'completed'], [$order->handover_status, $order->lifecycle], 'Paid + handed over completes');
        $this->post(route('admin.orders.v2.delivery', $order), ['revision' => $order->revision]);
        $this->assertSame('delivered', $order->refresh()->delivery_status);
        $this->get(route('admin.orders.show', $order))->assertSee('Diterima customer')->assertSee('menyerahkan pesanan');
    }

    public function test_jnt_steps_issue_and_keep_through_the_pages(): void
    {
        $order = $this->activeOrder('intercity');
        $this->actingAs($this->staff);
        $this->post(route('admin.orders.v2.jnt', $order), ['revision' => $order->revision, 'tracking_number' => 'JX-77']);
        $this->post(route('admin.orders.v2.keep', [$order->refresh(), 'start']), ['revision' => $order->revision, 'hours' => 12]);
        $this->post(route('admin.orders.v2.keep', [$order->refresh(), 'stock']), ['revision' => $order->revision]);
        $order->refresh();
        $this->assertSame(['JX-77', 'active'], [$order->tracking_number, $order->keep_status]);
        $this->assertNotNull($order->keep_stock_confirmed_at);
        $this->get(route('admin.orders.show', $order))->assertSee('Stok sudah dipisahkan')->assertSee('Keep sampai');

        $this->post(route('admin.orders.v2.issues', $order), ['type' => 'stock_problem', 'note' => 'Beta tinggal 0', 'line_id' => $order->items->last()->line_id, 'reported_quantity' => 0])
            ->assertSessionHas('order_notice.type', 'success');
        $issue = $order->issues()->sole();
        $this->get(route('admin.orders.show', $order))->assertSee('1 kendala')->assertSee('Berikutnya: Selesaikan kendala dulu.');
        $this->post(route('admin.orders.v2.issues.resolve', [$order, $issue->public_id]), ['note' => 'Diganti varian']);
        $this->assertSame('resolved', $issue->fresh()->status);

        $this->post(route('admin.orders.v2.pack', $order->refresh()), ['revision' => $order->revision, 'packed' => $order->items->mapWithKeys(fn ($item) => [$item->line_id => $item->quantity])->all()]);
        $this->post(route('admin.orders.v2.jnt', $order->refresh()), ['revision' => $order->revision, 'status' => 'picked_up']);
        $order->refresh();
        $this->assertSame(['picked_up', 'handed_over', 'jnt'], [$order->jnt_status, $order->handover_status, $order->handed_to]);
        $this->assertSame('converted', $order->keep_status, 'Handover ends the keep');
    }

    public function test_super_admin_panel_refund_reverse_and_adjustment_approval_with_staff_denied(): void
    {
        $order = $this->activeOrder('pickup');
        $this->actingAs($this->staff)->post(route('admin.orders.v2.payments', $order), ['revision' => $order->revision, 'amount' => '400000', 'payment_method' => 'transfer', 'confirmation_source' => 'proof_in_chat']);
        $order->refresh();

        foreach ([
            route('admin.orders.money.refund-decision', $order) => ['refund_due' => '0', 'reason' => 'Tidak ada refund'],
            route('admin.orders.money.refunds', $order) => ['amount' => '1000', 'refund_method' => 'cash'],
            route('admin.orders.money.reverse', [$order, OnlineOrderPayment::sole()->id]) => ['reason' => 'Salah catat'],
            route('admin.orders.money.reconcile', $order) => ['received' => '1', 'refund_due' => '0', 'already_refunded' => '0', 'note' => 'Cek mutasi'],
        ] as $url => $data) {
            $this->post($url, $data + ['revision' => $order->revision])->assertForbidden();
        }
        $this->post(route('admin.orders.v2.adjustments', $order), ['revision' => $order->revision, 'direction' => 'discount', 'amount' => '20.000', 'reason' => 'Diskon member'])
            ->assertSessionHas('order_notice.type', 'success');
        $adjustment = $order->adjustments()->sole();
        $this->post(route('admin.orders.v2.adjustments.decide', [$order->refresh(), $adjustment->id, 'approve']), ['revision' => $order->revision])->assertForbidden();
        $this->get(route('admin.orders.show', $order))->assertSee('Menunggu persetujuan Super Admin')->assertDontSee('Keuangan · Super Admin');

        $this->actingAs($this->owner);
        $this->post(route('admin.orders.v2.adjustments.decide', [$order, $adjustment->id, 'approve']), ['revision' => $order->revision]);
        $order->refresh();
        $this->assertSame('380000.00', $order->customerTotal());
        $page = $this->get(route('admin.orders.show', $order))->assertSee('Keuangan · Super Admin')->assertSee('Lebih bayar');

        $this->post(route('admin.orders.money.refund-decision', $order), ['revision' => $order->revision, 'refund_due' => '20.000', 'reason' => 'Kembalikan selisih diskon']);
        $this->post(route('admin.orders.money.refunds', $order->refresh()), ['revision' => $order->revision, 'amount' => '20000', 'refund_method' => 'transfer', 'reference' => 'TRF-9']);
        $order->refresh();
        $this->assertSame(['refunded', 'refunded'], [$order->refund_status, $order->payment_status]);

        $refund = OnlineOrderPayment::where('type', 'refund')->sole();
        $this->post(route('admin.orders.money.reverse', [$order, $refund->id]), ['revision' => $order->revision, 'reason' => 'Transfer gagal']);
        $this->assertSame('pending', $order->refresh()->refund_status);
        $this->get(route('admin.orders.show', $order))->assertSee('Pembatalan entri')->assertSee('Sisa refund');
    }

    public function test_customer_change_request_is_reviewed_and_legacy_paths_refuse_v2_orders(): void
    {
        $order = $this->activeOrder('local_delivery');
        $this->actingAs($this->staff)->post(route('admin.orders.v2.preparation', $order), ['revision' => $order->revision]);
        $token = $order->refresh()->customer_token_encrypted;

        $this->get(route('orders.customer.show', $token))->assertOk()->assertSee('Ajukan perubahan');
        $this->post(route('orders.customer.submit', $token), $this->recipient('local_delivery', ['address' => 'Jl. Baru 77, pagar hitam']))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Permintaan perubahan terkirim'));
        $this->assertSame('Jl. Contoh 1', $order->refresh()->address);
        $this->get(route('orders.customer.show', $token))->assertSee('sedang ditinjau toko');

        $change = $order->changeRequests()->sole();
        $this->get(route('admin.orders.show', $order))->assertSee('Permintaan perubahan')->assertSee('Jl. Baru 77, pagar hitam');
        $this->post(route('admin.orders.v2.change-requests.decide', [$order, $change->id, 'approve']), ['revision' => $order->revision]);
        $this->assertSame('Jl. Baru 77, pagar hitam', $order->refresh()->address);

        // ORD-01 endpoints never act on a V2 order.
        $this->patch(route('admin.orders.update', $order), $this->recipient('pickup') + ['revision' => $order->revision, 'courier_booked_by' => 'admin'])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'alur status baru'));
        $this->patch(route('admin.orders.advance', $order), ['from' => $order->stage, 'to' => 'paid', 'payment_method' => 'qris'])->assertSessionHas('error');
        $this->get(route('orders.staff.show', $order->staff_token_encrypted))->assertNotFound();
        $this->assertSame('local_delivery', $order->refresh()->fulfillment);
    }

    public function test_customer_confirms_receipt_of_a_v2_delivery_and_the_legacy_page_offers_reconciliation(): void
    {
        $order = $this->activeOrder('local_delivery');
        $this->actingAs($this->staff);
        $this->post(route('admin.orders.v2.pack', $order), ['revision' => $order->revision, 'packed' => $order->items->mapWithKeys(fn ($item) => [$item->line_id => $item->quantity])->all()]);
        $this->post(route('admin.orders.v2.handover', $order->refresh()), ['revision' => $order->revision, 'handed_to' => 'courier']);
        $token = $order->refresh()->customer_token_encrypted;
        auth()->logout();

        $this->get(route('orders.customer.show', $token))->assertSee('Pesanan sudah saya terima');
        $this->post(route('orders.customer.received', $token))->assertSessionHas('success');
        $order->refresh();
        $this->assertSame(['delivered', 'customer'], [$order->delivery_status, $order->delivery_confirmed_by]);
        $this->assertSame('customer', $order->events()->reorder('id', 'desc')->value('actor_type'));
        $this->get(route('orders.customer.show', $token))->assertDontSee('Pesanan sudah saya terima');

        config(['orders.v2_enabled' => false]);
        $legacy = $this->activeOrder('pickup');
        $legacy = app(OnlineOrderWorkflow::class)->advance($legacy, 'details_received', 'paid', 'admin', $this->owner, extra: ['payment_method' => 'transfer']);
        app(OnlineOrderWorkflow::class)->cancel($legacy, 'Customer batal', $this->owner);
        $this->actingAs($this->owner)->get(route('admin.orders.show', $legacy))->assertSee('Perlu rekonsiliasi')->assertSee('Simpan rekonsiliasi');
        $this->actingAs($this->staff)->get(route('admin.orders.show', $legacy))->assertDontSee('Simpan rekonsiliasi');
        $this->actingAs($this->owner)->get('/admin/orders?status=reconcile')->assertSee($legacy->code)->assertSee('Rekonsiliasi');
    }

    public function test_no_admin_form_field_is_named_method(): void
    {
        // A field called "method" shadows form.method in the browser and broke the submit guard (found in ORD-02d browser checks).
        $offenders = collect(File::allFiles(resource_path('views/admin')))
            ->filter(fn ($file) => preg_match('/name="method"/', $file->getContents()))->map->getRelativePathname()->values()->all();
        $this->assertSame([], $offenders);
    }

    private function activeOrder(string $fulfillment): OnlineOrder
    {
        $this->actingAs($this->owner)->post('/admin/orders', $this->orderPayload(['fill_customer' => '1'] + $this->recipient($fulfillment)));

        return OnlineOrder::latest('id')->first()->load('items');
    }

    private function orderPayload(array $extra = []): array
    {
        return ['items' => [['variant_id' => $this->alpha->id, 'quantity' => 2], ['variant_id' => $this->beta->id, 'quantity' => 1]],
            'submission_token' => (string) Str::uuid()] + $extra;
    }

    private function recipient(string $fulfillment, array $override = []): array
    {
        return array_merge([
            'customer_name' => 'Siti Sintetis', 'customer_phone' => '081234567890', 'fulfillment' => $fulfillment,
            'address' => $fulfillment === 'pickup' ? '' : ($fulfillment === 'intercity' ? 'Jl. Contoh 1 No. 5, Makassar' : 'Jl. Contoh 1'),
            'postcode' => $fulfillment === 'intercity' ? '90111' : '', 'packaging' => 'no_paperbag', 'customer_note' => '',
        ], $override);
    }
}
