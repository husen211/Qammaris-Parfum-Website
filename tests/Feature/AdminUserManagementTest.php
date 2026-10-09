<?php

namespace Tests\Feature;

use App\Actions\Users\ManageAdminUser;
use App\Exceptions\AdminUserRejected;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'synthetic-pass-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_super_admin_creates_staff_with_one_time_password_and_audit(): void
    {
        $super = $this->user(User::ROLE_SUPER_ADMIN);
        $response = $this->actingAs($super)->post(route('admin.users.store'), [
            'name' => 'Andi Uji', 'username' => 'Andi.Uji', 'email' => '', 'role' => User::ROLE_STAFF_ORDER,
        ]);

        $staff = User::where('username', 'andi.uji')->sole();
        $response->assertRedirect(route('admin.users.show', $staff))->assertSessionHas('temporary_password');
        $this->assertSame(User::ROLE_STAFF_ORDER, $staff->role);
        $this->assertNull($staff->email);
        $this->assertTrue($staff->must_change_password);
        $this->assertSame($super->id, $staff->created_by_id);
        $this->assertTrue(Hash::check(session('temporary_password'), $staff->password));

        $audit = DB::table('user_admin_changes')->where('user_id', $staff->id)->sole();
        $this->assertSame('created', $audit->action);
        $this->assertSame($super->id, $audit->actor_id);
        $this->assertContains('password', json_decode($audit->changed_fields, true));
        $this->assertStringNotContainsString($staff->password, json_encode($audit));
        $this->assertStringNotContainsString(session('temporary_password'), json_encode($audit));
    }

    public function test_role_validation_rejects_legacy_customer_and_duplicate_logins(): void
    {
        $super = $this->user(User::ROLE_SUPER_ADMIN, ['email' => 'owner@example.test']);
        foreach ([User::ROLE_LEGACY_ADMIN, User::ROLE_CUSTOMER, 'root'] as $role) {
            $this->actingAs($super)->post(route('admin.users.store'), ['name' => 'X', 'username' => 'x-'.$role, 'role' => $role])->assertSessionHasErrors('role');
        }
        $this->actingAs($super)->post(route('admin.users.store'), ['name' => 'X', 'email' => 'owner@example.test', 'role' => User::ROLE_STAFF_ORDER])->assertSessionHasErrors('email');
        $this->actingAs($super)->post(route('admin.users.store'), ['name' => 'X', 'role' => User::ROLE_STAFF_ORDER])->assertSessionHasErrors(['email', 'username']);
        $this->actingAs($super)->post(route('admin.users.store'), ['name' => 'X', 'username' => 'Bad Name!', 'role' => User::ROLE_STAFF_ORDER])->assertSessionHasErrors('username');
        $this->assertSame(1, User::count());
    }

    public function test_login_with_username_or_email_records_last_login_without_remember_me(): void
    {
        $staff = $this->user(User::ROLE_STAFF_ORDER, ['username' => 'ikrar', 'email' => 'ikrar@example.test']);

        $response = $this->post(route('login.perform'), ['login' => 'IKRAR', 'password' => self::PASSWORD]);
        $response->assertRedirect(route('admin.orders.index'));
        $this->assertAuthenticatedAs($staff);
        $this->assertNotNull($staff->fresh()->last_login_at);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertStringStartsNotWith('remember_web', $cookie->getName());
        }
        $this->post(route('logout'));

        $this->post(route('login.perform'), ['login' => 'ikrar@example.test', 'password' => self::PASSWORD])->assertRedirect(route('admin.orders.index'));
        $this->post(route('logout'));

        $super = $this->user(User::ROLE_SUPER_ADMIN, ['username' => 'owner']);
        $this->post(route('login.perform'), ['login' => 'owner', 'password' => self::PASSWORD])->assertRedirect(route('admin.dashboard'));
    }

    public function test_inactive_accounts_cannot_sign_in_and_get_a_generic_error(): void
    {
        $this->user(User::ROLE_STAFF_ORDER, ['username' => 'nonaktif', 'is_active' => false]);
        $this->from(route('login'))->post(route('login.perform'), ['login' => 'nonaktif', 'password' => self::PASSWORD])
            ->assertRedirect(route('login'))->assertSessionHasErrors(['login' => 'Email/username atau password salah.']);
        $this->assertGuest();
    }

    public function test_deactivation_and_password_reset_revoke_open_sessions(): void
    {
        $super = $this->user(User::ROLE_SUPER_ADMIN);
        $staff = $this->user(User::ROLE_STAFF_ORDER, ['username' => 'andi']);

        $this->post(route('login.perform'), ['login' => 'andi', 'password' => self::PASSWORD]);
        $this->get(route('admin.orders.index'))->assertOk();

        app(ManageAdminUser::class)->setActive($super, $staff, false, 'Resign');
        $this->app['auth']->forgetGuards();
        $this->get(route('admin.orders.index'))->assertRedirect(route('admin.login'))->assertSessionHas('error');
        $this->assertGuest();

        app(ManageAdminUser::class)->setActive($super, $staff->fresh(), true);
        $this->post(route('login.perform'), ['login' => 'andi', 'password' => self::PASSWORD]);
        $this->get(route('admin.orders.index'))->assertOk();
        $temporary = app(ManageAdminUser::class)->resetPassword($super, $staff->fresh());
        $this->app['auth']->forgetGuards();
        $this->get(route('admin.orders.index'))->assertRedirect(route('admin.login'));
        $this->post(route('login.perform'), ['login' => 'andi', 'password' => self::PASSWORD])->assertSessionHasErrors('login');
        $this->post(route('login.perform'), ['login' => 'andi', 'password' => $temporary])->assertRedirect(route('admin.orders.index'));
    }

    public function test_temporary_password_must_be_changed_before_using_admin(): void
    {
        $staff = $this->user(User::ROLE_STAFF_ORDER, ['username' => 'baru', 'must_change_password' => true]);
        $this->post(route('login.perform'), ['login' => 'baru', 'password' => self::PASSWORD]);
        $this->get(route('admin.orders.index'))->assertRedirect(route('admin.account.password'));
        $this->get(route('admin.account.password'))->assertOk()->assertSee('password sementara');

        $this->put(route('admin.account.password.update'), ['current_password' => 'wrong', 'password' => 'new-synthetic-1', 'password_confirmation' => 'new-synthetic-1'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('admin.account.password.update'), ['current_password' => self::PASSWORD, 'password' => 'new-synthetic-1', 'password_confirmation' => 'new-synthetic-1'])
            ->assertRedirect(route('admin.orders.index'));
        $this->get(route('admin.orders.index'))->assertOk();
        $this->assertFalse($staff->fresh()->must_change_password);
        $this->assertSame('password_changed', DB::table('user_admin_changes')->where('user_id', $staff->id)->value('action'));
    }

    public function test_admin_session_expires_after_twelve_idle_hours(): void
    {
        $this->user(User::ROLE_STAFF_ORDER, ['username' => 'idle']);
        $this->post(route('login.perform'), ['login' => 'idle', 'password' => self::PASSWORD]);
        $this->travel(11)->hours();
        $this->get(route('admin.orders.index'))->assertOk();
        $this->travel(12)->hours();
        $this->travel(1)->minutes();
        $this->get(route('admin.orders.index'))->assertRedirect(route('admin.login'))->assertSessionHas('error', 'Sesi berakhir karena tidak aktif. Silakan masuk kembali.');
    }

    public function test_last_active_super_admin_cannot_be_deactivated_or_demoted(): void
    {
        $owner = $this->user(User::ROLE_SUPER_ADMIN);
        $users = app(ManageAdminUser::class);

        foreach ([fn () => $users->setActive($owner, $owner, false), fn () => $users->update($owner, $owner, ['name' => $owner->name, 'email' => $owner->email, 'username' => null, 'role' => User::ROLE_STAFF_ORDER])] as $attempt) {
            try {
                $attempt();
                $this->fail('The last Super Admin must stay.');
            } catch (AdminUserRejected $error) {
                $this->assertStringContainsString('Super Admin aktif terakhir', $error->getMessage());
            }
        }
        $this->actingAs($owner)->patch(route('admin.users.status', $owner), ['active' => 0])->assertSessionHas('error');
        $this->assertTrue($owner->fresh()->is_active);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $owner->fresh()->role);

        // An inactive second Super Admin does not count as a fallback.
        $inactive = $this->user(User::ROLE_SUPER_ADMIN, ['is_active' => false]);
        $this->expectException(AdminUserRejected::class);
        try {
            $users->setActive($owner, $owner, false);
        } finally {
            $second = $users->setActive($owner, $inactive, true);
            $this->assertTrue($second->is_active);
            $users->setActive($owner, $owner->fresh(), false);
            $this->assertFalse($owner->fresh()->is_active);
        }
    }

    public function test_role_change_is_audited_and_ends_the_users_sessions(): void
    {
        $super = $this->user(User::ROLE_SUPER_ADMIN);
        $legacy = $this->user(User::ROLE_LEGACY_ADMIN, ['email' => 'lama@example.test']);
        $version = $legacy->auth_version;

        $this->actingAs($super)->put(route('admin.users.update', $legacy), ['name' => 'Admin Lama', 'email' => 'lama@example.test', 'role' => User::ROLE_STAFF_ORDER])
            ->assertRedirect(route('admin.users.show', $legacy));
        $legacy->refresh();
        $this->assertSame(User::ROLE_STAFF_ORDER, $legacy->role);
        $this->assertSame($version + 1, $legacy->auth_version);
        $audit = DB::table('user_admin_changes')->where('user_id', $legacy->id)->where('action', 'updated')->sole();
        $this->assertSame(['name', 'role'], json_decode($audit->changed_fields, true));
        $this->assertSame(['name' => 'Admin Lama', 'role' => User::ROLE_STAFF_ORDER], json_decode($audit->after, true));

        // The legacy role cannot be granted back.
        $this->actingAs($super)->put(route('admin.users.update', $legacy), ['name' => 'Admin Lama', 'email' => 'lama@example.test', 'role' => User::ROLE_LEGACY_ADMIN])
            ->assertSessionHasErrors('role');
        $this->actingAs($super)->get(route('admin.users.show', $legacy))->assertOk()->assertSee('mengubah akun')->assertSee('Staff Order');
    }

    public function test_bootstrap_command_previews_then_grants_only_verified_admin_accounts(): void
    {
        $legacy = $this->user(User::ROLE_LEGACY_ADMIN, ['email' => 'owner@example.test']);
        $this->artisan('qammaris:grant-super-admin', ['login' => 'owner@example.test'])->assertSuccessful()->expectsOutputToContain('Pratinjau saja');
        $this->assertSame(User::ROLE_LEGACY_ADMIN, $legacy->fresh()->role);

        $this->artisan('qammaris:grant-super-admin', ['login' => 'owner@example.test', '--confirm' => true])->assertSuccessful();
        $this->assertSame(User::ROLE_SUPER_ADMIN, $legacy->fresh()->role);
        $audit = DB::table('user_admin_changes')->where('user_id', $legacy->id)->sole();
        $this->assertSame('bootstrap_super_admin', $audit->action);
        $this->assertNull($audit->actor_id);

        $customer = $this->user(User::ROLE_CUSTOMER, ['email' => 'buyer@example.test']);
        $this->artisan('qammaris:grant-super-admin', ['login' => 'buyer@example.test', '--confirm' => true])->assertFailed();
        $this->assertSame(User::ROLE_CUSTOMER, $customer->fresh()->role);
        $inactive = $this->user(User::ROLE_STAFF_ORDER, ['username' => 'tidakaktif', 'is_active' => false]);
        $this->artisan('qammaris:grant-super-admin', ['login' => 'tidakaktif', '--confirm' => true])->assertFailed();
        $this->assertSame(User::ROLE_STAFF_ORDER, $inactive->fresh()->role);

        $this->artisan('qammaris:grant-super-admin', ['login' => 'pemilik', '--create' => true, '--name' => 'Pemilik'])->assertSuccessful();
        $this->assertNull(User::where('username', 'pemilik')->first());
        $this->artisan('qammaris:grant-super-admin', ['login' => 'pemilik', '--create' => true, '--name' => 'Pemilik', '--confirm' => true])->assertSuccessful();
        $created = User::where('username', 'pemilik')->sole();
        $this->assertSame(User::ROLE_SUPER_ADMIN, $created->role);
        $this->assertTrue($created->must_change_password);
    }

    public function test_existing_admin_accounts_are_not_promoted_by_the_migration(): void
    {
        $legacy = $this->user(User::ROLE_LEGACY_ADMIN);
        $this->assertSame(User::ROLE_LEGACY_ADMIN, $legacy->role);
        $this->assertTrue($legacy->is_active);
        $this->assertSame(0, User::where('role', User::ROLE_SUPER_ADMIN)->count());
        $this->assertSame(0, DB::table('user_admin_changes')->count());
    }

    private function user(string $role, array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => $role, 'password' => self::PASSWORD])->fresh();
    }
}
