<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminPwaController;
use App\Http\Controllers\PublicPwaController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** PWA-01: installable public website with its own identity, kill switch and no private caching. */
class PublicPwaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['store.pwa_enabled' => true]);
    }

    public function test_manifest_meets_installability_criteria_with_the_public_identity(): void
    {
        $response = $this->get('/manifest.webmanifest')->assertOk();
        $this->assertSame('application/manifest+json', $response->headers->get('Content-Type'));
        $this->assertNoCookies($response);
        $manifest = $response->json();

        $this->assertSame('Qammaris Perfumes', $manifest['name']);
        $this->assertSame('Qammaris', $manifest['short_name']);
        $this->assertSame('/', $manifest['id']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('id', $manifest['lang']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertFalse($manifest['prefer_related_applications']);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $manifest['theme_color']);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $manifest['background_color']);
        $this->assertArrayNotHasKey('gcm_sender_id', $manifest);

        $found = [];
        foreach ($manifest['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($path);
            [$width, $height, $type] = getimagesize($path);
            $this->assertSame(IMAGETYPE_PNG, $type, $icon['src']);
            $this->assertSame("{$width}x{$height}", $icon['sizes'], $icon['src']);
            $this->assertStringStartsWith('/images/pwa/public-', $icon['src']);
            $found[] = $icon['purpose'].':'.$icon['sizes'];
        }
        $this->assertEqualsCanonicalizing(['any:192x192', 'any:512x512', 'maskable:512x512'], $found);
        $this->assertSame([180, 180, IMAGETYPE_PNG], array_slice(getimagesize(public_path('images/pwa/public-apple-touch-icon.png')), 0, 3));

        // Shortcuts open public pages only, never the admin area.
        foreach ($manifest['shortcuts'] as $shortcut) {
            $this->assertFalse(str_starts_with($shortcut['url'], AdminPwaController::SCOPE), $shortcut['url']);
            $this->get($shortcut['url'])->assertOk();
        }
    }

    public function test_public_and_admin_apps_never_share_an_identity(): void
    {
        $public = $this->get('/manifest.webmanifest')->json();
        $admin = $this->get('/admin/manifest.webmanifest')->json();

        foreach (['id', 'name', 'short_name', 'start_url', 'scope', 'theme_color'] as $field) {
            $this->assertNotSame($public[$field], $admin[$field], $field);
        }
        foreach (['192x192:any', '512x512:any', '512x512:maskable'] as $index => $_) {
            $this->assertFileNotEquals(public_path(ltrim($admin['icons'][$index]['src'], '/')), public_path(ltrim($public['icons'][$index]['src'], '/')));
        }
        $this->assertFileNotEquals(public_path('images/pwa/admin-apple-touch-icon.png'), public_path('images/pwa/public-apple-touch-icon.png'));

        // The Admin worker URL and scope stay as they are; the public worker has its own.
        $this->assertSame('/admin', $this->get('/admin/sw.js')->headers->get('Service-Worker-Allowed'));
        $this->assertSame('/', $this->get('/sw.js')->headers->get('Service-Worker-Allowed'));
    }

    public function test_service_worker_is_revalidated_cookie_free_and_uses_only_public_named_caches(): void
    {
        $response = $this->get('/sw.js')->assertOk();
        $this->assertStringStartsWith('application/javascript', $response->headers->get('Content-Type'));
        $this->assertSame(PublicPwaController::SCOPE, $response->headers->get('Service-Worker-Allowed'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertNoCookies($response);

        $body = $response->getContent();
        $this->assertMatchesRegularExpression('/^const PUBLIC_SW = \{"version":"[0-9a-f]{12}","enabled":true,"offlineUrl":"\/offline"\};/', $body);
        $this->assertStringContainsString("const PREFIX = 'qammaris-public-';", $body);
        foreach (['Clear-Site-Data', 'localStorage', 'indexedDB', 'qammaris-admin-', 'pushManager', "addEventListener('push'", 'showNotification', 'sync'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body, $forbidden);
        }
    }

    public function test_offline_page_is_static_without_session_or_customer_data(): void
    {
        $response = $this->get('/offline')->assertOk();
        $this->assertNoCookies($response);
        $html = $response->getContent();
        $this->assertStringContainsString('Tidak ada koneksi internet', $html);
        $this->assertStringContainsString('href="/pesanan-saya"', $html);
        $this->assertStringNotContainsString('csrf', $html);
        $this->assertStringNotContainsString('_token', $html);
        // Only the precached public icon; no stylesheet or script files.
        preg_match_all('/<(?:link|script|img)[^>]+(?:href|src)="([^"]+)"/', $html, $resources);
        $this->assertSame(['/images/pwa/public-icon-192.png'], $resources[1]);
    }

    public function test_pwa_resources_never_start_a_session(): void
    {
        foreach (['pwa.manifest', 'pwa.service-worker', 'pwa.offline'] as $name) {
            $this->assertSame([], Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
    }

    public function test_public_pages_link_the_public_app_and_never_the_admin_app(): void
    {
        foreach (['/', '/products', '/cart', '/blog', '/pesanan-saya'] as $path) {
            $response = $this->get($path)->assertOk()
                ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
                ->assertSee('data-public-pwa="on"', false)
                ->assertSee('data-public-sw="/sw.js"', false)
                ->assertSee('data-public-scope="/"', false)
                ->assertSee('href="/images/pwa/public-apple-touch-icon.png"', false)
                ->assertSee('<meta name="apple-mobile-web-app-title" content="Qammaris">', false);
            foreach (['data-admin-pwa', '/admin/manifest.webmanifest', '/admin/sw.js', 'admin-apple-touch-icon'] as $needle) {
                $response->assertDontSee($needle, false);
            }
        }

        // Admin login and admin pages keep only the Admin PWA.
        $login = $this->get('/admin/login')->assertOk();
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER]);
        $admin = $this->actingAs($staff)->get('/admin/orders')->assertOk();
        foreach ([$login, $admin] as $response) {
            $response->assertSee('href="/admin/manifest.webmanifest"', false)
                ->assertDontSee('href="/manifest.webmanifest"', false)
                ->assertDontSee('data-public-pwa', false)
                ->assertDontSee('public-apple-touch-icon', false);
        }

        $includes = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_contains($file->getContents(), "'pwa._head'"))
            ->map(fn ($file) => str_replace('\\', '/', $file->getRelativePathname()))->values()->all();
        $this->assertSame(['layouts/app.blade.php'], $includes);
    }

    public function test_kill_switch_stops_install_and_ships_an_unregistering_worker(): void
    {
        config(['store.pwa_enabled' => false]);

        $this->get('/manifest.webmanifest')->assertNotFound();
        $this->assertStringContainsString('"enabled":false', $this->get('/sw.js')->assertOk()->getContent());
        $this->get('/')->assertOk()
            ->assertSee('data-public-pwa="off"', false)
            ->assertDontSee('rel="manifest"', false)
            ->assertDontSee('public-apple-touch-icon', false)
            // The pre-PWA icon stays, so a disabled PWA looks exactly like the site before PWA-01.
            ->assertSee('<link rel="apple-touch-icon" href="'.asset('images/logofav.jpg').'">', false);
        // The Admin PWA is unaffected by the public switch.
        $this->get('/admin/manifest.webmanifest')->assertOk();
    }

    public function test_the_switch_is_off_unless_configured(): void
    {
        $this->assertStringContainsString("env('PUBLIC_PWA_ENABLED', false)", File::get(config_path('store.php')));
        // Dark launch: the Admin PWA is also off unless the server enables it.
        $this->assertStringContainsString("env('ADMIN_PWA_ENABLED', false)", File::get(config_path('admin.php')));
    }

    public function test_private_pages_stay_uncacheable_with_the_pwa_on(): void
    {
        foreach (['/pesanan-saya', '/pesanan/'.str_repeat('a', 40)] as $path) {
            $cacheControl = (string) $this->get($path)->headers->get('Cache-Control');
            $this->assertStringContainsString('no-store', $cacheControl, $path);
        }
        $this->get('/cart/checkout')->assertStatus(302);
    }

    private function assertNoCookies(TestResponse $response): void
    {
        $this->assertSame([], $response->headers->getCookies(), 'PWA resources must not start a session');
    }
}
