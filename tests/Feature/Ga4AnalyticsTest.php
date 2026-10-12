<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

class Ga4AnalyticsTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        config(['analytics.ga4_measurement_id' => 'G-TEST123456']);
        $this->app['env'] = 'production';
    }

    public function test_discovery_pages_offer_an_optional_analytics_choice(): void
    {
        foreach (['/', '/products', '/blog', '/store/location', '/store/about'] as $path) {
            $this->get($path)->assertOk()->assertSee('data-ga4-consent', false)
                ->assertSee('data-ga4-decline', false)->assertSee('data-ga4-preferences', false)
                ->assertDontSee('https://www.googletagmanager.com/gtag/js', false);
        }
    }

    public function test_valid_private_order_checkout_quiz_and_admin_never_load_ga4(): void
    {
        $order = $this->v2Order('pickup');
        $this->get(route('orders.customer.show', $order->customer_token_encrypted))->assertOk()
            ->assertDontSee('data-ga4-', false)->assertDontSee('googletagmanager');
        foreach (['/cart', '/cart/checkout', '/fragrance-quiz', '/pesanan-saya', '/admin/login'] as $path) {
            $this->get($path)->assertDontSee('data-ga4-', false)->assertDontSee('googletagmanager');
        }
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]))
            ->get('/admin/orders')->assertOk()->assertDontSee('data-ga4-', false);
    }

    public function test_absent_invalid_ids_and_nonproduction_environments_disable_ga4(): void
    {
        foreach ([null, '', 'G-INVALID', 'G-TEST123456"><script>alert(1)</script>'] as $id) {
            config(['analytics.ga4_measurement_id' => $id]);
            $this->get('/')->assertDontSee('data-ga4-', false)->assertDontSee('alert(1)', false);
        }
        config(['analytics.ga4_measurement_id' => 'G-TEST123456']);
        foreach (['local', 'staging', 'uat', 'testing'] as $environment) {
            $this->app['env'] = $environment;
            $this->get('/')->assertDontSee('data-ga4-', false);
        }
    }
}
