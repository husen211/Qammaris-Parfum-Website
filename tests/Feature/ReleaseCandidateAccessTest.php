<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC 2026-10-11: main's Journal, blog and fragrance-preference admin areas under the order line's RBAC.
 * Super Admin and legacy admin keep main's access; Staff Order and customers stay out; guests land on /admin/login.
 */
class ReleaseCandidateAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_access_matrix_for_merged_admin_areas(): void
    {
        $post = BlogPost::create(['title' => 'Artikel RC', 'excerpt' => 'Ringkasan', 'content' => '<p>Isi.</p>', 'category' => 'Tips', 'is_published' => false]);
        $pages = [
            'journal' => ['/admin/blog-posts', '/admin/blog-posts/create', '/admin/blog-taxonomy', route('admin.blog-posts.edit', $post, false),
                route('admin.blog-media.index', $post, false), route('admin.blog-automation.edit', $post, false)],
            'catalog' => ['/admin/fragrance-preference', '/admin/products', '/admin/app-products'],
            'orders' => ['/admin/orders'],
            'owner' => ['/admin/users', '/admin/integrations/orders', '/admin/orders/payment-accounts'],
        ];
        $expected = [
            User::ROLE_SUPER_ADMIN => ['journal' => 200, 'catalog' => 200, 'orders' => 200, 'owner' => 200],
            User::ROLE_LEGACY_ADMIN => ['journal' => 200, 'catalog' => 200, 'orders' => 200, 'owner' => 403],
            User::ROLE_STAFF_ORDER => ['journal' => 403, 'catalog' => 403, 'orders' => 200, 'owner' => 403],
            User::ROLE_CUSTOMER => ['journal' => 403, 'catalog' => 403, 'orders' => 403, 'owner' => 403],
        ];

        foreach ($pages as $paths) {
            foreach ($paths as $path) {
                $this->get($path)->assertRedirect('/admin/login');
            }
        }
        foreach ($expected as $role => $areas) {
            $user = User::factory()->create(['role' => $role])->fresh();
            foreach ($areas as $area => $status) {
                foreach ($pages[$area] as $path) {
                    $this->actingAs($user)->get($path)->assertStatus($status);
                }
            }
        }

        $inactive = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => false])->fresh();
        $this->actingAs($inactive)->get('/admin/blog-posts')->assertRedirect('/admin/login');
    }

    public function test_super_admin_and_legacy_admin_can_write_the_journal_and_staff_cannot(): void
    {
        foreach ([User::ROLE_SUPER_ADMIN, User::ROLE_LEGACY_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role])->fresh();
            $this->actingAs($user)->post(route('admin.blog-posts.store'), ['title' => "Artikel {$role}", 'excerpt' => 'Ringkasan', 'content' => '<p>Isi.</p>', 'category' => 'Tips', 'is_published' => false])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('blog_posts', ['title' => "Artikel {$role}"]);
        }
        $staff = User::factory()->create(['role' => User::ROLE_STAFF_ORDER])->fresh();
        $this->actingAs($staff)->post(route('admin.blog-posts.store'), ['title' => 'Staf', 'content' => '<p>x</p>'])->assertForbidden();
        $this->assertDatabaseMissing('blog_posts', ['title' => 'Staf']);
    }
}
