<?php

namespace App\Console\Commands;

use App\Actions\Users\ManageAdminUser;
use App\Exceptions\AdminUserRejected;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Owner decision D2: Super Admin is granted only by this explicit bootstrap after the Owner verifies the
 * account identity. Nothing is promoted by migrations. Without --confirm the command only previews.
 */
class GrantSuperAdmin extends Command
{
    protected $signature = 'qammaris:grant-super-admin
        {login : Email or username of the verified account}
        {--create : Create a new account when none exists}
        {--name= : Display name for --create}
        {--confirm : Apply the change (otherwise preview only)}';

    protected $description = 'Grant Super Admin to one verified account (explicit bootstrap, audited).';

    public function handle(ManageAdminUser $users): int
    {
        $login = trim((string) $this->argument('login'));
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;
        $user = User::query()->where($isEmail ? 'email' : 'username', $isEmail ? $login : Str::lower($login))->first();
        $note = 'artisan qammaris:grant-super-admin';

        if (! $user) {
            if (! $this->option('create')) {
                $this->error('Akun tidak ditemukan. Gunakan --create --name="Nama" untuk membuat akun Super Admin baru.');

                return self::FAILURE;
            }
            $name = trim((string) $this->option('name'));
            if ($name === '' || (! $isEmail && ! preg_match('/^[a-z0-9._-]{3,40}$/', Str::lower($login)))) {
                $this->error('Isi --name dan gunakan email valid atau username 3–40 karakter (a-z, 0-9, titik, minus, garis bawah).');

                return self::FAILURE;
            }
            $this->line("Akan membuat Super Admin baru: {$name} <{$login}>.");
            if (! $this->option('confirm')) {
                $this->warn('Pratinjau saja. Jalankan ulang dengan --confirm untuk menerapkan.');

                return self::SUCCESS;
            }
            [$created, $password] = $users->createSuperAdminByBootstrap(
                ['name' => $name, 'email' => $isEmail ? $login : null, 'username' => $isEmail ? null : Str::lower($login)], $note);
            $this->info("Super Admin dibuat (#{$created->id}). Password sementara (tampil sekali, wajib diganti saat login):");
            $this->line($password);

            return self::SUCCESS;
        }

        $this->line("Akun #{$user->id} {$user->name} <{$user->loginIdentifier()}> — role saat ini: {$user->roleLabel()}, ".($user->is_active === false ? 'nonaktif' : 'aktif').'.');
        if ($user->role === User::ROLE_SUPER_ADMIN) {
            $this->info('Akun ini sudah Super Admin. Tidak ada perubahan.');

            return self::SUCCESS;
        }
        if (! $this->option('confirm')) {
            $this->warn('Pratinjau saja: akun ini akan menjadi Super Admin. Jalankan ulang dengan --confirm untuk menerapkan.');

            return self::SUCCESS;
        }

        try {
            $users->grantSuperAdminByBootstrap($user, $note);
        } catch (AdminUserRejected $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        $this->info('Akun sekarang Super Admin. Session lama akun ini dicabut; silakan masuk kembali.');

        return self::SUCCESS;
    }
}
