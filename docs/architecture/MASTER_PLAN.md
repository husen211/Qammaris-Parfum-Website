# Qammaris Catalog & Admin Modernization — Master Plan

## 1. Tujuan

Meningkatkan aplikasi Laravel Qammaris menjadi katalog parfum yang mudah digunakan di mobile dan admin workspace yang aman, cepat, serta siap menerima bulk import dan API terbatas pada masa depan.

Program ini tidak membangun ulang Majoo, checkout pembayaran, live inventory, atau AI recommendation pada fase sekarang.

## 2. Prinsip

1. Pertahankan Laravel, Blade, Eloquent, MySQL, URL produk, dan identitas visual Qammaris.
2. Lakukan perubahan secara bertahap; satu backlog item aktif pada satu waktu.
3. Tidak ada penghapusan massal data atau media sebagai bagian dari refactor.
4. Production bukan tempat eksperimen. Semua migrasi diuji pada staging dengan salinan data yang telah disanitasi atau backup yang sesuai.
5. Admin manusia, bulk import, dan API masa depan menggunakan operasi domain yang sama.
6. Kode deterministik menangani validasi dan perubahan data; AI tidak menjadi sumber kebenaran.
7. Hindari abstraksi baru sampai ada minimal dua caller nyata atau masalah terukur yang diselesaikan.

## 3. Nasib data yang ada

Seluruh product ID, slug, brand, kategori, gambar, variant ID, harga, dan data lain dianggap aset yang harus dipertahankan sampai terbukti tidak valid.

Strategi migrasi:

1. Ambil backup database, semua upload, dan konfigurasi deployment.
2. Buat inventory data dan file: jumlah record, relasi, path gambar, file hilang, duplikasi, dan orphan files.
3. Buat staging dari backup.
4. Jalankan additive migrations; jangan mengganti database dengan hasil seeding atau SQL lokal.
5. Jalankan reconciliation report sebelum mengubah harga atau struktur produk.
6. Pertahankan ID dan slug. Redirect wajib jika URL sengaja diubah.
7. Verifikasi jumlah record dan sample halaman sebelum dan setelah migrasi.
8. Cutover hanya setelah backup restore dan rollback procedure diuji.

Harga lama tidak otomatis dibetulkan pada migrasi arsitektur. Pembaruan harga menjadi batch terpisah dengan preview dan persetujuan.

## 4. Arsitektur target

```text
Customer browser
    -> Laravel public catalog
    -> catalog queries
    -> MySQL

Human admin
    -> Laravel session auth + admin authorization
    -> validated admin request
    -> shared product operation
    -> MySQL + media storage + audit log

Future machine client
    -> restricted API credential
    -> scoped API request
    -> same shared product operation
    -> MySQL + media storage + audit log
```

Laravel tetap monolith. Tidak diperlukan microservice, repository wrapper generik, event bus, atau SPA rewrite.

## 5. Model katalog

Keputusan bisnis saat ini adalah satu halaman katalog mewakili satu parfum dalam satu ukuran dan satu harga. Ukuran lain biasanya menjadi halaman katalog terpisah.

Untuk meminimalkan risiko, model `ProductVariant` dipertahankan pada fase awal sebagai detail teknis satu-offer-per-product. Admin tidak perlu melihat UI multi-variant secara default. Keputusan menggabungkan variant ke product hanya boleh dilakukan setelah audit data production dan consumer cart selesai.

ID internal database tetap menjadi identitas utama. SKU/kode katalog bersifat opsional. Identitas Shopee, Majoo, atau provider lain kelak disimpan sebagai mapping eksternal tanpa mengganti ID internal.

## 6. Availability

Website bukan live inventory. Pisahkan publication dari availability:

- Publication: `draft`, `published`, `archived`.
- Availability: `unknown`, `available`, `sold_out`.

Simpan `stock_quantity` bila tersedia, `availability_source`, dan `availability_checked_at`, tetapi angka stok tidak harus ditampilkan kepada customer. Data availability yang melewati freshness window berubah menjadi `unknown` pada tampilan publik.

## 7. Storage media

### Target

Gunakan Laravel Filesystem dan siapkan disk S3-compatible. Target yang direkomendasikan adalah Cloudflare R2 untuk media produk karena terpisah dari deployment aplikasi dan dapat digunakan melalui interface S3.

```text
GitHub: source code
MySQL: metadata produk dan path/object key
R2: file gambar
Environment variables: credentials dan endpoint
```

### Transisi

1. Pertahankan local/public storage selama inventory dan backup awal.
2. Hilangkan path hardcoded; semua akses melalui Laravel Storage disk.
3. Buat compatibility reader agar gambar lama tetap tampil.
4. Salin gambar ke R2; jangan memindahkan atau menghapus sumber dahulu.
5. Verifikasi object count, checksum/size, MIME type, dan sample URL.
6. Alihkan read ke R2 setelah verifikasi.
7. Alihkan upload baru ke R2.
8. Simpan sumber lama selama rollback window yang disetujui.
9. Penghapusan sumber lama menjadi pekerjaan terpisah dengan approval eksplisit.

Firebase Storage tidak menjadi pilihan awal karena aplikasi sudah menggunakan Laravel dan Laravel mendukung disk S3-compatible secara langsung.

## 8. Deployment

- Repository private di GitHub menjadi sumber kode.
- Pisahkan environment staging dan production.
- Deployment tidak boleh menimpa `.env`, database, atau media.
- Merge ke branch production hanya setelah checks yang relevan lulus.
- Database migration harus additive, direview, dan memiliki rollback/forward-fix plan.
- Jangan menjalankan seeder sebagai mekanisme update production.
- Simpan deployment record: commit, waktu, operator, hasil build, migration, dan health check.

## 9. Admin Panel V2

Modul minimum:

1. Dashboard tindakan: draft, incomplete products, missing images, price review, dan recent activity.
2. Catalog manager: search nama/ID/SKU, filter brand/status, bulk select, dan quick actions.
3. Product editor sederhana: informasi dasar, ukuran/harga, klasifikasi, konten, media, publication, dan history.
4. Brand/category management.
5. Media management dengan primary image dan ordering.
6. Import/export center dengan preview, conflict resolution, apply, dan batch report.
7. Audit log untuk perubahan sensitif.

## 10. Public catalog UX

- Mobile-first pada katalog, product detail, cart/inquiry, search, dan filter.
- Pertahankan visual premium Qammaris; jangan mengganti dengan UI dashboard/SaaS generik.
- Satu primary action per konteks.
- Produk sold out tetap dapat ditemukan dan diarahkan ke pertanyaan restock.
- Cart diperlakukan sebagai daftar produk yang ingin ditanyakan melalui WhatsApp, bukan bukti reservasi stok.
- Search/filter/sort harus mempertahankan state selama pagination.
- Performance visual harus diukur sebelum menambah animasi atau dependency.

## 11. API readiness

API baru dibuat setelah operasi produk, audit, permission, dan import stabil.

Karakteristik API:

- versi eksplisit;
- token revocable milik automation actor, bukan password admin;
- scopes read/write terpisah;
- field allowlist;
- idempotency untuk bulk/import;
- rate limit;
- preview sebelum bulk write;
- conflict detection;
- semua write tercatat di audit log;
- delete dan publish massal tidak diberikan pada scope awal.

## 12. Sepuluh fase program

### Phase 0 — Discovery dan keputusan

Konfirmasi repository/deployed commit, hosting, schema production, data counts, contoh export, product rules, dan asset inventory.

### Phase 1 — Safety, backup, staging, dan Git

Membuat backup terverifikasi, staging, repository workflow, environment contract, serta deployment/rollback runbook.

### Phase 2 — Test baseline dan catalog safety

Memperbaiki validation, variant ownership, unsafe rendering, publication rules, login throttling, cart integrity, dan file failure handling.

### Phase 3 — Product domain dan migration foundation

Menetapkan one-product-one-size UX, price authority, optional SKU, availability semantics, external mapping boundary, serta additive migrations.

### Phase 4 — Media storage

Menormalkan media path, memperbaiki primary image behavior, memasang Laravel disk abstraction, dan memigrasikan media ke R2 secara copy-verify-switch.

### Phase 5 — Admin Panel V2

Membangun catalog manager, product editor, draft/publish, brand/category CRUD, media UX, dan mobile admin navigation.

### Phase 6 — Bulk import/export dan audit

Menambahkan file contract, mapping, preview, conflict resolution, idempotent draft apply, akuisisi gambar aman melalui antrean, batch history, audit log, snapshot katalog read-only, serta preview maintenance berbasis internal ID dan row fingerprint.

### Phase 7 — Public catalog UX

Meningkatkan katalog, search/filter, product detail, cart/inquiry flow, availability display, accessibility, dan mobile performance.

Seluruh pekerjaan UI pada fase ini menggunakan `qammaris-ui-review` sebagai quality gate. Skill tersebut berlaku sebagai panduan audit dan verifikasi, sementara business rules, active backlog item, dan keputusan owner tetap mempunyai prioritas lebih tinggi.

### Phase 8 — Restricted API readiness

Mengekspos operasi yang benar-benar dibutuhkan dengan scoped credentials dan auditability. Belum menghubungkan AI.

### Phase 9 — Hardening, migration, dan production cutover

Regression, browser/device verification, performance, security checks, data/media reconciliation, backup restore rehearsal, cutover, dan post-launch observation.

## 13. Completion criteria program

- Seluruh item launch-blocking selesai.
- Restore database dan media telah diuji.
- Production dapat dideploy dari Git dengan deployment record.
- Admin dapat mengelola produk, brand, kategori, status, harga, ukuran, dan media tanpa phpMyAdmin.
- Bulk import dapat di-preview, diulang tanpa duplikasi, dan diaudit.
- Public catalog berfungsi pada mobile dan desktop dengan state/filter yang benar.
- Data existing tetap terlacak; tidak ada kehilangan ID, slug, atau gambar yang tidak disetujui.
- API boundary siap tetapi privilege tetap minimal.
