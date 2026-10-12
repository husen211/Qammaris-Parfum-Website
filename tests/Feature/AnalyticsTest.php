<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ANALYTICS-01: Cloudflare Web Analytics + Search Console tag on public pages in production only. */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '0123456789abcdef0123456789abcdef';

    private const VERIFICATION = 'aBcDeF0123456789_-xyz';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['analytics.cloudflare_token' => self::TOKEN, 'analytics.google_site_verification' => self::VERIFICATION]);
    }

    public function test_public_pages_carry_the_beacon_and_verification_tag_in_production(): void
    {
        $this->app['env'] = 'production';
        foreach (['/', '/products', '/blog', '/cart'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('<script defer src="https://static.cloudflareinsights.com/beacon.min.js" data-cf-beacon=\'{"token": "'.self::TOKEN.'"}\'></script>', false)
                ->assertSee('<meta name="google-site-verification" content="'.self::VERIFICATION.'">', false);
        }
    }

    public function test_order_links_admin_and_other_environments_never_load_it(): void
    {
        $this->app['env'] = 'production';
        // Order links carry private tokens in the URL; Pesanan Saya and the Admin are private too.
        foreach (['/pesanan-saya', '/pesanan/'.str_repeat('a', 40), '/admin/login'] as $path) {
            $this->assertStringNotContainsString('cloudflareinsights', $this->get($path)->getContent(), $path);
        }
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]))->get('/admin/orders')->assertDontSee('cloudflareinsights');

        foreach (['local', 'staging', 'uat', 'testing'] as $environment) {
            $this->app['env'] = $environment;
            $this->get('/')->assertDontSee('cloudflareinsights')->assertDontSee('google-site-verification');
        }
    }

    public function test_missing_or_malformed_values_render_nothing(): void
    {
        $this->app['env'] = 'production';
        config(['analytics.cloudflare_token' => null, 'analytics.google_site_verification' => '']);
        $this->get('/')->assertDontSee('cloudflareinsights')->assertDontSee('google-site-verification');

        config(['analytics.cloudflare_token' => 'x"><script>alert(1)</script>', 'analytics.google_site_verification' => '"><img src=x>']);
        $this->get('/')->assertDontSee('cloudflareinsights')->assertDontSee('google-site-verification')->assertDontSee('alert(1)', false);
    }
}
