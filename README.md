# Qammaris Perfumes Website

Katalog Laravel 12, Blade, Eloquent, MySQL/MariaDB, Tailwind 4/DaisyUI/Vite, dengan React islands terbatas. Qammaris App menjadi sumber UUID, availability dan harga produk terhubung; Shopee/manual upload melengkapi konten/foto. Draft tidak auto-publish. Customer memakai keranjang → data penerima → composer WhatsApp; belum ada payment gateway atau reservasi jumlah stok.

## Mulai dari sini

Baca [AGENTS](AGENTS.md) → [aturan bisnis current](docs/product/BUSINESS_RULES.md) → [arsitektur current](docs/architecture/ARCHITECTURE.md) → [item aktif/board](docs/planning/BACKLOG.md), kemudian ADR/runbook/source/tests yang relevan. [Master plan](docs/architecture/MASTER_PLAN.md) memuat tujuan dan gate penutupan, bukan diary implementasi.

| Dokumen | Satu tanggung jawab |
|---|---|
| AGENTS | Batas kerja, read order, approval, kualitas dan kontrak laporan |
| BUSINESS_RULES | Keputusan Owner yang berlaku; jangan jadikan riwayat aturan current |
| ARCHITECTURE | Flow kode/boundary yang benar-benar ada dan file entry |
| BACKLOG | Satu item aktif, acceptance, status, dependency dan evidence link |
| [ADR index](docs/architecture/decisions/README.md) | Alasan/tradeoff keputusan; ID unik dan supersession/alias |
| docs/runbooks | Langkah operasional; bukan izin menjalankan deployment/data writes |
| docs/verification, docs/audits | Bukti bertanggal, hasil nyata dan batas/unknown |
| [Riwayat context](docs/history/2026-10-07-context/README.md) | Catatan sebelum konsolidasi, tidak menjadi instruksi current |

Setiap fitur memperbarui item aktif serta hanya aturan/boundary/runbook yang berubah, dan menautkan bukti verifikasi. Commit/PR menyimpan rincian perubahan kode; jangan membuat duplikat agent/business-rule file atau menyalin semua diary release ke context current. Persetujuan implementasi bukan persetujuan release.

Journal CMS/public pages and draft API code were released through PR31 on2026-10-08 ([release evidence](docs/verification/blog-06/README.md)). Journal draft API (installed, default OFF): [kontrak](docs/api/JOURNAL_AUTOMATION.md), [panduan agent](docs/runbooks/JOURNAL_AGENT_GUIDE.md), [aktivasi/recovery](docs/runbooks/JOURNAL_AUTOMATION.md). Identitas/token mesin terpisah dari admin dan feed; publikasi tetap melalui Owner. Belum diaktifkan pada produksi.

## Kebutuhan lokal

- PHP 8.2+ yang kompatibel dengan lockfile, Composer 2.
- Node sesuai `.nvmrc` (22.20.0), npm sesuai packageManager (10.9.8).
- MySQL/MariaDB untuk development; SQLite in-memory untuk suite test.
- Ekstensi ZIP, XMLReader dan SimpleXML diperlukan importer XLSX; gunakan kebutuhan composer/CI sebagai acuan lainnya.

Di laptop Windows ini gunakan `C:\xampp\php\php.exe`; jangan mengasumsikan `php` global menunjuk runtime proyek. Install hanya untuk setup lokal yang diminta, bukan bagian dari audit dokumentasi.

## Setup lokal

Gunakan database **lokal kosong**, bukan staging/production. Jangan menimpa `.env` yang sudah ada; salin template hanya pada checkout baru. Isi credential lokal sebelum migration.

```powershell
Copy-Item .env.example .env
C:\xampp\php\php.exe composer.phar install
C:\xampp\php\php.exe artisan key:generate
C:\xampp\php\php.exe artisan migrate
C:\xampp\php\php.exe artisan storage:link
npm ci
npm run dev
```

Terminal lain: `C:\xampp\php\php.exe artisan serve`, lalu `http://127.0.0.1:8000`. Jangan reseed database existing. Detail: [LOCAL_DEVELOPMENT](docs/runbooks/LOCAL_DEVELOPMENT.md), [LOCAL_CATALOG_PREVIEW](docs/runbooks/LOCAL_CATALOG_PREVIEW.md).

## Pemeriksaan proyek

```powershell
C:\xampp\php\php.exe composer.phar validate --strict --no-check-publish
C:\xampp\php\php.exe artisan test
node --test tests/js/*.test.mjs
npm run build
```

`phpunit.xml` menggunakan SQLite in-memory; jangan sambungkan test ke database development/production. Jalankan pemeriksaan sesuai scope; build bukan bukti UI/device atau hasil deployment.

## Kode, data, media, dan release

- GitHub: source/migration/docs/lockfile. Env/secrets/database/uploads tidak masuk Git.
- Media manual dan Shopee memakai Laravel product disk; konfigurasi terakhir terverifikasi adalah public storage persisten di server. R2 bukan kewajiban fase current.
- Workflow GitHub CI → successful main-push → production release sudah diaktifkan/diamati dalam [cutover](docs/verification/p1-04/PRODUCTION_CUTOVER.md) dan [P9](docs/verification/p9-01/README.md). Source/assets dirilis lewat GitHub/SSH; File Manager bukan jalur rutin kode. Private env, DB, dan media tetap di runtime terpisah dari release.
- Merge main, termasuk docs-only, dapat memicu deploy bila gate konfigurasi aktif. Jangan merge/deploy tanpa persetujuan release Owner. Feature branch/PR tidak mengaktifkan production.
- Deployment menolak migration pending; additive migration/recovery membutuhkan scope terpisah. Jangan menjalankan seeder atau mengganti database/upload sebagai update kode.
- [HOSTINGER_GITHUB_DEPLOYMENT](docs/runbooks/HOSTINGER_GITHUB_DEPLOYMENT.md) memisahkan workflow current dari bukti staging/cutover lama. [LOCAL_BACKUP_RESTORE](docs/runbooks/LOCAL_BACKUP_RESTORE.md) hanya prosedur lokal.

AUD-02 adalah perubahan dokumentasi. Program belum ditutup: device/native-upload/new-event/populated-checkout limits tetap pada [BACKLOG](docs/planning/BACKLOG.md) dan [P9-01](docs/verification/p9-01/README.md). Counts/revision dalam evidence bertanggal bukan fakta live yang dijamin selamanya.
