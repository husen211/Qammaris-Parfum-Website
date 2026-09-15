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
| P2 | Tests & catalog safety | IN_PROGRESS | P1-01–P1-03 | Perilaku existing aman dan terlindungi regression tests |
| P3 | Product domain & migrations | BACKLOG | P2 | Struktur data sesuai business rules tanpa kehilangan identitas |
| P4 | Media storage | BACKLOG | P1, P2 | Media menggunakan storage abstraction dan migrasi terverifikasi |
| P5 | Admin Panel V2 | BACKLOG | P3, P4 | Pengelolaan katalog lengkap tanpa phpMyAdmin |
| P6 | Import/export & audit | BACKLOG | P3, P5 | Bulk workflow aman, idempotent, dan dapat dilacak |
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
