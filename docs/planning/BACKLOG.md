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
| P2 | Tests & catalog safety | BACKLOG | P1 | Perilaku existing aman dan terlindungi regression tests |
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

### P1-03 Staging topology dan hPanel Git — IN_REVIEW

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
- Workflow manual `Staging release` telah disiapkan untuk memverifikasi SHA source yang dipublikasikan hPanel, membangun/mengunggah asset Vite, menjalankan migration additive, memastikan storage link, dan mengoptimalkan Laravel. Workflow belum dapat dijalankan sebelum environment GitHub `staging` diberi SSH deployment key terbatas serta known-host verification.

Review checkpoint berikutnya:

- Konfigurasikan deployment key dan GitHub Environment `staging`, jalankan workflow terhadap revision staging yang sudah dipublikasikan hPanel, lalu verifikasi bahwa source, asset, migration, storage, cache, dan HTTP Basic Auth tetap sehat. Jangan mengubah production.

### P1-04 Production backup dan cutover preflight — BACKLOG

Membutuhkan staging hijau, backup production terbaru, recovery evidence, serta approval owner. Tidak boleh dimulai dari development lokal.

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
