# Qammaris Modernization Backlog

## Status workflow

- `BACKLOG`: belum siap dikerjakan.
- `READY`: scope dan acceptance criteria sudah lengkap.
- `IN_PROGRESS`: sedang dikerjakan; WIP limit satu item utama.
- `IN_REVIEW`: implementasi selesai dan menunggu review/verifikasi.
- `BLOCKED`: blocker dan kebutuhan penyelesaian harus ditulis.
- `DONE`: acceptance criteria dan verifikasi selesai.

## Program board

| ID | Phase | Status | Dependency | Outcome |
|---|---|---|---|---|
| P0 | Discovery & decisions | DONE | — | Baseline lokal, keterbatasan production, dan keputusan domain terdokumentasi |
| P1 | Safety, backup, staging & Git | IN_PROGRESS | P0 | Deployment repeatable dan recovery teruji |
| P2 | Tests & catalog safety | DONE | P1-01–P1-03 | Perilaku existing aman dan terlindungi regression tests |
| P3 | Product domain & migrations | DONE | P2 | Struktur data sesuai business rules tanpa kehilangan identitas |
| P4 | Media storage | DONE | P1, P2 | Media menggunakan storage abstraction dan migrasi terverifikasi |
| P5 | Admin Panel V2 | DONE | P3, P4 | Pengelolaan katalog lengkap tanpa phpMyAdmin |
| P6 | Import/export & audit | IN_PROGRESS | P3, P5 | Bulk workflow aman, idempotent, dan dapat dilacak |
| P7 | Public catalog UX | BACKLOG | P3, sebagian P5 | Mobile catalog dan inquiry flow matang |
| P8 | Restricted API readiness | BACKLOG | P5, P6 | Operasi machine-access terbatas dan auditable |
| P9 | Hardening & cutover | BACKLOG | P1–P8 | Production launch dan observation selesai |

## P0 — Discovery & decisions

### P0-01 Production baseline — DONE

Kumpulkan tanpa mengubah production:

- URL/repository/deployed commit.
- Jenis paket Hostinger dan layout document root.
- PHP/MySQL/Node versions.
- Migration status dan schema dump.
- Jumlah product/variant/image/brand/category.
- Format path gambar dan jumlah file.
- Backup/restore capability.
- Sample export Majoo/Shopee jika tersedia.

*Catatan: Baseline lokal telah selesai dan terverifikasi aman. Audit production ditunda berdasarkan keputusan eksplisit owner (lihat ADR-001). Seluruh unknown pada Hostinger telah didokumentasikan di `CURRENT_STATE.md` sebagai keterbatasan P0-01.*

Definition of Done:

- Semua fakta disimpan di `docs/architecture/CURRENT_STATE.md`.
- Unknown tetap ditandai unknown; tidak ditebak.
- Tidak ada credential atau data customer masuk dokumentasi/Git.

### P0-02 Product decisions — DONE

Konfirmasi:

- Required/optional fields produk.
- Apakah harga selalu ditampilkan.
- Freshness window availability.
- Perlakuan produk ukuran berbeda.
- Publication completeness rules.
- Kategori/filter customer yang benar-benar berguna.

*Catatan: keputusan telah disetujui owner pada 2026-09-14 dan dicatat pada `BUSINESS_RULES.md` serta `ADR-002-kontrak-produk-katalog.md`.*

Definition of Done:

- `BUSINESS_RULES.md` disetujui owner.
- Perubahan keputusan dicatat sebagai ADR bila memengaruhi schema/architecture.

## P1 — Safety, backup, staging & Git

### P1-01 Local safety dan verified snapshot — DONE

Outcome:

- Branch kerja `modernization/phase-1-foundation` terpisah dari `main`.
- Database bisnis, media, perubahan tracked user, dan governance mempunyai snapshot di luar repository.
- Dump database berhasil direstore ke database sementara dan record count cocok.
- Seluruh 38 file media cocok berdasarkan SHA-256.
- `.env` lokal menggunakan mode local dan URL localhost.
- `public/storage` menunjuk target repository aktif.

Known limitation:

- Tabel runtime lokal `sessions` terindikasi korup dan membuat full dump MariaDB gagal. Tabel runtime dikecualikan dari backup bisnis dan aplikasi lokal memakai file session. Tidak ada repair/drop yang dilakukan.

### P1-02 Reproducible setup dan CI — DONE

Outcome target:

- `.env.example`, runtime version, lockfile, README, dan runbook dapat digunakan dari fresh clone.
- GitHub CI menjalankan Composer validation, Laravel test, dan frontend build tanpa deployment.
- Seluruh check wajib hijau sebelum item selesai.

Current verification:

- Composer metadata valid.
- Frontend build berhasil dengan peringatan bundle besar.
- Laravel test lulus: 2 test, 3 assertion, termasuk homepage tanpa seeded store information.
- `composer audit` dan `npm audit` melaporkan 0 advisory/vulnerability setelah lockfile diperbarui dalam constraint yang disetujui.
- Branch `modernization/phase-1-foundation` telah dipush tanpa mengubah `main`.
- GitHub Actions run `34871619589` lulus pada commit `e135986`: job PHP 8.2/Laravel dan Node/Vite sama-sama hijau.
- Workflow hanya melakukan validation, install, test, dan build; tidak memuat langkah deployment.

### P1-03 Staging topology dan hPanel Git — DONE

Outcome target:

- Layout document root Laravel dibuktikan pada staging.
- hPanel Git terhubung ke branch staging dengan scope minimum.
- `.env`, database, dan storage staging terpisah.
- Auto-deploy tetap nonaktif sampai install/build/migration/rollback terbukti aman.

Tidak dieksekusi pada local foundation dan membutuhkan tindakan owner di hPanel.

Current progress:

- Target aman ditetapkan berupa website staging terpisah pada `staging.qammarisparfum.id` atau temporary domain Hostinger, bukan direktori production aktif.
- Domain utama dan deployment legacy harus tetap hidup sampai staging lulus, backup production terakhir telah diunduh, dan cutover mendapat approval owner.
- Dokumentasi Hostinger mengonfirmasi GitHub OAuth dapat digunakan tanpa setup SSH key dan target branch/root directory dapat dipilih, tetapi pemasangan atau penggantian repository dapat menimpa direktori target.
- Website staging terpisah telah dibuat, memakai PHP 8.2, dilindungi HTTP password, dan mempunyai database/user MySQL khusus staging.
- hPanel Git telah terhubung hanya ke repository Qammaris dan branch `modernization/phase-1-foundation`; deployment manual commit `240f94e` berhasil ke `public_html` tanpa menyentuh production.
- Build log membuktikan Composer dijalankan otomatis, tetapi Hostinger tidak menyediakan Node/npm. Vite dibangun lokal secara reproducible dan `public/build` diunggah sebagai artefak staging.
- Root `.htaccess` pada commit `8dc28df` berhasil mengarahkan fixed document root ke `public/`; homepage, katalog, dan asset CSS menghasilkan HTTP `200` setelah asset tersedia.
- `.env` berpermission `0600`, database kosong khusus staging, 12 migration batch 1, storage link, dan cache Laravel telah dibuat serta diverifikasi.
- HTTP Basic Auth terverifikasi menghasilkan `401` tanpa credential dan `200` dengan credential. Deploy Git dapat menimpa aturan auth sehingga pemasangan ulang wajib menjadi bagian release procedure.
- Auto-deploy telah dinonaktifkan. Production tetap tidak disentuh.
- Workflow manual `Staging release` memverifikasi SHA source yang dipublikasikan hPanel, membangun/mengunggah asset Vite, menjalankan migration additive, memastikan storage link, dan mengoptimalkan Laravel. GitHub Environment `staging` berisi secret SSH dan known-host verification; credential tidak dicatat di repository.
- Workflow `Staging release #1` berhasil pada 2026-09-15 (43 detik) terhadap SHA `8dc28dfd2567c992b7277e471df6985633ea0891`. Verifikasi server setelah rilis membuktikan `public/build/manifest.json`, symlink `public/storage`, dan seluruh 12 migration batch 1 tersedia. HTTP tanpa credential tetap menghasilkan `401`.
- Workflow `Staging release #2` berhasil pada 2026-09-15 (35 detik) terhadap SHA yang sama. Tidak ada migration pending setelah release ulang; build, storage link, dan Basic Auth tetap valid. Ini membuktikan release asset/migration yang sama dapat dijalankan ulang pada staging.
- Rollback asset staging terkontrol telah diuji: build backup dengan manifest berbeda diaktifkan sementara melalui rename, manifest tervalidasi, lalu build aktif dipulihkan. Dua folder rollback asset dipertahankan sebagai bukti; tidak ada data, media, source, atau production yang berubah.
- Pemeriksaan rollback source menunjukkan checkout hPanel staging detached dan shallow: `git log` hanya berisi commit aktif `8dc28df`. Karena tidak ada history atau branch checkout pada server, rollback source tidak boleh dipaksakan dari filesystem staging.
- Branch referensi `staging/known-good-8dc28df` telah dipush dan diverifikasi menunjuk tepat ke SHA staging sehat `8dc28dfd2567c992b7277e471df6985633ea0891`. Branch ini belum dipilih di hPanel dan tidak mengubah staging/production; branch tersebut menjadi ref source yang aman untuk recovery release berikutnya.
- Akses deployment memakai key terpisah yang dapat dicabut. Setelah audit filesystem account Hostinger yang disetujui owner, account tersebut hanya ditemukan memiliki dua domain Qammaris (`qammarisparfum.id` dan `staging.qammarisparfum.id`); pipeline hanya mengarah ke path staging. Production tidak disentuh.

Close-out review:

- Acceptance gate P1-03 dinyatakan lulus pada 2026-09-15: staging terpisah, source revision, environment/database/storage, workflow manual, idempotensi, proteksi akses, dan rollback asset mempunyai bukti verifikasi.
- Pada release source staging berikutnya, gunakan branch `staging/known-good-8dc28df` sebagai ref recovery bila release baru gagal, lalu jalankan health check autentik penuh. Ini adalah kewajiban operasi release berikutnya, bukan izin mengubah production.

### P1-04 Production backup dan cutover preflight — BACKLOG

Membutuhkan staging hijau, backup production terbaru, recovery evidence, serta approval owner. Tidak boleh dimulai dari development lokal.

## P2 — Tests & catalog safety

### P2-01 Public visibility dan variant ownership — DONE

Outcome:

- Produk nonaktif tidak dapat dibuka langsung atau dimasukkan ke cart.
- Update produk admin tidak dapat mengubah variant milik produk lain.

In scope:

- Regression test untuk detail produk, cart add/update, dan update variant admin.
- Guard pada controller/request tanpa mengubah schema atau UI.

Out of scope:

- Publication/availability schema baru.
- Redesign katalog atau admin.
- Perubahan harga, data, media, dan production.

Acceptance criteria:

- Detail produk nonaktif menghasilkan `404` tanpa menaikkan view count.
- Variant nonaktif atau milik produk nonaktif ditolak dari cart.
- Variant aktif milik produk aktif tetap dapat ditambahkan ke cart.
- ID variant pada update admin wajib dimiliki produk pada route.
- Seluruh test Laravel lulus.

Verification:

- Baseline sebelum fix: 4 test gagal dan membuktikan seluruh guard belum tersedia.
- Setelah fix: seluruh suite lulus, 8 test dan 18 assertion menggunakan SQLite in-memory.
- PHP syntax check lulus untuk seluruh file PHP yang diubah.
- Laravel Pint lulus untuk request dan test baru. Controller existing masih mempunyai style debt lama; tidak diformat massal agar diff tetap fokus.
- GitHub Actions CI run `34955143898` lulus: PHP 8.2/Laravel tests dan Node/Vite build hijau.
- Tidak ada migration, perubahan schema/data/media, UI, staging, atau production.

### P2-02 Admin product validation boundaries — DONE

Outcome:

- Admin tidak dapat menyimpan data produk yang melanggar batas schema atau keputusan katalog.

In scope:

- Validasi brand aktif, gender, deskripsi/notes, ukuran, harga, stok, compare-at price, dan maksimum tiga gambar.
- Regression test create/update menggunakan SQLite dan storage fake.

Out of scope:

- Draft/publication schema, external product code, import engine, dan download gambar remote.
- Perubahan UI, database nyata, staging, atau production.

Acceptance criteria:

- Gender hanya menerima Unisex, Pria, atau Wanita.
- Ukuran dan harga jual lebih dari nol; stok tidak negatif.
- Compare-at price kosong atau lebih besar dari harga jual terendah.
- Produk mempunyai maksimum tiga gambar total.
- Brand nonaktif ditolak untuk create/update baru.
- Produk existing tetap dapat mempertahankan brand lama yang kemudian dinonaktifkan.
- Jalur create valid dengan tiga gambar tetap berhasil.
- Seluruh test Laravel dan CI lulus.

Verification:

- Seluruh suite lokal lulus: 16 test dan 50 assertion menggunakan SQLite in-memory serta storage fake.
- Laravel Pint lulus untuk kedua Form Request dan seluruh test P2.
- PHP syntax check dan `git diff --check` lulus.
- GitHub Actions CI run `34958077412` lulus untuk PHP 8.2/Laravel tests dan Node/Vite build.
- Tidak ada migration, perubahan schema/data/media nyata, UI, staging, atau production.

### P2-03 Transaction-safe product image uploads — DONE

Outcome:

- Kegagalan database setelah upload gambar tidak meninggalkan file baru yatim atau perubahan produk setengah jadi.

In scope:

- Verifikasi hasil penyimpanan file gambar pada create/update produk.
- Cleanup file yang baru diunggah jika transaksi database gagal.
- Pesan error generik kepada admin dan pelaporan exception internal.
- Regression test dengan storage fake dan kegagalan model yang disengaja.

Out of scope:

- Penghapusan produk/gambar existing, migrasi storage, dan remote image download.
- Perubahan schema, UI, staging, atau production.

Acceptance criteria:

- Create yang gagal setelah satu atau lebih upload melakukan rollback database dan membersihkan seluruh file baru.
- Update yang gagal mempertahankan data/file existing dan membersihkan seluruh file baru.
- File yang dilaporkan tersimpan wajib terbukti tersedia pada disk.
- Detail exception tidak ditampilkan kepada admin.
- Seluruh test Laravel dan CI lulus.

Verification:

- Focused regression test lulus: `2 passed (11 assertions)`.
- Seluruh Laravel test lulus lokal pada PHP 8.2.12: `18 passed (61 assertions)`.
- PHP syntax check untuk controller dan regression test lulus.
- Pint check untuk regression test baru dan `git diff --check` lulus.
- CI GitHub Actions run `34958515257` lulus untuk commit implementasi `82906c5`.
- Tidak ada schema, data/media nyata, UI, staging, atau production yang diubah.

### P2-04 Login throttling — DONE

Outcome:

- Endpoint login dilindungi dari percobaan password berulang tanpa mengubah alur autentikasi existing.

In scope:

- Rate limit kegagalan login berdasarkan kombinasi email yang dinormalisasi dan alamat IP.
- Pesan kegagalan tetap generik dan penghitung dibersihkan setelah login berhasil.
- Regression test untuk lockout, isolasi identity key, dan pemulihan setelah penghitung dibersihkan.

Out of scope:

- Perubahan UI login, reset password, MFA, permission/role redesign, staging, dan production.

Acceptance criteria:

- Maksimum lima kegagalan login diizinkan dalam jendela satu menit untuk identity key yang sama.
- Percobaan berikutnya ditolak walaupun password benar sampai window berakhir atau counter dibersihkan.
- Email berbeda pada IP yang sama tidak menggunakan counter yang sama.
- Login berhasil membersihkan counter miliknya.
- Seluruh test Laravel dan CI lulus.

Verification:

- Focused regression test lulus: `3 passed (38 assertions)`.
- Seluruh Laravel test lulus lokal pada PHP 8.2.12: `21 passed (99 assertions)`.
- Pint, PHP syntax check, dan `git diff --check` lulus untuk file P2-04.
- CI GitHub Actions run `34958978794` lulus untuk commit implementasi `101088d`.
- Tidak ada schema, data/media, UI, staging, atau production yang diubah.

### P2-05 Checkout cart integrity — DONE

Outcome:

- Inquiry WhatsApp dibangun dari produk, brand, variant, dan harga terbaru di database; bukan dari snapshot session yang mungkin kedaluwarsa.

In scope:

- Validasi ulang seluruh item cart saat checkout terhadap variant dan produk aktif.
- Tolak checkout jika variant hilang/nonaktif, produk nonaktif, quantity tidak valid, atau stok existing tidak mencukupi.
- Gunakan nama, brand, ukuran, dan harga authoritative dari database saat membangun pesan WhatsApp.
- Regression test untuk item tidak tersedia dan snapshot session kedaluwarsa.

Out of scope:

- Availability schema baru, perubahan jaminan stok, redesign cart, payment checkout, staging, dan production.

Acceptance criteria:

- Produk/variant tidak aktif atau hilang tidak dapat dikirim sebagai inquiry.
- Checkout tidak mempercayai nama, brand, ukuran, atau harga dari session.
- Quantity wajib integer positif dan masih mengikuti pengecekan stok existing.
- Checkout valid tetap mengarah ke WhatsApp dengan data database terbaru.
- Seluruh test Laravel dan CI lulus.

Verification:

- Focused regression test lulus: `3 passed (13 assertions)`.
- Seluruh Laravel test lulus lokal pada PHP 8.2.12: `24 passed (112 assertions)`.
- Regression test baru lulus Pint; controller dan test lulus PHP syntax check serta `git diff --check`.
- `CartController` mempunyai style debt pre-existing di luar diff dan sengaja tidak diformat massal.
- CI GitHub Actions run `34959341933` lulus untuk commit implementasi `6c21d60`.
- Tidak ada schema, data/media, UI, staging, atau production yang diubah.

### P2-06 Safe rich-text rendering — DONE

Outcome:

- Konten artikel tetap mendukung rich text dasar tanpa dapat menyisipkan script atau atribut HTML berbahaya ke halaman publik.

In scope:

- Sanitizer HTML allowlist berbasis DOM bawaan PHP tanpa dependency baru.
- Sanitasi saat admin menyimpan artikel dan sanitasi ulang saat artikel ditampilkan untuk melindungi data legacy.
- Pertahankan elemen editorial dasar dan batasi atribut/link/image URL.
- Regression test untuk script, event handler, iframe, dan skema URL berbahaya.

Out of scope:

- Redesign blog/editor, migrasi isi artikel lama, WYSIWYG baru, schema, staging, dan production.

Acceptance criteria:

- Script, embedded executable content, event attributes, dan URL `javascript:` tidak muncul pada respons publik.
- Paragraph, heading, emphasis, list, quote, code, table, safe link, dan safe image tetap didukung.
- Konten baru disimpan dalam bentuk tersanitasi; record lama juga aman ketika dirender.
- Tidak ada dependency baru atau perubahan visual untuk markup yang diizinkan.
- Seluruh test Laravel dan CI lulus.

Verification:

- Focused regression test lulus: `2 passed (18 assertions)`.
- Seluruh Laravel test lulus lokal pada PHP 8.2.12: `26 passed (130 assertions)`.
- Pint lulus untuk sanitizer dan regression test baru.
- Seluruh file PHP yang diubah lulus syntax check dan `git diff --check`.
- CI GitHub Actions run `34960008760` lulus untuk commit implementasi `47ca61a`.
- Tidak ada schema, migrasi data/media, perubahan visual, staging, atau production yang diubah.

### P2-07 Safe global head metadata and JSON-LD — DONE

Outcome:

- Metadata HTML global di-escape pada context atribut/teks dan JSON-LD Organization/WebSite menjadi JSON valid serta aman dari penutupan tag script.

In scope:

- Normalisasi section title, description, robots, Open Graph type, dan image menjadi variabel layout yang di-escape.
- Serialisasi dua schema JSON-LD global dengan key `@context` yang valid dan JSON hex flags.
- Regression test memakai metadata hostile serta parsing seluruh JSON-LD global.

Out of scope:

- Product JSON-LD pada `resources/views/products/show.blade.php` karena file tersebut mempunyai perubahan lokal owner yang wajib dipertahankan.
- Perubahan visual, SEO content strategy, schema/data, staging, dan production.

Acceptance criteria:

- Metadata dari child view tidak dapat keluar dari `<title>` atau atribut `<meta>`.
- Dua blok JSON-LD global dapat di-decode sebagai JSON dan mempunyai key `@context` yang benar.
- Output JSON-LD tidak mengandung artefak kompilasi Blade/PHP atau literal `</script>` dari data.
- Tidak ada perubahan visual dan seluruh test Laravel/CI lulus.

Verification:

- Focused regression test lulus: `2 passed (15 assertions)`.
- Seluruh Laravel test lulus lokal pada PHP 8.2.12: `28 passed (145 assertions)`.
- Seluruh Blade template berhasil dikompilasi dengan `artisan view:cache`.
- Test baru lulus Pint dan PHP syntax check; `git diff --check` lulus.
- CI GitHub Actions run `34960419263` lulus untuk commit implementasi `d042b4f`.
- Tidak ada schema, data/media, perubahan visual, staging, atau production yang diubah.

### P2-08 Blog publication visibility — DONE

Outcome:

- Artikel draft, tanpa tanggal publish, atau terjadwal di masa depan tidak dapat dibuka langsung melalui slug publik.

In scope:

- Satukan aturan visibility artikel pada model dan gunakan pada detail publik.
- Regression test untuk draft, missing date, scheduled, dan published article.

Out of scope:

- Redesign blog/admin, workflow approval editorial, schema/data, staging, dan production.

Acceptance criteria:

- Detail publik menghasilkan `404` untuk draft, artikel tanpa tanggal publish, dan artikel terjadwal.
- Artikel published dengan waktu yang sudah lewat tetap menghasilkan `200`.
- Request tidak valid tidak menambah view count.
- Listing/category/sitemap tetap menggunakan scope publication existing.
- Seluruh test Laravel dan CI lulus.

Verification:

- Focused regression test lulus: `4 passed (8 assertions)`.
- Seluruh Laravel test lulus lokal pada PHP 8.2.12: `32 passed (153 assertions)`.
- Regression test baru lulus Pint; model/controller/test lulus PHP syntax check serta `git diff --check`.
- `BlogPost` mempunyai style debt pre-existing di luar diff dan sengaja tidak diformat massal.
- CI GitHub Actions run `34960611351` lulus untuk commit implementasi `e675601`.
- Tidak ada schema, data/media, perubahan visual, staging, atau production yang diubah.

### P2-09 Product detail JSON-LD reconciliation — DONE

Outcome:

- Product JSON-LD menjadi valid dan aman tanpa kehilangan perubahan owner pada halaman detail produk.

Dependency:

- Perubahan lokal owner di `resources/views/products/show.blade.php` harus direkonsiliasi tanpa di-stage atau ditimpa oleh agent.

Known issue:

- Inline key `@context` dapat diproses sebagai directive Blade dan serialisasi saat ini belum memakai JSON hex flags.

Acceptance criteria:

- Seluruh perubahan fallback/null-safety owner pada halaman detail dipertahankan.
- Product JSON-LD dapat di-decode dan mempunyai key `@context`/`@type` yang benar.
- Nama/deskripsi hostile tidak dapat menutup tag JSON-LD script.
- Availability tidak diklaim `InStock` sebelum semantics Phase 3 tersedia.
- Product tanpa variant tetap memakai `base_price`; guard tanpa harga disiapkan untuk draft nullable pada Phase 3.
- Seluruh test Laravel, kompilasi Blade, dan CI lulus.

Verification:

- Focused regression test lulus: `2 passed (12 assertions)`.
- Seluruh Laravel test lulus lokal pada PHP 8.2.12: `34 passed (165 assertions)`.
- Seluruh Blade template berhasil dikompilasi dengan `artisan view:cache`.
- Regression test baru lulus Pint/PHP syntax check dan `git diff --check` lulus.
- Perubahan fallback/null-safety owner dipertahankan dan kini masuk dalam scope commit atas persetujuan eksplisit owner.
- CI GitHub Actions run `34971935885` lulus untuk commit implementasi `0b207d9`.

### P2-10 Destructive product/media behavior — DONE

Outcome:

- Penghapusan product/image tidak menyebabkan kehilangan metadata, file hilang yang masih direferensikan, atau orphan media saat salah satu storage/database operation gagal.

Owner decision:

- Tombol hapus produk legacy diubah menjadi archive/nonaktif yang dapat dipulihkan. Hard delete product dan media tidak tersedia dari admin pada tahap ini.

Acceptance criteria:

- Request `DELETE` resource product legacy hanya mengubah `is_active` menjadi `false` dan bersifat idempotent.
- Product ID, slug, variant, metadata image, dan file media tetap utuh setelah archive.
- Admin dapat mengaktifkan kembali produk yang sudah diarsipkan.
- Katalog admin menampilkan status aktif/diarsipkan serta label aksi dan konfirmasi yang menjelaskan dampaknya.
- Customer/non-admin tidak dapat menjalankan archive atau restore.
- Endpoint dan kontrol hapus gambar legacy tidak menghapus metadata atau file; penghapusan aman ditunda ke Phase 4.

Implementation:

- Aksi resource `DELETE` product kini mengubah `is_active` menjadi `false`; product, slug, variant, metadata gambar, dan file tidak dihapus.
- Route `PATCH admin/products/{product}/restore` mengaktifkan kembali produk secara idempotent.
- Catalog manager legacy menampilkan status `Aktif`/`Diarsipkan`, aksi arsip yang tidak memakai affordance hard delete, serta konfirmasi yang menjelaskan data dan gambar tetap disimpan.
- Endpoint hapus gambar legacy dipertahankan sebagai no-op terotorisasi dan kontrol hapusnya di editor dihilangkan; admin menerima penjelasan bahwa penggantian/penghapusan aman disiapkan pada Phase 4.

Verification:

- Focused regression test lulus: `7 passed (33 assertions)`.
- Seluruh Laravel test lulus lokal pada PHP 8.2.12: `41 passed (198 assertions)`.
- Seluruh Blade template berhasil dikompilasi dengan `artisan view:cache`; production asset build Vite berhasil.
- Pint untuk controller, route, dan regression test baru lulus; PHP syntax check dan `git diff --check` lulus.
- Flow browser lokal aktif → arsip → restore berhasil dengan pesan/status yang sesuai.
- Admin catalog dan product editor diverifikasi pada viewport `1440x900` dan `390x844`; tidak ada page-level horizontal overflow atau browser console error. Table catalog mobile tetap memakai internal horizontal scroll legacy.
- Data audit sementara dibersihkan; jumlah produk lokal kembali `180`. Tidak ada schema, data/media existing, staging, atau production yang diubah.
- CI GitHub Actions run `34974320829` lulus untuk commit implementasi `81115c1`.

## P3 — Product domain & migrations

### P3-01 Publication dan availability foundation — DONE

Outcome:

- Publication dan availability menjadi state domain yang terpisah tanpa mengubah ID, slug, harga, variant, media, atau URL existing.

In scope:

- Migration additive untuk `publication_status`, `published_at`, `archived_at`, `availability_status`, `stock_quantity`, `availability_source`, dan `availability_checked_at`.
- Mapping compatibility `is_active=true` ke `published` dan `is_active=false` ke `archived` untuk data existing.
- Scope publik eksplisit `published` dengan `is_active` tetap menjadi guard transisi.
- Freshness rule 36 jam untuk status `available`; `sold_out` tetap eksplisit sampai diperbarui.
- Archive/restore admin menyinkronkan status baru dan field compatibility lama.

Out of scope:

- Perubahan UI admin/public, filter availability, dan penampilan quantity.
- Draft dengan field nullable, optional SKU, price authority, external provider mapping, serta reconciliation data.
- Staging migration dan production deployment.

Acceptance criteria:

- Migration bersifat additive dan mempertahankan seluruh product ID/slug serta record terkait.
- Hanya produk `published` dengan compatibility guard aktif yang dapat diakses katalog, detail, related products, sitemap, dan cart.
- Produk draft/archived tidak dapat diakses publik; sold out tidak otomatis diarsipkan.
- `available` tanpa pemeriksaan atau yang lebih lama dari 36 jam dibaca sebagai `unknown`.
- Archive/restore idempotent dan menyinkronkan `publication_status`, timestamp, serta `is_active`.
- Regression test, migration round-trip pada database kosong, seluruh test Laravel, dan CI lulus.

Verification:

- Migration lokal berhasil pada database berisi `180` produk. Jumlah record tetap `180`, seluruh produk legacy aktif terpetakan ke `published`, seluruh availability terinisialisasi `unknown`, dan checksum identitas `id:slug` tetap `75e4cf84ef65227df2fbf74279d689c59a0ff737f893a2c8004da92676f820de` sebelum maupun setelah migration.
- Migration `up -> down -> up` berhasil pada database SQLite kosong sementara; file database sementara sudah dibersihkan.
- Seluruh test Laravel lulus: `48 passed (224 assertions)`.
- `composer validate --strict`, Vite production build, Blade clear/cache, Laravel Pint untuk seluruh file PHP yang diubah, pemeriksaan syntax PHP, dan `git diff --check` lulus. Pint tingkat repository masih mendeteksi formatting legacy pada file di luar scope P3-01. Vite hanya melaporkan warning existing untuk DaisyUI `@property` dan chunk `about-lanyard` yang besar.
- Tidak ada UI, media, staging, atau production yang diubah. Verifikasi viewport browser tidak berlaku karena item ini tidak mengubah tampilan.
- CI GitHub Actions untuk commit implementasi `6684f33` lulus pada run `35049913891` (push) dan `35049916555` (pull request), mencakup test PHP/Laravel serta build Node/Vite.

### P3-02 Draft fields, one-offer price authority, dan optional SKU — DONE

Outcome:

- Draft dapat disimpan hanya dengan nama kerja, sedangkan produk lengkap menggunakan tepat satu offer sebagai sumber ukuran dan harga jual.

In scope:

- Membuat brand, kategori, deskripsi, `base_price`, dan gender nullable untuk draft tanpa mengubah nilai record existing.
- Membuat SKU offer nullable dan menghentikan pembuatan SKU acak.
- Membatasi satu offer teknis per produk serta mempertahankan ID offer existing ketika diedit.
- Menyinkronkan `products.base_price` hanya dari harga offer melalui satu application operation.
- Menyederhanakan bagian ukuran/harga form admin legacy menjadi satu offer tanpa redesign admin panel.

Out of scope:

- Workflow tombol simpan draft/publish dan daftar alasan draft belum publish; masuk Admin Panel V2.
- Reconciliation 116 produk tanpa offer, enam harga tidak valid, dan 26 konflik harga existing.
- External provider mapping, bulk import, update harga massal, media, staging, dan production.

Acceptance criteria:

- Draft bernama dapat disimpan pada domain tanpa brand, kategori, deskripsi, gender, harga, offer, atau gambar.
- Form admin legacy hanya menerima satu offer dengan ukuran dan harga valid.
- SKU kosong tersimpan sebagai `null`; tidak ada SKU acak baru.
- Create/update offer menyinkronkan `base_price`, mempertahankan ID offer saat update, dan menolak offer milik produk lain.
- Migration tidak mengubah ID, slug, harga, SKU, offer, atau media existing dan constraint satu-offer telah dipastikan aman dari audit lokal.
- Relevant tests, migration round-trip, seluruh test Laravel, browser mobile/desktop, build, dan CI lulus.

Verification:

- Audit pre-migration memastikan `180` produk, `64` offer, tidak ada produk dengan lebih dari satu offer, dan tidak ada SKU duplikat.
- Migration lokal berhasil tanpa update record. Count tetap `180` produk dan `64` offer; checksum produk `d3f59d5f53d20ae380946c61f7ebbd3dc3b512643ec294a827f14ebef5589532` serta checksum offer `0d91092d3d993d953855e64d22a50eb8964c7563210f7d832ee1742afb5a3511` identik sebelum/sesudah migration.
- Migration `up -> down -> up` berhasil pada database SQLite sementara dan file sementara sudah dibersihkan.
- Relevant tests lulus `24 passed (91 assertions)` dan seluruh test Laravel lulus `55 passed (254 assertions)`.
- Composer strict validation, Blade clear/cache, syntax PHP, Laravel Pint untuk file yang diubah, `git diff --check`, dan Vite production build lulus. Warning existing DaisyUI `@property` serta chunk `about-lanyard` besar tetap tercatat dan tidak diperluas oleh item ini.
- Browser lokal memverifikasi create, edit dengan offer, edit tanpa offer, native required validation, dan urutan keyboard pada `390x844` serta `1440x900`. Lebar dokumen sama dengan viewport pada keduanya, tombol Add Variant berjumlah nol, hanya tiga field offer tampil, dan console tidak memiliki warning/error.
- Baseline sebelum perubahan diaudit dari implementasi Blade/Git existing; screenshot sebelum tidak tersedia karena akses admin lokal belum tersedia saat baseline. Screenshot sesudah perubahan diverifikasi langsung pada kedua viewport.
- Akun admin sintetis lokal dibuat khusus verifikasi dan tepat satu record tersebut telah dihapus kembali. Tidak ada produk/media test tersimpan.
- Tidak ada staging, production, media existing, nilai harga existing, SKU existing, ID, atau slug yang diubah.
- CI GitHub Actions untuk commit implementasi `b09a284` lulus pada run `35054778566` (push) dan `35054781975` (pull request), mencakup test PHP/Laravel serta build Node/Vite.

### P3-03 External product identity boundary — DONE

Outcome:

- Kode produk Shopee/Majoo dapat dipetakan secara idempotent ke product internal tanpa mengganti ID, slug, atau SKU katalog.

In scope:

- Tabel mapping terpisah untuk provider dan kode produk eksternal.
- Constraint satu kode eksternal hanya dapat dimiliki satu product dalam provider yang sama.
- Constraint satu product hanya mempunyai satu identity untuk setiap provider.
- Operasi domain bersama untuk normalisasi, pencocokan idempotent, dan penolakan konflik mapping.
- Relasi Eloquent dan regression test untuk isolation antar-provider serta perlindungan identity internal.

Out of scope:

- Upload/import spreadsheet, integrasi API Shopee/Majoo, sinkronisasi stok/harga, pencocokan berdasarkan nama, UI admin, media, staging, dan production.

Dependencies:

- Kontrak identity pada `BUSINESS_RULES.md` dan product ID/slug existing yang wajib dipertahankan.

Risks:

- Rebinding kode provider dapat menggabungkan dua listing berbeda; karena itu mapping existing bersifat immutable dan konflik harus masuk review.

Acceptance criteria:

- Provider dinormalisasi ke lowercase dan kode eksternal di-trim tanpa dicampur dengan SKU offer.
- Mapping ulang provider/kode/product yang sama bersifat idempotent dan mempertahankan ID mapping.
- Kode yang sama dapat digunakan provider berbeda, tetapi duplikat dalam provider yang sama ditolak.
- Product yang sudah mempunyai identity pada sebuah provider tidak dapat di-rebind ke kode baru; listing provider yang dibuat ulang harus menjadi draft baru.
- Migration additive tidak mengubah record product, variant, media, ID, slug, harga, atau SKU existing.
- Focused test, migration round-trip, seluruh test Laravel, quality checks, dan CI lulus.

Verification:

- Migration lokal membuat tabel mapping kosong tanpa mengubah data existing. Count tetap `180` produk, `64` offer, dan `19` image; checksum product `f866dfdd36f21bae071fbce45d44444c0b27b407e323fad88951e5c82d4d1285` serta offer `3a41523458121b2be1db0c1314e909e611880c3a96db0ffb67f7433925623e77` identik sebelum/sesudah migration.
- Migration `up -> down -> up` berhasil pada database SQLite kosong sementara dan file sementara sudah dibersihkan.
- Focused regression test lulus: `9 passed (22 assertions)`; seluruh test Laravel lulus: `64 passed (276 assertions)`.
- Composer strict validation, Blade clear/cache, syntax PHP, scoped Laravel Pint, `git diff --check`, dan Vite production build lulus.
- Warning Vite existing untuk DaisyUI `@property` serta chunk `about-lanyard` besar tetap tercatat dan tidak diperluas oleh item ini.
- Tidak ada UI, media, staging, atau production yang diubah. Verifikasi browser tidak berlaku karena item ini hanya mengubah domain/schema backend.
- CI GitHub Actions untuk commit implementasi `6e92e0a` lulus pada run `35055611565` (push) dan `35055613609` (pull request), mencakup test PHP/Laravel serta build Node/Vite.

## P4 — Media storage

### P4-01 Product media storage boundary dan local inventory — DONE

Outcome:

- Upload dan pembacaan gambar produk menggunakan satu boundary Laravel Filesystem yang disk-nya dapat diganti melalui environment tanpa mengubah domain/controller.

In scope:

- Inventory metadata dan file produk lokal tanpa mengubah atau menghapus data/media.
- Konfigurasi disk serta direktori khusus media produk dengan default compatibility `public`.
- Service untuk normalisasi object key legacy, upload-terverifikasi, URL resolution, dan rollback cleanup.
- Mengganti hardcoded disk pada `ProductImage` dan admin upload dengan service bersama.
- Regression test untuk configurable disk, path legacy, missing file, pencegahan remote hotlink, dan rollback upload.

Out of scope:

- Instalasi adapter S3, pembuatan bucket/R2 credential, copy file ke cloud, perubahan metadata massal, orphan cleanup, UI media, remote image acquisition, staging, dan production.

Dependencies:

- P2-03 transaction-safe upload, P2-10 destructive media guard, dan aturan copy-verify-switch-retain.

Risks:

- Path production belum diaudit penuh; compatibility reader harus tetap mendukung format legacy `storage/` dan `public/` tanpa menulis ulang record.

Acceptance criteria:

- Disk upload/read/delete produk ditentukan oleh `PRODUCT_MEDIA_DISK`, bukan hardcoded `public` pada model/controller.
- Upload baru menghasilkan object key relatif canonical di direktori `products` dan diverifikasi tersedia sebelum metadata dianggap sukses.
- Path legacy `storage/...` dan `public/...` tetap dapat dibaca tanpa migrasi data.
- Path hilang/tidak aman dan URL remote menghasilkan placeholder sehingga halaman publik tidak melakukan hotlink provider.
- Kegagalan transaksi tetap membersihkan hanya file baru pada disk yang dikonfigurasi.
- Tidak ada metadata/file existing yang dipindah, ditulis ulang, atau dihapus.
- Focused test, seluruh test Laravel, quality checks, dan CI lulus.

Verification:

- Inventory lokal sebelum/sesudah implementasi tetap `19` metadata pada `14` produk, `14` primary image, `20` file pada direktori produk, tanpa metadata yang kehilangan file. Tidak ada metadata atau file yang ditulis ulang/dihapus.
- Satu kandidat orphan `products/IeCuw7MDgiwWOxJGaJk8j3ZCe5BxKzvpiKwifR4u.jpg` hanya dicatat dan dipertahankan.
- Seluruh 19 object key existing berhasil di-resolve melalui boundary baru pada disk `public` tanpa placeholder.
- Focused storage/transaction test lulus: `7 passed (26 assertions)`; seluruh test Laravel lulus: `69 passed (291 assertions)`.
- Composer strict validation, Blade clear/cache, syntax PHP, scoped Laravel Pint, `git diff --check`, dan Vite production build lulus.
- Warning Vite existing untuk DaisyUI `@property` serta chunk `about-lanyard` besar tetap tercatat dan tidak diperluas oleh item ini.
- Tidak ada perubahan tampilan, schema, data, media, staging, atau production. Verifikasi browser tidak berlaku karena item ini mengubah boundary storage backend tanpa mengubah markup/UI.
- CI GitHub Actions untuk commit implementasi `2f3c15f` lulus pada run `35056320507` (push) dan `35056322360` (pull request), mencakup test PHP/Laravel serta build Node/Vite.

### P4-02 Shared media attachment dan primary lifecycle — DONE

Outcome:

- Admin, import, dan API masa depan memakai operasi domain yang sama untuk menambahkan gambar serta memilih primary image tanpa membuat primary ganda atau melewati batas tiga gambar.

In scope:

- Operasi transactional untuk attach metadata gambar dengan maksimum tiga gambar per produk.
- Gambar pertama otomatis menjadi primary; primary baru menurunkan primary sebelumnya secara atomik.
- Operasi terpisah untuk memilih primary existing yang wajib dimiliki produk pada scope-nya.
- Admin product create/update memakai operasi bersama setelah upload file terverifikasi.
- Regression test untuk invariant primary, idempotency, ownership, batas tiga gambar, dan cleanup ketika attach gagal.

Out of scope:

- Penghapusan/penggantian file lama, reorder UI, redesign media editor, schema constraint baru, R2, remote image acquisition, staging, dan production.

Dependencies:

- P4-01 product media storage boundary dan maksimal tiga gambar pada business rules.

Risks:

- Database lintas MySQL/SQLite tidak menyediakan partial unique constraint portable untuk primary image; invariant dijaga dengan lock product dan operasi domain bersama.

Acceptance criteria:

- Attach pertama selalu menghasilkan tepat satu primary image meskipun caller tidak meminta primary.
- Metadata baru hanya dapat dibuat untuk object key canonical di direktori produk yang benar-benar tersedia pada disk terkonfigurasi.
- Attach primary baru mempertahankan metadata/file existing dan memastikan hanya satu primary.
- Attach keempat ditolak tanpa mengubah metadata existing.
- Pemilihan primary mempertahankan ID, path, dan urutan serta menolak image milik product lain.
- Controller tidak membuat `ProductImage` langsung untuk alur create/update.
- Data/media existing tidak ditulis ulang atau dihapus.
- Focused test, seluruh test Laravel, quality checks, dan CI lulus.

Verification:

- Audit lokal sebelum/sesudah tetap `19` metadata pada `14` produk, tanpa produk di atas tiga gambar, tanpa koleksi bergambar yang kehilangan primary, tanpa primary ganda, dan maksimum existing tiga gambar.
- Focused media/admin regression test lulus: `24 passed (93 assertions)`; seluruh test Laravel lulus: `76 passed (315 assertions)`.
- Controller create/update tidak lagi membuat `ProductImage` secara langsung; upload terverifikasi diteruskan ke operasi `AttachProductImage`.
- Composer strict validation, Blade clear/cache, syntax PHP, scoped Laravel Pint, `git diff --check`, dan Vite production build lulus.
- Warning Vite existing untuk DaisyUI `@property` serta chunk `about-lanyard` besar tetap tercatat dan tidak diperluas oleh item ini.
- Tidak ada schema, metadata/file existing, tampilan, staging, atau production yang diubah. Verifikasi browser tidak berlaku karena item ini mengubah operasi media backend tanpa mengubah markup/UI.
- Masukan owner tentang preservasi page/search/filter/sort setelah edit dicatat pada business rules dan `P5-01`; tidak diselipkan ke scope media.
- CI GitHub Actions untuk commit implementasi `16d29e1` lulus pada run `35058656237` (push) dan `35058658414` (pull request), mencakup test PHP/Laravel serta build Node/Vite.

### P4-03 R2 copy-verify foundation — DONE

Outcome:

- Media produk dapat disalin secara non-destruktif dari disk aktif ke disk target S3-compatible dengan manifest dan verifikasi checksum sebelum cutover dipertimbangkan.

In scope:

- Adapter Flysystem S3 resmi untuk Laravel.
- Disk Cloudflare R2 terpisah dengan credential/endpoint hanya dari environment.
- Command inventory/copy dengan mode dry-run default dan flag apply eksplisit.
- Hanya object key canonical yang direferensikan metadata produk yang diproses; source tidak pernah dihapus.
- Target existing diverifikasi; mismatch tidak ditimpa otomatis.
- Manifest JSON lokal berisi batch ID, disk, mode, checksum, ukuran, status, dan ringkasan tanpa credential.
- Regression test menggunakan fake disks untuk dry-run, copy+verify, already verified, missing source, mismatch, dan idempotency.

Out of scope:

- Membuat bucket/token Cloudflare, mengisi credential nyata, menjalankan copy ke R2 nyata, mengganti `PRODUCT_MEDIA_DISK`, public delivery URL/domain, orphan cleanup, staging, dan production.

Dependencies:

- P4-01 storage boundary, P4-02 media lifecycle, serta konfigurasi bucket/token R2 milik owner pada tahap berikutnya.

Risks:

- Object storage bukan transaksi database; source harus tetap dipertahankan dan mismatch target wajib direview tanpa overwrite otomatis.

Acceptance criteria:

- Dry-run adalah default dan tidak menulis target.
- Apply hanya menyalin object yang source-nya valid dan target belum ada.
- Source dan target diverifikasi dengan SHA-256 serta ukuran setelah copy.
- Target existing yang cocok menjadi `already_verified`; target mismatch dilaporkan dan tidak ditimpa.
- Missing/invalid source tidak menghentikan seluruh batch tetapi menghasilkan status gagal dan exit code non-zero.
- Manifest tidak memuat access key, secret, endpoint credential, atau isi file.
- Tidak ada database, source media, staging, atau production yang diubah.
- Focused test, seluruh test Laravel, quality checks, dan CI lulus.

Verification:

- Adapter `league/flysystem-aws-s3-v3` versi `3.35.3` terpasang dan `composer validate --strict` lulus.
- Command `product-media:copy-verify` terdaftar pada Artisan; default dry-run dan apply eksplisit dilindungi regression test.
- Focused test lulus: `7 passed (40 assertions)`.
- Seluruh test Laravel lulus: `83 passed (355 assertions)`.
- Build Vite production lulus; warning existing DaisyUI `@property` dan chunk `about-lanyard` tetap dicatat sebagai pekerjaan optimasi terpisah.
- Audit lokal read-only: 19 metadata `product_images`, 19 referensi unik, 0 referensi file hilang, dan 20 file pada direktori produk.
- Database, source media, konfigurasi disk aktif, staging, dan production tidak diubah. Bucket/credential serta copy/cutover R2 nyata tetap belum dilakukan.
- Implementasi tercatat pada commit `81cf3bc` (`feat: add r2 media copy verification`).
- CI GitHub Actions untuk commit implementasi lulus pada run `35059749288` (push) dan `35059752504` (pull request), mencakup test PHP/Laravel serta build Node/Vite.

### P4-04 R2 staging copy verification — DONE

Outcome:

- Seluruh media produk yang direferensikan metadata tersalin ke bucket R2 khusus Qammaris dan terbukti identik sebelum disk aktif atau URL publik dipertimbangkan untuk dialihkan.

In scope:

- Bucket R2 khusus Qammaris dengan token S3 `Object Read & Write` yang dibatasi hanya ke bucket tersebut.
- Credential disimpan pada environment operator/staging yang aman, tidak di Git, database, manifest, atau output terminal.
- Dry-run terhadap source `public` dan target `r2` tanpa mengubah source, database, atau disk aktif.
- Apply copy, verifikasi SHA-256/ukuran, rerun idempotent, serta review manifest untuk seluruh referenced object.
- Verifikasi bahwa local source tetap tersedia sebagai rollback source.

Out of scope:

- Mengganti `PRODUCT_MEDIA_DISK`, mengaktifkan upload/read production dari R2, menghubungkan custom domain, public production delivery, cleanup local/orphan file, atau deployment production.

Dependencies:

- Owner mengaktifkan Cloudflare R2, membuat bucket, dan menyediakan credential S3 bucket-scoped melalui channel aman atau memberi izin eksplisit untuk mengontrol dashboard Cloudflare.
- P4-03 command copy-verify sudah selesai dan teruji.

Risks:

- Pembuatan R2 merupakan perubahan pada layanan eksternal dan dapat melibatkan aktivasi billing; tidak boleh dilakukan tanpa tindakan/izin owner.
- Secret hanya ditampilkan satu kali saat token dibuat dan tidak boleh ditempel ke chat, Git, dokumentasi, atau log.

Acceptance criteria:

- Preflight credential berhasil tanpa mencetak nilainya.
- Dry-run selesai tanpa failure/conflict dan merencanakan seluruh referenced object yang belum ada.
- Apply menghasilkan hanya `copied_verified` atau `already_verified`; source tidak terhapus.
- Rerun apply menghasilkan `already_verified` untuk seluruh referenced object.
- Count, ukuran, dan checksum sesuai manifest; credential tidak muncul pada file atau output version control.
- Database, active product disk, staging public traffic, dan production tidak berubah.

Verification:

- Preflight lokal 2026-09-16 mengonfirmasi disk aktif tetap `public` dan target tetap `r2`.
- Bucket private `qammaris-website-media-staging` dibuat pada Cloudflare R2 dengan lokasi otomatis Asia Pacific dan standard storage class. Bucket operasional Qammaris App tidak diubah.
- Token R2 `Object Read & Write` dibuat dengan scope hanya ke bucket `qammaris-website-media-staging`; token account-wide tidak digunakan.
- GitHub Environment `staging` menyimpan `R2_ACCESS_KEY_ID` dan `R2_SECRET_ACCESS_KEY` sebagai encrypted secrets. Nilai credential tidak dicatat di chat, Git, dokumentasi, database, atau output terminal.
- GitHub Environment `staging` menyimpan `R2_BUCKET`, `R2_ENDPOINT`, `R2_REGION`, dan `PRODUCT_MEDIA_TARGET_DISK` sebagai environment variables. `PRODUCT_MEDIA_DISK` tidak diubah dan public delivery tidak diaktifkan.
- Credential dipasang langsung ke `.env` operator lokal yang diabaikan Git melalui bridge localhost sekali pakai; bridge dan tab langsung dibuang setelah penulisan. Pemeriksaan seluruh tracked file menemukan `0` credential leak.
- Dry-run berhasil dengan `19 planned_copy`, tanpa failure atau conflict; manifest `01M2MGXXYD0ET75X6PFCT0T8XC.json` tidak menulis object target.
- Apply berhasil dengan `19 copied_verified`; manifest `01M2MGZ47P565TKDDQRHNW1GX6.json`. Source lokal tetap tersedia seluruhnya.
- Rerun apply berhasil dengan `19 already_verified`; manifest `01M2MGZRVK74Q4Y0Y6YXJAMHBX.json`, membuktikan idempotensi terhadap bucket nyata.
- Verifikasi manifest terakhir mencatat `19` object, source dan target masing-masing `15.339.002` byte, serta `0` mismatch ukuran/SHA-256. Fingerprint gabungan kedua apply manifest identik.
- Database lokal tetap `180` products dan `19` product images; `0` referenced source hilang. Disk aktif tetap `public`, target tetap `r2`, bucket tetap private, dan production/staging public traffic tidak berubah.
- Focused regression test lulus: `7 passed (40 assertions)`. `composer validate --strict`, credential leak scan, dan `git diff --check` lulus.

### P4-05 R2 staging delivery rehearsal — DONE

Outcome:

- Media R2 dapat dibaca melalui public development URL khusus bucket staging dan jalur write/read/delete sintetis terbukti sebelum disk aktif aplikasi dialihkan.

In scope:

- Public development URL `r2.dev` hanya untuk bucket `qammaris-website-media-staging` sebagai fallback staging sementara.
- Kontrak `R2_URL` untuk URL object publik staging.
- Verifikasi HTTPS, content type, content length, dan sample object yang sudah lolos copy-verify.
- Rehearsal upload object sintetis, read/checksum, lalu cleanup object sintetis yang dibuat oleh rehearsal.
- Bukti rollback dengan mempertahankan `PRODUCT_MEDIA_DISK=public` dan seluruh source lokal.

Out of scope:

- Mengubah production DNS/domain, mengaktifkan bucket Qammaris App, mengganti `PRODUCT_MEDIA_DISK` staging/production, memindahkan database/media source, cleanup 19 object hasil migrasi, atau deployment production.

Dependencies:

- P4-04 selesai dengan 19 object identik pada bucket R2 staging.
- Public development URL Cloudflare R2 tersedia untuk rehearsal non-production.

Risks:

- Mengaktifkan `r2.dev` membuat seluruh object pada bucket dapat dibaca publik. Hanya media katalog staging yang boleh berada pada bucket ini.
- `r2.dev` mempunyai rate limit dan tidak menyediakan Cloudflare Cache/WAF sehingga tidak boleh menjadi endpoint production.
- Resolver DNS lokal saat rehearsal mengembalikan IPv4 non-Cloudflare untuk hostname `r2.dev`; verifikasi deterministik dilakukan ke edge Cloudflare dengan TLS/SNI tetap memakai hostname yang benar. Perbaikan DNS client/jaringan tetap diperlukan sebelum browser lokal dapat memakai URL tanpa override.

Acceptance criteria:

- Public development URL HTTPS berstatus aktif hanya pada bucket staging; custom domain production tetap belum dihubungkan.
- Sample referenced image merespons `200` dengan MIME gambar dan ukuran yang cocok dengan manifest.
- Object sintetis dapat ditulis, dibaca ulang dengan checksum identik, diakses melalui delivery URL, lalu dibersihkan tanpa menyentuh 19 object migrasi.
- Database, disk aktif, source lokal, staging application traffic, dan production tidak berubah.
- Credential tidak masuk Git, dokumentasi, URL, atau output terminal.

Verification:

- Cloudflare R2 mengaktifkan public development URL `https://pub-f71b3243d61541f5a14dba6a479ded39.r2.dev` hanya untuk bucket `qammaris-website-media-staging`; bucket Qammaris App tidak diubah.
- GitHub Environment `staging` menyimpan `R2_URL` sebagai non-secret environment variable. Credential tetap berada pada encrypted secrets dan tidak ditampilkan.
- Sample `products/b9A7Hqhl1hzuv47VovEM0QUWRIr0TmbFlL2eSWn1.jpg` merespons `200`, `image/jpeg`, `304.172` byte, dan SHA-256 `74911ab4f1b07d057bfa6c7a864d164ef71abae1d27df6a75229cd903560d1cc`, identik dengan manifest copy-verify.
- Object sintetis `rehearsals/p4-05-01M2MJ2G93ECD6B0M3SAJNA4X0.png` berhasil ditulis dan dibaca melalui S3, diakses melalui HTTPS sebagai `image/png` berukuran `68` byte dengan checksum identik, lalu dihapus. Verifikasi akhir menemukan `0` object rehearsal P4-05 tersisa.
- Database lokal tetap `180` products dan `19` product images; `19` referenced source unik tetap tersedia dan `0` source hilang. Disk aktif tetap `public`, target tetap `r2`.
- Custom domain belum dapat dipasang karena zone `qammarisparfum.id` belum berada pada account Cloudflare/DNS yang sama. Tidak ada perubahan DNS, staging application traffic, deployment, atau production.
- Resolver lokal mengarahkan IPv4 hostname `r2.dev` ke `202.169.44.80` dan timeout. Fetch verifikasi memakai edge Cloudflare `104.18.50.34` dengan hostname/TLS asli dan lulus; ini dicatat sebagai keterbatasan jaringan lokal, bukan kegagalan object R2.

### P4-06 R2 staging application cutover rehearsal — DONE

Outcome:

- Aplikasi staging membaca dan menulis media produk melalui R2 dengan health check dan rollback ke disk `public` yang teruji, tanpa mengubah production.

Dependencies:

- P4-05 selesai.
- DNS client/operator dapat mengakses delivery hostname secara normal atau staging memakai custom domain pada zone Cloudflare yang terkelola.
- Snapshot database/media staging dan recovery ref tersedia sebelum cutover.

In scope:

- Workflow manual khusus staging yang memverifikasi revision sebelum perubahan.
- Snapshot `.env` staging dengan permission tetap privat, cutover sementara `PRODUCT_MEDIA_DISK=r2`, serta rollback otomatis ke konfigurasi awal walaupun rehearsal gagal.
- Verifikasi aplikasi membaca sample existing dan menulis object sintetis melalui `ProductMediaStorage`, boundary yang sama dengan upload admin.
- Cleanup object sintetis dan verifikasi database/media count sebelum serta sesudah rehearsal.

Out of scope:

- Menambah akun admin staging, mengisi database staging dengan data lokal/production, deployment production, perubahan DNS/system-wide resolver, cleanup 19 object R2, atau menghapus source lokal.

Risks:

- `.env` staging berisi secret sehingga backup, fragment, dan output command tidak boleh dapat dibaca publik atau tercetak di log.
- Workflow harus selalu memulihkan `.env` awal dan membersihkan object/file sintetis melalui trap/finally bila salah satu verifikasi gagal.
- Browser operator masih tidak dapat memuat `r2.dev` melalui resolver lokal; validasi delivery dilakukan dari runner/server dan masalah DNS tetap menjadi blocker sebelum R2 dibiarkan aktif permanen.

Acceptance criteria:

- `PRODUCT_MEDIA_DISK=r2` hanya diterapkan pada environment staging setelah preflight dan approval cutover staging.
- Halaman katalog/detail staging memuat sample existing image melalui delivery URL dan upload admin baru tersimpan di R2.
- Rollback ke `public` dipraktikkan tanpa kehilangan metadata maupun file.
- Seluruh source lokal serta 19 object hasil copy tetap dipertahankan; cleanup menjadi pekerjaan terpisah.
- Production dan DNS production tidak berubah.

Verification:

- Command `product-media:rehearse-r2` membatasi eksekusi ke `staging`/`testing`, mewajibkan disk aktif `r2`, memverifikasi sample existing melalui S3 dan public HTTPS, menulis PNG sintetis melalui `ProductMediaStorage`, lalu membersihkannya melalui `finally`.
- Empat regression test command lulus untuk jalur sukses, checksum mismatch, kegagalan public delivery, cleanup, dan penolakan disk non-R2. Seluruh suite lokal lulus: `87 passed (365 assertions)`; `composer validate --strict` dan `git diff --check` lulus.
- Percobaan workflow pertama pada run `35071287366` berhenti di guard permission `.env` sebelum backup/cutover dibuat. Workflow kemudian dinormalkan agar mengunci permission ke `0600`, memberi checkpoint tanpa secret, dan menjalankan rollback cleanup dengan exit status asli tetap dipertahankan.
- hPanel mempublikasikan commit `11aed38f63b762aef8b8987e5fa6317e538a3805` hanya ke `staging.qammarisparfum.id`; auto-deploy tetap nonaktif.
- GitHub Actions `Staging release #4` run `35071927012` sukses dalam 53 detik. Preflight membuktikan disk awal `public`, cutover sementara mengaktifkan `r2`, dan rollback akhir membuktikan disk kembali `public` serta hash `.env` identik.
- Sample existing `products/b9A7Hqhl1hzuv47VovEM0QUWRIr0TmbFlL2eSWn1.jpg` tervalidasi dengan ukuran `304.172` byte, SHA-256 yang diharapkan, HTTPS `200`, dan MIME `image/jpeg`.
- Upload sintetis berukuran `68` byte tervalidasi melalui S3 dan HTTPS `200` dengan MIME `image/png`; `cleanup_verified=true`. Jumlah object produk setelah cleanup tetap `19`.
- Count database staging sebelum/sesudah tetap `0 products : 0 product_images`. Karena P4-06 sengaja tidak menambah akun atau data staging, verifikasi halaman katalog/admin tidak berlaku pada database kosong; command menguji boundary storage yang sama dengan upload admin tanpa membuat data semu.
- Source lokal dan seluruh 19 object R2 dipertahankan. Tidak ada perubahan DNS, bucket Qammaris App, database/media production, maupun deployment production.

## P5 — Admin Panel V2 captured requirements

### P5-01 Preserve catalog working context — DONE

Outcome:

- Admin dapat mengedit produk berulang dari catalog manager tanpa kehilangan page, search, filter brand, dan sort yang sedang digunakan.

In scope:

- Normalisasi query catalog untuk `page`, `search`, `brand_id`, dan `sort` dengan allowlist server-side.
- Sort eksplisit terbaru, nama A–Z, dan nama Z–A pada catalog manager.
- Return context relatif yang tervalidasi pada link edit, breadcrumb, cancel, dan redirect update sukses.
- Fallback ke catalog default untuk context hilang/tidak valid/external dan koreksi page yang melewati hasil terakhir.
- Regression test serta verifikasi browser mobile/desktop pada alur daftar → edit → batal/simpan.

Out of scope:

- Redesign visual menyeluruh catalog manager/product editor, filter status baru, bulk actions, draft/publish workflow, brand/category CRUD, staging, dan production.

Dependencies:

- P3 product domain dan P4 media storage selesai.
- Business rule UX preservasi context telah disetujui owner.

Risks:

- Return URL yang dipercaya mentah dapat menjadi open redirect; hanya path catalog internal dan parameter allowlist yang boleh direkonstruksi server.
- Perubahan nama/brand dapat mengeluarkan produk dari hasil filter; context tetap dipertahankan dan page kedaluwarsa harus dikoreksi ke page terakhir yang valid.

Acceptance criteria:

- Catalog manager mempertahankan query yang tervalidasi selama pagination dan saat membuka editor.
- Breadcrumb dan cancel kembali ke context catalog yang sama.
- Update sukses kembali ke context yang sama dengan pesan sukses; validation error tetap berada pada editor dan mempertahankan return context.
- Context external, path selain catalog, parameter asing, nilai sort ilegal, dan page tidak valid tidak digunakan sebagai tujuan redirect.
- UI tetap dapat digunakan pada viewport `390x844` dan `1440x900` tanpa overflow baru atau console error.
- Focused tests, seluruh test Laravel, Blade compilation, build, quality checks, dan CI lulus.

Implementation notes:

- Controller merekonstruksi context catalog hanya dari `page`, `search`, `brand_id`, dan `sort`; nilai brand harus ada, sort dibatasi allowlist, dan return path harus tepat menuju `/admin/products` tanpa scheme, host, credential, port, atau fragment.
- Link edit membawa return context tersebut ke editor. Breadcrumb, cancel, hidden form field, dan redirect update sukses memakai hasil normalisasi yang sama.
- Pagination menggunakan context hasil normalisasi dan page yang melewati hasil terakhir diarahkan ke page terakhir yang masih valid.
- Catalog manager sekarang mempunyai sort eksplisit `Terbaru`, `Nama A–Z`, dan `Nama Z–A` serta label aksesibel untuk search/filter/sort.

Verification:

- Focused regression test lulus: `5 passed (27 assertions)`; regression admin/catalog terkait lulus: `25 passed (102 assertions)`; seluruh test Laravel lulus: `92 passed (392 assertions)`.
- Composer validation, Blade compilation/cache, scoped Laravel Pint, `git diff --check`, dan Vite production build lulus. Warning existing DaisyUI `@property` dan chunk `about-lanyard` besar tidak diperluas oleh item ini.
- Browser nyata memverifikasi catalog admin responsive pada surface mobile dan desktop: sort `Nama A–Z`, pagination page 2, link edit, cancel, serta update sukses seluruhnya kembali ke `/admin/products?page=2&sort=name_asc`; tidak ditemukan console error.
- Akun admin audit lokal sementara dibuat hanya untuk browser desktop dan telah dihapus setelah verifikasi. Submit browser memakai nilai produk yang sama; tidak ada perubahan nilai bisnis produk, schema, media, staging, atau production.
- Commit implementasi `1e1ddd8` lulus GitHub Actions CI run `35073801793` dalam 25 detik.

### P5-02 Manual availability control and truthful admin status — DONE

Outcome:

- Admin dapat menandai produk sebagai belum dikonfirmasi, tersedia, atau sold out secara eksplisit tanpa menyamakan snapshot quantity dengan status live.

In scope:

- Filter availability pada catalog manager dengan semantics effective availability dan context yang tetap terbawa selama pagination/edit.
- Tampilan availability yang jujur pada daftar admin, termasuk waktu pengecekan dan perlakuan available yang melewati freshness window 36 jam sebagai unknown.
- Kontrol availability manual pada product editor; perubahan status atau konfirmasi ulang menyimpan source `manual` dan checked time baru tanpa mengubah publication.
- Perbaikan layout baris katalog pada mobile agar identitas, harga, availability, edit, dan archive/restore dapat diakses tanpa horizontal overflow.
- Regression test dan verifikasi browser untuk populated, filtered, empty, validation, success, stale, mobile, dan desktop states.

Out of scope:

- Perubahan availability pada public catalog, sinkronisasi Majoo/Shopee/AI, bulk availability update, publication/draft workflow, quantity reconciliation, audit log, staging, dan production.

Dependencies:

- P3 availability domain foundation dan P5-01 catalog working context selesai.

Risks:

- Menurunkan status dari variant stock dapat memberi klaim sold out/available yang salah; UI dan query harus memakai availability metadata.
- Edit biasa tidak boleh memperpanjang freshness status available kecuali status berubah atau admin memilih konfirmasi ulang.
- Availability tidak boleh mengubah publication/archive state.

Acceptance criteria:

- Snapshot quantity `0` dengan availability unknown tidak ditampilkan sebagai sold out.
- Filter available hanya memuat konfirmasi available yang masih fresh; available stale masuk filter unknown; sold out tetap sold out tanpa expiry.
- Status manual yang berubah atau dikonfirmasi ulang menyimpan `availability_source=manual` dan `availability_checked_at`, tetapi edit biasa mempertahankan timestamp lama.
- Context filter availability dipertahankan pada pagination, edit, cancel, validation error, dan update sukses.
- Catalog manager mobile tidak mempunyai horizontal overflow untuk operasi utama dan desktop tetap mempertahankan density tabel.
- Focused tests, seluruh test Laravel, Blade compilation, build, quality checks, browser mobile/desktop, dan CI lulus.

Implementation notes:

- Catalog manager sekarang memakai effective availability sebagai sumber tampilan dan filter: `available` hanya berlaku selama konfirmasi masih fresh 36 jam, `sold_out` tidak kedaluwarsa, dan status yang belum/stale ditampilkan sebagai belum dikonfirmasi.
- Product editor menyediakan kontrol manual `unknown`, `available`, dan `sold_out`, serta opsi konfirmasi ulang. Perubahan availability mencatat source `manual` dan waktu pengecekan tanpa mengubah publication/archive state; edit produk biasa mempertahankan timestamp sebelumnya.
- Angka snapshot variant stock tidak lagi dipakai sebagai klaim availability pada daftar admin. Produk dengan quantity `0` dan availability unknown tetap ditampilkan sebagai belum dikonfirmasi.
- Toolbar filter dan baris produk dirapikan responsif. Operasi utama mobile—identitas, harga, availability, edit, dan archive/restore—dapat diakses tanpa horizontal overflow, sedangkan desktop mempertahankan tabel padat.

Verification:

- Focused availability dan catalog-context tests: 10 passed, 69 assertions.
- Seluruh Laravel test suite: 97 passed, 434 assertions.
- Laravel Pint untuk controller, request, dan test baru lulus; Blade view cache, production Vite build, Composer validation, dan `git diff --check` lulus. Build hanya melaporkan warning existing DaisyUI `@property` dan chunk `about-lanyard` yang besar.
- Browser lokal diverifikasi pada permukaan mobile compact dan desktop wide untuk populated, filter available/sold out, empty state, edit, success redirect, context preservation, serta layout tanpa horizontal overflow. Validasi nilai ilegal diverifikasi melalui feature test. Console browser bersih.
- Produk dan akun audit lokal sementara telah dihapus setelah verifikasi. Tidak ada schema, media, staging, atau production yang diubah.
- Commit implementasi `b2b1764` lulus GitHub Actions CI run `35075494855` dalam 26 detik.

### P5-03 Draft/publish workflow and publication readiness — DONE

Outcome:

- Admin dapat menyimpan produk parsial sebagai draft, melihat alasan produk belum siap tayang, dan mem-publish hanya setelah seluruh syarat katalog terpenuhi.

In scope:

- Operasi publication readiness bersama yang memeriksa brand, nama/slug, kategori, deskripsi, gender, tepat satu offer aktif dengan ukuran/harga valid, dan tepat satu primary image.
- Product create dapat menyimpan draft hanya dengan nama kerja atau langsung publish jika lengkap.
- Product editor untuk draft/archived menampilkan alasan belum siap, menyimpan perubahan parsial, dan menyediakan publish action yang dilindungi gate; published existing tetap dapat diedit tanpa status berubah otomatis.
- Filter dan status publication pada catalog manager dengan context yang tetap terbawa selama pagination/edit/update.
- Restore archived memakai publish gate sehingga produk tidak kembali tayang dalam keadaan belum lengkap.
- Regression test dan browser verification untuk draft minimal, publish ditolak, publish sukses, filtered/empty state, context preservation, mobile, dan desktop.

Out of scope:

- Mengubah status 180 produk legacy secara massal, reconciliation offer/harga/gambar existing, bulk publish, approval workflow multi-user, audit log, media delete/reorder, public catalog redesign, staging, dan production.

Dependencies:

- P3 publication/draft foundation, P4 primary-image lifecycle, P5-01 catalog context, dan P5-02 responsive catalog manager selesai.

Risks:

- Data lokal legacy berisi produk published yang belum memenuhi kontrak baru; item ini tidak boleh otomatis unpublish atau memblokir edit korektif pada produk tersebut.
- Draft tidak boleh menjadi public hanya karena availability berubah atau form biasa disimpan.
- Publish dan restore harus atomik terhadap perubahan produk, offer, serta image baru; kegagalan tidak boleh meninggalkan state atau file parsial.

Acceptance criteria:

- Draft baru dapat disimpan hanya dengan nama kerja, berstatus `draft`, `is_active=false`, tidak mempunyai offer/gambar buatan, dan tidak dapat diakses publik.
- Publish baru atau draft/archived existing ditolak dengan alasan spesifik bila field wajib, offer, atau primary image belum lengkap; state sebelumnya tetap aman.
- Produk lengkap dapat dipublish dengan `published_at`, `publication_status=published`, dan `is_active=true` tanpa mengubah availability.
- Published existing yang diedit tetap published dan tidak dipaksa turun status karena kekurangan legacy; field form yang sebelumnya wajib tetap dijaga pada jalur ini.
- Catalog manager dapat memfilter draft/published/archived, menampilkan status publication yang jujur, dan mempertahankan filter pada edit/cancel/update.
- UI create/edit menjelaskan perbedaan simpan draft dan publish, menampilkan readiness reasons, serta tetap usable tanpa overflow pada mobile/desktop.
- Focused tests, seluruh test Laravel, Blade compilation, build, quality checks, browser mobile/desktop, dan CI lulus.

Completion evidence:

- Publication readiness dipusatkan pada action domain yang memeriksa identitas produk, brand aktif, kategori, deskripsi, gender, tepat satu offer aktif, serta tepat satu primary image dengan file storage yang benar-benar tersedia.
- Create mendukung draft minimal hanya dengan nama kerja dan direct publish tetap kompatibel untuk client lama, tetapi dilindungi validasi lengkap. Edit draft/archived dapat disimpan parsial atau dipublish secara eksplisit; edit produk legacy published tidak otomatis menurunkan statusnya.
- Restore archived memakai publish gate yang sama. Kegagalan publish/restore menjaga state sebelumnya dan menampilkan alasan spesifik dalam bahasa Indonesia.
- Catalog manager mempunyai filter Draft/Tayang/Diarsipkan, status publication yang jujur, empty state, dan context `publication` yang tetap terbawa pada edit, cancel, serta update.
- Focused regression suite lulus, diikuti seluruh suite Laravel: 104 test dan 487 assertion. Laravel Pint, Blade view cache, Composer validation, production Vite build, dan `git diff --check` lulus. Build hanya melaporkan warning existing DaisyUI `@property` dan chunk `about-lanyard` yang besar.
- Browser lokal diverifikasi pada viewport mobile compact dan desktop wide untuk draft minimal, publish ditolak, readiness reasons, populated/empty filter, serta context-preserving update. Tidak ada horizontal overflow dan console browser bersih.
- Produk draft dan akun admin audit lokal sementara telah dihapus setelah verifikasi. Tidak ada data bisnis existing, schema, media existing, staging, atau production yang diubah.

### P5-04 Brand and category management — DONE

Outcome:

- Admin dapat mengelola brand dan kategori tanpa phpMyAdmin, sambil menjaga relasi produk dan URL existing tetap utuh.

In scope:

- Halaman admin terpisah untuk daftar, pencarian, membuat, dan mengedit brand serta kategori.
- Status aktif/nonaktif yang reversible untuk kedua taxonomy; hard delete tidak disediakan.
- Jumlah produk per taxonomy dan status aktif ditampilkan agar dampak perubahan terlihat sebelum admin bertindak.
- Migration additive `categories.is_active` dengan default aktif sehingga seluruh kategori existing tetap tersedia.
- Slug brand/kategori tidak berubah saat nama diedit untuk mencegah identifier dan URL lama berubah tanpa sengaja.
- Pilihan taxonomy pada product editor hanya menawarkan data aktif, tetapi tetap mempertahankan pilihan current yang sudah nonaktif untuk edit korektif.
- Publish/restore produk menolak brand atau kategori nonaktif; filter kategori publik dan footer hanya menampilkan kategori aktif.
- Navigasi admin desktop/mobile menuju Brands dan Categories, beserta state populated, search, empty, validation, success, dan responsive.

Out of scope:

- Hard delete, merge, reassign massal, hierarchy kategori, bulk taxonomy import, logo brand upload, audit log, redesign public catalog, staging, dan production.

Dependencies:

- P3 product domain, P5-01 catalog context, dan P5-03 publication readiness selesai.
- Business rule taxonomy nonaktif tidak boleh menghapus produk atau memutus URL telah disetujui owner.

Risks:

- Menonaktifkan taxonomy yang masih dipakai dapat menghilangkannya dari pilihan baru dan filter publik; relasi produk existing harus tetap tersimpan dan produk published legacy tidak boleh otomatis turun status.
- Regenerasi slug saat rename dapat memutus identifier existing; slug harus stabil setelah record dibuat.
- Migration category harus additive dengan default aktif agar data existing tidak berubah makna.

Acceptance criteria:

- Admin dapat mencari, membuat, mengedit, mengaktifkan, dan menonaktifkan brand/kategori dari UI tanpa route delete.
- Nama wajib unik, deskripsi opsional, slug unik dibuat saat create dan tidak berubah saat rename.
- Menonaktifkan taxonomy tidak menghapus atau memindahkan produk; jumlah relasi tetap sama dan operasi dapat dibalik.
- Semua kategori existing menjadi aktif setelah migration dan ID/slug existing tidak berubah.
- Create product hanya menampilkan taxonomy aktif; editor mempertahankan current taxonomy nonaktif, sedangkan publish/restore menolaknya dengan alasan spesifik.
- Filter kategori publik dan footer tidak menampilkan kategori nonaktif tanpa menghapus produk atau URL produk.
- UI usable pada viewport mobile compact dan desktop wide tanpa horizontal overflow atau console error.
- Focused tests, seluruh test Laravel, migration round-trip, Blade compilation, production build, quality checks, browser verification, dan CI lulus.

Verification:

- Admin mempunyai halaman Brand dan Kategori terpisah untuk search, create, edit, status aktif/nonaktif, jumlah produk, populated state, dan empty state. Route hard-delete tidak tersedia.
- Migration category diuji `up → down → up` pada database lokal: kelima ID/slug kategori tetap identik, jumlah tetap `5`, dan setelah re-apply seluruh `5` kategori berstatus aktif.
- Product create hanya memuat taxonomy aktif; editor mempertahankan current taxonomy nonaktif untuk koreksi, sedangkan publish/restore memakai gate brand/kategori aktif. Filter kategori publik dan footer hanya memuat kategori aktif.
- Slug brand/kategori stabil saat nama diedit. Browser audit membuktikan perubahan nama audit tidak mengubah slug.
- Focused regression tests lulus: taxonomy `8 test / 50 assertion` dan publication `7 test / 53 assertion`. Seluruh suite Laravel lulus: `112 test / 537 assertion`.
- Laravel Pint, Blade view cache, Composer validation strict, `git diff --check`, dan Vite production build lulus. Warning existing DaisyUI `@property` dan chunk `about-lanyard` sekitar 3,28 MB tidak diperluas oleh item ini.
- Browser lokal diverifikasi pada viewport `390x844` dan desktop `1536 px`: navigasi mobile, create, duplicate validation berbahasa Indonesia, edit, slug stability, search, empty state, activate/deactivate, product taxonomy options, dan success states bekerja tanpa horizontal overflow atau console warning/error.
- Akun admin serta brand/kategori audit sintetis telah dihapus setelah verifikasi. Tidak ada product/media bisnis, staging, production, atau bucket yang diubah.

Documentation updates:

- Backlog diperbarui dengan scope, acceptance criteria, implementasi, dan bukti verifikasi. Business rules existing sudah mencakup larangan cascade delete, preservasi URL, serta status nonaktif reversible sehingga tidak memerlukan duplikasi aturan.

### P5-05 Product media management UX — DONE

Outcome:

- Admin dapat memahami dan mengelola maksimal tiga foto produk—primary, urutan, penambahan, dan pengeluaran dari galeri—tanpa membuat primary ganda atau kehilangan file secara tidak dapat dipulihkan.

In scope:

- Panel media product editor berbahasa Indonesia dengan jumlah slot, label foto utama/tambahan, posisi urutan, dan state kosong/penuh.
- Aksi scoped untuk menjadikan foto existing sebagai primary.
- Aksi Naik/Turun untuk mengubah urutan foto tanpa drag-and-drop atau dependency baru.
- Pengeluaran foto dari galeri sebagai soft archive metadata; file storage dipertahankan untuk recovery dan cleanup fisik tetap pekerjaan terpisah.
- Primary yang diarsipkan otomatis diganti foto aktif berikutnya; foto terakhir produk published tidak dapat diarsipkan.
- Upload baru tetap memakai `AttachProductImage`, mematuhi maksimum tiga foto aktif, dan menunjukkan sisa slot serta validation error dengan jelas.
- Return context product catalog dipertahankan setelah aksi media.
- Regression test dan browser verification untuk populated, empty, full, primary, reorder, archive-blocked, success/error, mobile, dan desktop states.

Out of scope:

- Hard delete file, restore UI untuk arsip foto, crop/editor gambar, remote URL acquisition, bulk media, drag-and-drop, migrasi/cutover R2 baru, staging, dan production.

Dependencies:

- P4-01 storage boundary, P4-02 attach/primary lifecycle, dan P5-03 publication readiness selesai.

Risks:

- Database dan object storage tidak mempunyai transaksi bersama; soft archive mempertahankan file agar kegagalan database tidak menghasilkan metadata aktif yang menunjuk file hilang.
- Mengarsipkan primary dapat membuat produk tanpa cover; produk published harus selalu mempunyai primary aktif, sedangkan draft/archived boleh kembali incomplete.
- Aksi media wajib menolak image ID milik produk lain dan perubahan urutan parsial/duplikat.

Acceptance criteria:

- Editor menampilkan maksimal tiga foto aktif dalam urutan deterministic, tepat satu label primary bila koleksi tidak kosong, dan slot upload yang tersisa.
- Admin dapat memilih primary existing secara idempotent tanpa mengubah ID, path, atau urutan.
- Naik/Turun hanya menukar posisi valid dan menjaga urutan aktif menjadi `0..n-1`.
- Arsip foto tidak menghapus file; record soft-deleted tetap menyimpan product ID/path dan tidak dihitung dalam batas tiga foto aktif.
- Arsip primary memilih primary aktif berikutnya atomik. Arsip foto terakhir produk published ditolak tanpa mengubah metadata/file.
- Cross-product image ID ditolak pada primary, move, dan archive.
- Editor mempertahankan catalog return context serta usable pada `390x844` dan `1440x900` tanpa overflow atau console error.
- Focused tests, seluruh test Laravel, migration round-trip, Blade compilation, production build, quality checks, dan CI lulus.

Verification:

- Migration additive `deleted_at` berhasil dijalankan pada 19 metadata existing tanpa mengubah ID, path, primary, urutan, maupun file. Round-trip `up → down → up` mempertahankan 19 record dengan checksum identik `adfcfbf74a3ed7cc5b6cc6c42f0d4cca8e43b8e04a5abcb57f88a9e53d7808e6` dan nol record terarsip.
- Focused regression lulus: `23 test / 105 assertion`. Seluruh suite Laravel lulus: `121 test / 585 assertion`.
- Laravel Pint untuk file P5-05, Blade view cache, Composer validation strict, `git diff --check`, dan Vite production build lulus. Warning existing DaisyUI `@property` dan chunk `about-lanyard` sekitar 3,28 MB tidak diperluas oleh item ini.
- Browser lokal diverifikasi pada viewport `390x844` dan `1440x900`: gambar termuat dari URL lokal yang benar, tidak ada horizontal overflow, state penuh `3/3`, label primary/tambahan, tombol disabled pada batas urutan, perubahan primary, reorder, toast sukses, serta preservasi `return_to?page=2` bekerja tanpa console warning/error.
- Produk dan metadata media sintetis untuk browser audit sudah dihapus setelah verifikasi. File existing hanya dibaca dan tetap tersedia; jumlah produk bisnis kembali 180 dan metadata media aktif kembali 19.
- Tidak ada hard delete file, dependency baru, staging, production, bucket, DNS, maupun project lain yang diubah.

Implementation notes:

- `ProductImage` memakai `SoftDeletes`; query/relationship normal hanya menampilkan media aktif dan batas tiga foto juga hanya menghitung media aktif.
- `ArchiveProductImage` mempertahankan file, mempromosikan primary berikutnya secara atomik, menormalisasi urutan, dan menolak arsip foto terakhir produk published.
- `MoveProductImage` hanya menerima arah Naik/Turun, mengunci koleksi per product, menukar tetangga valid, dan menormalisasi urutan `0..n-1`.
- Endpoint media baru selalu memverifikasi kepemilikan image terhadap product dan kembali ke editor dengan catalog return context yang sama.
- Editor produk memakai panel berbahasa Indonesia untuk empty/full/primary/order/archive states; form aksi dipisahkan dari form edit utama agar markup tetap valid.
- `.env` lokal diarahkan ke `http://127.0.0.1:8011` agar URL media sesuai dengan port dev server; perubahan lokal ini tidak dilacak Git.

Documentation updates:

- Backlog, business rules media, dan `ADR-010-product-media-archive-and-ordering.md` diperbarui.
- Kandidat pekerjaan berikutnya adalah mendefinisikan `P6-01` kontrak file bulk import/export dan preview; belum dimulai pada item ini.

## P6 — Import/export dan audit

### P6-01 Canonical product CSV contract and read-only preview — DONE

Outcome:

- Admin dapat mengunduh kontrak CSV Qammaris, mengunggah hasil kurasi Claude, dan melihat preview deterministik per baris tanpa satu pun perubahan database atau media.

In scope:

- Kontrak canonical UTF-8 CSV dengan header tetap untuk provider, kode produk, nama, deskripsi, harga, brand, gender, snapshot stok, best seller, kategori, ukuran, fragrance notes, dan maksimal tiga URL gambar sumber.
- Download template CSV dari admin dengan satu baris contoh yang jelas dan aman dibuka di spreadsheet umum.
- Upload `.csv` maksimum 5 MB dan maksimum 1.000 baris data; file kosong, encoding selain UTF-8, header berubah/duplikat, jumlah kolom tidak konsisten, dan baris kosong ditangani eksplisit.
- Parser bounded tanpa dependency baru, fingerprint SHA-256, dan normalisasi nilai yang tidak mengarang data.
- Preview read-only berbahasa Indonesia dengan summary valid/perlu review/error, nomor baris sumber, action candidate create/update/conflict, serta issue spesifik.
- Pencocokan update hanya melalui `provider + kode_produk` pada `product_external_identities`; nama/brand mirip tidak pernah dipakai sebagai auto-match.
- Lookup brand/kategori exact case-insensitive untuk preview; taxonomy kosong/tidak ditemukan/nonaktif menjadi review, bukan dibuat otomatis.
- URL gambar hanya divalidasi sebagai URL HTTPS sumber dan tidak diunduh pada item ini.
- Navigasi admin serta state initial, validation, structural error, populated preview, dan responsive.

Out of scope:

- Membaca XLSX mentah Shopee, menyimpan batch/import rows, apply/write database, membuat/memperbarui product, mengunduh gambar, conflict resolution UI, riwayat batch, audit mutation, export data existing, queue, staging, dan production.

Dependencies:

- P3 product domain/external identity foundation dan P5 Admin Panel V2 selesai.
- Dataset input adalah hasil kurasi/normalisasi Claude, bukan export Shopee mentah.

Risks:

- Formula injection dan encoding spreadsheet dapat menyamarkan nilai; parser memperlakukan seluruh input sebagai data tidak tepercaya dan output preview tetap escaped.
- Kode produk panjang atau berawalan nol tidak boleh berubah menjadi angka; canonical contract memperlakukannya sebagai teks.
- Preview yang tidak dipersist dapat berubah bila database berubah sebelum apply; karena apply belum tersedia, fingerprint dan hasil preview hanya menjadi evidence untuk tahap berikutnya.
- File besar atau baris rusak dapat menghabiskan memori; parsing dibatasi dan dihentikan dengan error terkontrol.

Acceptance criteria:

- Template memakai header exact dan dapat diunduh tanpa dependency spreadsheet baru.
- Upload valid menghasilkan fingerprint, summary, dan preview maksimal 1.000 baris tanpa menulis product, offer, identity, image, maupun file storage.
- Duplicate `provider + kode_produk` dalam file menjadi error per baris; mapping existing menghasilkan candidate update; identity conflict/ambiguous tidak ditulis.
- Required structural fields `provider`, `kode_produk`, dan `nama_produk` divalidasi; field publikasi yang kosong menghasilkan review karena import masa depan selalu draft.
- Nilai angka, boolean, controlled gender/provider, notes, taxonomy, dan maksimum tiga HTTPS image URL dinormalisasi atau diberi issue spesifik.
- Header hilang/lebih/duplikat, CSV invalid, non-UTF-8, lebih dari 1.000 baris, dan file di atas 5 MB ditolak secara aman.
- Halaman usable pada `390x844` dan `1440x900`, tanpa horizontal page overflow atau console error; tabel preview memakai container scroll horizontal terkontrol.
- Focused tests, seluruh test Laravel, Blade compilation, production build, quality checks, dan CI lulus.

Verification:

- Focused test `AdminProductImportPreviewTest`: 10 test / 73 assertion lulus, termasuk auth admin, template BOM UTF-8, create/update candidate, duplicate identity, taxonomy case-insensitive tanpa auto-create, missing publish data, escaping input tidak tepercaya, structural error, non-UTF-8, batas 1.000 baris, dan batas 5 MB.
- Seluruh suite Laravel: 131 test / 658 assertion lulus.
- `artisan route:list --name=admin.product-imports` mengonfirmasi tiga route production: halaman, template, dan preview.
- `artisan view:cache`, `composer validate --strict`, targeted Pint, dan `npm run build` lulus. Build hanya mempertahankan warning existing DaisyUI `@property` dan chunk 3D besar yang tidak berasal dari item ini.
- Browser audit initial dan populated preview lulus pada `1440x900` serta `390x844`: summary 3 baris menampilkan 1 valid, 1 perlu review, 1 error; page width sama dengan viewport; tabel kontrak/preview memakai scroll horizontal internal terkontrol; console tanpa warning/error.
- State populated dirender melalui parser dan view production menggunakan route audit lokal sementara karena file chooser browser automation menolak path lokal; route, CSV, akun admin audit, dan seluruh salinan file sementara telah dihapus setelah verifikasi. Multipart upload tetap diverifikasi oleh feature test production route.
- Jumlah data lokal sebelum dan sesudah preview identik: 180 products, 65 offers, 0 external identities, dan 19 product images. Tidak ada product, offer, identity, media, atau uploaded CSV yang disimpan.

Documentation updates:

- Backlog, business rules import, `docs/product/PRODUCT_IMPORT_CSV.md`, dan `ADR-011-canonical-product-csv-preview-boundary.md` diperbarui.
- Kandidat item berikutnya adalah `P6-02` untuk persistence batch, immutable preview result, idempotency, conflict handling, dan apply draft yang eksplisit; belum dimulai pada item ini.

### P6-02 Persistent immutable preview batches — DONE

Outcome:

- Setiap preview CSV yang berhasil diparsing mempunyai batch audit immutable dan dapat dibuka ulang tanpa menyimpan file sumber atau mengubah katalog.

In scope:

- Batch ID, actor, nama/ukuran/fingerprint file, versi kontrak, fingerprint state katalog, summary, baris normalisasi, candidate action, matched product, issue, dan timestamp.
- Idempotency key untuk actor + file + state katalog + versi kontrak yang sama.
- Riwayat batch terbaru dan penanda batch pada hasil preview admin.
- Produk, offer, taxonomy, external identity, media, dan file upload tetap tidak berubah.

Out of scope:

- Apply ke product, approval workflow, update produk published, download gambar, rollback mutation, export existing data, queue, staging, dan production.

Acceptance criteria:

- Preview sukses membuat tepat satu batch dan row audit; upload identik pada state katalog identik memakai batch yang sama.
- Perubahan state katalog menghasilkan batch baru walau file sama, sehingga preview lama tidak dapat dianggap current.
- File upload tidak disimpan dan payload preview tersimpan sebagai normalized JSON yang escaped saat dirender.
- Structural failure sebelum preview tidak membuat batch kosong.
- Admin dapat melihat batch ID serta daftar preview terbaru; non-admin tetap ditolak.
- Focused/full tests, migration round-trip, Pint, Blade, build, browser desktop/mobile, dan CI lulus.

Verification:

- Migration `2026_09_16_140000_create_product_import_batches_table` berhasil diterapkan pada database lokal dan tercatat batch 8; tabel katalog tidak diubah.
- Focused `AdminProductImportPreviewTest`: 10 test / 90 assertion lulus, termasuk persistence batch/row, payload normalized, idempotency pada file+actor+state identik, batch baru setelah state katalog berubah, dan structural failure tanpa batch kosong.
- Seluruh suite Laravel: 131 test / 675 assertion lulus. Targeted Pint, Blade compilation, Composer strict validation, `git diff --check`, dan production asset build lulus; warning existing DaisyUI/chunk 3D tetap tidak berasal dari item ini.
- Browser audit initial, populated batch, reload idempotent, dan recent history lulus pada `1440x900` serta `390x844`; page tidak overflow horizontal, ketiga tabel memakai container scroll internal, dan console tanpa warning/error.
- Browser evidence menampilkan batch #1 dengan 3 row (1 valid, 1 review, 1 error), dua fingerprint, actor, filename, size, dan timestamp. Reload tetap menampilkan satu row riwayat untuk batch yang sama.
- Data katalog lokal sebelum/sesudah identik: 180 products, 65 offers, 0 external identities, 19 images. Batch/row sintetis, route audit, dan CSV sementara sudah dihapus; tabel audit kembali 0 batch / 0 row.

Documentation updates:

- Business rules, kontrak CSV, backlog, dan `ADR-012-persistent-immutable-import-preview-batches.md` diperbarui.
- Kandidat berikutnya adalah `P6-03` explicit draft apply dengan revalidation state/payload, transaction, row outcome, dan aturan ketat untuk produk existing published; belum dimulai pada item ini.

### P6-03 Explicit transactional draft apply — DONE

Outcome:

- Admin dapat menerapkan batch preview yang masih current menjadi draft baru atau pembaruan draft existing dengan jejak audit per baris dan tanpa mengubah produk published/archived.

In scope:

- Apply eksplisit dari batch persisted dengan konfirmasi manusia, revalidasi fingerprint state katalog, versi kontrak, serta hash payload setiap baris.
- Satu transaksi untuk seluruh batch; pengulangan request pada batch yang sudah applied tidak menjalankan mutation kedua kali.
- Baris create membuat product draft/nonaktif, external identity, dan single offer bila harga+ukuran lengkap.
- Baris update hanya boleh mengubah product existing berstatus draft dan mempertahankan nilai existing ketika field CSV kosong.
- Baris error/conflict dan mapping ke product published/archived ditahan serta dicatat sebagai outcome, bukan diterapkan.
- Audit batch/row menyimpan actor apply, waktu, jumlah applied/blocked, product hasil, pesan outcome, serta snapshot before/after terkontrol.
- URL gambar tetap hanya kandidat sumber; apply tidak mengunduh atau membuat metadata media.

Out of scope:

- Update product published/archived, conflict resolution per baris, download gambar, taxonomy auto-create, publish massal, undo batch, export katalog, queue, staging, dan production.

Dependencies:

- P6-01 canonical CSV preview dan P6-02 persistent immutable preview batch selesai.

Risks:

- State katalog dapat berubah setelah preview; batch stale harus ditolak sebelum mutation.
- Payload audit dapat rusak atau dimodifikasi; hash mismatch harus menahan seluruh batch.
- Identity/provider yang berubah secara concurrent dapat memicu unique conflict; transaction harus rollback tanpa partial catalog write.

Acceptance criteria:

- New row valid/review membuat draft dan tidak pernah publish otomatis.
- Existing mapped draft dapat diperbarui, sedangkan field kosong mempertahankan nilai existing.
- Existing mapped published/archived, row error, dan conflict dicatat blocked tanpa perubahan katalog.
- Fingerprint stale atau payload hash mismatch menghasilkan nol catalog mutation dan status batch yang jelas.
- Apply kedua pada batch applied bersifat idempotent.
- Focused/full tests, migration round-trip, Pint, Blade, build, browser desktop/mobile, dan CI lulus.

Verification:

- Migration additive `2026_09_16_150000_add_apply_outcomes_to_product_imports` berhasil melalui siklus lokal `up → down → up` tanpa mengubah jumlah katalog.
- Focused import regression lulus: `18 test / 190 assertion`. Seluruh suite Laravel lulus: `139 test / 775 assertion`.
- Targeted Pint, Blade compilation, Composer strict validation, `git diff --check`, dan production asset build lulus. Build hanya mempertahankan warning existing DaisyUI `@property` serta chunk `about-lanyard` sekitar 3,28 MB.
- Browser lokal pada `390x844` dan `1440x900` membuktikan upload multipart nyata, preview 1 valid + 1 error, konfirmasi apply, hasil 1 draft dibuat + 1 error ditahan, history status, internal table scroll, nol page overflow, dan nol console warning/error.
- Produk, offer, identity, batch/row, serta CSV sintetis audit telah dihapus setelah verifikasi. Data lokal kembali 180 products, 65 offers, 0 external identities, 19 images, 0 batches, dan 0 rows.
- Staging, production, media storage, bucket, DNS, serta project lain tidak disentuh.
- GitHub Actions CI untuk commit `64dd18a` lulus pada PHP 8.2/Laravel tests dan Node/Vite build: `https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/35096096685`.

Documentation updates:

- Business rules import, kontrak CSV, backlog, dan `ADR-013-transactional-product-import-draft-apply.md` diperbarui.
- `P6-04` safe acquisition URL gambar import telah dilanjutkan sebagai item berikutnya dan selesai pada commit `43c450f`.

### P6-04 Safe imported image acquisition — DONE

Outcome:

- Admin dapat memindahkan maksimal tiga URL gambar dari batch import yang sudah applied ke storage Qammaris melalui antrean terpisah, dengan validasi isi file, audit per kandidat, serta retry yang tidak menduplikasi media berhasil.

In scope:

- Aksi eksplisit setelah apply untuk menyiapkan satu job per baris produk yang mempunyai URL sumber dan product draft hasil apply.
- Allowlist host HTTPS, larangan redirect, timeout, batas ukuran, pemeriksaan MIME aktual, dimensi, dan checksum sebelum file disimpan.
- Penyimpanan melalui `ProductMediaStorage` dan attachment melalui `AttachProductImage`; URL provider tidak pernah menjadi URL publik.
- Audit status per kandidat gambar, actor/waktu request, hasil product image, error aman, ringkasan batch, dan retry hanya untuk kandidat yang belum berhasil.
- Maksimum tiga gambar aktif, preservasi primary existing, kegagalan gambar tidak membatalkan draft, dan produk yang tidak lagi draft ditahan.
- UI admin Import Produk untuk initial, queued/processing, success, partial failure, no-source, retry, mobile, dan desktop states.

Out of scope:

- Mengubah produk published/archived, overwrite gambar existing, crop/optimasi gambar, fuzzy deduplication lintas produk, hotlink provider, auto-publish, hard delete media, lifecycle cleanup, staging, production, dan deployment queue worker.

Dependencies:

- P4 storage/attachment boundary dan P6-03 explicit transactional draft apply selesai.

Risks:

- URL eksternal merupakan input tidak tepercaya; request hanya boleh menuju host HTTPS yang dikonfigurasi dan redirect harus ditolak agar tidak menjadi jalur SSRF.
- Database dan object storage tidak transactional; file baru wajib dibersihkan bila attachment metadata gagal.
- Job dapat diulang setelah partial success; kandidat berhasil harus dikenali dari audit dan tidak boleh diunduh atau ditautkan dua kali.

Acceptance criteria:

- Hanya batch `applied`, row `created/updated`, dan product yang masih draft dapat masuk antrean.
- Request tidak menunggu seluruh download; satu job bounded dibuat per row dan dispatch ulang tidak menduplikasi kandidat yang sudah stored.
- HTTPS/host, status HTTP, redirect, byte limit, MIME aktual JPEG/PNG/WebP, dimensi, serta checksum divalidasi sebelum storage write.
- Gambar pertama pada draft kosong menjadi primary; media existing tidak diganti dan batas tiga gambar aktif selalu dipatuhi.
- Kegagalan download/validasi/attach dicatat per kandidat dan tidak menghapus draft atau media lain; retry hanya memproses kandidat non-success.
- UI tetap usable pada `390x844` dan `1440x900`, tidak overflow, mempunyai copy status/recovery yang jelas, dan console bersih.
- Focused/full tests, migration round-trip, Pint, Blade, build, quality checks, browser verification, dan CI lulus.

Verification:

- Migration additive `2026_09_16_160000_add_image_acquisition_to_product_import_rows` berhasil melalui siklus lokal `up → down → up`. Count katalog tetap 180 products, 65 offers, 19 images, 0 external identities, 0 import batches, dan 0 import rows setelah data audit dibersihkan.
- Focused regression import/media lulus: 40 test / 291 assertion. Seluruh suite Laravel lulus: 147 test / 814 assertion.
- Targeted Pint, lima route import, Blade clear/cache, Composer strict validation, `git diff --check`, dan Vite production build lulus. Build hanya mempertahankan warning existing DaisyUI `@property` serta chunk `about-lanyard` sekitar 3,28 MB.
- Browser end-to-end memakai upload CSV nyata, apply dua draft, lalu akuisisi satu PNG valid dan penolakan satu host di luar allowlist. UI menampilkan 1 tersimpan + 1 gagal/ditahan; retry tidak menambah gambar sukses kedua.
- Audit mobile `390x844` dan desktop `1440x900` lulus: document width sama dengan viewport, tabel memakai scroll horizontal internal, tombol utama setinggi 44 px, state recovery jelas, dan console tanpa warning/error.
- Dua product, satu image/object, dua identity, satu batch, dua row, CSV, serta allowlist audit sementara telah dihapus secara presisi. Browser dikembalikan ke halaman import bersih dan data lokal kembali ke baseline.
- Staging, production, DNS, bucket production, dan project lain tidak disentuh. Worker deployed belum dipasang sesuai out-of-scope dan dicatat pada runbook.
- Commit implementasi `43c450f` lulus GitHub Actions CI: `https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/35101961541`.

Documentation updates:

- Business rules media/import, kontrak CSV, master plan, `ADR-014-safe-imported-image-acquisition.md`, dan `PRODUCT_IMPORT_IMAGE_QUEUE.md` telah diperbarui.
- Kandidat berikutnya adalah `P6-05` conflict resolution manual untuk mapping product published/archived dan baris ambigu; belum dimulai.

### P6-05 Manual protected-product import resolution — DONE

Outcome:

- Admin dapat mereview baris import yang ditahan karena mapping mengarah ke produk published/archived, memilih field yang benar-benar disetujui, lalu menerapkannya tanpa mengubah status publikasi atau availability produk.

In scope:

- Panel resolusi per baris setelah batch applied, berisi perbandingan nilai katalog saat ini dan kandidat import.
- Pilihan field eksplisit untuk nama, deskripsi, brand, kategori, gender, terlaris, stok, fragrance notes, serta pasangan harga/ukuran.
- Konfirmasi manusia, audit actor/waktu/field/snapshot sebelum-sesudah, one-time idempotency, dan optimistic stale guard.
- Published/archived tetap published/archived; slug, availability status, identity, media, dan field kosong tidak ditimpa.
- Error/conflict struktural diberi recovery copy yang jelas: perbaiki CSV dan buat preview baru, tanpa override berisiko.

Out of scope:

- Auto-resolve konflik, fuzzy matching, mengubah identity/provider, auto-create taxonomy, auto-publish, mengubah availability status, akuisisi gambar protected row, undo, staging, production, dan deployment.

Dependencies:

- P6-03 transactional apply dan P6-04 safe image acquisition selesai.

Risks:

- Produk dapat berubah setelah apply; resolusi harus ditolak bila snapshot katalog tidak lagi sama.
- Update parsial terhadap produk published/archived berisiko mengubah halaman publik; hanya field terpilih yang boleh dimutasi dan status publikasi wajib dipertahankan.
- Harga dan ukuran adalah satu offer; keduanya harus diterapkan sebagai satu pilihan atomik.

Acceptance criteria:

- Hanya row `blocked_protected` dari batch applied yang dapat diresolusi dan harus berasal dari batch pada URL.
- Admin wajib memilih minimal satu field dan memberi konfirmasi eksplisit.
- Nilai kosong/taxonomy tidak aktif tidak dapat menimpa katalog; harga+ukuran diterapkan bersama.
- Resolusi kedua tidak melakukan mutation ulang, dan perubahan katalog sejak snapshot menahan resolusi dengan pesan recovery.
- Actor, waktu, selected fields, pesan, dan snapshot before/after tersimpan; UI menunjukkan state unresolved/resolved.
- UI usable pada `390x844` dan `1440x900`, keyboard/touch jelas, tidak overflow, dan console bersih.
- Focused/full tests, migration round-trip, Pint, Blade, build, quality checks, browser verification, dan CI lulus.

Verification:

- Migration additive `2026_09_16_170000_add_manual_resolution_to_product_import_rows` lulus siklus lokal `up → down → up` tanpa mengubah baseline katalog.
- Focused regression import lulus: 29 test / 263 assertion. Seluruh suite Laravel lulus: 150 test / 848 assertion.
- Pint dirty, Blade clear/cache, `git diff --check`, dan Vite production build lulus. Build hanya mempertahankan warning existing DaisyUI `@property` serta chunk `about-lanyard` sekitar 3,28 MB.
- Browser end-to-end pada row published membuktikan perbandingan field, pemilihan nama + offer, konfirmasi, state resolved, actor/waktu, dan status publikasi/availability yang tetap terlindungi.
- Audit mobile `390x844` dan desktop `1440x900` lulus: document width sama dengan viewport, layout card terbaca, kontrol minimal 44 px, state success jelas, dan console tanpa warning/error.
- Product, offer, identity, batch, dan row sintetis audit dibersihkan presisi. Data lokal kembali ke 180 products, 65 offers, 19 images, 0 identities, 0 batches, dan 0 rows.
- Staging, production, DNS, bucket, dan project lain tidak disentuh.
- GitHub Actions CI untuk commit `8e4293b` lulus pada PHP 8.2/Laravel tests dan Node/Vite build: `https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/35105693142`.

Documentation updates:

- Business rules, kontrak CSV, backlog, dan `ADR-015-manual-protected-import-resolution.md` diperbarui.
- Kandidat berikutnya adalah P6-06 batch report/export untuk kebutuhan audit operasional; belum dimulai.

### P6-06 Import batch audit report and CSV export — DONE

Outcome:

- Admin dapat membuka jejak batch import dan mengunduh laporan CSV per baris untuk review/arsip operasional tanpa mengubah katalog atau menyimpan ulang file sumber.

In scope:

- Export CSV UTF-8 BOM per batch dari audit persisted, satu baris laporan untuk setiap import row.
- Kolom laporan mencakup identitas batch/baris, kandidat, outcome apply, product hasil, resolusi manual, outcome gambar agregat, issue, actor, dan timestamp.
- Sanitasi formula injection untuk semua cell; deskripsi panjang, snapshot internal, URL sumber gambar, dan data rahasia tidak diexport.
- Tombol download pada detail batch dan riwayat batch dengan copy/status yang jelas pada mobile dan desktop.
- Admin authorization, safe filename, response no-store, serta regression test isi dan boundary export.

Out of scope:

- Export katalog produk, XLSX/PDF, mengunduh file sumber asli, menyertakan before/after snapshot penuh, retention/hard delete audit, background export, email/share, staging, production, dan deployment.

Dependencies:

- P6-02 persisted preview, P6-03 apply outcomes, P6-04 image outcomes, dan P6-05 manual resolution audit selesai.

Risks:

- Nilai import adalah input tidak tepercaya dan dapat memicu formula spreadsheet; setiap cell wajib disanitasi.
- Snapshot/URL mentah dapat memperbesar file atau membocorkan detail yang tidak dibutuhkan; report memakai allowlist kolom operasional.
- Batch hingga 1.000 baris harus distream tanpa membangun file permanen atau mengubah data.

Acceptance criteria:

- Hanya admin terautentikasi yang dapat mengunduh report batch existing; batch tidak ada menghasilkan 404.
- CSV memakai BOM UTF-8, header/version tetap, nama file aman, dan satu row per audit row dalam urutan line number.
- Formula-like values tidak dieksekusi saat dibuka di spreadsheet dan URL gambar/snapshot internal tidak muncul.
- Outcome created/updated/blocked, resolution, image aggregate, issue, actor, serta waktu dapat dibaca tanpa membuka database.
- UI initial/empty/populated tetap usable pada `390x844` dan `1440x900`, download dapat dicapai dengan keyboard/touch, tidak overflow, dan console bersih.
- Focused/full tests, Pint, Blade, build, quality checks, browser download verification, dan CI lulus.

Verification:

- Focused import regression lulus: 32 test / 299 assertion. Seluruh suite Laravel lulus: 153 test / 884 assertion.
- Pint dirty, Blade clear/cache, Composer strict validation, route audit, `git diff --check`, dan Vite production build lulus. Build hanya mempertahankan warning existing DaisyUI `@property` serta chunk `about-lanyard` sekitar 3,28 MB.
- Browser localhost membuktikan detail batch + history menampilkan link report, klik memicu download CSV nyata, dan state populated/empty dapat dibaca.
- Audit mobile `390x844` dan desktop `1440x900` lulus: document width sama dengan viewport, tabel memakai scroll horizontal internal, tombol download setinggi minimum 44 px, dan console tanpa warning/error.
- Product, batch, serta dua row sintetis audit dihapus presisi. Data lokal kembali ke 180 products, 65 offers, 19 images, 0 identities, 0 batches, dan 0 rows.
- Tidak ada migration atau perubahan schema. Staging, production, DNS, bucket, dan project lain tidak disentuh.
- GitHub Actions CI untuk commit `545dc62` lulus pada PHP 8.2/Laravel tests dan Node/Vite build: `https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/35109268873`.

Documentation updates:

- Business rules, kontrak CSV/report, backlog, dan `ADR-016-import-batch-audit-csv-report.md` diperbarui.
- Kandidat berikutnya adalah P6-07 canonical catalog export untuk kebutuhan bulk maintenance; belum dimulai.

### P6-07 Read-only catalog maintenance snapshot — DONE

Outcome:

- Admin dapat mengunduh snapshot CSV seluruh katalog untuk rekonsiliasi dan persiapan bulk maintenance tanpa mengubah data atau membentuk jalur write-back tersembunyi.

In scope:

- Export CSV UTF-8 BOM, satu baris per product dalam urutan ID internal, mencakup draft, published, dan archived.
- Kontrak versioned berisi ID/slug stabil, publication, availability recorded/effective, taxonomy, single offer, stok snapshot, best seller, fragrance notes, external identity agregat, serta ringkasan kelengkapan media.
- Harga dan ukuran dibaca dari offer aktif; identity diurutkan deterministik; media hanya diexport sebagai jumlah aktif dan penanda primary tanpa path/URL.
- Sanitasi formula injection pada semua cell, response streaming `no-store`, filename aman, admin authorization, serta empty-state header-only.
- Tombol download dan copy read-only yang jelas pada halaman Import Produk, diverifikasi mobile dan desktop.

Out of scope:

- Re-import langsung, bulk update/write, XLSX/PDF, export binary/path/URL media, data customer, staging, production, deployment, dan perubahan schema.

Dependencies:

- P3 single-offer authority, P4 media boundary, dan P6-06 safe CSV report selesai.

Risks:

- Snapshot dapat menjadi stale segera setelah download; timestamp per product disertakan dan file dinyatakan read-only.
- Data legacy dapat belum mempunyai offer atau external identity; cell dibiarkan kosong dan tidak ditebak dari nama/SKU.
- Spreadsheet dapat mengeksekusi nilai formula; setiap cell wajib melewati sanitizer yang sama dengan report audit import.

Acceptance criteria:

- Hanya admin terautentikasi dapat mengunduh snapshot; non-admin ditolak.
- Header/version tetap, BOM UTF-8, satu row per product dalam urutan ID, dan database kosong menghasilkan header saja.
- Semua publication status tercakup; harga/ukuran berasal dari offer aktif; external identity teragregasi deterministik.
- Formula-like values aman dan tidak ada image path, provider image URL, secret, atau mutation katalog.
- UI menjelaskan snapshot tidak dapat langsung diimport, usable pada `390x844` dan `1440x900`, tidak overflow, keyboard/touch jelas, dan console bersih.
- Focused/full tests, Pint, Blade, Composer strict, route audit, build, browser download, dan CI lulus.

Verification:

- Focused snapshot/report regression lulus: 7 test / 76 assertion. Seluruh suite Laravel lulus: 157 test / 924 assertion.
- Pint targeted, Blade clear/cache, Composer strict validation, route audit, `git diff --check`, dan Vite production build lulus. Build mempertahankan warning existing DaisyUI `@property` serta chunk `about-lanyard` sekitar 3,28 MB.
- Browser localhost membuktikan tombol snapshot, copy read-only, dan download CSV nyata pada mobile `390x844` serta desktop `1440x900`.
- Kedua viewport mempunyai document width sama dengan viewport, tombol download setinggi 44 px, halaman tetap berada pada route import setelah download, dan console tanpa warning/error.
- Tidak ada migration atau mutation katalog. Database lokal tetap 180 products, 65 offers, 19 images, 0 identities, 0 import batches, dan 0 import rows.
- Staging, production, DNS, bucket, media object, dan project lain tidak disentuh.
- Commit implementasi `1aa6a77` lulus GitHub Actions CI pada PHP 8.2/Laravel tests dan Node/Vite build: `https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/35111807230`.

Documentation updates:

- Business rules, master plan, kontrak `PRODUCT_CATALOG_SNAPSHOT.md`, backlog, dan `ADR-017-read-only-catalog-maintenance-snapshot.md` diperbarui.
- Kandidat berikutnya adalah `P6-08` kontrak preview bulk maintenance berbasis ID internal dengan stale/conflict guard; belum dimulai.

## P7 prerequisite — Qammaris UI quality gate

Sebelum item UI pada P5 atau P7 masuk `IN_PROGRESS`:

- baca `skills/qammaris-ui-review/SKILL.md`;
- tetapkan surface public catalog atau admin panel;
- simpan baseline screenshot pada viewport yang disepakati;
- definisikan primary user task dan state yang harus diuji;
- pastikan perubahan tidak membawa framework, dependency, atau estetika baru tanpa alasan produk.

Baseline performance finding: build 2026-09-14 menghasilkan chunk `about-lanyard` sekitar 3,28 MB sebelum gzip. Ukur dampaknya pada mobile dan lakukan code-splitting/removal hanya pada item performance yang disetujui.

## Template backlog item

```md
### ID — Judul — STATUS

Outcome:

In scope:

Out of scope:

Dependencies:

Risks:

Implementation notes:

Acceptance criteria:

Verification:

Documentation updates:

Final report:
- files changed
- schema/data impact
- tests/checks and results
- screenshots when UI changed
- known limitations
- rollback or forward-fix notes
- suggested next item, without starting it
```

## WIP dan transisi

1. Maksimal satu item utama berstatus `IN_PROGRESS`.
2. Item boleh dipindah ke `IN_PROGRESS` hanya jika scope, dependencies, dan acceptance criteria lengkap.
3. Agent tidak boleh memulai item berikutnya hanya karena item aktif terlihat selesai.
4. Reviewer memindahkan item dari `IN_REVIEW` ke `DONE` setelah bukti verifikasi cukup.
5. Temuan baru dimasukkan ke backlog; jangan memperluas scope diam-diam.
6. Blocker harus menyebut bukti, dampak, dan input/akses yang dibutuhkan.
