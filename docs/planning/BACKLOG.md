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
| P6 | Import/export & audit | DONE | P3, P5 | Bulk workflow aman, idempotent, dan dapat dilacak |
| P7 | Public catalog UX | DONE | P3, sebagian P5 | Mobile catalog dan inquiry flow matang |
| P8 | Restricted API readiness | IN_PROGRESS | P5, P6 | Integrasi availability aplikasi dan operasi machine-access terbatas yang auditable |
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

### P1-04 Production backup dan cutover preflight — IN_PROGRESS

Membutuhkan staging hijau, konkret target/release/data-transfer/recovery plan serta approval owner. Latest Owner direction (2026-10-06) waives legacy data migration and backup for this launch; prior backup gates are superseded. Tidak boleh dimulai dari development lokal.

Initial preflight scope (superseded regarding legacy merge/backup by the Owner override below): Owner requests the next phase on 2026-10-06 after the 350-product staging wave. Active scope is remote-staging-led preflight and a concrete release/data/media/recovery plan. Production server inspection awaits explicit read-only scope because prior SSH authorization was staging-only and ADR-001 deferred production audit. Public production pages may be read. No production deploy, database/environment/account/permission changes, webhook URL switch or backup yet; Owner requests the final backup immediately before approved cutover. Staging catalog must never replace production wholesale. Verify source/asset/schema drift, preserve legacy IDs/slugs/media/users/content, isolate the fixture/private reviews, identify migration blockers, and document exact next approval scope.


Preflight checkpoint 2026-10-06: remote staging source comparison matches candidate85ef088 after line-ending normalization (218/220; remaining staging Basic Auth). Existing CI37421516661 passed that exact source. 350 real launching records/95 retained drafts previewed; all350 have a qammaris_app identity and one offer. All1016 launch JPEGs exist/35,974,303 bytes/350 primary images, checksums recorded; no files copied/backed up. Stage22 migrations, users/blog/store0; never replace production wholesale. Feed401/200,checkpoint457/has_more=false,queues0,minute cron43/44 seconds old at06:20:46UTC. Public legacy catalog displays208; actual production database/schema/IDs/accounts remain Not confirmed pending read-only scope. Coherent build bundle and production ID/slug/price-conflict preview remain gates; final backup/isolated restore/cutover not performed. Runbook `docs/runbooks/PRODUCTION_CUTOVER_PREFLIGHT.md`, evidence `docs/verification/p1-04/`. No application/schema/data/media/env/account/permission/source-app/production changes or new broad suite/build. Continue P1-04; not DONE.

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

### P6-08 Internal-ID bulk maintenance preview — DONE

Outcome:

- Admin dapat mengupload CSV maintenance hasil kurasi dan melihat preview perubahan per produk berbasis ID internal tanpa melakukan mutation katalog.

In scope:

- Kontrak CSV `maintenance-v1` berisi `product_id`, `expected_updated_at`, `expected_row_fingerprint`, dan allowlist field produk yang aman untuk direview.
- Preview memvalidasi header/encoding/ukuran/jumlah row, ID product, duplicate row, optimistic timestamp, taxonomy aktif, controlled values, angka, notes, serta pasangan harga+ukuran.
- Cell kosong berarti pertahankan nilai current; tidak ada semantics clear/delete pada v1.
- Perbandingan current vs kandidat menghasilkan daftar field berubah, status valid/review/error, issue, product match, fingerprint, actor, dan batch audit immutable.
- Batch maintenance memakai tabel audit existing tetapi dipisahkan berdasarkan `contract_version`; riwayat import provider tidak tercampur.
- Halaman admin maintenance mempunyai template CSV, upload, initial/error/populated/history states, serta link jelas dari pusat Import Produk.

Out of scope:

- Apply/write katalog, clear field, publication/availability/media/external identity mutation, create product, auto-create taxonomy, XLSX, staging, production, dan deployment.

Dependencies:

- P6-07 snapshot katalog read-only dan audit batch P6-02 selesai.

Risks:

- Product atau offer dapat berubah setelah snapshot; `expected_updated_at`, `expected_row_fingerprint`, dan catalog-state fingerprint wajib menghasilkan stale/conflict, bukan overwrite.
- Reuse tabel audit dapat mencampur flow; seluruh query dan route harus membatasi contract version secara eksplisit.
- Input hasil olahan AI tetap tidak tepercaya; validation dan escaping server-side wajib.

Acceptance criteria:

- Hanya admin dapat membuka, mengunduh template, dan menjalankan preview maintenance.
- File valid menghasilkan batch persisted tanpa mutation product/offer/taxonomy/media; upload identik pada actor+file+state identik memakai batch yang sama.
- Missing/stale/duplicate product, invalid taxonomy/value/number, dan pair harga+ukuran yang tidak lengkap ditahan dengan recovery copy jelas.
- Field kosong preserve; row tanpa perubahan ditandai review/no-op; slug, publication, availability, media, dan identity tidak menjadi input.
- Riwayat maintenance terpisah dari riwayat import provider dan batch ID tidak dapat dibuka silang antar-flow.
- UI usable pada `390x844` dan `1440x900`, tabel menggunakan overflow internal, kontrol minimal 44 px, dan console bersih.
- Focused/full tests, migration round-trip bila ada migration, Pint, Blade, Composer strict, route audit, build, browser upload nyata, dan CI lulus.

Verification:

- Focused feature tests lulus: 24 test / 230 assertion untuk maintenance preview, snapshot, report, dan provider import preview; suite khusus maintenance terakhir lulus 8 test / 71 assertion.
- Full Laravel suite lulus: 165 test / 996 assertion.
- Pint targeted, Blade compile/cache, Composer strict validation, route audit, `git diff --check`, dan production Vite build lulus. Build tetap menampilkan warning existing untuk DaisyUI `@property` dan chunk `about-lanyard` sekitar 3,28 MB.
- Browser audit upload multipart nyata lulus pada `390x844` dan `1440x900`: preview menghasilkan 1 row valid dan 1 row error, batch/history dapat direload, template dapat diunduh, tabel hasil memakai overflow internal, action button minimal 44 px, tidak ada document overflow, dan console bersih.
- Batch browser audit sintetis dan file CSV sementara sudah dihapus secara terarah setelah verifikasi. Baseline lokal tetap: 180 products, 65 offers, 19 images, 0 identities, 0 import batches, dan 0 import rows.
- Tidak ada migration, mutation katalog/media/taxonomy, staging, production access, atau deployment.
- Commit implementasi `bbeac30` lulus GitHub Actions CI pada PHP 8.2/Laravel tests dan Node/Vite build: `https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/35115120096`.

Documentation updates:

- Business rules, master plan, kontrak snapshot katalog v2, kontrak `PRODUCT_MAINTENANCE_CSV.md`, backlog, dan `ADR-018-internal-id-maintenance-preview.md` diperbarui.
- Kandidat berikutnya adalah `P6-09` transactional maintenance apply dengan revalidation, explicit confirmation, outcome per row, dan rollback/forward-fix yang terukur; belum dimulai.

### P6-09 Transactional bulk maintenance apply — DONE

Outcome:

- Admin dapat menerapkan baris valid dari preview `maintenance-v1` secara eksplisit, transactional, idempotent, dan auditable tanpa mengubah field di luar kontrak.

In scope:

- Endpoint admin apply dengan checkbox konfirmasi dan contract isolation.
- Revalidation contract version, payload hash, catalog-state fingerprint, product timestamp, row fingerprint, matched product, taxonomy aktif, dan pasangan harga+ukuran di dalam transaksi.
- Mutation allowlist untuk nama, deskripsi, brand, gender, stok snapshot, best seller, kategori, satu offer harga+ukuran, serta fragrance notes.
- Outcome per row dengan before/after snapshot, actor, waktu, status updated/skipped/blocked, serta batch terminal status.
- UI initial/preview/applying-disabled/success/stale/invalid/failed dan riwayat status maintenance.

Out of scope:

- Create product, clear/delete/archive, publication, availability, slug, media, external identity, taxonomy creation, partial retry, undo otomatis, staging, production, dan deployment.

Dependencies:

- P6-08 preview maintenance berbasis internal ID dan row fingerprint selesai.

Risks:

- Katalog dapat berubah setelah preview; apply wajib berhenti stale sebelum mutation.
- Kegagalan pada satu mutation unexpected wajib me-rollback seluruh catalog write.
- Reuse tabel import wajib tetap terisolasi melalui `contract_version` pada route, query, dan action.

Acceptance criteria:

- Hanya admin dengan konfirmasi eksplisit dapat apply batch maintenance previewed.
- Hanya row valid dengan perubahan yang diterapkan; review/no-op dan error tidak menulis katalog tetapi mempunyai outcome jelas.
- Apply identik tidak mengulang mutation; batch non-current atau lintas kontrak ditolak.
- Perubahan katalog setelah preview menghasilkan stale tanpa partial write.
- Setiap row sukses menyimpan before/after snapshot dan actor batch; publication, availability, slug, media, dan external identity tetap identik.
- Focused/full tests, Pint, Blade, Composer strict, route audit, build, browser audit mobile/desktop, dan CI lulus.

Verification:

- Focused maintenance preview/apply tests lulus: 14 test / 139 assertion, termasuk auth, explicit confirmation, contract isolation, allowlist mutation, protected-field preservation, no-op/error outcome, idempotency, stale state, tampered payload, dan full transaction rollback.
- Full Laravel suite lulus: 171 test / 1.064 assertion.
- Pint targeted, Blade compile/cache, Composer strict validation, route audit empat endpoint maintenance, `git diff --check`, dan production Vite build lulus. Build tetap mempunyai warning existing DaisyUI `@property` dan chunk `about-lanyard` sekitar 3,28 MB.
- Browser audit nyata berhasil menjalankan upload CSV, preview, checkbox confirmation, apply, reload batch, outcome row, dan history pada desktop `1280x720`; document width sama dengan viewport dan console bersih.
- Render browser mobile nyata melalui frame `390x844` mempunyai `clientWidth=390`, `scrollWidth=390`, tiga action utama setinggi 44 px, success state terbaca, dan console bersih. Baseline awal juga direkam sebelum perubahan pada surface maintenance lokal.
- Produk #263, batch #8, brand #23, kategori #7, CSV, dan wrapper viewport sintetis dihapus secara terarah setelah audit. Baseline lokal kembali menjadi 180 products, 65 offers, 19 images, 0 identities, 0 batches, dan 0 rows.
- Tidak ada migration, data/media production, staging, credential, deployment, atau repository lain yang disentuh.
- Commit implementasi `bf2bd28` lulus GitHub Actions CI pada PHP 8.2/Laravel tests dan Node/Vite build: `https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/35117849699`.

Documentation updates:

- Business rules, master plan, kontrak `PRODUCT_MAINTENANCE_CSV.md`, backlog, dan `ADR-019-transactional-maintenance-apply.md` diperbarui.
- Phase 6 selesai. `P7-01` audit dan kontrak UX discovery katalog publik mobile-first telah selesai. Kandidat berikutnya adalah `P7-02` query/state discovery; belum dimulai.

## P7 — Public catalog UX

### P7-01 Audit dan kontrak UX discovery katalog publik — DONE

Outcome:

- Baseline katalog publik mobile dan desktop, gap terhadap business rules, serta kontrak implementasi discovery-to-inquiry terdokumentasi sebelum redesign visual dimulai.

In scope:

- Audit kode dan browser nyata untuk listing, filter/search/sort, pagination, kartu produk, detail, availability, dan jalur inquiry.
- Baseline viewport `390x844` dan `1440x900`, termasuk overflow, touch target, state URL, dan console.
- Kontrak URL/state, informasi kartu, availability truth, empty/error states, aksesibilitas, performa, serta pembagian pekerjaan Phase 7.
- ADR untuk keputusan state katalog dan batas inquiry.

Out of scope:

- Perubahan visual katalog, controller/query, cart, schema, data produk, media, dependency, staging, production, deployment, dan integrasi AI/API.

Dependencies:

- P3 product domain, P4 media boundary, serta P5 admin publication/availability selesai.

Risks:

- Data lokal legacy belum memenuhi publication completeness sehingga visual baseline tidak mewakili katalog final.
- Redesign tanpa kontrak state dapat menghilangkan filter saat pagination atau kembali dari detail.
- Label stok dari variant dapat secara keliru dianggap live inventory dan bertentangan dengan availability efektif.

Implementation notes:

- Surface aktif hanya public catalog. Primary task: customer menemukan parfum, memahami harga/ukuran/status secara jujur, membuka detail, lalu memulai inquiry tanpa kehilangan state discovery.
- Gunakan `qammaris-ui-review`; pertahankan Laravel, Blade, Tailwind, DaisyUI, dan identitas black/ivory/gold.

Acceptance criteria:

- Temuan current state dibuktikan melalui kode, data lokal read-only, dan browser nyata pada dua viewport target.
- Kontrak canonical search/filter/sort/pagination dan preservasi state sampai detail mempunyai allowlist yang eksplisit.
- Kartu/detail membedakan `available`, `sold_out`, dan `unknown` tanpa klaim live stock; sold-out tetap discoverable.
- Mobile behavior, touch target, keyboard/focus, empty/error/legacy-data state, performa gambar, dan bahasa UI ditetapkan.
- Urutan item implementasi Phase 7 cukup kecil untuk diverifikasi dan tidak memasukkan production atau data migration.
- Dokumentasi lulus link/reference review, Markdown hygiene, dan `git diff --check`.

Verification:

- Audit kode mencakup `ProductController`, listing/detail Blade, product domain, route, cart/inquiry, serta test safety existing.
- Browser baseline direkam pada `390x844` dan `1440x900`: listing/detail tidak mengalami horizontal overflow dan console tidak mempunyai warning/error.
- Audit mobile membuktikan beberapa target utama masih 28–40 px, hero/detail media menunda informasi inti, dan mobile sort tidak merefleksikan URL aktual.
- Audit URL membuktikan state `search=Mykonos`, `brand[]=17`, dan `sort=price_high` hilang pada pagination (`/products?page=2`) serta tidak dibawa ke detail.
- Query read-only lokal membuktikan 180 published product semuanya effective `unknown`; 115 belum mempunyai offer aktif dan 166 belum mempunyai primary image. Tidak ada data yang diubah.
- Kontrak menetapkan allowlist query, normalization, page reset/preservation, deterministic sort, card/detail hierarchy, availability copy/action, inquiry boundary, aksesibilitas, performa, legacy-data state, dan lima slice implementasi berikutnya.
- Full Laravel suite lulus: 171 test / 1.064 assertion. Composer strict validation dan Vite production build lulus; warning existing DaisyUI `@property` serta chunk `about-lanyard` sekitar 3,28 MB tetap tercatat.
- Markdown trailing-whitespace check dan `git diff --check` lulus.
- Commit kontrak `937c866` lulus GitHub Actions CI: `https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/35180945632`.
- Tidak ada perubahan runtime, schema, data, media, dependency, staging, production, deployment, credential, atau repository lain.

Documentation updates:

- Kontrak baru `docs/product/PUBLIC_CATALOG_UX.md` menjadi source of truth Phase 7 untuk discovery-to-inquiry.
- `BUSINESS_RULES.md` menegaskan URL state, price authority, availability copy, sold-out discovery, dan cart sebagai daftar inquiry.
- `MASTER_PLAN.md` memecah Phase 7 menjadi delivery incremental.
- `ADR-020-public-catalog-state-and-inquiry-boundary.md` menerima keputusan GET state, validated detail context, effective availability, single offer, serta inquiry boundary.

Final report:

- Files changed: lima file dokumentasi; tidak ada application code yang diubah.
- Schema/data impact: nihil; seluruh pemeriksaan database bersifat read-only.
- Screenshots: baseline listing dan detail direkam melalui browser nyata pada mobile dan desktop sebelum implementasi UI apa pun.
- Known limitations: data lokal legacy membuat sebagian besar kartu memakai placeholder atau fallback; reconciliation tetap pekerjaan terpisah dan tidak boleh disamarkan oleh redesign.
- Rollback: revert commit dokumentasi/close-out; tidak ada migration atau data rollback.
- Suggested next item: `P7-02` query/state discovery untuk validation, filter gender/harga/availability, sort deterministic, pagination preservation, dan parity mobile/desktop; belum dimulai.

### P7-02 Query dan state discovery katalog publik — DONE

Outcome:

- Customer dapat mencari, memfilter, mengurutkan, berpindah halaman, dan membuka detail tanpa kehilangan state katalog yang valid pada mobile maupun desktop.

In scope:

- Satu boundary normalisasi server-side untuk query `search`, `brand[]`, `category`, `gender`, `price_min`, `price_max`, `availability`, `sort`, dan `page`.
- Filter brand, kategori, audience, rentang harga offer aktif, serta effective availability.
- Sort deterministic terbaru, harga rendah/tinggi, dan populer; pagination 24 produk dengan query allowlist yang dipertahankan.
- Parity kontrol mobile/desktop, total result summary, active-filter count, clear all, empty state, serta context kembali dari detail.
- Regression tests dan browser verification `390x844` serta `1440x900`.

Out of scope:

- Redesign product card, copy/status availability pada kartu, single-offer detail redesign, cart/inquiry flow, data reconciliation, schema/migration, dependency, staging, production, dan deployment.

Dependencies:

- P7-01 kontrak UX dan ADR-020 selesai.

Risks:

- Harga legacy pada `base_price` dapat berbeda dari offer; filter/sort wajib hanya memakai offer aktif.
- Raw availability dapat stale; filter wajib memakai semantics effective availability 36 jam.
- Query tidak valid tidak boleh menghasilkan error, open redirect, atau diteruskan ke pagination/detail.

Implementation notes:

- Surface aktif hanya public catalog. URL GET adalah source of truth dan form tetap berfungsi tanpa JavaScript.
- Pertahankan URL `/products` dan `/products/{slug}`, Laravel/Blade/Tailwind/DaisyUI, serta visual identity existing.

Acceptance criteria:

- Query valid dinormalisasi sekali dan dipakai oleh database query, kedua UI filter, pagination, serta return context detail.
- Query invalid/unknown diabaikan secara deterministik; taxonomy nonaktif/tidak dikenal tidak diteruskan.
- Search mencakup nama dan brand; filter gender, category, multi-brand, price range, dan effective availability dapat dikombinasikan.
- Harga berasal dari offer aktif; sort mempunyai tie-breaker ID dan null offer selalu di akhir.
- Pagination memakai 24 item dan mempertahankan seluruh state allowlisted; filter/sort form tidak meneruskan page lama.
- Mobile dan desktop menampilkan selected state yang sama, current sort benar, total hasil benar, clear state jelas, dan target aksi utama minimal 44 px.
- Detail menyediakan kembali ke hasil dari context allowlisted dan canonical URL tetap tanpa query.
- Focused/full tests, Pint, Blade compile, Composer strict, route audit, build, browser states, console, overflow, dan CI lulus.

Verification:

- `php artisan test tests/Feature/PublicCatalogDiscoveryTest.php tests/Feature/ProductDetailJsonLdTest.php tests/Feature/CatalogSafetyTest.php` lulus: 14 test, 59 assertions.
- Full `php artisan test` lulus: 177 test, 1.096 assertions.
- Pint targeted, `composer validate --strict`, route audit `/products`, Blade clear/cache, `git diff --check`, dan `npm run build` lulus; build hanya mempertahankan warning existing DaisyUI `@property` dan chunk lanyard besar.
- Browser audit `390x844`: state filter/sort sinkron, panel mobile menampilkan nilai aktif, submit menghasilkan URL bersih, kombinasi filter dan empty state benar, target aksi utama 44 px, serta tidak ada horizontal overflow.
- Browser audit `1440x900`: 180 total/24 kartu per halaman, sidebar dan sort aktif benar, pagination mempertahankan query, detail mempertahankan return context, canonical detail tanpa query, tidak ada horizontal overflow, dan console tanpa error/warning.
- CI implementasi hijau pada commit `b53ffc7`: https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/35183620004

Documentation updates:

- Backlog ini merekam scope, acceptance criteria, hasil test/build/browser, dan batas bahwa P7-03 belum dimulai.
- Implementasi terdokumentasi melalui `ProductCatalogState`, focused feature tests, serta atribut data katalog yang menjadi kontrak sinkronisasi form mobile/desktop.

Rollback:

- Revert commit implementasi `b53ffc7` dan commit close-out P7-02; tidak ada migration, data, media, dependency, staging, production, atau deployment yang perlu di-rollback.

Suggested next item:

- `P7-03` redesign presentation kartu/list katalog dan copy effective availability berdasarkan kontrak P7-01; belum dimulai.

### P7-03 Trust layer kartu produk katalog publik — DONE

Outcome:

- Customer dapat memahami identitas produk, satu ukuran/harga yang berwenang, dan status ketersediaan efektif langsung dari kartu tanpa klaim data yang belum terbukti.

In scope:

- Kartu katalog memakai hanya relasi `activeOffer` untuk ukuran dan harga.
- Copy effective availability untuk `available`, `unknown`/stale, dan `sold_out`.
- State netral untuk offer atau gambar yang belum lengkap, placeholder lokal, label terlaris, fixed image ratio, serta image loading yang sesuai posisi viewport.
- Penyesuaian density listing mobile-first tanpa mengubah discovery state P7-02.
- Focused regression tests dan browser verification `390x844` serta `1440x900`.

Out of scope:

- Redesign detail P7-04, inquiry/cart P7-05, perubahan filter/query P7-02, rekonsiliasi data, schema/migration, write media, dependency, staging, production, dan deployment.

Dependencies:

- P7-01 kontrak UX/ADR-020 dan P7-02 discovery state selesai.

Risks:

- Sebagian besar data lokal belum mempunyai offer aktif atau gambar; UI tidak boleh menyamarkan gap tersebut dengan `base_price`, gambar remote, atau copy stok yang meyakinkan secara palsu.
- Nama panjang, sold-out, dan kombinasi badge/status dapat mengubah tinggi kartu serta density dua kolom pada mobile.

Implementation notes:

- Surface aktif hanya public catalog. Primary task: memahami produk, ukuran/harga tepercaya, dan effective availability sebelum membuka detail.
- Baseline browser sebelum perubahan telah direkam pada `390x844` dan `1440x900` sesuai quality gate `qammaris-ui-review`.
- Pertahankan Laravel/Blade/Tailwind/DaisyUI dan identitas visual existing; tanpa dependency atau framework baru.

Acceptance criteria:

- Ukuran dan harga kartu hanya berasal dari satu offer aktif; `base_price` dan `compare_at_price` tidak menjadi fallback presentasi kartu.
- Produk tanpa offer aktif tidak menampilkan harga/ukuran tebakan dan menampilkan `Data sedang dilengkapi`.
- Produk tanpa gambar memakai asset placeholder lokal, tidak melakukan hotlink provider, dan menyatakan foto sedang dilengkapi.
- Effective availability menampilkan tepat `Tersedia saat diperiksa`, `Konfirmasi stok`, atau `Sold out`; raw available yang stale dipresentasikan sebagai unknown.
- Sold-out tetap discoverable dan dapat membuka detail; label `Terlaris` hanya muncul ketika flag aktif.
- Kartu mempunyai satu target detail yang semantik, focus state jelas, gambar stabil, nama panjang aman, dan density dua kolom mobile tetap terbaca.
- Focused/full tests, Pint, Blade compile, build, browser states, console, overflow, dan CI lulus.

Verification:

- Focused tests `PublicCatalogCardTrustTest`, `PublicCatalogDiscoveryTest`, `ProductMediaStorageTest`, `ProductDetailJsonLdTest`, dan `CatalogSafetyTest` lulus: 23 test, 109 assertions.
- Full `php artisan test` lulus: 181 test, 1.131 assertions.
- Pint targeted, `composer validate --strict`, route audit `/products`, Blade clear/cache, `git diff --check`, dan `npm run build` lulus; build hanya mempertahankan warning existing DaisyUI `@property` dan chunk lanyard besar.
- Browser audit `390x844`: dua kolom tetap terbaca, nama panjang aman, satu link detail per kartu, keempat state audit tampil, placeholder lokal digunakan, 4 gambar initial memakai high priority, dan tidak ada horizontal overflow.
- Browser audit `1440x900`: sidebar/discovery state P7-02 tetap utuh, ukuran/harga offer aktif serta tiga effective availability tampil benar, sold-out tetap dapat dibuka, tidak ada external image request, horizontal overflow, atau console error/warning.
- Empat record sintetis lokal untuk state browser telah dihapus kembali; verifikasi akhir menunjukkan `0` record audit tersisa.
- CI implementasi hijau pada commit `5e48eb3`: https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/35190905471

Documentation updates:

- Backlog ini merekam scope, acceptance criteria, hasil test/build/browser, commit, CI, serta batas bahwa P7-04 belum dimulai.
- Partial `products._card`, placeholder SVG lokal, dan focused feature test menjadi kontrak implementasi trust layer kartu.

Rollback:

- Revert commit implementasi `5e48eb3` dan commit close-out P7-03; tidak ada migration, data, media, dependency, staging, production, atau deployment yang perlu di-rollback.

Suggested next item:

- `P7-04` redesign detail produk single-offer dan presentation fragrance notes berdasarkan kontrak P7-01; belum dimulai.

### P7-04 Detail produk mobile-first dan single-offer — DONE

Outcome:

- Customer dapat memahami brand, nama, satu ukuran/harga berwenang, effective availability, deskripsi, dan fragrance notes lebih awal, lalu kembali ke context hasil katalog yang tervalidasi.

In scope:

- Information hierarchy detail mobile-first dengan ringkasan produk sebelum media pada viewport sempit dan komposisi dua kolom pada desktop.
- Presentasi satu `activeOffer` sebagai informasi, bukan selector variant; tidak ada fallback harga dari `base_price`.
- Effective availability dan checked-time context yang tidak mengklaim live stock.
- Gallery maksimal tiga gambar dengan placeholder lokal, thumbnail accessible, fixed aspect ratio, dan progressive image loading.
- Deskripsi, fragrance notes optional, best-seller state, explicit return-to-results, serta related product yang memakai trust card P7-03.
- Focused regression tests dan browser verification `390x844` serta `1440x900`.

Out of scope:

- Redesign inquiry list/cart dan contextual WhatsApp P7-05, perubahan controller cart/checkout, filter/query P7-02, rekonsiliasi data, schema/migration, write media, dependency, staging, production, dan deployment.

Dependencies:

- P7-01 kontrak UX/ADR-020, P7-02 discovery state, dan P7-03 card trust layer selesai.

Risks:

- Published legacy row dapat kehilangan offer atau image; detail wajib menyatakan data belum lengkap tanpa menebak harga, ukuran, atau gambar.
- Gallery, nama/deskripsi/notes panjang, dan related products dapat mendorong primary information terlalu jauh atau menyebabkan overflow pada mobile.
- Cart legacy masih memvalidasi quantity variant stock dan terminology inquiry belum selesai; perubahan semantics end-to-end tetap ditahan untuk P7-05.

Implementation notes:

- Surface aktif hanya public catalog detail. Primary task: memahami produk dan availability secara jujur, lalu kembali ke hasil katalog.
- Baseline browser produk nyata `Rasasi Hawas For Him` telah direkam pada `390x844` dan `1440x900` sesuai quality gate `qammaris-ui-review`.
- Pertahankan URL, context query allowlisted, Laravel/Blade/Tailwind/DaisyUI, dan identitas black/ivory/gold tanpa dependency baru.

Acceptance criteria:

- Mobile menampilkan brand, nama, ukuran, harga, availability, dan action existing sebelum media mendorong informasi tersebut jauh ke bawah; desktop tetap mempunyai komposisi foto dan detail yang seimbang.
- Ukuran/harga serta JSON-LD offer hanya berasal dari satu `activeOffer`; row tanpa offer menampilkan `Data sedang dilengkapi` tanpa `base_price` atau action cart aktif.
- Tidak ada selector ukuran palsu atau copy `In Stock`/variant stock sebagai availability publik.
- Effective availability menampilkan tepat `Tersedia saat diperiksa`, `Konfirmasi stok`, atau `Sold out`; stale available menjadi unknown dan quantity snapshot tidak ditampilkan sebagai stok live.
- Gallery tidak menduplikasi primary image, memakai source storage/placeholder lokal, mempunyai alt/focus/selected state, dan tidak hotlink provider/placeholder eksternal.
- Fragrance notes menampilkan hanya kelompok nonempty; long content aman, output tetap escaped, dan UI utama konsisten Bahasa Indonesia.
- Return-to-results serta related links mempertahankan context allowlisted dan canonical detail tetap tanpa query.
- Focused/full tests, Pint, Blade compile, build, browser states, keyboard, console, overflow, dan CI lulus.

Verification:

- Focused tests `PublicProductDetailTrustTest`, `ProductDetailJsonLdTest`, `PublicCatalogDiscoveryTest`, `PublicCatalogCardTrustTest`, `ProductMediaStorageTest`, dan `CatalogSafetyTest` lulus: 29 test, 168 assertions.
- Full `php artisan test` lulus: 187 test, 1.190 assertions.
- Pint targeted, `composer validate --strict`, route audit `/products`, Blade clear/cache, `git diff --check`, dan `npm run build` lulus; build hanya mempertahankan warning existing DaisyUI `@property` dan chunk lanyard besar.
- Browser audit `390x844`: identitas, satu ukuran/harga, effective availability, quantity, serta dua action tampil sebelum media; fresh/stale/sold-out/missing-offer, placeholder lokal, long content, dan no-overflow telah diverifikasi.
- Browser audit `1440x900`: komposisi foto/detail seimbang, informasi dan action terlihat pada initial viewport, explicit return membawa `search=Rasasi&sort=popular&page=2`, tidak ada external image request atau horizontal overflow.
- Gallery produk nyata dengan dua gambar berhasil diganti melalui keyboard; `aria-pressed`, main image source, dan alt text berubah sesuai pilihan tanpa menduplikasi thumbnail primary.
- Console browser tidak mempunyai error/warning. Empat record sintetis lokal telah dihapus; verifikasi akhir menunjukkan `0` record audit tersisa.
- CI implementasi hijau pada commit `cbad5ff`: https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/35193525231

Documentation updates:

- Backlog ini merekam scope, acceptance criteria, hasil test/build/browser, commit, CI, serta batas bahwa P7-05 belum dimulai.
- `PublicProductDetailTrustTest` dan kontrak JSON-LD offer menjadi regression boundary untuk detail single-offer, missing data, gallery, notes, availability, related card, dan context kembali.

Rollback:

- Revert commit implementasi `cbad5ff` dan commit close-out P7-04; tidak ada migration, data, media, dependency, staging, production, atau deployment yang perlu di-rollback.

Suggested next item:

- `P7-05` inquiry list dan contextual WhatsApp dengan semantics non-reservasi, termasuk sold-out/restock dan compatibility route cart; belum dimulai.

### P7-04A Refinement urutan media detail mobile — DONE

Outcome:

- Customer melihat foto produk lebih dahulu pada detail mobile tanpa galeri memenuhi hampir satu layar, sementara komposisi desktop tetap seimbang.

In scope:

- Menempatkan galeri sebelum ringkasan produk pada viewport sempit.
- Membatasi tinggi media utama mobile dengan rasio yang lebih ringkas dan menampilkan foto utuh.
- Regression test markup serta browser verification `390x844` dan `1440x900`.

Out of scope:

- Inquiry/cart P7-05, copy/action WhatsApp, data/media, backend, schema, dependency, staging, production, dan deployment.

Dependencies:

- P7-04 selesai dan feedback visual owner pada halaman detail.

Risks:

- Foto portrait dapat terpotong bila container dipendekkan; mobile wajib memakai `object-contain`.
- Perubahan order tidak boleh mengubah komposisi desktop atau accessibility gallery.

Acceptance criteria:

- Pada mobile, urutan setelah navigasi adalah galeri lalu brand/nama/harga/status/action.
- Media utama mobile tidak melebihi lebar konten, memakai rasio `4:3`, dan mempertahankan seluruh objek dengan `object-contain`.
- Pada desktop, galeri tetap kolom kiri dengan rasio portrait dan ringkasan tetap kolom kanan.
- Tidak ada horizontal overflow, console error baru, atau perubahan business/data behavior.
- Focused test, Blade compile, build, dan browser audit dua viewport lulus.

Verification:

- Focused tests `PublicProductDetailTrustTest`, `ProductDetailJsonLdTest`, dan `CatalogSafetyTest` lulus: 15 test / 96 assertions.
- Pint check pada focused test, Blade clear/cache, `git diff --check`, dan Vite production build lulus; build mempertahankan warning existing DaisyUI `@property` serta chunk `about-lanyard` sekitar 3,28 MB.
- Browser `390x844`: galeri berpindah dari posisi setelah action (mulai sekitar 681 px) menjadi tepat setelah navigasi (mulai sekitar 144 px); tinggi image utama turun dari sekitar 427 px menjadi 256 px dengan `object-fit: contain`.
- Browser `390x844` untuk produk tanpa foto: placeholder lokal, alt text, label `Foto sedang dilengkapi`, urutan media-first, dan no-overflow terverifikasi.
- Browser `1440x900`: galeri tetap di kiri dan ringkasan di kanan; media memakai rasio portrait serta `object-fit: cover`, tidak ada horizontal overflow atau console error/warning.

Documentation updates:

- `PUBLIC_CATALOG_UX.md` diperbarui mengikuti keputusan owner bahwa detail mobile memakai compact media-first.
- Backlog ini merekam refinement sebagai koreksi terukur terhadap P7-04 tanpa memperluas scope ke P7-05.

Rollback:

- Revert perubahan class order/aspect/object-fit, regression test, dan pembaruan dokumentasi; tidak ada schema, data, media, dependency, staging, production, atau deployment yang perlu di-rollback.

Suggested next item:

- `P7-05` inquiry list dan contextual WhatsApp; belum dimulai.

### P7-04B Refinement skala foto detail responsif — DONE

Outcome:

- Foto produk terasa proporsional seperti product gallery retail: objek dipusatkan dengan ruang napas, tidak memenuhi seluruh bidang pada mobile maupun desktop.

In scope:

- Menambah inset internal pada media utama agar visual foto lebih kecil dari gallery stage.
- Mengubah desktop dari crop `cover` portrait menjadi gallery square dengan `object-contain`.
- Mempertahankan urutan media-first mobile, thumbnail, dan seluruh behavior gallery existing.
- Regression test serta browser verification `390x844`, `430x932`, dan `1440x900`.

Out of scope:

- Menyalin branding, promo, review, wishlist, sticky cart, atau information architecture C&F.
- Inquiry/cart P7-05, data/media source, backend, schema, dependency, staging, production, dan deployment.

Dependencies:

- P7-04A selesai dan referensi visual C&F diberikan owner.

Risks:

- Inset yang terlalu besar membuat foto produk sulit dikenali; ukuran wajib diverifikasi pada produk dengan gambar serta placeholder.
- Perubahan desktop tidak boleh mengubah urutan kolom atau action produk.

Acceptance criteria:

- Mobile mempertahankan gallery stage `4:3`, tetapi media mempunyai inset konsisten sehingga tidak edge-to-edge.
- Desktop memakai gallery stage square dan `object-contain`; foto tidak dipotong atau diperbesar memenuhi kolom.
- Produk dengan foto dan placeholder tetap terbaca, tidak overflow, serta informasi produk tidak terdorong berlebihan.
- Tidak ada console error baru atau perubahan behavior/data.
- Focused test, Blade compile, build, dan browser audit tiga viewport lulus.

Verification:

- Focused tests `PublicProductDetailTrustTest`, `ProductDetailJsonLdTest`, dan `CatalogSafetyTest` lulus: 15 test / 97 assertions.
- Pint check, Blade clear/cache, `git diff --check`, dan Vite production build lulus; warning existing DaisyUI `@property` serta chunk `about-lanyard` sekitar 3,28 MB tetap tercatat.
- Browser `390x844`: stage tetap `4:3`, media memakai inset 32 px, `object-fit: contain`, informasi produk tetap langsung menyusul, dan tidak ada horizontal overflow.
- Browser `430x932`: komposisi compact media-first mengikuti proporsi referensi owner; foto terpusat, harga/status/dua action tetap terbaca dalam alur awal halaman, dan tidak ada overflow.
- Browser `1440x900`: stage berubah dari portrait 578x723 px dengan crop menjadi square sekitar 580x580 px, inset 64 px, dan foto utuh terpusat; detail tetap di kolom kanan dan console bersih.
- Produk tanpa foto pada `390x844` mempertahankan placeholder lokal, alt text, label data belum lengkap, inset yang sama, dan no-overflow.

Documentation updates:

- `PUBLIC_CATALOG_UX.md` menetapkan stage mobile `4:3`, desktop square, serta `object-contain` dengan inset responsif.
- Backlog ini merekam referensi C&F sebagai prinsip skala/whitespace saja, bukan izin menyalin branding atau fitur di luar scope.

Rollback:

- Revert class stage/inset/object-fit, regression assertion, dan dokumentasi P7-04B; tidak ada schema, data, media, dependency, staging, production, atau deployment yang perlu di-rollback.

Suggested next item:

- `P7-05` inquiry list dan contextual WhatsApp; belum dimulai.

### P7-04C Koreksi single-frame gallery produk — DONE

Outcome:

- Detail produk memakai satu frame foto portrait yang bersih tanpa efek kotak kecil di dalam stage besar, termasuk ketika produk mempunyai beberapa gambar.

In scope:

- Frame `4:5` terpusat dengan batas lebar mobile/desktop.
- Media nyata tanpa padding tambahan; placeholder tetap mempunyai inset.
- Thumbnail lebih ringkas, menempel di bawah frame, dan terpusat pada mobile.
- Browser audit produk dua gambar `Rasasi Hawas Fire For Him`, produk satu gambar, dan placeholder.

Out of scope:

- Penggantian/retouch file foto, background removal, promo/review/wishlist C&F, P7-05, backend, data, schema, dependency, staging, production, dan deployment.

Dependencies:

- P7-04B selesai; feedback owner membuktikan pendekatan inset universal gagal pada foto dengan background sendiri.

Risks:

- Foto sumber berbeda rasio dapat menyisakan whitespace; `object-contain` dipertahankan agar foto tidak terpotong.
- Lebar frame harus cukup kecil untuk tidak terasa hero, tetapi cukup besar untuk inspeksi produk.

Acceptance criteria:

- Tidak ada stage beige besar di luar foto nyata atau padding palsu pada media nyata.
- Mobile memakai frame maksimum 15 rem; desktop maksimum 24 rem dengan rasio `4:5`.
- Thumbnail tidak selebar kolom, target tetap memadai, dan selected/focus behavior gallery tidak berubah.
- Placeholder tetap jelas, berlabel, dan tidak membesar memenuhi frame.
- Tidak ada overflow, console error baru, atau perubahan business/data behavior.
- Focused tests, Blade compile, build, dan browser audit `390x844`, `430x932`, serta `1440x900` lulus.

Verification:

- Focused tests `PublicProductDetailTrustTest`, `ProductDetailJsonLdTest`, dan `CatalogSafetyTest` lulus: 15 test / 101 assertions.
- Pint check, Blade clear/cache, `git diff --check`, dan Vite production build lulus; warning existing DaisyUI `@property` serta chunk `about-lanyard` sekitar 3,28 MB tetap tercatat.
- `Rasasi Hawas Fire For Him` pada `430x932`: real image menjadi satu frame `240x300` tanpa padding/outer beige stage, dua thumbnail 56 px terpusat tepat di bawah, dan tidak ada overflow atau console error.
- Produk yang sama pada `390x844`: frame tetap `240x300`, title menyusul setelah thumbnail, selected state gallery tetap tersedia, dan tidak ada overflow.
- Produk yang sama pada `1440x900`: frame `384x480` terpusat di kolom kiri, thumbnail berada dalam lebar frame, detail tetap di kolom kanan, dan console bersih.
- Produk satu gambar `Rasasi Hawas For Him` memakai frame yang sama tanpa thumbnail/padding; placeholder lokal memakai inset 32 px dan label data belum lengkap.

Documentation updates:

- `PUBLIC_CATALOG_UX.md` mengganti kontrak universal inset dengan single-frame portrait dan membedakan perlakuan real image dari placeholder.
- Backlog mencatat bahwa validasi gallery wajib mencakup produk multi-image, bukan hanya satu fixture visual.

Rollback:

- Revert wrapper single-frame, conditional placeholder inset, thumbnail alignment/size, regression assertions, dan dokumentasi P7-04C; tidak ada schema, data, media, dependency, staging, production, atau deployment yang perlu di-rollback.

Suggested next item:

- `P7-05` inquiry list dan contextual WhatsApp; belum dimulai.

### P7-05 Daftar inquiry dan WhatsApp kontekstual — DONE

Outcome:

- Customer dapat mengumpulkan produk yang ingin ditanyakan dan mengirim inquiry WhatsApp dengan konteks produk yang current tanpa janji live stock, reservasi, checkout, invoice, atau pembayaran.

In scope:

- Rename seluruh terminology customer-facing cart menjadi `Daftar Inquiry` dengan route/session internal existing tetap kompatibel.
- Detail produk: tambah ke daftar untuk effective `available`/`unknown`, direct contextual WhatsApp untuk stok, dan direct restock inquiry untuk `sold_out`.
- Session list tidak memakai `variant stock` sebagai batas quantity; batas input defensif tetap diterapkan.
- Drawer dan halaman `/cart` membaca product, offer, price, image, URL, dan effective availability current dari database, bukan snapshot session.
- Halaman daftar inquiry tanpa form alamat/pengiriman; optional note dan satu action `Tanyakan via WhatsApp`.
- Pesan WhatsApp memuat brand, nama, ukuran, canonical URL, quantity minat, harga current, status website, serta intent stok/restock tanpa mengarang availability.
- Empty, stale/error/retry, disabled/unavailable contact, success feedback, mobile, desktop, keyboard/focus, dan compatibility route.

Out of scope:

- Live stock, reservasi, pembayaran, order persistence, CRM/chat history, login customer, Shopee/Majoo sync, data migration, schema, dependency, staging, production, dan deployment.

Dependencies:

- P7-01 sampai P7-04C selesai; ADR-020 dan `PUBLIC_CATALOG_UX.md` menetapkan boundary inquiry/non-reservasi.

Risks:

- Session legacy menyimpan snapshot yang stale; seluruh presentasi dan pesan wajib resolve ulang current database values.
- Product/offer dapat menjadi unpublished/nonaktif setelah masuk list; UI harus memberi recovery yang jujur tanpa mengirim data stale.
- Membuka WhatsApp mentransmisikan detail inquiry ke pihak ketiga; automated browser verification hanya memeriksa URL/href dan tidak mengirim pesan.

Acceptance criteria:

- Tidak ada copy `Keranjang Belanja`, `checkout`, `pesanan`, `pengiriman`, `invoice`, `payment`, pajak, atau ongkir pada flow customer-facing inquiry.
- Add/update inquiry tidak memeriksa `variant stock`; quantity valid 1–99 dan sold-out tidak dapat ditambahkan sebagai inquiry biasa.
- Product detail menghasilkan contextual WhatsApp URL current untuk intent stok/restock; missing offer tidak mengarang ukuran/harga.
- Drawer/page hanya merender current database values dan placeholder lokal; stale list menampilkan error/recovery dan tidak mengirim inquiry.
- Inquiry WhatsApp list tidak meminta nama, nomor, atau alamat; optional note tervalidasi dan message menegaskan konfirmasi stok/harga serta non-reservasi.
- Existing `/cart` routes tetap tersedia, input tervalidasi, external URL aman, output escaped, dan tidak ada schema/data/media mutation.
- Focused/full tests, Pint, Blade compile, route audit, build, browser states `390x844`/`1440x900`, console, overflow, dan CI lulus.

Implementation notes:

- `InquiryWhatsApp` menjadi satu builder terenkapsulasi untuk inquiry detail dan daftar; nomor dinormalisasi sebelum URL `wa.me` dibuat.
- Session lama tetap menyimpan key kompatibel, tetapi presentation dan pesan tidak membaca snapshot nama, brand, harga, gambar, slug, atau availability dari session.
- Seluruh item harus resolve secara utuh. Satu item stale membuat drawer mengembalikan state review dan halaman menahan action WhatsApp agar data parsial tidak terkirim.
- Status sold out ditolak pada add biasa dengan recovery ke direct restock inquiry. Nilai `variant.stock` tidak dipakai sebagai gate add/update.
- UI hanya membuka WhatsApp setelah customer menekan action; audit otomatis tidak mengikuti external link.

Verification:

- Focused regression lulus: 28 test / 181 assertions untuk inquiry, checkout integrity, catalog safety, product detail trust, dan domain state.
- Full suite lulus: 193 test / 1261 assertions.
- Pint, Blade cache, `git diff --check`, tujuh route `/cart`, dan Vite production build lulus.
- Build mempertahankan warning existing DaisyUI `@property` serta chunk `about-lanyard` sekitar 3,28 MB; tidak ada dependency baru.
- Browser nyata populated state lulus pada `390x844` dan `1440x900`: detail, drawer, serta halaman daftar memakai current values, tidak overflow, dan console tanpa warning/error.
- Contextual WhatsApp href diverifikasi memuat produk, ukuran, harga, status, canonical URL, intent, serta disclaimer; link eksternal tidak dibuka dan tidak ada pesan yang dikirim.
- Empty, stale, sold-out, zero-stock snapshot, missing offer, validation, dan current-data replacement diverifikasi lewat feature test terisolasi.

Documentation updates:

- `BUSINESS_RULES.md` menetapkan compatibility route, current-data resolution, sold-out/restock, quantity minat, dan batas data customer.
- `PUBLIC_CATALOG_UX.md` merekam behavior P7-05 yang sudah diimplementasikan dan batas WhatsApp non-otomatis.

Rollback:

- Revert builder WhatsApp, controller/request inquiry, Blade/JavaScript drawer-page-detail, regression test, dan dokumentasi P7-05. Tidak ada schema, data, media, dependency, staging, production, atau deployment yang perlu di-rollback.

Suggested next item:

- `P7-06` accessibility/performance hardening dan end-to-end regression katalog publik; belum dimulai.

### P7-06 Accessibility, performance, dan end-to-end regression katalog publik — DONE

Outcome:

- Journey katalog publik listing → detail → daftar inquiry tetap cepat, dapat dipakai dengan keyboard dan assistive technology, serta terlindungi regression test lintas-surface.

In scope:

- Landmark dan skip link global untuk melewati navigasi menuju konten utama.
- Header publik: native interactive semantics, Bahasa Indonesia, state expanded/controls, keyboard Escape, focus yang dapat diprediksi, reduced motion, dan target minimum 44 px.
- Dialog filter serta drawer inquiry: accessible name/status, focus return, dan state trigger yang sinkron.
- Heading outline footer dan penghapusan tautan placeholder/hash yang tidak mempunyai target nyata.
- Prioritas loading gambar katalog dibatasi pada kandidat LCP pertama; gambar berikutnya memakai lazy loading dengan dimensi tetap.
- Regression journey listing/detail/inquiry dan guard query katalog agar jumlah query tidak bertambah mengikuti jumlah produk.
- Audit nyata pada `390x844` dan `1440x900`, keyboard, reduced-motion contract, overflow, console, build output, dan full test suite.

Out of scope:

- Redesign visual baru, perubahan business rule/query contract, perubahan schema/data/media, dependency baru, Lighthouse target production, rekomendasi AI, Shopee/Majoo sync, staging, production, dan deployment.

Dependencies:

- P7-01 sampai P7-05 selesai; ADR-020 dan `PUBLIC_CATALOG_UX.md` menjadi contract discovery-to-inquiry.

Baseline:

- Listing mempunyai satu `h1` dan tidak overflow pada `390x844`/`1440x900`, tetapi layout tidak menyediakan skip link atau target ID pada `main`.
- Hamburger masih memakai `div role=button`, label Inggris, tanpa `aria-expanded`/`aria-controls`; tombol header hanya 40 px dan animasi GSAP belum menghormati reduced motion.
- Footer melompati heading `h1` ke `h3/h4` serta mengekspos `Cara Pesan`, kebijakan privasi, dan syarat sebagai hash link tanpa target nyata.
- Empat kartu pertama diberi `fetchpriority=high`; kandidat LCP belum dibedakan dari media berikutnya.
- Browser baseline console bersih dan layout existing dipertahankan sebagai pembanding, bukan alasan melakukan redesign.

Risks:

- Perubahan semantics React navigation dapat mengubah focus/animation behavior; verifikasi keyboard dan viewport wajib.
- Lazy loading terlalu agresif dapat menunda media di atas fold; hanya kandidat LCP pertama yang diberi high priority, sedangkan browser tetap dapat memuat gambar near-viewport.
- Query-count assertion yang terlalu spesifik mudah rapuh; regression membandingkan pertumbuhan query untuk dataset kecil dan besar, bukan angka absolut environment tertentu.

Acceptance criteria:

- Tab pertama menawarkan skip ke konten utama; semua header action memakai elemen native, nama Indonesia, visible focus, target minimum 44 px, dan state expanded yang benar.
- Menu dapat ditutup dengan Escape, fokus kembali ke trigger, dan animation duration menjadi nol pada `prefers-reduced-motion: reduce`.
- Filter dialog serta drawer mempunyai accessible title/status dan fokus kembali ke trigger setelah ditutup.
- Tidak ada tautan footer `href="#"` atau fragment tanpa target pada public layout; heading landmark tidak melompati level.
- Hanya kartu pertama memakai `fetchpriority="high"`; kartu berikutnya lazy dengan width/height sehingga layout stabil.
- End-to-end feature test mengunci state katalog, detail, add inquiry, current database values, dan contextual WhatsApp; query regression membuktikan listing bebas N+1.
- Focused/full tests, Pint, Blade compile, `git diff --check`, build, browser mobile/desktop, keyboard, overflow, console, dan CI lulus.

Implementation notes:

- Layout publik menyediakan skip link `Lewati ke konten utama` dan target fokus `main-content` tanpa mengubah hierarchy visual.
- Header React memakai native button/link, label Bahasa Indonesia, `aria-expanded`/`aria-controls`, target utama 44 px, Escape dengan focus return, serta durasi nol pada reduced motion.
- Filter menyinkronkan expanded state dan mengembalikan fokus ke trigger; drawer inquiry mempunyai accessible title/status dan mengembalikan fokus ke tombol header.
- Footer memakai heading outline logis dan tidak lagi mengekspos tautan placeholder/hash tanpa tujuan nyata.
- Hanya gambar kartu pertama memakai `fetchpriority="high"`; kartu selanjutnya memakai lazy loading dan dimensi intrinsik tetap.
- Regression baru membandingkan query count dataset 1 vs 24 produk dan mengunci journey listing → detail → add inquiry → current database values → contextual WhatsApp.

Verification:

- Focused regression lulus: 25 test / 220 assertions untuk discovery, card trust, detail trust, inquiry, serta hardening.
- Full suite lulus: 196 test / 1294 assertions; Pint dan Blade compile lulus.
- `npm run build`, route audit, dan `git diff --check` lulus. Warning existing DaisyUI `@property` dan chunk lazy `about-lanyard` sekitar 3,28 MB tetap tercatat; tidak ada dependency baru.
- Browser nyata `390x844`: satu `h1`, skip link menjadi fokus pertama dan memindahkan fokus ke `main`, menu Escape/focus return lulus, filter dan drawer focus return lulus, tiga header action utama 44×44, satu high-priority image, empat lazy image, tanpa horizontal overflow.
- Browser nyata `1440x900`: seluruh lima action header yang terlihat minimal 44 px, satu `h1`, satu high-priority image, empat lazy image, tanpa horizontal overflow; console tanpa warning/error.
- External WhatsApp link tidak dibuka, tidak ada data/media/schema/dependency/production/deployment yang diubah.

Documentation updates:

- `PUBLIC_CATALOG_UX.md` merekam kontrak accessibility/performance yang kini sudah diimplementasikan dan diverifikasi.

Rollback:

- Revert skip/main landmark, header semantics/focus/reduced-motion, dialog focus return, footer cleanup, image priority, regression test, dan dokumentasi P7-06. Tidak ada migration, data, media, dependency, staging, production, atau deployment yang perlu di-rollback.

Suggested next item:

- Saran awal setelah P7: kontrak read-only API website. Arahan Owner 2026-10-05 kemudian menetapkan P8-01 sebagai integrasi consumer aplikasi Qammaris (lihat item di bawah); outgoing API tetap future work.

## P8 — Application integration and restricted API readiness

### P8-01 Qammaris app webhook, feed worker and reconciliation — IN_REVIEW

Owner authorization: 2026-10-05; implement Laravel against final app handoff section 6. This replaces the earlier suggestion to start with an outgoing read-only website API; that API remains future work.

Outcome:

- Stateless HMAC receiver at `/integrations/qammaris-app/webhook`; 202 only after database job persistence, 503 for configuration/enqueue failure.
- Database worker reads validated sequence feed from persisted checkpoint; 30-minute scheduler queues the same operation.
- Page transaction coordinates source snapshots, mapped availability/hidden/ETA, machine audit and checkpoint. Retry/duplicate/obsolete revisions do not regress data.
- UUID provider `qammaris_app` and exact CLI mapping preview/confirmation replay cached state. Unmapped snapshots remain review data; no auto matching/publish/draft creation.
- Connected stock has no expiry/outage downgrade. Public visibility guard preserves website publication and admin completeness/last-image protections.

Scope exclusions:

- Public/admin layout or label changes, Shopee media import, source price apply/review UI, outgoing website mutation API, credentials, deployed worker/scheduler, staging/production deployment and broad production mapping.

Data/media impact:

- One additive migration creates three integration tables and adds two product metadata columns. It does not backfill/mutate existing product values, IDs, slugs, offers, relationships or media.
- No migration was applied to development/production in this task. Local verification uses isolated SQLite and synthetic secrets; no real upstream request.

Verification:

- Baseline identity/admin availability: 14 tests / 64 assertions passed before edits.
- Final full suite: **217 tests / 1,437 assertions passed** using SQLite in-memory. Includes durable enqueue, actual database worker consumption, HMAC/replay window, invalid payload/feed, checkpoint concurrency/rollback, revision replay, unmatched cache/mapping, tombstones, retained offer/media, connected availability and hidden-published admin/media protections.
- Laravel Pint check passed for all changed/new PHP files; `git diff --check` passed. Route listing confirms POST receiver with `throttle:120,1` and no session/CSRF middleware; scheduler listing confirms `*/30 * * * *`.
- Existing UI unchanged; browser screenshots are not applicable to this backend item.

Documentation: BUSINESS_RULES, MASTER_PLAN, ARCHITECTURE, ADR-021 and QAMMARIS_APP_INTEGRATION runbook.

Remaining gates: reviewer acceptance and approved staging activation (actual API credentials/network, MySQL locking, persistent worker, scheduler, clock synchronization and deployment URL). Forward fix after activation; retain checkpoint/source/audit instead of resetting or dropping data.

### P8-02 Connected availability public copy — IN_REVIEW

Apply Owner's exact labels to connected products: Tersedia, Habis, Habis · Restok segera, Tanyakan ketersediaan. Remove connected checked-time/expiry verification messaging in detail/inquiry. Preserve legacy/manual behavior, existing design and URLs. Verify mobile/desktop before/after and relevant availability/inquiry tests. Do not start automatically.

Implementation / verification 2026-10-05:

- Owner's `gas lanjut` authorized this focused public item. Shared `CatalogAvailability` presenter supplies card/detail/inquiry/WhatsApp labels; existing colors/layout/actions remain. Connected details hide checked timestamps and source recheck text; old timestamps/ETA do not expire status. Filter wording is Indonesian, values unchanged.
- Inquiry resolves current source metadata from DB, ignores session presentation fields, uses neutral app-only notices and retains legacy/mixed confirmation. Drawer resets notice on loading/error/empty and renders the current server notice safely.
- Full suite `222 passed (1487 assertions)`, focused tests, Pint and diff whitespace checks passed; Vite build passed with existing CSS/chunk warnings.
- Real browser at 390×844 / 1440×900 checked labels, inquiry quantity/success/loading/error/retry/empty, Escape focus return, filters, unknown/restock actions and legacy detail. No observed overflow or console warning/error. Evidence: `docs/verification/p8-02/README.md` with before/after screenshots.
- Preview isolated from unavailable local MySQL using public tables from the verified 2026-09-14 local backup. Original MySQL/.env/media untouched. Four disposable synthetic products/offers removed; final 180 products / 64 offers / 19 images / zero users. No P8-02 schema change, live API call, credential change or deployment.
- Docs updated: BUSINESS_RULES, ARCHITECTURE, ADR-021 scope, integration/local preview runbooks and evidence. UI rollback needs focused code revert/asset rebuild only; after activation prefer forward fix, retain P8-01 state.

Next recommended item, not started: approved staging activation/verification of P8-01 + P8-02 (Owner secrets, final webhook URL, MySQL migration/locking, persistent worker, 30-minute reconciliation and one reviewed synthetic staging mapping). Reviewer acceptance and live end-to-end delivery remain outstanding; local readiness is not live connection readiness.

Future separate items: reviewed initial SKU/UUID matching, source price proposals UI, Shopee media preview/acquisition for existing catalog, outgoing restricted API. They are not authorized by P8-01.

### P8-03 Staging integration activation and runtime proof — IN_REVIEW

Follow-up 2026-10-06 local: authenticated real staging catalog/detail reviewed at **1440×900 / 390×844**, Tersedia/price/size and website-hosted placeholder loaded, no horizontal overflow; mobile search/filter/context-return and desktop empty state passed. **55 local tests / 322 assertions** and **27 deployed MySQL rollback checks** passed: mapped revision no-op, OTW/unknown/hidden scope/detail/inquiry, outage/malformed page retention, related-record preservation and complete test rollback. Source HTTP was synthetic only for these rollback cases; real source-originated rare-state and exact ingress/browser-transition timing remain unconfirmed. Current checkpoint/product revision **456**, audits **5**, queue **0**, no sync error; controlled receiver revisions **456/456/1** returned 202 and were no-ops.

Hosting blocker fixed within P8-03: worker inherited Hostinger cron lock on **fd 3**, preventing repeated watchdog invocation. Existing staging watchdog now closes fd 3 before starting its persistent child; protected original script retained, original mode 0700 unchanged, no new cron/permission/env changes. Exact old worker gracefully refreshed; one new PHP worker verified without provider lock, heartbeats advanced **17:11:02 → 17:13:01 UTC**, no schedule:work, queue empty. Reviewable value-free script in `tools/hostinger/staging-qammaris-worker-watchdog.sh`; bash syntax passed. Evidence/screenshots and remaining gates: `docs/verification/p8-03/runtime-follow-up.md`.

Photo readiness: safe draft image acquisition and media preservation already implemented/tested, but AOERA fixture remains a placeholder and raw Shopee XLSX name/size matching/import for the launch catalog has **not** run. Staging disk remains public; permanent production R2 delivery not confirmed. Real-photo matching and completeness review belong to the explicitly scoped launch catalog/media work, not automatic stock synchronization. Non-blocking browser observation: seven initialization deprecation warnings, zero error entries; source not confirmed, investigate during the P9 browser/performance review rather than expanding this item.
Owner availability cycle 2026-10-05: selected AOERA reached **Habis** automatically at revision/checkpoint **453**, source status **16:51:56.923 UTC** → website audit/cache **16:52:07 UTC** (about **10 seconds**). Owner then returned it to available: source status **16:55:03.862 UTC** → website audit/cache **16:55:17 UTC** (about **13 seconds**), revision/checkpoint **454**, staging product **1** **Tersedia**, still published/not hidden. Queue empty, last error null; catalog/detail HTTP-context renders **200** with Tersedia at **16:56:49 UTC**. Fixed price 180000, 50 ml, slug and image ID 1 retained. No manual sync/source mutation was triggered by verification. Delays compare stored timestamps; clock offsets, exact webhook ingress/202 and browser paint latency were not independently captured. The automatic sold-out/revert cycle passed; browser review and remaining mapped scenarios are pending. P8-03 remains IN_PROGRESS. Evidence: `docs/verification/p8-03/aoera-staging-test.md`.
Owner-selected test preparation 2026-10-05: AOERA MAJESTIC 50 ML, staging product **1**, source UUID `00360de8-31bd-4982-bd73-ddaaba2d9658`, revision **59**, baseline label **Tersedia**. Created only one expressly synthetic fixture with a 50 ml offer, fixed initial price 180000, test taxonomy/audience and bundled placeholder on staging public disk. Reviewed CLI mapping preview, applied the exact pair, published through readiness operation, and repeated mapping with no duplicate identity/audit. Catalog/detail HTTP-context render **200**; external unauthenticated detail **401**. Browser visual review remains blocked by review Basic Auth, which was not changed. Owner sold-out/revert and real delivery latency remain pending; no operational source change yet. Evidence/retention: `docs/verification/p8-03/aoera-staging-test.md`. No application code, production, source-app database/frontend or local preview changes.

Latest continuation 2026-10-05 explicitly authorizes website staging SSH plus the existing signed-in Chrome hPanel session for the internal Node backend. Existing staging secret pair read into memory and reused (no rotation, output, clipboard or secret file); only three WEBSITE integration env variables added/applied, backend restarted on the same `921569b4` commit. Feed now **401 without key / 200 with key**. Database worker drained **452** unique snapshots across **200/200/52** pages to checkpoint **452**, has_more=false; **6** hidden tombstones retained. Website products remain **0**; no automatic product/price/media creation. Existing two minute crons proved execution by fresh advancing heartbeats; temporary schedule:work stopped, one actual PHP integration worker verified. Duplicate/older signed wakeups returned 202 and completed with unchanged snapshots/checkpoint. No filesystem permission, app database/frontend or website production changes. Evidence: `docs/verification/p8-03/README.md`.

Owner's continuation 2026-10-05 authorizes preparation for the next integration step. Remote deployment, credentials/permissions, synthetic staging records and the actual activation still require explicit Owner direction per repository instructions.

Owner's subsequent explicit activation request authorizes staging deployment/environment/worker setup and only the internal Node backend integration env/restart. No app database/frontend changes or website production cutover. Earlier browser/File Manager blockers were resolved by a fresh backend tab and explicitly authorized staging SSH. Random secrets remain outside the repository, with presence-only reporting. Operational instructions: `docs/runbooks/QAMMARIS_APP_OWNER_ENV_HANDOFF.md` and the value-free `tools/Manage-QammarisStagingSecrets.ps1` helper.

Outcome: demonstrate actual app webhook → database worker → checkpoint/audit → mapped public status on isolated staging, with 30-minute reconciliation and crash/retry recovery. P8-01/P8-02 review acceptance is a prerequisite to deployment.

In scope: staged release preflight and exact revision, protected Owner-configured credentials, approved staging ingress, dedicated persistent worker/scheduler, one approved synthetic pair, MySQL concurrency/page/revision/hidden/outage verification and evidence. Out of scope: production deployment, broad production mapping, automatic catalog/price/media writes, outgoing API or internal-app code changes.

Historical preparation (before explicit activation request):

- Local runtime presence checks: API key false, webhook secret false, client configured false; secret values were not printed. POST receiver/throttle and 30-minute schedule definitions confirmed.
- Integration/presentation regression rerun: `24 passed (186 assertions)`, isolated SQLite/fake HTTP. This is local proof, not upstream connectivity.
- Existing staging workflow applies unconditional Basic Auth and does not start/restart integration worker or install cron. Historical staging was verified 2026-09-15; current access/hosting state not confirmed. Two public HEAD targets failed at socket transport; no HTTP status was obtained, so no remote route/outage claim is made.
- Reviewable activation/launch plan, candidate URLs, runtime matrix, remaining three delivery stages and progress denominator in `docs/runbooks/QAMMARIS_APP_LAUNCH_READINESS.md`.

Earlier activation evidence (2026-10-05): deployed `1867d83` through successful CI/staging release after protected staging backup; pending additive migrations applied, baseline catalog/users empty and preserved. Secrets generated without output, encrypted outside Git and installed into protected website env/cache (0600). Basic Auth preserved at both rewrite levels; exact webhook URI bypasses human Basic Auth but Laravel POST/HMAC remains mandatory. Tested 401 unsigned, 422 signed invalid payload, 202 signed synthetic signal, worker retries and checkpoint retention at 0. Actual half-hour scheduler invocation observed at 15:00 UTC. Source both with/without key returns 503; no snapshots/products mapped. Full local suite 222 tests / 1487 assertions passed. Evidence: `docs/verification/p8-03/README.md`.

Acceptance remains IN_PROGRESS: selected AOERA mapping and automatic Owner sold-out/revert passed; authenticated available-state browser review and rollback mapped-state/revision guards passed; exact real source webhook/UI transition latency and remaining real-source runtime scenarios are pending. Watchdog restart recovery passed in about 34 seconds; actual half-hour reconciliation passed at **16:00 UTC**, worker success **16:00:04**, queue empty. Feed connection, MySQL pagination/drain, ingress/cache no-op and minute cron are now confirmed. Host SSH has no crontab binary; the existing hPanel crons supervise worker/schedule invocation, temporary schedule:work is stopped. Three historical failed queue jobs from the source outage are retained. Do not equate feed connectivity with full end-to-end/public launch readiness or begin P8-04/production cutover.

Activation data/media impact: dedicated staging additive schema, 452 source snapshots/checkpoint and synthetic queue signals; no catalog product/media/price writes, app database/frontend or website production changes. Retain staging backup/integration state; prefer forward fix after source data arrives. This continuation changes only operational documentation/screenshots in Git. Next action: finish remaining P8-03 browser/delivery and mapped-state runtime gates; the selected AOERA sold-out/revert cycle has passed. Next dependent item (not started): P8-04 reviewed launch identities, then separately approved P9-01 cutover with P1-04 backup preflight.

### P8-04 Feed-authoritative launch drafts and Shopee media — IN_REVIEW

Verification 2026-10-06 local: exact PHP overlay **bc470a7**, green CI **37350390737**, protected 22-table staging backup/gzip integrity, env/auth/assets/permissions unchanged; no migrations. Batch 1 creates **445 nonactive drafts**, six tombstones and existing AOERA skipped; **446 total connected records**, repeat apply no duplicate. Source **401/200**, checkpoint **456**, availability parity true. **518 website-stored real photos for 179 drafts**, all checksum/byte/MIME/dimension checks pass after bounded connection retries; image queue drained/worker exited, stock worker one, cron fresh, no schedule:work. **83 manual photo candidates / 183 unmatched** left for Owner. Full tests **234 / 1560**, final focused **20 / 112**, Pint/diff checks and CI passed. Real photo delivery checked at 1440x900 / 390x844; draft public 404/catalog exclusion passed. **0 new drafts ready to publish**: 196 missing category, 445 description/audience, 266 primary image, 130 offer/size. Review report has exact per-product blockers; no automatic publication. Details/limitations/rollback in `docs/verification/p8-04/README.md` and launch-draft runbook. Old local 180 catalog remains read-only matching reference; actual production mappings/storage/restore not confirmed. P9 follow-up: draft-only active taxonomy can appear in public filters with empty results; assess during approved launch acceptance, not fixed here.

Owner direction 2026-10-06: continue through P8-04; defer the real OTW test in P8-03. Feed cache (452 synchronized snapshots) is the launch source, not the old 180-product local catalog or Majoo Excel. This explicitly authorizes staging draft preparation and strong Shopee photo matching/acquisition; production cutover remains separate.

Outcome: one mapped website record per visible source UUID, new records always draft/nonactive; Owner can review missing size/concentration/price/media and source=app records before publication.

In scope: persisted immutable preview/apply audit, source and catalog stale guards, UUID idempotency, initial source price/name/brand, explicit size/concentration parsing, exact strong name/size photo matching, cover + first two additional downloads through existing safe media operations, read-only old-catalog mapping report, staging verification.

Out of scope: automatic publication, published product edits, source price updates applied to existing catalog, copying old catalog as launch data, ambiguous photo auto-match, inferred size/audience/description, production deployment, app code/database/frontend, credentials/permissions changes.

Dependencies: P8-01/02 deployed and P8-03 feed/webhook/reconciliation operational. P8-03 remains IN_REVIEW; deferred OTW, exact ingress/browser timing and independent concurrency proof remain explicit limitations, not claimed passed.

Risks: source names often omit size/concentration; such records remain incomplete drafts. Old slugs/media require reviewed matching during eventual production migration. Staging catalogue must not replace production DB wholesale.

Acceptance: preview records all source rows including hidden; apply creates only visible unmapped nonconflicting drafts; existing IDs/slugs/price/media remain; repeat apply no-op; stale/tampered preview rolls back; source=app flagged; ambiguous/unmatched photos retained for manual upload; cover and at most two additional files downloaded/validated/stored, no hotlinks; audit/review CSV and tests plus staged counts recorded. No schema change or production cutover.

### P8-05 Prioritized launch catalog review — IN_REVIEW

Owner direction 2026-10-06: continue toward launch and defer additional backups of the old catalog. Existing preservation obligations remain; this item does not authorize production cutover or destructive writes.

Outcome: prioritize the closest-to-complete feed drafts and provide a guarded maintenance template so Owner can complete factual descriptions/audience in bulk rather than editing 445 records individually.

In scope: fresh read-only staging capture of linked drafts, actual publication blockers and current row fingerprints; deterministic review ordering; Owner-editable review package and exact maintenance-v1 template; verification that untouched templates are no-op in the existing Laravel preview. Owner provided the fresh Shopee basic export `mass_update_basic_info_1853666049_20261006100429.xlsx` on 2026-10-06, authorizing factual description proposals by existing exact provider identity, with provenance and no automatic apply.

Out of scope: applying/publishing products, invented descriptions/audience/size, relaxed publication requirements, old-catalog copy, ambiguous matching, new admin accounts, migrations, additional old-data backup, production deployment, app changes, credentials or permission changes.

Dependencies: P8-04 staged drafts/media, existing P5 publication readiness and P6 maintenance snapshot/preview. P8-03 deferred runtime gates remain separately tracked.

Risks: source status changes can invalidate maintenance fingerprints; use fresh preview before any apply. Supplemental Shopee text cannot replace feed names/prices/status; existing provider pairing and current fingerprints must remain authoritative. Review priority does not mean approval to publish.

Acceptance criteria: all current linked visible drafts classified once, with no published/hidden record in templates; priority based on actual blockers rather than guessed facts; mandatory maintenance headers/ID/timestamp/fingerprint present; untouched template produces zero mutations and no unexpected validation errors; files remain private, formula-safe and traceable to capture; no catalog/media/production mutation.

Verification 2026-10-06: fresh guarded READ ONLY staging capture, 445 drafts classified once: 113 description/audience only, 66 with photos needing other data, 263 needing photos/data, 3 source=app review. Private workbook (113/332 rows) and maintenance templates created. Staging Laravel previews: 113 and 445 review/no-op rows, zero errors/changes; product/image/identity/batch/row counts unchanged. Local maintenance regression **14 passed / 139 assertions**. Recalculation, zero formula errors, disposable workflow-input checks, saved workbook IDs/UUIDs/fingerprints/UTC dates, dropdowns/panes and restored blank inputs checked; both sheet openings visually inspected. No MS Excel roundtrip proof, supplemental Shopee content or catalog apply/publish. No application/PHP/frontend/DB/schema/media/env/permission/production change and no additional old-data backup. Evidence: `docs/verification/p8-05/README.md`; workflow: `docs/runbooks/QAMMARIS_LAUNCH_CATALOG_REVIEW.md`. Next: factual completion with Owner review and existing guarded maintenance; do not auto-start cutover.

Shopee description follow-up 2026-10-06: Owner supplied the fresh basic export as the description source. All **371 source products** have descriptions; **179 visible drafts** have existing exact Shopee identity and unchanged media-baseline source name, **266** have no provider pairing. Description-only maintenance proposals preserve source text/current ISO guards; no name/price/size/audience/status/media changes. Fresh READ ONLY staging preview: **179 valid / 0 review / 0 errors**, only `deskripsi_produk` in changes, catalog-state fingerprint and products/images/identities/batches/rows unchanged. Source workbook SHA retained; CSV roundtrip/exact guard/proposed-field checks and four unsafe-capture rejection cases passed. No apply/publish, account, app/PHP/schema/environment/permission/production change. Private review: `storage/app/private/p8-05-review/shopee-20261006100429/verified-proposals/description-preview.csv`. Source approval fulfilled; exact bulk apply/publication remains a separate human decision. Evidence/runbook updated.

### P8-06 Curated copy and staged wave-one review — DONE

Owner direction 2026-10-06: use provided Shopee descriptions, strip emoji/READY STOCK/shipping/hashtags, infer audience only from clear source name/description and flag missing/conflicting facts. Launch in waves: first 113 structurally complete photographed drafts, leave 266 without photos as drafts, separate size/concentration corrections. Production DB backup is deferred until immediately before approved cutover.

Outcome: 113 draft products have cleaned source copy and evidence-backed audience where clear, with an accessible private visual review and corrections list. Publish in staging only after Owner reviews/approves the actual wave; production cutover is separate.

In scope: deterministic curation with provenance/removal/audience evidence, fixed wave-one product IDs and existing exact Shopee mappings, fresh guarded maintenance preview and transactional/idempotent description/audience-only draft apply with distinct Owner-authorized CLI audit; reuse existing Laravel operations without synthetic admin identity; private mobile/desktop visual review and source correction lists.

Out of scope: publishing before Owner review, guessing ambiguous audience, applying to missing-photo or non-wave drafts, product/price/slug/media/availability changes, relaxed publication gate, new admin accounts, migrations, extra old-data backup, production/app/frontend/environment/permission changes or cutover.

Dependencies: P8-04 existing source identities/media, P8-05 description source/preview, existing publication-readiness/maintenance actions. Production release remains dependent on reviewed approved subset and final staging checks.

Risks: ambiguous audience is not auto-filled; such products cannot publish until corrected. Source or catalog changes invalidate preview. CLI actor must be isolated from human admin maintenance and production; no generic machine write/API is introduced.

Acceptance: exact 113 cohort; copy cleanup preserves product facts/notes, gender has source evidence or explicit review flag; only description/gender draft fields change; transactional replay and stale/tamper/protected-record tests; existing 266 image-less products remain drafts; private visual review usable at 390x844/1440x900; short corrections separate and do not gate cohort. Owner review remains required for publication; no cutover.

Verification 2026-10-06: fixed 113 cohort from existing exact Shopee identities; cleaned copy and evidence recorded. Actual text already has zero emoji/READY STOCK/shipping/hashtags. 76 clear audiences, 34 unknown, 3 conflicts retained for Owner correction. Fresh READ ONLY guard comparison of all 445 drafts passed. CI **37405845254** on **326aafc269c18968a18542ef7f2fbe5889e5d440** passed; four allowlisted PHP files overlaid on staging without env/cache/auth/assets/mode changes. Baseline server Git remains 1867d83; code originals retained, no new DB backup. Persisted **batch 2 / launch-copy-v1**, 113 valid rows; exact draft apply changed 113 descriptions/76 genders and timestamps only; replay product/audit digest identical. 76 have zero publication blockers, 37 only audience. All 332 other drafts, AOERA fixture, 316 offers, 519 images, 625 identities and 113 cover checksums retained; 266 without photos remain drafts; users 0; checkpoint 456. Feed 401/200, has_more=false; one worker, no schedule:work, fresh minute cron heartbeats. Private review has 113 covers, copy/evidence/search/filter/corrections; also served behind existing staging Basic Auth (anonymous 401, Owner browser renders) at `/owner-review/p8-06-wave-one/`. Chrome 390x844 and 1440x900: no overflow/broken loaded images, search/clear/review/conflict/empty/reset/keyboard tested; both actual OTW snapshots retain Habis · Restok segera in review. Local full suite 242/1591; replay assertions then copy suite 8/33; 7 Python checks and reproducible 5-file artifact verified; Pint/diff checks passed. Evidence `docs/verification/p8-06/README.md`, ADR-023 and runbook updated. Owner review/corrections and exact publication approval remain pending; no product publish, schema/production/app/credential/permission changes, database backup or cutover.

Owner follow-up after P8-07: continue the current photographed catalog; all 95 image-less drafts remain untouched. Existing launch-copy guards are unchanged: only structurally complete connected, visible Shopee drafts with clear blank audience receive proposals. Current cohort 175 (122 clear / 49 unknown / 4 conflicting). Persisted batch 5 verifies 46 valid gender-only changes with exact row guards/source CSV hash; apply and replay passed. Full 446-product comparison allows only those 46 gender/timestamp changes; 400 other product records and all descriptions/IDs/slugs/prices/316 offers/1,017 media/796 identities/source snapshots/publication retained. Actual readiness increases 76 -> 122; 53 audience-only and 175 photographed structural-gap drafts remain held, plus 95 without photos. Protected review at `/owner-review/p8-06-launch-ready/` includes 175 verified website covers and an exact 122-product guarded publication list (SHA c9eda30849a7e439fdcdeb6be4cd958e6f5f71a146da81b0550b652d35b48013); real Chrome download bytes match. Directory and six files anonymous401. Chrome 390x844/1440x900: counts122/53/4, search/empty/reset/descriptions/focus pass; zero overflow/broken loaded images/external images/console errors. Local 22 maintenance/copy tests/172 assertions and 12 Python checks pass; JS syntax/whitespace and CI37412582498 on04c608d pass. Health04:14:55UTC:401/200,checkpoint456/has_more=false,one stock worker,queues0,fresh cron,no schedule:work/image workers,users0. No application PHP/schema/package/env/auth/existing permission/production changes, media writes, extra DB backup, publication or cutover. Review/source data stays private; screenshots/evidence/runbook/ADR updated. Owner approval of the concrete 122-product list is the next publication gate, not an inferred approval from earlier continuation.

Publication completion 2026-10-06: Owner explicitly approved the exact 122-product CSV above. Fresh capture confirms all 122 timestamps/fingerprints/readiness and mappings unchanged. A fixed staging-only operator script reuses `PublishProduct`, persists batch 6 (`launch-publish-v1`, null human actors, 122 before/after rows), rechecks source/identity/media guards under ordered locks and applies atomically. Successful rehearsal rolls back all writes; deliberate failure on the last row also leaves products/source/audit unchanged. Apply 122 and replay passed with identical applied-audit digest. Before/after comparison confirms only publication fields/timestamps on the exact 122; all 324 other product records, 316 offers, 1,017 images, 796 identities and 452 source snapshots/checkpoint retained. Public scope contains exactly approved IDs plus existing fixture: 123 published / 323 drafts; all 95 image-less remain drafts. Draft routes with/without photos return 404. Chrome CSS viewports 390x844 / 1440x900 verify catalog, search 9 PM=2, gallery/copy, status filters 40 Habis / 83 Tersedia, empty/reset, pagination 25–48, restock link and inquiry 2 units/Rp1,196,000. Own inquiry removed; no WhatsApp message sent. Zero overflow/broken loaded/external photos/application console errors; browser-extension deprecation warnings are identified separately. Local 42 tests/319 assertions, production refusal, PHP lint/Pint/whitespace and real MySQL rehearsal/replay pass. Health 04:31:30 UTC: feed 401/200, checkpoint 456/has_more=false, cron 28 seconds old, one stock worker, queues 0, no schedule:work, users 0; protected env/config/auth/modes and all media checksums retained. No application deployment, schema/package/env/auth/permission/production/app changes, additional DB backup or cutover. Evidence/runbook/ADR/business rules updated. P8-06's approved staged wave is complete; other draft facts and deferred P8-03 OTW/timing gates remain separately tracked. Recommended next item P1-04/P9 production preflight requires Owner direction; not started.

Owner named-product continuation 2026-10-06: explicitly approves Bali Cliff 1 Pria (37/100 ml), Toffee Coffee EDP Unisex (existing100 ml), Nero Unisex100 ml, ILIAD Unisex30 ml, and their staging publication. Five fixed records only (29/34/35/36/350); preserve prices, stock, descriptions, media, URLs and identities. Preview/audit, atomic rehearsal, application and no-op replay through existing product operations; no account or production changes. Owner also reports existing OTW labels on staging verified and accepted; new timed source-app OTW exercise is not claimed.

Named-product completion: batch7 applied5, replay no-op;128 published(127launch + fixture),318 drafts including95 without photos. Only approved fields and two missing offers changed; prices/URLs/copy/stock/media/identities and441 other records retained.37 tests/269 assertions, lint/Pint, production refusal, real MySQL rollback rehearsals and mobile390x844/desktop1440x900 checks passed. Feed401/200, checkpoint456/has_more=false, cron/worker healthy, all1017 media integrity retained. Owner accepts existing OTW display and requests no further testing; a fresh timed OTW exercise is not claimed. Evidence docs/verification/p8-06/README.md and owner-followup/. Production preflight/cutover not started.

Owner enrichment continuation2026-10-06: approves proceeding with the exact49 candidates listed in the175-row enrichment check. Fill only blank category/audience and eight missing size offers at unchanged current prices, then readiness-gated staging publication. Preserve brands/names/copy/prices/URLs/media/availability/identities; remaining121 enriched-review rows plus other drafts unchanged. Immutable preview/audit, source/row guards, single rehearsal/apply/replay and focused actual catalog/browser verification; no additional broad test suite or next phase.

Enrichment wave completion2026-10-06: exact49 approved candidates applied/published via batch8 owner-enrich-v1;41 blank categories/49 audiences/eight missing offers, unchanged prices.177published(176launch+fixture),269drafts including95withoutphotos; remaining121 enriched-review rows retained. Preview exactly inspected, one real MySQL rehearsal rolledback, guarded apply/replay digest-identical, production refusal/PHP lint/Pint and full446 before/after checks pass;397 other products/all318existingoffers/1017media/796identities/452sources preserved. Current API401/200,checkpoint456/has_more=false,cron/stockworker healthy. Chrome390x844/1440x900 shows177, LiquidBrun150ml/Rp769000/search1, no overflow/broken loaded/external images and inspected-tab errors0. Owner speed instruction: no additional broad suite/build/failurecycle. Evidence enrichment-wave/ andREADME; docs/ADR/runbook updated. No application deployment/schema/env/permission/production change or extraDBbackup. Next production preflight/cutover not started.

Owner accepted-review continuation2026-10-06: explicitly accepts all121 remaining enrichment proposals, including estimated sizes/concentrations and eight Extrait->EDP conflicts; unclear/default/estimated gender becomes Unisex. Publish exact121 photographed connected drafts after readiness gate; allow only the two proposal taxonomy names bodyspray andPerfumeOil when missing. Preserve names/brands/copy/prices/URLs/images/stock/identities/oldoffers and all95 no-photo drafts. Previously separate53 audience-only drafts remain outside exact121 list. Guarded immutable preview/audit, one rehearsal/apply/replay and narrow actualdata/browser checks; no broad tests orproduction cutover.

Accepted-review completion2026-10-06: batch9 owner-accept-v1 publishes all121 exact approved drafts after immutable preview inspection, one real MySQL rollback rehearsal, atomic apply and no-op replay.52 unclear/default/estimated audiences become Unisex;92 blank categories and8 approved Extrait->EDP overrides,65 missing offers at unchanged current prices, two new categories bodyspray/Perfume Oil. Current298 published=297 launching+unchangedfixture,148drafts=95no-photo+53 separate audience-only. All325 other product records,326 existing offers,1017 images,796 identities,452 source snapshots/checkpoint457 and original categories retained. Lint/Pint/production refusal and complete data comparison passed. API401/200,has_more=false, cron44 seconds old,one stock worker,no schedule:work,empty queues; protected config/auth/media retained. Chrome390x844/1440x900 proves count298, new category filters and existing stock labels/covers; screenshots/data evidence accepted-review/. Owner accepts proposal uncertainty; existing copy/name mentioning Extrait may remain despite approved EDP category. No broadtests/build/application deployment/schema/account/env/permission/production/source-app change or extra backup. P8-06 exact121 wave complete; P1-04 production preflight/cutover is separate, not started.

Owner final gender approval 2026-10-06: publish exactly the 53 remaining photographed gender-only drafts using the supplied explicit ID->Pria/Wanita/Unisex list (30 Unisex, 9 Pria, 14 Wanita). Preserve names, original copy, categories, prices, all offers/media/stock/URLs/identities and all 95 no-photo/no-description drafts. Persist immutable preview and nullable Owner-approved operator audit; one transactional rehearsal, atomic apply/replay and narrow actual data/browser verification. No broad tests or next phase/cutover.

Final gender completion 2026-10-06: exact 53 Owner-listed genders (30 Unisex / 9 Pria / 14 Wanita) applied and published through fixed staging operator and existing PublishProduct. Batch 10 owner-gender-v1 preview inspected against exact manifest/before snapshots; one real transaction rehearsal and full rollback check, atomic apply/replay passed. 351 published = 350 launching + fixture; remaining 95 drafts all lack photos/descriptions. All 393 other records, 391 offers, 1017 media, 796 identities, categories and 452 sources/checkpoint 457 retained. PHP lint/Pint/production refusal and full data/audit checks pass; Chrome 390x844/1440x900 proves count 351 and newly published catalog entries. API 401/200, cron 33 seconds old, one stock worker, no schedule:work, empty queues; protected config/auth/media retained. Original name/copy audience wording is preserved even if Owner's final gender differs. No broad test suite/build, schema/application deployment/account/env/permission/source-app/production change or extra backup. Evidence audience-final/README, rules/ADR/runbook/current architecture updated. P8-06 approved photographed catalog complete; recommended P1-04 preflight/cutover remains separate and not started.

Unrelated observation for later preflight: during final-wave browser checks, a search-button action on catalog page 3 left the URL unchanged. Direct search GET and subsequent Unisex filter worked. Cause/reproducibility Not confirmed; not investigated or fixed during the data publication task. No confirmed product-publication defect.

### P8-07 Owner-approved Shopee SKU pairing and candidate review — IN_REVIEW

Owner direction 2026-10-06: use the app team's 371-row mapping CSV. Only 9 `sku` and 289 `kuat` rows may pair automatically through exact current feed SKU to UUID. Present 26 `perlu_cek` and 30 `ambigu` rows with their supplied candidates for Owner selection. Keep 17 unmatched and other unpaired products as drafts; never publish automatically.

Outcome: additional visible staging drafts receive reviewed Shopee identity, cleaned description and website-stored cover plus at most two additional images; actual photo counts and missing SKUs are reported.

In scope: bounded staging CLI contract; immutable preview/apply audit; exact unique SKU/UUID and existing identity guards; retain existing copy/media/IDs/slugs/prices/status; reuse safe image downloader/queue; private candidate-selection review with explicit export, no automatic candidate application; actual staging verification.

Out of scope: published fixture edits, publication, production cutover, inferred/missing/duplicate SKU matching, replacing existing media or descriptions, audience/taxonomy/offer changes, migrations, packages, accounts, credentials, app changes or additional database backup.

Dependencies: P8-04 mapped drafts/image operations and P8-06 copy cleanup; current synchronized app snapshots; Owner-provided media/basic workbooks and mapping CSV.

Risks: approved rows may include protected records, changed SKUs, duplicate identities or failed downloads. Such records are reported without forcing a match; candidate choices require a fresh guarded preview before a later apply.

Acceptance: approved rows only; missing/nonunique/hidden/protected/conflicting rows unchanged; draft writes transactional and replay no-op; safe cover-first downloads, existing media retained; all 56 review rows/candidates visible and selectable; anonymous review requests remain 401; actual counts verified; desktop/mobile review and meaningful regression tests recorded. Production and publication remain separate.

Verification 2026-10-06: unchanged 371-row Owner CSV (9 sku/289 kuat/26 perlu_cek/30 ambigu/17 tidak_ketemu) and exact media/basic IDs/names. All 298 approved SKUs and all 102 candidate references resolve in current feed; missing SKU **0**. Approved set includes **297 drafts + 1 published AOERA fixture**, retained unchanged. CI **37408533984** on **f199ccf** passed. Four allowlisted PHP files plus one constant forward fix overlaid without schema/env/auth/cache/assets/existing mode changes. MySQL preview initially rejected an overlong contract name; transaction left 2 batches/565 rows and catalog unchanged. `qammaris-pairs-v1` fits existing varchar(20), no migration. Persisted **batch 3: 371 rows / 297 valid / 74 held**; exact source/target/proposal/preapply comparison passed. Apply **118 new Shopee identities, 184 blank descriptions**; replay product/audit digest identical. **342/342** new product images stored and checksum/byte/MIME/dimension verified, **519** original file checksums/metadata retained. Actual **297 drafts with photos, 148 without**, 445 draft/nonactive + existing fixture; all IDs/slugs/offers/stock/audience/publication retained. Review at `/owner-review/p8-07-pairing/` has 56 verified covers/102 candidates; one transient cover connection failure retried successfully. 35 occupied candidate references flagged (including two rows whose candidates all need conflict review), selection does not rebind. Anonymous directory/four files **401**, Owner Chrome renders at390x844/1440x900. Search/SKU/status26/30/empty/reset/clear/keyboard/focus/choice/download/duplicate-UUID export rejection passed; own test export captured/removed from Downloads, choices cleared. No overflow, broken loaded images, external image links or console errors. Full local **251 tests/1644 assertions**, after contract fix focused **9/53**; **6 Python** checks, JS syntax/Pint/diff passed. Feed **401/200**, checkpoint456/has_more=false, cron fresh, one actual stock PHP worker, no schedule:work or image worker, queues empty; no new accounts/database backup/production/app changes. Evidence `docs/verification/p8-07/README.md`, ADR-024 and pairing runbook. Owner selection/photo/content acceptance remain pending; no automatic publication or cutover.

Owner follow-up 2026-10-06: supplied 53 explicit candidate choices; three deliberately unselected remain held. Apply only fresh, exact, nonconflicting choices through a separately audited preview within P8-07. No publication or rebinding. Verification: commit8209387 / CI37411318967 passed; full local255 tests/1671 assertions, focused13/78, Pint and whitespace checks passed. Two allowlisted PHP files overlaid with exact hashes and existing modes/config/auth retained. Batch4:53 selected/53 valid/0 held, compared exactly to Owner export and original review; applied53 descriptions+53 identities, replay digest identical. All156 photos stored (one failed cover plus two blocked additions successfully retried). Original861 media bytes and metadata retained; source snapshots, all446 product IDs/slugs/other fields and316 offers unchanged. Staging350 photographed drafts/95 without,445 drafts+1 fixture;76 fully publication-ready. Photographed cohort still274 audience,136 category,75 offer blockers (overlap); do not imply350 ready to publish. Three unselected: Des Tentations For Men, Rasasi Shuhrah Pour Homme, Khadlaj Island Dreams. Health03:59:11UTC:feed401/200,checkpoint456/has_more=false,no sync error,cron9 seconds old,one stock PHP worker,no image/schedule:work workers,queues0,users0. No UI/publication/schema/production/env/credential/account change, no new DB backup. Owner content acceptance, P8-06 remaining factual completion and separately approved publication/cutover remain next.

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


P1-04 follow-up to the final-wave search observation: Chrome1440x900 search-button submission from page3 after page load navigates to the correct 9 AM Dive query/result; Chrome390x844 filter-dialog MALEALI submission also works. No captured console errors. Prior issue not reproduced; original cause Not confirmed. No code fix claimed; screenshots/browser-checks in P1-04 evidence.


P1-04 Owner override after preflight: legacy production data may be discarded and no backup is requested. Launch a separate fresh target for350 public+95drafts instead of merging old catalog/accounts/content/URLs. Legacy DB/schema/URL pairing is no longer a gate; earlier preflight merge/backup requirements are superseded. Leave legacy directory/database untouched for reversible routing, no deletion needed/performed. Minimal hosting inspection, guarded target transfer implementation/preview, exact target/env/admin/runtime authorization and public cutover remain. Rules, ADR001, master plan and current runbook updated; no production change or backup. Continue P1-04 only.


P1-04 continuation2026-10-06 after “lanjutt gas”: minimal production hosting read-only scope granted/completed, no bootstrap/DBread/serverwrite. Publicroot `/home/u429527638/domains/qammarisparfum.id/public_html` loads sibling laravel_app; publicstorage links its storage/app/public. CLI PHP8.2.33, requiredextensions, lockLaravel12.39.0; installed/web runtime/MySQL/hPanel cron/worker Notconfirmed. Actual application envstatuses app/DBterisi, APIkey/webhooksecrettidak; no values emitted/persisted. Frozen private localreleasepacket candidate85ef088:241curatedsource/staticfiles,complete8assetbuild+manifest,all1016verifiedphotoJPEGs,fresh445catalogpreview+entryadapter/checksums,total79,572,205bytes. Fresh350public+95draft/390offers/795identities(445qammaris_app)/36brands/5categories, no readiness/orphan/UUID/slug/offer failures, onlytwo viewcounts/timestampschanged. No fixture/accounts/runtime/env included; no legacy backup/migration/deletion. Publicmetadata/evidence/runbook/ADRupdated. RemainingP1-04: guarded targettransfer implementation/preview, exact production database/env/admin/runtime preparation authorization, verifiedtarget and separatelyapproved cutover/webhook switch. No nextitemstarted or broadtests/build/install.

## P1-04 authorized production preparation — 2026-10-06

Owner authorizes a separate fresh production target, GitHub auto-deployment preparation and Owner admin admin@qammaris.com. Owner explicitly permits sending that new admin password in chat after successful account creation; API/webhook secrets remain confidential. Public cutover and backend webhook destination switch remain final concrete approval gates.

Created releases/qammaris-85ef088 and new runtime directories under the production domain, plus protected0600 env: production/debugfalse/HTTPS sessions/fresh APP_KEY and the same staging API credential pair read in server memory. Legacy files/database/routing/permissions untouched. Owner created u429527638_qam_launch in hPanel; row verified. Database credential installation, account creation and target transfer are still pending.

New catalog:bootstrap-launch accepts only the reviewed350public+95draft/1016photo cohort in the approved fresh MySQL target/releases path. Persists an audited preview, verifies UUID/offer/media ownership and checksums/MIME, remaps IDs and reuses existing offer/identity/media/publication operations. Refuses existing catalog, requires exact preview for apply, supports rollback rehearsal and idempotent replay. Does not copy users/checkpoints/queues/sessions/fixture1. Focused6tests/37assertions passed; PHP lint, Pint and whitespace passed. No schema/dependency/public UI changes. Production apply not yet run.

Production release workflow builds locked Composer/Vite and curated source artifact without env/media/runtime/database/accounts. Main green CI plus PRODUCTION_DEPLOY_ENABLED=true and server .production-active gate recurring deployment; feature builds cannot deploy. Planned shared protectedenv/storage + per-commit releases/current link retain prior code and runtime. Deploy rejects pending migrations and reverts code after failed /up. Workflow/SSH setup/actual build and deployment remain to verify. Adding workflow is not activation. Initial artifact commit supersedes the older85ef088 source packet once CI succeeds. Continue P1-04, not another backlog item.

P1-04 target preparation verified2026-10-06: GitHub artifact7632361408c72d43b0ec07b3c38d6640d918962a built successfully in run37427677886; CI37427681959 passed. Official artifact digest and inner archive SHA verified. Source/vendor/build installed to its separate release;22 existing migrations in new qam_launch database, no seeds/legacy writes. Audited preview1 validated445rows/1016photo manifests. Actual MariaDB11.8.9 rehearsal rolled back all business rows, then final apply350public+95draft/390offers/795identities/36brands/5categories; replay exact business-table digest unchanged. New Owner admin admin@qammaris.com created and hash verified; password only in session memory for Owner delivery, not documentation/files. Secure clipboard handoff explicitly authorized; credential read into memory and protected sharedenv only. Initial database-worker feed401without/200with key,452snapshots/checkpoint457/has_morefalse,350public/95draft/0hidden,242available/108soldout,queue0/failed0/noerror. Backend webhook still points staging. Public routing remains legacy.

Owner explicitly approved GitHub production environment and storage of existing deployment SSH key after automatic approval review rejected the earlier general authorization. Production secrets now terisi, branch policy main only; repository PRODUCTION_DEPLOY_ENABLED=false. No server auth/key/permission changes or public cutover. Current internal link points newrelease, persistent protectedenv/storage shared; two value-free worker/scheduler scripts prepared and bash syntax passed. hPanel cron page under production domain actually displays staging entries too: treat it as account-wide and preserve both. Add only distinct production commands, never remove/duplicate staging cron.

## Final target checkpoint — 2026-10-06

Current evidence: docs/verification/p1-04/PRODUCTION_TARGET_PREPARATION.md and production-cron-20261006.png. Target source3538c72 is GitHub-built/CI-green;350public+95draft,1016verifiedphotos,Owner admin created,initiallivefeed452snapshots/checkpoint457/errornull,onePHPworker/zeroqueue/failed/schedule:work. Two production minute cron entries saved exactly once, alongside untouched staging entries; both heartbeats07:28:02UTC. public_html_next points current/public but legacy remains served. Web-context kernel checks pass; a CLI-only footer500 was a verification-context artifact, no application fix. Public HTTPS/browser/static-media/adminlogin and source outbound production delivery remain unproved until activation.

PR2 is retargeted to main for a concrete full launch review; the sole add/add conflict with main's older staging workflow retains the complete staging-tested workflow. Main is not merged/changed. Production artifact packaging now includes explicit directory modes and recurring extraction preserves them; otherwise private deployment umask would make public assets unreadable. Existing legacy/staging permissions remain unchanged. Only newly created target traversal/public-photo modes still require final activation approval; env/config credentials stay0600 and private data stays private. Initial public switch, main promotion/auto-deploy enable and only Node backend WEBSITE_WEBHOOK_URL change remain pending. No new backlog item.
