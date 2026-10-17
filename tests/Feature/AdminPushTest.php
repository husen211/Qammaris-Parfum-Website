<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOnlineOrder;
use App\Jobs\SendAdminPush;
use App\Models\AdminPushSubscription;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\AdminPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** ORD-09 (Owner 2026-10-17): push notification to the order team when someone orders on the website. */
class AdminPushTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/e2e-device-0001';

    private const KEYS = ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth' => 'tBHItJI5svbpez7KI4CCXg'];

    private const TEST_PUBLIC_KEY = 'BIRvSxyIetInb3dbHYSOvO4G-5Ittb7xVoHSLhoHxnw24m73F4dFDTLZRhCZHmFtT5OYuPq4EA6OiR1ARtPF8dw';

    private const TEST_PRIVATE_KEY = 'BSlE3aZMjEC3_3IWlG38NYGfjEHud9UCT5gDZiW3V5M';

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Fixed test-only key pair (not a deployment secret): key generation needs OpenSSL EC, missing on some Windows PHPs.
        config(['admin.pwa_enabled' => true, 'admin_push.vapid.public_key' => self::TEST_PUBLIC_KEY, 'admin_push.vapid.private_key' => self::TEST_PRIVATE_KEY,
            'orders.v2_enabled' => true, 'orders.website_checkout' => true]);
        $this->staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
    }

    public function test_a_device_turns_notifications_on_and_off_for_its_signed_in_account(): void
    {
        $this->actingAs($this->staff)->get(route('admin.orders.index'))->assertOk()
            ->assertSee('data-admin-push-key="'.config('admin_push.vapid.public_key').'"', false)->assertSee('data-admin-push', false)->assertSee('Notifikasi pesanan');

        $this->postJson(route('admin.orders.push-subscriptions.store'), ['endpoint' => self::ENDPOINT, 'keys' => self::KEYS])->assertOk();
        $this->assertSame([$this->staff->id, self::KEYS['auth']], [AdminPushSubscription::sole()->user_id, AdminPushSubscription::sole()->auth_token]);
        $this->postJson(route('admin.orders.push-subscriptions.store'), ['endpoint' => 'http://insecure.test/x', 'keys' => self::KEYS])->assertUnprocessable();

        // Shared store laptop: another account signs in on the same browser and takes the subscription over.
        $other = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $this->actingAs($other)->postJson(route('admin.orders.push-subscriptions.store'), ['endpoint' => self::ENDPOINT, 'keys' => self::KEYS])->assertOk();
        $this->assertSame([1, $other->id], [AdminPushSubscription::count(), AdminPushSubscription::sole()->user_id]);
        // Only the owner of the subscription removes it.
        $this->actingAs($this->staff)->deleteJson(route('admin.orders.push-subscriptions.destroy'), ['endpoint' => self::ENDPOINT])->assertOk();
        $this->assertSame(1, AdminPushSubscription::count());
        $this->actingAs($other)->deleteJson(route('admin.orders.push-subscriptions.destroy'), ['endpoint' => self::ENDPOINT])->assertOk();
        $this->assertSame(0, AdminPushSubscription::count());

        // Accounts outside the order team cannot subscribe.
        $customer = User::factory()->create(['role' => 'customer'])->fresh();
        $this->actingAs($customer)->postJson(route('admin.orders.push-subscriptions.store'), ['endpoint' => self::ENDPOINT, 'keys' => self::KEYS])->assertForbidden();
    }

    public function test_a_new_website_checkout_queues_one_push_without_customer_contact_data(): void
    {
        Queue::fake();
        $variant = $this->variant();
        $key = (string) Str::uuid();
        [$order] = app(CreateOnlineOrder::class)->fromWebsiteCheckout([$variant->id => ['quantity' => 2, 'price' => '150000.00']], $this->details(), $key);
        Queue::assertPushed(SendAdminPush::class, 1);
        Queue::assertPushed(SendAdminPush::class, function (SendAdminPush $job) use ($order) {
            return $job->payload['title'] === 'Pesanan baru dari website'
                && str_contains($job->payload['body'], $order->code.' · 2 barang · ')
                && $job->payload['url'] === route('admin.orders.show', $order, false)
                && ! str_contains(json_encode($job->payload), '080000000101') && ! str_contains(json_encode($job->payload), 'E2E Pembeli');
        });

        // A double tap on "Kirim pesanan" replays the same order: no second push.
        app(CreateOnlineOrder::class)->fromWebsiteCheckout([$variant->id => ['quantity' => 2, 'price' => '150000.00']], $this->details(), $key);
        Queue::assertPushed(SendAdminPush::class, 1);
    }

    public function test_nothing_is_offered_or_sent_until_the_vapid_keys_are_set(): void
    {
        config(['admin_push.vapid.public_key' => '', 'admin_push.vapid.private_key' => '']);
        Queue::fake();
        $this->actingAs($this->staff)->get(route('admin.orders.index'))->assertOk()->assertDontSee('data-admin-push-key', false)->assertDontSee('Notifikasi pesanan');
        $this->postJson(route('admin.orders.push-subscriptions.store'), ['endpoint' => self::ENDPOINT, 'keys' => self::KEYS])->assertNotFound();
        app(CreateOnlineOrder::class)->fromWebsiteCheckout([$this->variant()->id => ['quantity' => 1, 'price' => '150000.00']], $this->details(), (string) Str::uuid());
        Queue::assertNothingPushed();
    }

    public function test_the_job_reaches_the_order_team_only_and_drops_expired_devices(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $writer = User::factory()->create(['role' => User::ROLE_CUSTOMER])->fresh();
        foreach ([[$this->staff, 'staff'], [$owner, 'owner'], [$writer, 'writer'], [$owner, 'gone']] as [$user, $name]) {
            AdminPushSubscription::create(['user_id' => $user->id, 'endpoint' => "https://push.test/{$name}", 'endpoint_hash' => AdminPushSubscription::hashEndpoint("https://push.test/{$name}"),
                'public_key' => self::KEYS['p256dh'], 'auth_token' => self::KEYS['auth']]);
        }
        $fake = new class extends AdminPushSender
        {
            public array $endpoints = [];

            public function send(Collection $subscriptions, array $payload): array
            {
                $this->endpoints = $subscriptions->pluck('endpoint')->sort()->values()->all();

                return ['sent' => count($this->endpoints) - 1, 'expired' => [AdminPushSubscription::hashEndpoint('https://push.test/gone')]];
            }
        };
        $this->app->instance(AdminPushSender::class, $fake);

        (new SendAdminPush(['title' => 't', 'body' => 'b', 'url' => '/admin/orders', 'tag' => 'x']))->handle($fake);
        $this->assertSame(['https://push.test/gone', 'https://push.test/owner', 'https://push.test/staff'], $fake->endpoints, 'Accounts outside the order team get no order push');
        $this->assertSame(['https://push.test/owner', 'https://push.test/staff', 'https://push.test/writer'], AdminPushSubscription::orderBy('endpoint')->pluck('endpoint')->all());
        $this->assertNotNull(AdminPushSubscription::where('endpoint', 'https://push.test/staff')->value('last_sent_at'));

        // "Kirim tes": only the asking account's devices.
        (new SendAdminPush(['title' => 't', 'body' => 'b', 'url' => '/admin/orders', 'tag' => 'x'], $this->staff->id))->handle($fake);
        $this->assertSame(['https://push.test/staff'], $fake->endpoints);
        Queue::fake();
        $this->actingAs($this->staff)->postJson(route('admin.orders.push-subscriptions.test'))->assertOk();
        Queue::assertPushed(SendAdminPush::class, fn (SendAdminPush $job) => $job->onlyUserId === $this->staff->id);
    }

    public function test_the_key_command_prints_a_pair_without_storing_it(): void
    {
        if (! @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC])) {
            $this->markTestSkipped('OpenSSL EC keys unavailable here (Windows PHP without OPENSSL_CONF); Linux servers and CI have them.');
        }
        $this->artisan('admin-push:vapid')->expectsOutputToContain('ADMIN_PUSH_VAPID_PUBLIC_KEY=')->expectsOutputToContain('ADMIN_PUSH_VAPID_PRIVATE_KEY=')->assertSuccessful();
    }

    private function details(): array
    {
        return ['customer_name' => 'E2E Pembeli Sintetis', 'customer_phone' => '080000000101', 'fulfillment' => 'pickup', 'packaging' => 'paperbag', 'payment_preference' => 'qris'];
    }

    private function variant(): ProductVariant
    {
        $brand = Brand::firstOrCreate(['name' => 'E2E Brand Sintetis'], ['is_active' => true]);
        $product = Product::create(['brand_id' => $brand->id, 'category_id' => Category::firstOrCreate(['name' => 'EDP'])->id, 'name' => 'Push Sintetis',
            'description' => 'Sintetis.', 'base_price' => 150000, 'gender' => 'Unisex', 'is_active' => true, 'availability_status' => 'available', 'availability_source' => 'qammaris_app']);

        return ProductVariant::create(['product_id' => $product->id, 'volume' => 50, 'price' => 150000, 'sku' => 'ORD09-PUSH-'.Str::random(4), 'stock' => 1, 'is_active' => true]);
    }
}
