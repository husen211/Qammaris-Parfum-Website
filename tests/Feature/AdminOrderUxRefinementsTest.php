<?php

namespace Tests\Feature;

use App\Models\StoreInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/** ADM-01 (Owner 2026-10-17): admin shell scrolling, refresh button, Sharelok after checkout. */
class AdminOrderUxRefinementsTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['orders.v2_enabled' => true, 'orders.simple_ux' => true]);
    }

    public function test_admin_shell_is_light_with_a_single_scroller(): void
    {
        // OS dark mode made the canvas behind the admin shell black and the document scrolled under <main>.
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $html = $this->actingAs($staff)->get(route('admin.orders.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<html lang="id" data-theme="light" class="h-full bg-gray-50 \[color-scheme:light\]"/', $html);
        $this->assertStringContainsString('<body data-navigation-shell="admin" class="h-full overflow-hidden', $html);
        $this->assertStringContainsString('<main class="relative flex-1 overflow-x-hidden overflow-y-auto overscroll-contain', $html, 'absolute children (sr-only) must not extend the document');
        $this->post('/admin/logout');
        $this->get('/admin/login')->assertOk()->assertSee('data-theme="light"', false);
    }

    public function test_order_pages_offer_a_get_refresh_and_a_hint_while_waiting_for_the_customer(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $order = $this->v2Order(null);
        $this->assertSame('awaiting_customer', $order->lifecycle);
        $page = $this->actingAs($staff)->get(route('admin.orders.show', $order))->assertOk();
        $page->assertSee('href="'.route('admin.orders.show', $order).'" data-admin-refresh', false)
            ->assertSee('Muat ulang')->assertSee('data-refresh-hint', false);
        $this->get(route('admin.orders.index', ['status' => 'all']))->assertOk()->assertSee('data-admin-refresh', false)
            ->assertSee('href="'.route('admin.orders.index', ['status' => 'all']).'" data-admin-refresh', false);

        $order->forceFill(['lifecycle' => 'active'])->save();
        $this->get(route('admin.orders.show', $order))->assertSee('data-admin-refresh', false)->assertDontSee('data-refresh-hint', false);
    }

    public function test_sharelok_shows_right_after_a_website_checkout_in_production(): void
    {
        StoreInfo::create(['whatsapp_number' => '6285144924931']);
        $order = $this->v2Order('local_delivery');
        $order->forceFill(['lifecycle' => 'draft', 'source' => 'website', 'created_by' => null])->save();
        $link = route('orders.customer.show', $order->customer_token_encrypted);

        $this->app['env'] = 'production';
        $this->get($link)->assertOk()->assertSee('Kirim Sharelok')->assertSee('https://wa.me/6285144924931', false)
            ->assertDontSee('data-location-test-note', false);

        // Test environments keep the store number out (ORD-07); the page explains where the button is.
        $this->app['env'] = 'local';
        config(['store.whatsapp_test_number' => null]);
        $this->get($link)->assertOk()->assertSee('data-location-test-note', false)->assertDontSee('https://wa.me/6285144924931', false);

        // Pickup orders never show it.
        $order->forceFill(['fulfillment' => 'pickup'])->save();
        $this->app['env'] = 'production';
        $this->get($link)->assertOk()->assertDontSee('Kirim Sharelok');
    }
}
