<?php

namespace Tests\Feature;

use App\Actions\Orders\OnlineOrderFulfillment;
use App\Models\IntegrationOutbox;
use App\Models\User;
use App\Support\OrderActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/** ORD-02e staging preflight: read-only, never prints secrets, fails on anything that would leak into production. */
class OrderApiPreflightTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase;

    private const API_SECRET = 'preflight-api-secret-not-real-000000001';

    private const HOOK_SECRET = 'preflight-hook-secret-not-real-00000002';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        config([
            'orders.v2_enabled' => true, 'orders_api.enabled' => true, 'orders_api.client_id' => 'qammaris-app-staging',
            'orders_api.secret' => self::API_SECRET, 'orders_api.secret_previous' => '', 'orders_api.webhook_enabled' => true,
            'orders_api.webhook_url' => 'https://tunnel.example.test/api/integrations/website/orders/events', 'orders_api.webhook_secret' => self::HOOK_SECRET,
            'orders_api.app_task_links' => false, 'qammaris_app.api_key' => '', 'mail.default' => 'log',
        ]);
    }

    public function test_a_clean_staging_passes_with_fixtures_and_never_prints_secrets(): void
    {
        User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'username' => 'pemilik-uji']);
        $this->assertSame(0, Artisan::call('qammaris:order-api:fixtures', ['--actor' => 'pemilik-uji']));
        IntegrationOutbox::query()->update(['delivered_at' => now()]);

        $status = Artisan::call('qammaris:order-api:preflight', ['--json' => true]);
        $output = Artisan::output();
        $this->assertSame(0, $status, $output);
        $report = json_decode($output, true);
        $this->assertTrue($report['ok']);
        $checks = collect($report['checks'])->keyBy('check');
        foreach (['local', 'intercity', 'customerCourier', 'pickup', 'issue', 'costs'] as $scenario) {
            $this->assertSame('PASS', $checks["fixture:{$scenario}"]['status']);
        }
        $this->assertSame('PASS', $checks['serializable']['status']);
        $this->assertStringContainsString('tunnel.example.test', $checks['webhook_target']['detail']);
        foreach ([self::API_SECRET, self::HOOK_SECRET, (string) config('app.key')] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
    }

    public function test_anything_that_reaches_production_or_real_people_fails(): void
    {
        config([
            'orders_api.webhook_url' => 'https://api.qammarisapp.com/api/integrations/website/orders/events',
            'qammaris_app.api_key' => 'catalog-key', 'qammaris_app.base_url' => 'https://api.qammarisapp.com/api/public/v1',
            'orders_api.app_task_links' => true, 'orders_api.webhook_secret' => self::API_SECRET, 'mail.default' => 'smtp',
        ]);

        $this->assertSame(1, Artisan::call('qammaris:order-api:preflight', ['--json' => true]));
        $checks = collect(json_decode(Artisan::output(), true)['checks'])->keyBy('check');
        foreach (['webhook_target', 'catalog_sync', 'QAMMARIS_ORDER_APP_TASK_LINKS', 'secret_per_direction', 'mail'] as $check) {
            $this->assertSame('FAIL', $checks[$check]['status'], $check);
        }
        $this->assertSame('WARN', $checks['fixture:local']['status']);
    }

    public function test_production_is_refused_and_flags_off_fail(): void
    {
        $this->app['env'] = 'production';
        config(['orders_api.enabled' => false]);

        $this->assertSame(1, Artisan::call('qammaris:order-api:preflight', ['--json' => true]));
        $checks = collect(json_decode(Artisan::output(), true)['checks'])->keyBy('check');
        $this->assertSame(['FAIL', 'FAIL'], [$checks['environment']['status'], $checks['QAMMARIS_ORDER_API_ENABLED']['status']]);
    }

    public function test_unreadable_orders_and_a_stopped_worker_are_reported(): void
    {
        $order = $this->v2Order();
        // An issue opened in the Website while the API was off: r4.1 cannot represent it.
        config(['orders_api.enabled' => false]);
        app(OnlineOrderFulfillment::class)->openIssue($order, null, OrderActor::user($this->orderOwner), 'stock_problem', 'Stok kurang');
        config(['orders_api.enabled' => true]);
        IntegrationOutbox::query()->update(['delivered_at' => null, 'failed_at' => null, 'next_attempt_at' => now()->subMinutes(10)]);

        $this->assertSame(1, Artisan::call('qammaris:order-api:preflight', ['--json' => true]));
        $checks = collect(json_decode(Artisan::output(), true)['checks'])->keyBy('check');
        $this->assertSame('FAIL', $checks['website_issues']['status']);
        $this->assertStringContainsString($order->public_id, $checks['website_issues']['detail']);
        $this->assertSame('FAIL', $checks['serializable']['status']);
        $this->assertSame('FAIL', $checks['webhook_worker']['status']);
    }

    public function test_pages_are_not_indexed_outside_production(): void
    {
        $this->get('/up')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->app['env'] = 'production';
        $this->get('/up')->assertHeaderMissing('X-Robots-Tag');
    }
}
