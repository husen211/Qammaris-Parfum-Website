<?php

namespace App\Actions\Users;

use App\Exceptions\AdminUserRejected;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Account management by a Super Admin. Every change locks the account (and, when Super Admin access could be
 * lost, all Super Admin rows) so the last active Super Admin can never be deactivated or demoted.
 */
class ManageAdminUser
{
    public function __construct(private readonly RecordUserAdminChange $audit) {}

    /** @return array{0: User, 1: string} the account and its one-time temporary password */
    public function create(User $actor, array $data): array
    {
        $this->authorize($actor);
        $this->assertAssignable($data['role']);
        $password = $data['password'] ?? $this->temporaryPassword();

        $user = DB::transaction(function () use ($actor, $data, $password): User {
            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'username' => $data['username'] ?? null,
                'password' => $password,
                'role' => $data['role'],
                'is_active' => true,
                'must_change_password' => true,
                'auth_version' => 1,
                'created_by_id' => $actor->id,
                'updated_by_id' => $actor->id,
            ])->save();
            $this->audit->handle($user, $actor, 'created', [], null, ['password']);

            return $user;
        });

        return [$user, $password];
    }

    public function update(User $actor, User $user, array $data): User
    {
        $this->authorize($actor);

        return $this->mutate($actor, $user, function (User $locked) use ($data): ?string {
            if ($data['role'] !== $locked->role) {
                $this->assertAssignable($data['role']);
            }
            $locked->forceFill([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'username' => $data['username'] ?? null,
                'role' => $data['role'],
            ]);
            if ($locked->isDirty('role')) {
                $locked->auth_version = (int) $locked->auth_version + 1;
            }

            return null;
        }, 'updated');
    }

    public function setActive(User $actor, User $user, bool $active, ?string $note = null): User
    {
        $this->authorize($actor);

        return $this->mutate($actor, $user, function (User $locked) use ($active): ?string {
            if ($locked->is_active === $active) {
                return null;
            }
            $locked->is_active = $active;
            // Revoke every open session of a deactivated account.
            $locked->auth_version = (int) $locked->auth_version + 1;

            return null;
        }, $active ? 'activated' : 'deactivated', $note);
    }

    /** @return string the one-time temporary password */
    public function resetPassword(User $actor, User $user): string
    {
        $this->authorize($actor);
        $password = $this->temporaryPassword();
        $this->mutate($actor, $user, function (User $locked) use ($password): ?string {
            $locked->password = $password;
            $locked->must_change_password = true;
            $locked->auth_version = (int) $locked->auth_version + 1;

            return 'password';
        }, 'password_reset');

        return $password;
    }

    /**
     * Self-service change of one's own password (also clears the temporary-password flag).
     * Other sessions of the account are revoked; the caller keeps the current one with the returned version.
     */
    public function changeOwnPassword(User $user, string $password): int
    {
        return DB::transaction(function () use ($user, $password): int {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $before = $this->audit->snapshot($locked);
            $locked->password = $password;
            $locked->must_change_password = false;
            $locked->auth_version = (int) $locked->auth_version + 1;
            $locked->save();
            $this->audit->handle($locked, $locked, 'password_changed', $before, null, ['password']);
            $user->forceFill($locked->only(['password', 'must_change_password', 'auth_version']));

            return (int) $locked->auth_version;
        });
    }

    /**
     * Explicit bootstrap used only by the console command; the Owner verifies the identity first (D2).
     */
    public function grantSuperAdminByBootstrap(User $user, string $note): User
    {
        return DB::transaction(function () use ($user, $note): User {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->is_active === false) {
                throw new AdminUserRejected('Akun tidak aktif. Aktifkan akun terlebih dahulu.');
            }
            if ($locked->role === User::ROLE_CUSTOMER) {
                throw new AdminUserRejected('Akun customer tidak dijadikan Super Admin. Buat akun admin terpisah dengan --create.');
            }
            $before = $this->audit->snapshot($locked);
            if ($locked->role === User::ROLE_SUPER_ADMIN) {
                return $locked;
            }
            $locked->role = User::ROLE_SUPER_ADMIN;
            $locked->auth_version = (int) $locked->auth_version + 1;
            $locked->save();
            $this->audit->handle($locked, null, 'bootstrap_super_admin', $before, $note);

            return $locked;
        });
    }

    public function createSuperAdminByBootstrap(array $data, string $note): array
    {
        $password = $this->temporaryPassword();
        $user = DB::transaction(function () use ($data, $password, $note): User {
            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'username' => $data['username'] ?? null,
                'password' => $password,
                'role' => User::ROLE_SUPER_ADMIN,
                'is_active' => true,
                'must_change_password' => true,
                'auth_version' => 1,
            ])->save();
            $this->audit->handle($user, null, 'bootstrap_super_admin', [], $note, ['password']);

            return $user;
        });

        return [$user, $password];
    }

    private function mutate(User $actor, User $user, Closure $change, string $action, ?string $note = null): User
    {
        return DB::transaction(function () use ($actor, $user, $change, $action, $note): User {
            // Lock every Super Admin first, in a fixed order, so concurrent demotions cannot both pass the guard.
            $superAdmins = User::query()->where('role', User::ROLE_SUPER_ADMIN)->orderBy('id')->lockForUpdate()->get();
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $before = $this->audit->snapshot($locked);
            $extra = $change($locked);

            $losesSuperAdmin = $before['role'] === User::ROLE_SUPER_ADMIN && $before['is_active'] !== false
                && ($locked->role !== User::ROLE_SUPER_ADMIN || $locked->is_active === false);
            if ($losesSuperAdmin) {
                $remaining = $superAdmins->where('id', '!=', $locked->id)->where('is_active', '!==', false)->count();
                if ($remaining === 0) {
                    throw new AdminUserRejected('Ini Super Admin aktif terakhir. Tambahkan Super Admin lain sebelum menonaktifkan atau mengubah role akun ini.');
                }
            }

            if (! $locked->isDirty() && $extra === null) {
                return $locked;
            }
            $locked->updated_by_id = $actor->id;
            $locked->save();
            $this->audit->handle($locked, $actor, $action, $before, $note, $extra ? [$extra] : []);

            return $locked;
        });
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->exists && Gate::forUser($actor)->allows('users.manage'), 403);
    }

    private function assertAssignable(string $role): void
    {
        if (! in_array($role, User::ASSIGNABLE_ROLES, true)) {
            throw new AdminUserRejected('Role tidak dapat diberikan.');
        }
    }

    private function temporaryPassword(): string
    {
        // Avoid look-alike characters; shown once to the Super Admin.
        return Str::password(12, symbols: false);
    }
}
