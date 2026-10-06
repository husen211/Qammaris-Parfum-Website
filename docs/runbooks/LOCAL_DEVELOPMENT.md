# Runbook Development Lokal

## Tujuan

Menjalankan Qammaris secara lokal tanpa mengubah PHP default yang masih dibutuhkan project kantor dan tanpa risiko terhubung ke production.

## Kontrak runtime

- PHP: `C:\xampp\php\php.exe` (8.2.12 pada baseline 2026-09-14)
- Laravel: 12.69.2 dari `composer.lock` pada baseline Phase 1
- Node: versi pada `.nvmrc`
- Package manager: npm sesuai `package.json`
- Database: MySQL/MariaDB pada `127.0.0.1`

Jangan menggunakan command `php` atau Composer global untuk Qammaris sebelum memastikan versinya. Pada laptop ini command global tersebut dapat memakai PHP 7.4.

## Menyalakan service lokal

MySQL XAMPP dapat dinyalakan melalui XAMPP Control Panel atau:

```powershell
Start-Process -FilePath 'C:\xampp\mysql_start.bat' -WorkingDirectory 'C:\xampp' -WindowStyle Hidden
```

Pastikan port 3306 aktif sebelum menjalankan command yang membutuhkan MySQL.

## Menjalankan aplikasi

```powershell
C:\xampp\php\php.exe artisan config:clear
C:\xampp\php\php.exe artisan storage:link
C:\xampp\php\php.exe artisan serve
```

Frontend development:

```powershell
npm ci
npm run dev
```

## Environment lokal

Nilai minimum:

- `APP_ENV=local`
- `APP_DEBUG=true`
- `APP_URL=http://127.0.0.1:8000`
- `DB_HOST=127.0.0.1`
- `SESSION_DRIVER=file`
- `CACHE_STORE=file`
- `QUEUE_CONNECTION=sync`
- `FILESYSTEM_DISK=public`

Jangan menyalin `.env` production ke laptop. Jangan commit `.env`.

## Test

`phpunit.xml` menggunakan SQLite in-memory, session array, cache array, queue sync, dan mail array. Test tidak boleh membaca MySQL development.

```powershell
C:\xampp\php\php.exe artisan test
```

## Build

```powershell
npm run build
```

Build yang berhasil bukan pengganti test perilaku atau pemeriksaan browser.

## Storage

`public/storage` harus menunjuk ke `storage/app/public` pada repository aktif. Verifikasi:

```powershell
Get-Item public\storage | Select-Object LinkType,Target
Test-Path public\storage\products
```

Jangan commit upload produk atau `public/storage`.

## Kondisi baseline yang diketahui

- Tabel runtime MySQL `sessions` terindikasi korup pada 2026-09-14.
- Environment lokal menggunakan `SESSION_DRIVER=file`, sehingga tabel tersebut tidak dibutuhkan untuk development saat ini.
- Jangan drop, repair, atau membuat ulang tabel tanpa pekerjaan terpisah dan backup.
