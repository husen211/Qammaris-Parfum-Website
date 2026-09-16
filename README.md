# Qammaris Perfumes Website

Katalog parfum Qammaris berbasis Laravel 12, Blade, MySQL/MariaDB, Tailwind CSS 4, Vite, dan komponen React terbatas.

Modernisasi dilakukan bertahap berdasarkan:

- `AGENTS.md`
- `docs/architecture/MASTER_PLAN.md`
- `docs/product/BUSINESS_RULES.md`
- `docs/planning/BACKLOG.md`

## Kebutuhan lokal

- PHP 8.2 atau lebih baru yang kompatibel dengan Laravel 12
- Composer 2
- Node.js sesuai `.nvmrc` (22.20.0)
- npm 10
- MySQL atau MariaDB

Pada laptop Windows saat ini, PHP Qammaris tersedia di `C:\xampp\php\php.exe`. Perintah `php` global masih dapat menunjuk PHP 7.4 untuk project kantor, sehingga command Qammaris harus memakai executable XAMPP secara eksplisit.

## Setup lokal

1. Salin `.env.example` menjadi `.env`.
2. Isi koneksi database lokal. Jangan gunakan credential production.
3. Generate application key.
4. Jalankan migration pada database lokal kosong.
5. Buat storage link.
6. Install dependency frontend menggunakan lockfile.
7. Jalankan development server.

Contoh PowerShell untuk laptop ini:

```powershell
Copy-Item .env.example .env
C:\xampp\php\php.exe composer.phar install
C:\xampp\php\php.exe artisan key:generate
C:\xampp\php\php.exe artisan migrate
C:\xampp\php\php.exe artisan storage:link
npm ci
npm run dev
```

Jalankan Laravel:

```powershell
C:\xampp\php\php.exe artisan serve
```

Website lokal tersedia di `http://127.0.0.1:8000`.

## Verifikasi

```powershell
C:\xampp\php\php.exe composer.phar validate --strict --no-check-publish
C:\xampp\php\php.exe artisan test
npm run build
```

Test menggunakan SQLite in-memory sesuai `phpunit.xml` dan tidak boleh terhubung ke database development maupun production.

## Aturan data dan media

- GitHub menyimpan source code, migration, dokumentasi, dan lockfile.
- `.env`, credential, database dump, serta upload produk tidak boleh masuk Git.
- Database menyimpan data/metadata katalog.
- Upload production nantinya menggunakan Laravel Filesystem dan storage terpisah.
- Jangan menjalankan seeder sebagai cara memperbarui production.
- Jangan menghapus database atau media lama sebelum backup serta cutover baru diverifikasi.

## Deployment

Belum ada deployment otomatis yang diaktifkan. Rencana deployment Hostinger melalui GitHub dijelaskan di `docs/runbooks/HOSTINGER_GITHUB_DEPLOYMENT.md`. Menghubungkan repository, mengaktifkan auto-deploy, migration production, dan cutover membutuhkan persetujuan owner serta backup yang terverifikasi.

## Runbook

- `docs/runbooks/LOCAL_DEVELOPMENT.md`
- `docs/runbooks/LOCAL_BACKUP_RESTORE.md`
- `docs/runbooks/HOSTINGER_GITHUB_DEPLOYMENT.md`
