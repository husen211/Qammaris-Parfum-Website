<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOnlineOrder;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminRbacTest extends TestCase
{
    use RefreshDatabase;

    /** Admin entry points that must stay reachable before login (ORD-02b), with their exact middleware. */
    private const PUBLIC_ADMIN_ROUTES = [
        'admin.login' => ['web', 'guest'],
        'admin.login.perform' => ['web', 'guest'],
        'admin.logout' => ['web', 'auth'],
        'admin.pwa.manifest' => [],
        'admin.pwa.service-worker' => [],
        'admin.pwa.offline' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_every_admin_route_requires_admin_middleware_and_its_area_ability(): void
    {
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'admin.')) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            if (array_key_exists($name, self::PUBLIC_ADMIN_ROUTES)) {
                $this->assertNotContains('admin', $middleware, $name);
                $plain = array_values(array_filter($middleware, fn ($item) => is_string($item) && ! str_starts_with($item, 'cache.headers')));
                $this->assertEqualsCanonicalizing(self::PUBLIC_ADMIN_ROUTES[$name], $plain, $name);
                $public[] = $name;

                continue;
            }
            $this->assertContains('auth', $middleware, $name);
            $this->assertContains('admin', $middleware, $name);
            $expected = $this->expectedAbility($name);
            if ($expected !== null) {
                $this->assertContains('can:'.$expected, $middleware, "$name must require $expected");
            }
            $abilities = array_values(array_filter($middleware, fn ($item) => is_string($item) && str_starts_with($item, 'can:')));
            if (in_array($name, ['admin.orders.revert', 'admin.orders.reimburse'], true)) {
                $this->assertContains('can:orders.finance', $abilities, $name);
            }
            $checked++;
        }
        $this->assertGreaterThan(60, $checked);
        $this->assertEqualsCanonicalizing(array_keys(self::PUBLIC_ADMIN_ROUTES), $public ?? []);
    }

    public function test_staff_order_is_forbidden_from_every_non_order_admin_page(): void
    {
        $staff = $this->user(User::ROLE_STAFF_ORDER);
        [$product, $brand, $category, $post] = $this->catalog();
        $other = $this->user(User::ROLE_SUPER_ADMIN);
        $params = ['product' => $product, 'brand' => $brand, 'category' => $category, 'blog_post' => $post, 'user' => $other];
        $visited = 0;

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true) || $this->expectedAbility($name) === 'orders.manage'
                || array_key_exists($name, self::PUBLIC_ADMIN_ROUTES)) {
                continue;
            }
            $parameters = $route->parameterNames();
            if (array_diff($parameters, array_keys($params)) !== []) {
                continue; // import batch pages: covered by the route middleware assertion above
            }
            $url = route($name, array_intersect_key($params, array_flip($parameters)));
            $response = $this->actingAs($staff)->get($url);
            if ($name === 'admin.dashboard') {
                $response->assertRedirect(route('admin.orders.index'));
            } elseif ($name === 'admin.account' || str_starts_with($name, 'admin.account.')) {
                $response->assertOk();
            } else {
                $response->assertForbidden();
            }
            $visited++;
        }
        $this->assertGreaterThan(15, $visited);
    }

    public function test_staff_order_cannot_mutate_catalog_blog_or_accounts(): void
    {
        $staff = $this->user(User::ROLE_STAFF_ORDER);
        $super = $this->user(User::ROLE_SUPER_ADMIN);
        [$product, $brand, , $post] = $this->catalog();

        $this->actingAs($staff)->post(route('admin.products.store'), ['name' => 'Hack', 'publication_action' => 'draft'])->assertForbidden();
        $this->actingAs($staff)->put(route('admin.products.update', $product->id), ['name' => 'Hack'])->assertForbidden();
        $this->actingAs($staff)->delete(route('admin.products.destroy', $product->id))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.brands.store'), ['name' => 'Hack'])->assertForbidden();
        $this->actingAs($staff)->patch(route('admin.brands.status', $brand), ['is_active' => 0])->assertForbidden();
        $this->actingAs($staff)->post(route('admin.blog-posts.store'), ['title' => 'Hack'])->assertForbidden();
        $this->actingAs($staff)->delete(route('admin.blog-posts.destroy', $post))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.users.store'), ['name' => 'Hack', 'username' => 'hack', 'role' => User::ROLE_SUPER_ADMIN])->assertForbidden();
        $this->actingAs($staff)->patch(route('admin.users.status', $super), ['active' => 0])->assertForbidden();
        $this->actingAs($staff)->post(route('admin.users.password-reset', $super))->assertForbidden();

        $this->assertSame('Catalog product', $product->fresh()->name);
        $this->assertTrue($brand->fresh()->is_active);
        $this->assertNotNull($post->fresh());
        $this->assertSame(2, User::count());
        $this->assertTrue($super->fresh()->is_active);
    }

    public function test_customers_and_inactive_accounts_cannot_use_admin(): void
    {
        $customer = $this->user(User::ROLE_CUSTOMER);
        $this->actingAs($customer)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();

        $inactive = $this->user(User::ROLE_SUPER_ADMIN, ['is_active' => false]);
        $this->actingAs($inactive)->get(route('admin.users.index'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_legacy_admin_keeps_todays_access_without_user_management(): void
    {
        $legacy = $this->user(User::ROLE_LEGACY_ADMIN);
        $this->actingAs($legacy)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($legacy)->get(route('admin.products.index'))->assertOk();
        $this->actingAs($legacy)->get(route('admin.blog-posts.index'))->assertOk();
        $this->actingAs($legacy)->get(route('admin.orders.index'))->assertOk();
        $this->actingAs($legacy)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($legacy)->get(route('admin.dashboard'))->assertDontSee('Pengguna &amp; Role', false);
    }

    public function test_super_admin_reaches_every_area_and_menus_follow_abilities(): void
    {
        $super = $this->user(User::ROLE_SUPER_ADMIN);
        foreach (['admin.dashboard', 'admin.products.index', 'admin.blog-posts.index', 'admin.orders.index', 'admin.users.index', 'admin.shopee-imports.index'] as $name) {
            $this->actingAs($super)->get(route($name))->assertOk();
        }
        $this->actingAs($super)->get(route('admin.orders.index'))->assertSee('Pengguna &amp; Role', false);

        $staff = $this->user(User::ROLE_STAFF_ORDER);
        $page = $this->actingAs($staff)->get(route('admin.orders.index'))->assertOk();
        foreach (['Products', 'Blog Posts', 'Import Produk', 'Pengguna &amp; Role', 'Dashboard'] as $hidden) {
            $page->assertDontSee('>'.$hidden, false);
        }
        $page->assertSee('Pesanan Online')->assertSee('Staff Order');
    }

    public function test_order_actions_follow_owner_rules_for_staff_order(): void
    {
        $staff = $this->user(User::ROLE_STAFF_ORDER);
        $super = $this->user(User::ROLE_SUPER_ADMIN);
        $variant = $this->offer();

        $this->actingAs($staff)->post(route('admin.orders.store'), ['items' => [['variant_id' => $variant->id, 'quantity' => 1]], 'fill_customer' => 1] + $this->details())
            ->assertRedirect();
        $order = OnlineOrder::sole();
        $this->assertSame(OnlineOrder::STAGE_DETAILS_RECEIVED, $order->stage);

        // D5: address correction allowed, money changes are not.
        $this->actingAs($staff)->patch(route('admin.orders.update', $order), $this->adminDetails($order->fresh()->revision, ['address' => 'Patokan baru dekat pasar']))
            ->assertSessionHas('success');
        $this->actingAs($staff)->patch(route('admin.orders.update', $order), $this->adminDetails($order->fresh()->revision, ['shipping_fee' => '15000', 'shipping_payer' => 'added_to_transfer']))
            ->assertSessionHas('error', 'Perubahan ongkir atau pendanaan driver hanya dapat dilakukan Super Admin.');
        $this->assertNull($order->fresh()->shipping_fee);

        // Staff Order marks payment received; corrections and reimbursements stay with Super Admin.
        $this->actingAs($staff)->patch(route('admin.orders.advance', $order), ['from' => 'details_received', 'to' => 'paid', 'payment_method' => 'transfer'])
            ->assertSessionHas('success');
        $this->actingAs($staff)->patch(route('admin.orders.revert', $order), ['from' => 'paid'])->assertForbidden();
        $this->actingAs($staff)->patch(route('admin.orders.reimburse', $order))->assertForbidden();

        // D4: paid orders are cancelled by Super Admin only.
        $this->actingAs($staff)->patch(route('admin.orders.cancel', $order), ['cancel_reason' => 'Customer batal'])
            ->assertSessionHas('error', 'Pesanan yang sudah dibayar atau diserahkan hanya dapat dibatalkan oleh Super Admin.');
        $this->assertSame('paid', $order->fresh()->stage);
        $this->actingAs($staff)->get(route('admin.orders.show', $order))->assertDontSee('Batalkan pesanan…')->assertDontSee('Koreksi: batalkan');

        $this->actingAs($super)->patch(route('admin.orders.revert', $order), ['from' => 'paid'])->assertSessionHas('success');
        $this->actingAs($staff)->patch(route('admin.orders.cancel', $order), ['cancel_reason' => 'Customer batal'])->assertSessionHas('success');
        $this->assertSame('cancelled', $order->fresh()->stage);

        // Address is locked for Staff Order once the order is closed.
        $this->actingAs($staff)->patch(route('admin.orders.update', $order), $this->adminDetails($order->fresh()->revision, ['address' => 'Alamat lain lagi']))
            ->assertSessionHas('error');
    }

    public function test_order_operations_reject_accounts_without_order_ability(): void
    {
        $this->expectException(HttpException::class);
        app(CreateOnlineOrder::class)->handle($this->user(User::ROLE_CUSTOMER), [$this->offer()->id => 1]);
    }

    private function expectedAbility(string $name): ?string
    {
        return match (true) {
            $name === 'admin.dashboard', $name === 'admin.account', str_starts_with($name, 'admin.account.') => null,
            str_starts_with($name, 'admin.orders.') => 'orders.manage',
            str_starts_with($name, 'admin.users.') => 'users.manage',
            str_starts_with($name, 'admin.blog-posts.') => 'blog.manage',
            default => 'catalog.manage',
        };
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['role' => $role]);

        return $user->fresh();
    }

    private function catalog(): array
    {
        $brand = Brand::create(['name' => 'RBAC brand', 'is_active' => true]);
        $category = Category::create(['name' => 'EDP']);
        $product = Product::create([
            'name' => 'Catalog product', 'brand_id' => $brand->id, 'category_id' => $category->id, 'description' => 'Synthetic',
            'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []], 'gender' => 'Unisex', 'base_price' => 100000,
            'publication_status' => 'draft', 'is_active' => false, 'availability_source' => 'manual', 'availability_status' => 'available',
        ]);
        $post = BlogPost::create(['title' => 'RBAC post', 'slug' => 'rbac-post', 'excerpt' => 'Synthetic', 'content' => '<p>Synthetic</p>', 'category' => 'Tips', 'author' => 'Test', 'is_published' => false]);

        return [$product, $brand, $category, $post];
    }

    private function offer()
    {
        $brand = Brand::firstOrCreate(['name' => 'Order brand'], ['is_active' => true]);
        $category = Category::firstOrCreate(['name' => 'EDP']);
        $product = Product::create([
            'name' => 'Order scent', 'brand_id' => $brand->id, 'category_id' => $category->id, 'description' => 'Synthetic',
            'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []], 'gender' => 'Unisex', 'base_price' => 200000,
            'publication_status' => 'published', 'is_active' => true, 'availability_source' => 'manual', 'availability_status' => 'available',
            'availability_checked_at' => now(),
        ]);

        return $product->variants()->create(['volume' => 100, 'price' => 200000, 'stock' => 0, 'is_active' => true]);
    }

    private function details(array $overrides = []): array
    {
        return array_merge(['customer_name' => 'Synthetic Customer', 'customer_phone' => '081234567890', 'fulfillment' => 'local_delivery',
            'address' => 'Patokan sintetis', 'packaging' => 'no_paperbag', 'customer_note' => ''], $overrides);
    }

    private function adminDetails(int $revision, array $overrides = []): array
    {
        return array_merge($this->details(), ['revision' => $revision, 'courier_booked_by' => 'admin'], $overrides);
    }
}
