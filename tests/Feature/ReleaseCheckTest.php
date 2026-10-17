<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** ORD-08/09 release check: read-only, one table for the Owner after the deploy. */
class ReleaseCheckTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://api.qammaris.test/api/integrations/website/orders/employees';

    private const OWNER = '6650aaaabbbbccccddddeeee';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'orders_api.enabled' => true, 'orders_api.webhook_enabled' => true, 'orders_api.app_task_links' => false,
            'orders_api.webhook_url' => 'https://api.qammaris.test/api/integrations/website/orders/events',
            'orders_api.webhook_secret' => 'test-webhook-secret-0123456789', 'orders_api.webhook_client_id' => 'qammaris-website',
            'orders_api.app_owner_ids' => [self::OWNER], 'admin.pwa_enabled' => true, 'queue.default' => 'database',
            // Test-only VAPID pair (same as AdminPushTest), not a deployment secret.
            'admin_push.vapid.subject' => 'mailto:admin@qammaris.test',
            'admin_push.vapid.public_key' => (new \ReflectionClass(AdminPushTest::class))->getConstant('TEST_PUBLIC_KEY'),
            'admin_push.vapid.private_key' => (new \ReflectionClass(AdminPushTest::class))->getConstant('TEST_PRIVATE_KEY'),
        ]);
    }

    public function test_everything_ready_passes_and_names_the_app_employees(): void
    {
        Http::fake([self::URL => Http::response(['data' => [
            ['app_user_id' => '6660aaaa0000000000000001', 'display_name' => 'Andi'], ['app_user_id' => '6660aaaa0000000000000002', 'display_name' => 'Ikrar'],
        ], 'approver' => ['app_user_id' => self::OWNER, 'display_name' => 'Owner Qammaris']])]);

        $this->artisan('qammaris:release-check')->assertSuccessful()
            ->expectsOutputToContain('2 aktif: Andi, Ikrar')
            ->expectsOutputToContain('Owner Qammaris ada di QAMMARIS_APP_OWNER_IDS')
            ->expectsOutputToContain('kunci VAPID valid')
            ->expectsOutputToContain('Release check OK.')
            ->doesntExpectOutputToContain((string) config('admin_push.vapid.private_key'));
        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->hasHeader('X-Qammaris-Signature'));
        $this->assertNull(Cache::get('orders.app_employees.last'), 'The check caches nothing');
    }

    public function test_the_usual_release_mistakes_are_reported(): void
    {
        Http::fake([self::URL => Http::response(['data' => [], 'approver' => ['app_user_id' => '6650ffffffffffffffffffff', 'display_name' => 'Owner Lain']])]);
        config(['orders_api.app_task_links' => true, 'admin_push.vapid.public_key' => '', 'admin_push.vapid.private_key' => '']);

        $this->artisan('qammaris:release-check')->assertFailed()
            ->expectsOutputToContain('Owner Lain (6650ffffffffffffffffffff) belum ada di QAMMARIS_APP_OWNER_IDS')
            ->expectsOutputToContain('tidak ada karyawan aktif')
            ->expectsOutputToContain('masih membuka App')
            ->expectsOutputToContain('mati (ADMIN_PUSH_VAPID_* kosong)');

        Http::swap(new Factory);
        Http::fake([self::URL => Http::response(['error' => 'down'], 503)]);
        $this->artisan('qammaris:release-check')->assertFailed()->expectsOutputToContain('App tidak terjangkau atau menolak');
    }
}
