# Phase 0 Local Repository Baseline

## 1. Audit Boundary and Date
- **Date**: 2026-09-06
- **Boundary**: Local repository (`c:\projects\Qammaris App\QammmarisWebsite(tailwind)`)

## 2. Git/Repository Baseline
- **Current Branch**: `main`
- **HEAD Commit**: `a3073ca61df766ece9843088d8eb53aac7bb2fed` (Sat Feb 7 12:41:14 2026 +0800 - update fitur)
- **Remotes**: `origin` `https://github.com/husen211/Qammaris-Parfum-Website.git` (fetch/push)
- **Tracked/Untracked Status**:
  - `M resources/views/products/show.blade.php`
  - `?? AGENTS.md`
  - `?? docs/`
  - `?? skills/`
- **CI/Deployment**: No GitHub Actions, GitLab CI, deploy scripts, Docker files, or Hostinger-specific configurations found.
- **Setup Docs**: `README.md` exists. No detailed deployment runbooks.

## 3. Runtime/Dependency Contract
- **PHP Requirements**: `^8.2` (from `composer.json`).
- **Laravel Framework Version**: Composer requires `^12.0`. Baseline awal memakai 12.39.0; lockfile diperbarui dalam major yang sama ke 12.69.2 pada 2026-09-14 untuk menutup security advisories.
- **Default PATH PHP Runtime**: `php` executable on the default PATH is PHP `7.4.33` and is incompatible with this project.
- **Alternative PHP Runtime**: An alternative PHP runtime exists at `C:\xampp\php\php.exe`, version `8.2.12`.
- **Artisan & Server Execution**:
  - `C:\xampp\php\php.exe artisan --version` menggunakan Laravel Framework `12.69.2` setelah security update Phase 1.
  - `C:\xampp\php\php.exe artisan serve` successfully starts the local development server.
  - Therefore, Qammaris can be run with the explicit XAMPP PHP executable without changing the default PHP 7.4 runtime used by other projects.
- **Node Requirements**: No strict version in `package.json`, but `v22.20.0` is active locally.
- **Frontend Stack**: Tailwind CSS `^4.0.0`, React/Three.js (`@react-three/drei`, `@react-three/fiber`), GSAP, DaisyUI, Vite.

## 4. Application Map
- **Route Groups**: 
  - Public: `/`, `/products`, `/products/{slug}`, `/fragrance-quiz`
  - Cart/Inquiry: `/cart`, `/cart/add`, `/cart/checkout`, etc.
  - Blog: `/blog`, `/blog/{slug}`
  - Store: `/store/location`, `/store/about`
  - Auth: `/login`, `/logout`
  - Admin: `/admin`, `/admin/products`, `/admin/blog-posts`
- **Controllers**: `HomeController`, `ProductController`, `CartController`, `BlogController`, `StoreController`, `FragranceQuizController`, `AuthController`, `AdminProductController`, `AdminBlogPostController`, `AdminDashboardController`.
- **Models**: `Product`, `ProductVariant`, `ProductImage`, `Brand`, `Category`, `BlogPost`, `StoreInfo`, `User`.
- **Services**: `FragranceQuizService.php`.
- **API**: No `routes/api.php` or machine-auth mechanisms currently exist.

## 5. Product/Database Boundary
- **Migrations**: Base tables for users, brands, categories, products, product_images, product_variants, blog_posts, store_info exist. Migrations have been run up to batch 2 locally.
- **Data Location**:
  - **Product**: `base_price`, `slug`, `description`, `fragrance_notes`, `gender`, `is_active`
  - **Variant**: `volume`, `price`, `sku`, `stock`, `is_active`
  - **Image**: `image_path`
- **Relationships**: `ProductVariant` and `ProductImage` belong to `Product`. `Product` belongs to `Brand` and `Category`.
- **Deletion Behavior**: `cascade` on `brand_id`, `category_id`, and `product_id`. Deleting a brand will currently delete all its products and their variants/images (this violates data preservation if done accidentally).
- **Verified Local DB Aggregate Counts** (query ke `DB_HOST=127.0.0.1`, diverifikasi ulang 2026-09-14):
  - `products`: 180
  - `product_variants`: 64
  - `product_images`: 19
  - `brands`: 21
  - `categories`: 5
  - `blog_posts`: 5
- Seluruh migration yang tersedia berstatus `Ran`; tidak ada migration yang dijalankan atau data yang diubah selama verifikasi.

## 6. Media/Storage Baseline
- **Laravel Disks**: `local`, `public`, `s3` are configured in `config/filesystems.php`.
- **Local Public Storage**: diperbaiki pada 2026-09-14. `public/storage` sekarang menunjuk ke `storage/app/public` pada repository aktif dan target dapat diakses. Junction rusak sebelumnya dipindahkan ke snapshot Phase 1 agar recoverable.
- **Media Path Conventions**: Uploads are saved as `products/filename.ext` in `storage/app/public` via `$image->store('products', 'public')`.
- **Verified Local Media Counts**:
  - `storage/app/public` recursively contains 38 files.
  - `public/images` recursively contains 16 files.
  - *(Method: `Get-ChildItem -Path <dir> -Recurse -File | Measure-Object` in PowerShell)*
- **Deployment Risk**: `storage/app/public` is typically excluded from Git and won't be replaced by git deployment, but manual FTP deployments could overwrite it.

## 7. Tests and Automation Baseline
- **Existing Tests**: Only default Laravel `ExampleTest.php` in `tests/Feature` and `tests/Unit`.
- **Coverage**: No business behavior is currently covered by tests.

## 8. Local Environment Safety
- Risiko konfigurasi lokal diselesaikan pada 2026-09-14: `APP_ENV=local`, `APP_DEBUG=true`, dan `APP_URL=http://127.0.0.1:8000`.
- Database tetap berada di `DB_HOST=127.0.0.1`; credential dan nama database tidak dicatat dalam dokumentasi.
- Session menggunakan file, cache menggunakan file, dan queue berjalan sync untuk development lokal.
- `.env.example` yang sebelumnya korup telah diganti dengan template development tanpa credential.

## 9. Confirmed Facts Table

| Fact | Classification |
|---|---|
| Laravel version required is ^12.0; locked/installed framework version is 12.69.2 | Confirmed from repository/runtime on 2026-09-14 |
| Admin panel uses web routes, not API | Confirmed from repository |
| Delete brand cascades to products | Confirmed from repository |
| No automated test coverage exists | Confirmed from repository |
| Node stack includes Three.js & Tailwind 4 | Confirmed from repository |
| Default PATH PHP (7.4.33) is incompatible | Confirmed from local filesystem/runtime |
| XAMPP PHP (8.2.12 at `C:\xampp\php\php.exe`) runs artisan and dev server | Confirmed from local filesystem/runtime |
| Local `public/storage` junction points to the active repository storage | Confirmed from local filesystem/runtime on 2026-09-14 |
| Local database aggregate counts (Products: 180, Brands: 21, etc.) | Confirmed from local database |
| Local media counts (storage: 38 files, public/images: 16 files) | Confirmed from local filesystem |

## 10. Keputusan Owner atas Baseline Production
- **Audit Date**: 2026-09-06
- Audit production melalui SSH atau File Manager tidak dilanjutkan pada tahap ini. Fakta production (seperti versi PHP server Hostinger, status migration production, tabel aktual) masih berstatus **Unknown**.
- Fakta production yang belum diketahui ini diterima sebagai keterbatasan item `P0-01` dan tidak akan ditebak.
- Hostinger deployment saat ini diperlakukan murni sebagai sistem legacy dan bukan *source of truth* untuk pengembangan kode, melainkan GitHub yang menjadi sumber kode yang sah.
- Pengembangan akan fokus di lokal, disusul staging, baru kemudian dilakukan deployment baru ke server, tanpa perlu menggantungkan proses pada pengumpulan bukti server lama saat ini.

## 11. Pre-Existing Working-Tree Changes Preserved
- `resources/views/products/show.blade.php`: User's modification to handle null brands and calculate display price dynamically.
- `AGENTS.md` (untracked)
- `docs/` (untracked)
- `skills/` (untracked)

## 12. Phase 1 Safety Findings

- Snapshot database bisnis dan media dibuat di luar repository pada 2026-09-14 dan mempunyai manifest/checksum.
- Restore database sementara berhasil dengan aggregate count yang sama; database sementara kemudian dihapus.
- Archive media berisi 38 file dan seluruh hash cocok dengan sumber.
- Full database dump tidak dapat digunakan karena MariaDB crash saat membaca tabel runtime `sessions`; log mengindikasikan page corruption. Tidak ada repair atau drop yang dilakukan.
- Baseline test awal menemukan homepage gagal pada test database kosong karena `$storeInfo` tidak tersedia. Provider kemudian dibatasi agar tetap membagikan data default selama unit test, dan feature test dibuat independen dari manifest Vite. Hasil akhir: 2 test dan 3 assertion lulus lokal maupun CI.
- Frontend build berhasil, tetapi chunk `about-lanyard` berukuran sekitar 3,28 MB sebelum gzip dan menjadi risiko performance mobile untuk backlog mendatang.
- `composer audit` awal menemukan 41 advisory pada 13 package. Lockfile diperbarui dalam constraint existing dan audit akhir melaporkan 0 advisory.
- `npm audit` awal menemukan 14 vulnerability. Lockfile diperbarui tanpa `--force` atau major upgrade dan audit akhir melaporkan 0 vulnerability.
- GitHub Actions run `34871619589` pada branch `modernization/phase-1-foundation` berhasil menjalankan PHP 8.2/Laravel test dan Node/Vite build. Workflow tidak melakukan deployment.

## 13. Staging Hostinger Baseline

- Staging terpisah berada di `staging.qammarisparfum.id`; production lama tidak diubah.
- hPanel Git memakai branch `modernization/phase-1-foundation`; revision aplikasi terverifikasi `8dc28df`.
- Runtime staging: PHP `8.2.33`, Laravel `12.69.2`, Composer `2.8.9`, environment `staging`, debug off.
- `.env` dan database MySQL staging dibuat terpisah dari production. Secret tidak dicatat dalam repository atau dokumentasi.
- Seluruh 12 migration berjalan sebagai batch 1. Aggregate `users`, `brands`, `categories`, `products`, `product_images`, `product_variants`, `blog_posts`, dan `store_info` semuanya `0`; tidak ada seeding/import.
- Hostinger tidak menyediakan Node/npm pada shell. Asset Vite dibangun lokal lalu diunggah ke `public/build`; homepage, `/products`, `/login`, `/cart`, dan asset CSS terverifikasi HTTP `200`; `/admin` mengarahkan guest ke `/login` dengan HTTP `302`.
- `public/storage` menunjuk ke `storage/app/public`. Symlink dibuat lewat shell karena fungsi PHP `exec`/`symlink` dinonaktifkan.
- HTTP Basic Auth terverifikasi `401` tanpa credential dan `200` dengan akun review.
- Risiko tersisa: redeploy Git dapat menimpa root `.htaccess`; persistensi `.env`, `public/build`, storage link, dan auth pada redeploy belum dibuktikan. Auto-deploy tetap off sampai pipeline release repeatable tersedia.
