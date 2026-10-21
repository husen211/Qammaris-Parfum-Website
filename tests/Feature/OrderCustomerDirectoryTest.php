<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOnlineOrder;
use App\Actions\Orders\OnlineOrderFulfillment;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\IntegrationOutbox;
use App\Models\OnlineOrder;
use App\Models\User;
use App\Support\OrderActor;
use App\Support\OrderApi\OrderApiSchema;
use App\Support\OrderApi\OrderSerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/** ORD-05: customers saved automatically, the Pelanggan pages, address memory, Super Admin removal and the backfill. */
class OrderCustomerDirectoryTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        config(['orders.v2_enabled' => true, 'orders.website_checkout' => true, 'orders.simple_ux' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
    }

    public function test_customer_link_and_confirmed_website_orders_are_remembered_by_number(): void
    {
        // The customer fills the link: the order joins the customer with that number (created on first sight).
        $linkOrder = $this->v2Order(null);
        $this->post(route('orders.customer.submit', $linkOrder->customer_token_encrypted), [
            'customer_name' => 'E2E Rina Sintetis', 'customer_phone' => '0812-0000-0099', 'fulfillment' => 'pickup',
            'packaging' => 'paperbag', 'payment_preference' => 'transfer',
        ])->assertSessionHasNoErrors();
        $customer = Customer::sole();
        $this->assertSame(['E2E Rina Sintetis', '6281200000099', $customer->id], [$customer->name, $customer->phone, $linkOrder->fresh()->customer_id]);

        // A website checkout is only a draft: nobody is remembered until staff confirm it after the chat.
        $variant = array_key_first($this->orderLines);
        [$draft] = app(CreateOnlineOrder::class)->fromWebsiteCheckout([$variant => ['quantity' => 1, 'price' => '150000']], [
            'customer_name' => 'Rina', 'customer_phone' => '+62 812 0000 0099', 'fulfillment' => 'pickup', 'packaging' => 'no_paperbag',
        ], (string) Str::uuid());
        $this->assertNull($draft->customer_id);
        $confirmed = app(OnlineOrderFulfillment::class)->confirmWebsiteOrder($draft, $draft->revision, OrderActor::user($this->staff));
        $this->assertSame([$customer->id, 1], [$confirmed->customer_id, Customer::count()], 'same number = same customer');
    }

    public function test_handover_keeps_the_address_once_and_the_create_form_has_no_manual_save(): void
    {
        $this->actingAs($this->staff)->get(route('admin.orders.create'))->assertOk()
            ->assertDontSee('name="new_customer"', false)->assertSee('otomatis tersimpan');
        $ops = app(OnlineOrderFulfillment::class);
        $actor = OrderActor::user($this->staff);
        foreach ([1, 2] as $round) {
            $order = $this->payInFull($this->v2Order('local_delivery', ['address' => 'Jl. Sintetis 7, pagar hijau']), $this->staff);
            $order = $ops->pack($order, $order->revision, $actor, $order->items->map(fn ($item) => ['line_id' => $item->line_id, 'quantity' => $item->quantity])->all());
            $order = $ops->requestCourier($order, $order->revision, $actor, 'maxim', 'arrived');
            $order = $ops->handover($order, $order->revision, $actor, 'courier');
            $address = CustomerAddress::sole();
            $this->assertSame([$order->customer_id, 'local', 'Jl. Sintetis 7, pagar hijau', $address->id], [$address->customer_id, $address->type, $address->address, $order->customer_address_id], "round {$round}");
        }
        $this->assertSame(1, Customer::count());
    }

    public function test_pelanggan_pages_for_order_staff_only_with_search_and_edit(): void
    {
        $order = $this->v2Order(details: ['customer_name' => 'E2E Wira Sintetis', 'customer_phone' => '081200000077']);
        $customer = Customer::sole();

        $this->actingAs($this->staff)->get(route('admin.customers.index'))->assertOk()->assertSee('E2E Wira Sintetis')->assertSee('081200000077')->assertSee('1 pesanan');
        $this->get(route('admin.customers.index', ['search' => '0812-0000-0077']))->assertSee('E2E Wira Sintetis');
        $this->get(route('admin.customers.index', ['search' => 'tidak ada']))->assertSee('Pelanggan tidak ditemukan');
        $this->get(route('admin.customers.show', $customer))->assertOk()->assertSee($order->code)->assertDontSee('Hapus data pelanggan');
        $this->patch(route('admin.customers.update', $customer), ['name' => 'E2E Wira S', 'note' => 'Suka oud'])->assertRedirect();
        $this->assertSame(['E2E Wira S', 'Suka oud'], [$customer->fresh()->name, $customer->fresh()->note]);
        $this->post(route('admin.customers.anonymize', $customer), ['confirm' => 'HAPUS'])->assertForbidden();

        $outsider = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $this->actingAs($outsider)->get(route('admin.customers.index'))->assertForbidden();
    }

    public function test_super_admin_removes_personal_data_only_without_running_orders_and_the_app_gets_the_cleared_copy(): void
    {
        config(['orders_api.webhook_enabled' => true]);
        $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $order = $this->v2Order('intercity', ['customer_name' => 'E2E Dewi Sintetis', 'customer_phone' => '081200000055', 'customer_note' => 'Titip satpam']);
        $customer = Customer::sole();
        IntegrationOutbox::query()->update(['delivered_at' => now()]);

        $this->actingAs($owner)->get(route('admin.customers.show', $customer))->assertSee('Hapus data pelanggan');
        $this->post(route('admin.customers.anonymize', $customer), ['confirm' => 'hapus aja'])->assertSessionHasErrors('confirm');
        $this->post(route('admin.customers.anonymize', $customer), ['confirm' => 'HAPUS'])->assertSessionHas('error');
        $this->assertNull($customer->fresh()->anonymized_at, 'an order still in progress blocks the removal');

        $order->forceFill(['lifecycle' => 'completed', 'stage' => OnlineOrder::STAGE_COMPLETED])->save();
        $revision = $order->fresh()->revision;
        $this->post(route('admin.customers.anonymize', $customer), ['confirm' => 'HAPUS'])->assertRedirect(route('admin.customers.index'));

        $customer->refresh();
        $order->refresh();
        $this->assertNotNull($customer->anonymized_at);
        $this->assertSame(['Pelanggan dihapus', '', $owner->id], [$customer->name, $customer->phone, $customer->anonymized_by]);
        $this->assertSame(['Pelanggan dihapus', null, null, null, null], [$order->customer_name, $order->customer_phone, $order->address, $order->postcode, $order->customer_note]);
        $this->assertSame([$order->code, $revision + 1], [OnlineOrder::sole()->code, $order->revision], 'code and money stay; a new revision informs the App');
        $this->assertSame([], OrderApiSchema::errors(json_decode(json_encode(OrderSerializer::order($order))), 'Order'));
        $bodies = IntegrationOutbox::orderBy('id')->pluck('body');
        $this->assertStringNotContainsString('081200000055', $bodies->implode(' '));
        $this->assertStringNotContainsString('E2E Dewi', $bodies->implode(' '));
        $this->assertSame('customer_anonymized', $order->events()->reorder('id', 'desc')->value('kind'));

        $this->get(route('admin.customers.index'))->assertDontSee('Pelanggan dihapus');
        $this->get(route('admin.customers.show', $customer))->assertNotFound();
        $this->getJson(route('admin.orders.customer-search', ['q' => '081200000055']))->assertExactJson(['items' => []]);
        // The number may order again later and becomes a new customer.
        $this->v2Order(details: ['customer_name' => 'E2E Dewi Baru', 'customer_phone' => '081200000055']);
        $this->assertSame(2, Customer::count());
    }

    public function test_backfill_previews_without_writing_then_links_without_webhooks(): void
    {
        config(['orders_api.webhook_enabled' => true]);
        $first = $this->v2Order(details: ['customer_phone' => '081200000011']);
        $second = $this->v2Order(details: ['customer_phone' => '0812 0000 0011']);
        $third = $this->v2Order(details: ['customer_phone' => '081200000022']);
        $noPhone = $this->v2Order(null);
        OnlineOrder::query()->update(['customer_id' => null]);
        Customer::query()->delete();
        $outbox = IntegrationOutbox::count();

        $this->artisan('qammaris:customers:backfill')->expectsOutputToContain('Preview only')->assertSuccessful();
        $this->assertSame(0, Customer::count());

        $this->artisan('qammaris:customers:backfill', ['--apply' => true])->expectsOutputToContain('Linked 3 order(s)')->assertSuccessful();
        $this->assertSame(2, Customer::count());
        $this->assertSame($first->fresh()->customer_id, $second->fresh()->customer_id);
        $this->assertNotSame($first->fresh()->customer_id, $third->fresh()->customer_id);
        $this->assertNull($noPhone->fresh()->customer_id);
        $this->assertSame($outbox, IntegrationOutbox::count(), 'backfill sends nothing to the App');
        $this->artisan('qammaris:customers:backfill', ['--apply' => true])->expectsOutputToContain('Linked 0 order(s)');
    }
}
