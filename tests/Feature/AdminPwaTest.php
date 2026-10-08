<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminPwaController;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** ORD-02b: Qammaris Admin PWA installability, scope, privacy and shared-phone login/logout. */
class AdminPwaTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'synthetic-pass-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_manifest_meets_installability_criteria_with_a_separate_admin_identity(): void
    {
        $response = $this->get('/admin/manifest.webmanifest')->assertOk();
        $this->assertSame('application/manifest+json', $response->headers->get('Content-Type'));
        $this->assertNoCookies($response);
        $manifest = $response->json();

        $this->assertSame('Qammaris Admin', $manifest['name']);
        $this->assertLessThanOrEqual(12, mb_strlen($manifest['short_name']));
        $this->assertSame('/admin', $manifest['id']);
        $this->assertSame('/admin', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertFalse($manifest['prefer_related_applications']);
        $this->assertStringStartsWith($manifest['scope'].'/', $manifest['start_url']);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $manifest['theme_color']);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $manifest['background_color']);
        foreach ($manifest['shortcuts'] as $shortcut) {
            $this->assertStringStartsWith('/admin/', $shortcut['url']);
            $this->get($shortcut['url'])->assertRedirect(route('admin.login'));
        }

        $found = [];
        foreach ($manifest['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($path);
            [$width, $height, $type] = getimagesize($path);
            $this->assertSame(IMAGETYPE_PNG, $type, $icon['src']);
            $this->assertSame('image/png', $icon['type']);
            $this->assertSame("{$width}x{$height}", $icon['sizes'], $icon['src']);
            $found[] = $icon['purpose'].':'.$icon['sizes'];
        }
        $this->assertEqualsCanonicalizing(['any:192x192', 'any:512x512', 'maskable:512x512'], $found);
        $this->assertSame([180, 180], array_slice(getimagesize(public_path('images/pwa/admin-apple-touch-icon.png')), 0, 2));

        // A public icon must stay the public one; the admin app has its own files.
        $this->assertFileNotEquals(public_path('images/pwa/admin-icon-192.png'), public_path('images/logofav.jpg'));
    }

    public function test_scope_covers_admin_only_and_never_a_public_route(): void
    {
        $scope = AdminPwaController::SCOPE;
        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            $path = '/'.ltrim($route->uri(), '/');
            $isAdmin = str_starts_with((string) $route->getName(), 'admin.');
            $this->assertSame($isAdmin, str_starts_with($path, $scope), "{$route->getName()} {$path}");
        }
    }

    public function test_service_worker_is_scoped_revalidated_cookie_free_and_caches_only_admin_named_caches(): void
    {
        $response = $this->get('/admin/sw.js')->assertOk();
        $this->assertStringStartsWith('application/javascript', $response->headers->get('Content-Type'));
        $this->assertSame('/admin', $response->headers->get('Service-Worker-Allowed'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertNoCookies($response);

        $body = $response->getContent();
        $this->assertMatchesRegularExpression('/^const ADMIN_SW = \{"version":"[0-9a-f]{12}","enabled":true,"offlineUrl":"\/admin\/offline"\};/', $body);
        $this->assertStringContainsString("const PREFIX = 'qammaris-admin-';", $body);
        $this->assertStringNotContainsString('Clear-Site-Data', $body);
        $this->assertStringNotContainsString('localStorage', $body);
        $this->assertStringNotContainsString('indexedDB', $body);
    }

    public function test_offline_page_is_static_without_session_or_account_data(): void
    {
        $response = $this->get('/admin/offline')->assertOk();
        $this->assertNoCookies($response);
        $html = $response->getContent();
        $this->assertStringContainsString('Tidak ada koneksi internet', $html);
        // The service worker rewrites exactly this marker for failed form submissions.
        $this->assertSame(1, substr_count($html, 'data-request-method="GET"'));
        $this->assertStringNotContainsString('csrf', $html);
        $this->assertStringNotContainsString('_token', $html);
        $this->assertDoesNotMatchRegularExpression('/<(link|script)[^>]+(href|src)=/', $html, 'Offline page must be self-contained');
    }

    public function test_kill_switch_stops_install_and_ships_an_unregistering_worker(): void
    {
        config(['admin.pwa_enabled' => false]);

        $this->get('/admin/manifest.webmanifest')->assertNotFound();
        $this->assertStringContainsString('"enabled":false', $this->get('/admin/sw.js')->assertOk()->getContent());
        $this->get('/admin/login')->assertOk()
            ->assertSee('data-admin-pwa="off"', false)
            ->assertDontSee('manifest.webmanifest', false);
    }

    public function test_guests_stay_inside_the_admin_scope_and_land_on_their_area_after_login(): void
    {
        $this->get('/admin/orders')->assertRedirect('/admin/login');
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/login')->assertRedirect('/admin/login');

        $login = $this->get('/admin/login')->assertOk()
            ->assertSee('data-admin-pwa="on"', false)
            ->assertSee('href="/admin/manifest.webmanifest"', false)
            ->assertSee('action="'.route('admin.login.perform').'"', false)
            ->assertSee('rel="apple-touch-icon"', false)
            ->assertDontSee('data-admin-clear-caches', false);
        $this->assertNoStore($login);

        // Without a stored deep link each role lands on its own area.
        $this->flushSession();
        $staff = $this->user(User::ROLE_STAFF_ORDER, ['username' => 'staf.toko']);
        $this->post('/admin/login', ['login' => 'staf.toko', 'password' => self::PASSWORD])->assertRedirect('/admin/orders');
        $this->assertAuthenticatedAs($staff);
        $this->get('/admin/login')->assertRedirect('/admin/orders');
        $this->post('/admin/logout');

        $this->user(User::ROLE_SUPER_ADMIN, ['username' => 'pemilik']);
        $this->post('/admin/login', ['login' => 'pemilik', 'password' => self::PASSWORD])->assertRedirect('/admin');
        $this->post('/admin/logout');

        // A shared deep link survives login.
        $this->get('/admin/orders/create')->assertRedirect('/admin/login');
        $this->post('/admin/login', ['login' => 'staf.toko', 'password' => self::PASSWORD])->assertRedirect('/admin/orders/create');
    }

    public function test_logout_ends_the_session_and_clears_only_admin_caches(): void
    {
        $staff = $this->user(User::ROLE_STAFF_ORDER);
        $this->actingAs($staff)->get('/admin/orders')->assertOk();

        $logout = $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertNull($logout->headers->get('Clear-Site-Data'), 'Clear-Site-Data is origin-wide and would wipe the public cart');
        $this->assertGuest();

        $this->get('/admin/login')->assertOk()
            ->assertSee('Anda sudah keluar')
            ->assertSee('data-admin-clear-caches="true"', false);
        $this->get('/admin/orders')->assertRedirect('/admin/login');

        // A forced logout (account deactivated) also lands inside the scope and clears admin caches.
        $this->actingAs($staff)->get('/admin/orders')->assertOk();
        $staff->forceFill(['is_active' => false])->save();
        $this->get('/admin/orders')->assertRedirect('/admin/login')->assertSessionHas('admin_clear_caches', true);

        // The legacy /logout route keeps working and uses the same safe landing.
        $this->actingAs($this->user(User::ROLE_SUPER_ADMIN))->post('/logout')->assertRedirect('/admin/login')->assertSessionHas('admin_clear_caches', true);
    }

    public function test_admin_pages_are_never_stored_and_carry_the_pwa_shell(): void
    {
        $staff = $this->user(User::ROLE_STAFF_ORDER, ['name' => 'Andi Sintetis']);
        $response = $this->actingAs($staff)->get('/admin/orders')->assertOk();
        $this->assertNoStore($response);
        $response->assertSee('data-admin-pwa="on"', false)
            ->assertSee('href="/admin/manifest.webmanifest"', false)
            ->assertSee('data-admin-offline', false)
            ->assertSee('Andi Sintetis · Staff Order')
            ->assertSee('action="'.route('admin.logout').'"', false);

        $nav = $this->bottomNav($response->getContent());
        $this->assertSame(['Pesanan', 'Buat', 'Akun'], array_column($nav, 'label'));
        $this->assertSame([route('admin.orders.index'), route('admin.orders.create'), route('admin.account')], array_column($nav, 'href'));
        $this->assertSame([true, false, false], array_column($nav, 'current'));
        // Staff Order has nothing else to open, so the extra menu is hidden.
        $response->assertDontSee('<summary', false);

        $this->assertSame([false, true, false], array_column($this->bottomNav($this->get('/admin/orders/create')->assertOk()->getContent()), 'current'));
        $this->assertSame([false, false, true], array_column($this->bottomNav($this->get('/admin/account')->assertOk()->getContent()), 'current'));

        $owner = $this->actingAs($this->user(User::ROLE_SUPER_ADMIN))->get('/admin/orders')->assertOk();
        $owner->assertSee('<summary', false)->assertSee('data-admin-bottom-nav', false);
        $this->assertNoStore($this->get('/admin')->assertOk());
    }

    public function test_public_pages_do_not_load_the_admin_app(): void
    {
        foreach (['/', '/products', '/cart', '/blog'] as $path) {
            $response = $this->get($path)->assertOk();
            foreach (['data-admin-pwa', 'manifest.webmanifest', '/admin/sw.js', 'admin-apple-touch-icon'] as $needle) {
                $response->assertDontSee($needle, false);
            }
            $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'), $path);
        }

        $includes = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_contains($file->getContents(), 'admin._pwa-head'))
            ->map(fn ($file) => str_replace('\\', '/', $file->getRelativePathname()))->sort()->values()->all();
        $this->assertSame(['auth/login.blade.php', 'layouts/admin.blade.php'], $includes);
    }

    public function test_account_page_shows_who_is_signed_in_and_a_clear_logout(): void
    {
        $staff = $this->user(User::ROLE_STAFF_ORDER, ['name' => 'Rina Sintetis', 'username' => 'rina', 'email' => null]);
        $this->actingAs($staff)->get('/admin/account')->assertOk()
            ->assertSee('Rina Sintetis')
            ->assertSee('Staff Order · rina')
            ->assertSee('12 jam')
            ->assertSee('Keluar dari akun ini')
            ->assertSee(route('admin.account.password'), false);
    }

    public function test_a_repeated_create_submission_makes_one_order(): void
    {
        $staff = $this->user(User::ROLE_STAFF_ORDER);
        $variant = $this->offer();
        $form = $this->actingAs($staff)->get('/admin/orders/create')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/name="submission_token" value="([0-9a-f-]{36})"/', $form, $match));
        $payload = ['items' => [['variant_id' => $variant->id, 'quantity' => 1]], 'submission_token' => $match[1]];

        $first = $this->post('/admin/orders', $payload);
        $order = OnlineOrder::sole();
        $first->assertRedirect(route('admin.orders.show', $order));

        $this->post('/admin/orders', $payload)
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'sudah dibuat sebelumnya'));
        $this->assertSame(1, OnlineOrder::count());
        $this->assertSame(1, $order->items()->count());

        // Tokens are per account, a new form gets a new token, and malformed tokens are rejected.
        $this->actingAs($this->user(User::ROLE_SUPER_ADMIN))->post('/admin/orders', $payload)->assertRedirect();
        $this->post('/admin/orders', ['submission_token' => (string) Str::uuid()] + $payload)->assertRedirect();
        $this->assertSame(3, OnlineOrder::count());
        $this->post('/admin/orders', ['submission_token' => 'not-a-uuid'] + $payload)->assertSessionHasErrors('submission_token');
        $this->assertSame(3, OnlineOrder::count());
    }

    /** @return list<array{label: string, href: string, current: bool}> */
    private function bottomNav(string $html): array
    {
        $this->assertSame(1, preg_match('/<nav aria-label="Navigasi pesanan" data-admin-bottom-nav.*?<\/nav>/s', $html, $nav));
        preg_match_all('/<a href="([^"]+)"\s*(aria-current="page")?.*?<\/svg>\s*(\w+)\s*<\/a>/s', $nav[0], $links, PREG_SET_ORDER);

        return array_map(fn ($link) => ['label' => $link[3], 'href' => $link[1], 'current' => $link[2] !== ''], $links);
    }

    private function assertNoStore(TestResponse $response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }

    private function assertNoCookies(TestResponse $response): void
    {
        $this->assertSame([], $response->headers->getCookies(), 'PWA resources must not start a session');
    }

    private function user(string $role, array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => $role, 'password' => self::PASSWORD])->fresh();
    }

    private function offer(): ProductVariant
    {
        $brand = Brand::create(['name' => 'PWA Brand', 'is_active' => true]);
        $category = Category::create(['name' => 'EDP']);
        $product = Product::create([
            'name' => 'PWA Synthetic', 'brand_id' => $brand->id, 'category_id' => $category->id, 'description' => 'Synthetic',
            'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []], 'gender' => 'Unisex', 'base_price' => 250000,
            'publication_status' => 'published', 'is_active' => true, 'availability_source' => 'manual',
            'availability_status' => 'available', 'availability_checked_at' => now(),
        ]);

        return $product->variants()->create(['volume' => 100, 'price' => 250000, 'stock' => 0, 'is_active' => true]);
    }
}
