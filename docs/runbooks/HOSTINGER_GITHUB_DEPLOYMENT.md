# Hostinger melalui GitHub — deployment dan riwayat

**Status current (AUD-02, 2026-10-07):** workflow production GitHub sudah aktif/diamati; bukan rencana belum terpasang. [Cutover proof](../verification/p1-04/PRODUCTION_CUTOVER.md) dan [P9 observation](../verification/p9-01/README.md) mencatat revision serta batas verifikasi pada tanggalnya.

Routine code/asset delivery uses CI plus `.github/workflows/production-release.yml` and `tools/hostinger/` SSH scripts, with per-commit releases and protected shared env/storage. File Manager is not required for normal releases; Git is not storage for secrets, DB, or uploaded photos. Successful main-push CI, enable flag, production environment and server activation/revision/pending-migration guards apply. Even docs-only main merges can deploy; explicit Owner release approval is still required. This documentation task does not change pipeline/settings or authorize deployment.

The staging setup, initial pending steps, R2 rehearsal and launch checkpoints below are retained **dated history**. Later activation evidence supersedes their pending/off statements. The Owner's legacy-backup waiver applies to the exact 2026-10-06 launch, not future destructive data changes. Current runtime steps/health/worker recovery: [QAMMARIS_APP_INTEGRATION](QAMMARIS_APP_INTEGRATION.md), [SHOPEE_ADMIN_IMPORT](SHOPEE_ADMIN_IMPORT.md) and the code scripts. General recovery/data protections in AGENTS remain; do not repeat old hPanel setup or cutover instructions to redeploy a current release.

Current production preparation (2026-10-06): [P1-04 preflight](PRODUCTION_CUTOVER_PREFLIGHT.md) records the 350-product launching manifest, media checksums, source/build differences and fresh-target/cutover gates. Owner now waives legacy data migration and backup; historical backup/preservation rules below are superseded for this release. Historical staging counts below are not the current launching baseline. This runbook does not authorize production activation.

## Tujuan

Menjadikan repository GitHub sebagai source of truth source code Qammaris tanpa menjadikan Git sebagai penyimpanan `.env`, database, atau upload media.

## Kondisi staging yang sudah dibuktikan (2026-09-15)

- Website terpisah tersedia pada `staging.qammarisparfum.id`; website production lama tidak diubah.
- PHP staging menggunakan versi 8.2.
- Directory `/` pada staging dilindungi HTTP Basic Auth. Tanpa credential menghasilkan HTTP `401`; dengan akun review menghasilkan HTTP `200`.
- Database dan user MySQL khusus staging telah dibuat dan tidak memakai database production.
- hPanel Git terhubung hanya ke repository `Qammaris-Parfum-Website`, branch `modernization/phase-1-foundation`.
- Deployment manual commit `240f94e` ke `public_html` selesai dalam 16 detik. Commit layout `8dc28df` kemudian berhasil dipublikasikan.
- Build log membuktikan hPanel menjalankan `composer install --prefer-dist --quiet --no-interaction`, lalu publishing.
- hPanel tidak menyediakan Node/npm dan build log tidak menjalankan Vite. Asset dibangun secara reproducible dengan Node `22.20.0` dan npm `10.9.8` di lokal, lalu artefak `public/build` diunggah ke staging.
- Auto-deploy sempat aktif secara default dan mempublikasikan commit `8dc28df`, lalu dinonaktifkan kembali serta diverifikasi dalam kondisi off.
- Root `.htaccess` berhasil mengarahkan fixed document root `public_html` ke entry point Laravel pada `public/`.
- `.env` staging dibuat langsung di server dengan permission `0600`, `APP_ENV=staging`, `APP_DEBUG=false`, dan database khusus staging. Nilai secret tidak dicatat di Git.
- Laravel `12.69.2`, PHP `8.2.33`, dan Composer `2.8.9` terverifikasi pada staging.
- Seluruh 12 migration berjalan pada batch 1 di database staging baru. Tabel bisnis terverifikasi kosong; tidak ada seeder atau import data.
- Storage link dibuat melalui shell karena fungsi PHP `exec` dan `symlink` dinonaktifkan oleh Hostinger.
- `config`, `route`, `view`, dan framework metadata cache berhasil dibuat melalui `php artisan optimize`.
- Health check terautentikasi: homepage, katalog `/products`, `/login`, dan `/cart` menghasilkan HTTP `200`; `/admin` menghasilkan redirect `302` yang benar ke `/login`; asset CSS menghasilkan HTTP `200`.

Tidak ada migration atau cutover production yang diizinkan oleh runbook ini.

## Aturan cutover domain utama

Website yang sekarang melayani `qammarisparfum.id` tidak dihapus sebelum seluruh gate berikut terpenuhi:

1. backup terbaru file production dan database telah dibuat serta diunduh;
2. staging baru lulus health check katalog, detail produk, login/admin, asset, media, dan inquiry WhatsApp;
3. struktur release Laravel, install dependency, build asset, migration, serta rollback sudah diuji;
4. commit release dan database/media yang akan digunakan sudah dicatat;
5. owner memberikan approval cutover secara eksplisit.

Saat cutover, domain yang sama tetap digunakan. Yang diganti adalah deployment aplikasinya, bukan registrasi domain atau DNS tanpa alasan yang telah diverifikasi. Deployment legacy dipertahankan selama rollback window; cleanup menjadi tindakan terpisah.

## Target staging

Pilihan utama adalah website terpisah pada `staging.qammarisparfum.id`. Jika paket tidak mendukung website/subdomain tambahan, gunakan temporary domain Hostinger. Staging wajib mempunyai:

- PHP 8.2 atau lebih baru yang kompatibel dengan lockfile;
- database dan user database khusus staging;
- `.env`, `APP_KEY`, session, cache, dan storage yang terpisah dari production;
- HTTPS serta perlindungan dari indexing/search engine;
- direktori target kosong yang tidak berbagi `public_html` dengan production legacy;
- auto-deploy nonaktif sampai seluruh deployment proof selesai.

## Target alur

```text
feature branch
  -> pull request
  -> CI Laravel tests + frontend build
  -> review
  -> merge deployment branch
  -> staging deployment
  -> health check + migration preview/review
  -> approval owner
  -> production deployment
  -> observation + rollback bila perlu
```

## Staging release terkontrol

Shared hosting Hostinger pada staging ini tidak menyediakan Node/npm atau deploy hook kustom. Karena itu, source code tetap dipublikasikan oleh hPanel Git, sedangkan workflow GitHub Actions `Staging release` menyelesaikan bagian yang tidak dapat dilakukan hPanel:

1. owner menekan deploy hPanel untuk commit yang sudah lulus CI;
2. owner menjalankan workflow GitHub Actions secara manual dengan SHA penuh commit yang sama;
3. workflow membuktikan revision remote sama persis sebelum menyentuh asset;
4. workflow menjalankan migration additive dan memastikan storage link ada sebelum asset diganti;
5. workflow membuat Vite asset dari `package-lock.json`, mengunggah archive sementara, mengaktifkan `public/build` melalui rename pada server, memasang ulang Basic Auth staging bila file credential server tersedia, lalu menjalankan `php artisan optimize`.

Workflow tidak mengubah production, tidak menjalankan seeder, tidak mengirim `.env`, dan tidak menimpa media pada `storage/app/public`. Jika revision di staging berbeda dengan input, workflow gagal sebelum upload asset.

### Konfigurasi satu kali di GitHub

Buat GitHub Environment bernama `staging`, lalu simpan secret berikut hanya pada environment tersebut:

- `STAGING_SSH_HOST`
- `STAGING_SSH_PORT`
- `STAGING_SSH_USERNAME`
- `STAGING_SSH_PRIVATE_KEY`
- `STAGING_SSH_KNOWN_HOSTS`

Tambahkan environment variable `STAGING_PATH` dengan path absolut release staging. Nilai credential tidak boleh ditaruh dalam workflow, dokumentasi, commit, atau log. SSH key khusus deployment wajib berbeda dari password login owner dan dapat dicabut terpisah.

**Batas scope saat ini:** pipeline hanya menerima path staging melalui environment variable dan key deployment terpisah dari password owner. Pada 2026-09-15, audit filesystem account Hostinger yang disetujui owner menemukan hanya domain `qammarisparfum.id` dan `staging.qammarisparfum.id`; workflow tetap tidak memiliki langkah atau secret path production. Key dapat dicabut dengan menghapus public key terkait dari `~/.ssh/authorized_keys` dan menghapus secret private key dari GitHub Environment `staging`.

Hasil akhir workflow menyisakan folder `public/build.previous-<timestamp>` sebagai rollback asset cepat. Folder itu tidak boleh dibersihkan otomatis; pembersihan memerlukan retention policy terpisah.

### Bukti release pertama

Pada 2026-09-15, workflow GitHub Actions `Staging release #1` selesai sukses dalam 43 detik untuk SHA `8dc28dfd2567c992b7277e471df6985633ea0891`, setelah hPanel telah mempublikasikan SHA yang sama ke staging. Pemeriksaan pasca-rilis langsung di staging membuktikan hal berikut:

- `public/build/manifest.json` tersedia;
- `public/storage` adalah symlink;
- seluruh 12 migration berada di batch 1 dengan status `Ran`;
- file Basic Auth dan aturannya tersedia; HTTP tanpa credential menghasilkan `401`.

Tidak ada source, migration, data, media, atau konfigurasi production yang diubah. Checkout Git staging menampilkan perubahan yang memang diharapkan dari release (`.htaccess` Basic Auth dan backup `public/build.previous-<timestamp>`). Dua file kecil lama di root checkout bernama `2` dan `20` ditemukan saat inspeksi; keduanya tidak disentuh karena di luar scope release dan perlu keputusan retention/cleanup terpisah.

Workflow `Staging release #2` kemudian dijalankan ulang untuk SHA yang sama dan berhasil dalam 35 detik. Setelahnya, migration pending berjumlah nol dan manifest build, symlink storage, serta respons anonim `401` tetap valid. Untuk membuktikan recovery asset, folder build backup dengan manifest berbeda diaktifkan sementara melalui rename atomik, tervalidasi, lalu build aktif dipulihkan. Dua folder rollback asset dipertahankan; tidak ada folder dihapus dan tidak ada source, database, media, atau production yang diubah.

### Bukti rehearsal storage R2

Pada 2026-09-16, hPanel mempublikasikan commit `11aed38f63b762aef8b8987e5fa6317e538a3805` hanya ke staging dengan auto-deploy tetap nonaktif. Workflow `Staging release #4` run `35071927012` kemudian sukses dan membuktikan alur cutover sementara berikut:

- permission `.env` dikunci ke `0600`, backup disimpan privat, dan konfigurasi awal terbukti memakai disk `public`;
- `PRODUCT_MEDIA_DISK=r2` aktif hanya selama rehearsal;
- sample existing dari 19 object migrasi lolos verifikasi ukuran, checksum, HTTPS `200`, dan MIME;
- upload PNG sintetis melalui `ProductMediaStorage` lolos read/public-delivery lalu dibersihkan;
- database staging tetap `0 products : 0 product_images` dan jumlah object produk tetap `19`;
- `.env` asli dipulihkan dengan hash identik dan disk aktif akhir terverifikasi kembali `public`.

Percobaan sebelumnya, run `35071287366`, gagal pada guard permission sebelum backup/cutover dibuat. Guard diperbaiki menjadi normalisasi permission yang portable dan cleanup trap dipertegas tanpa mencetak secret. Production, DNS production, dan bucket operasional Qammaris App tidak disentuh.

**Batas rollback source yang ditemukan:** checkout Git hPanel di staging berada pada detached, shallow snapshot; `git log` server hanya memuat SHA aktif `8dc28df`. Jangan menjalankan `git checkout`, `reset`, atau rollback source dari filesystem staging. Recovery source harus memakai riwayat deployment/ref yang terbukti tersedia di hPanel, atau branch/tag rollback sementara yang disetujui dan dipublikasikan melalui prosedur hPanel yang sama.

Untuk menyediakan ref recovery yang eksplisit, branch `staging/known-good-8dc28df` telah dipush dan diverifikasi menunjuk ke SHA staging sehat `8dc28dfd2567c992b7277e471df6985633ea0891`. Branch itu belum dipilih pada konfigurasi hPanel dan tidak memicu deployment. Pada release source berikutnya, branch tersebut dapat dipilih/dipublikasikan kembali melalui hPanel jika recovery diperlukan; lalu wajib menjalankan workflow asset yang sesuai SHA dan health check staging.

## Pemisahan tanggung jawab

- GitHub: source code, migration, test, dokumentasi, lockfile.
- Hostinger environment: `.env` dan credential.
- MySQL: data katalog.
- Laravel Filesystem/storage terpisah: media produk production.
- hPanel Git: mengambil revision repository yang sudah lolos review; bukan sumber data.

## Prasyarat staging

1. Seluruh check wajib berstatus hijau.
2. Branch deployment dan aturan review disetujui.
3. PHP Hostinger terbukti memenuhi requirement PHP 8.2+.
4. Layout Laravel/document root dipilih dan diuji pada staging.
5. `.env` staging dibuat langsung di Hostinger dan tidak pernah masuk Git.
6. Database staging baru tersedia.
7. Build asset mempunyai proses reproducible dari `package-lock.json`.
8. Storage staging terpisah dari production.
9. Backup/rollback staging diuji.

## Integrasi hPanel Git yang direncanakan

Hostinger menyediakan koneksi GitHub melalui OAuth pada menu website bagian Advanced/Git, tanpa membutuhkan SSH untuk koneksi repository. Saat implementasi:

1. Hubungkan hanya repository Qammaris dan berikan scope minimum.
2. Pilih branch staging terlebih dahulu, bukan `main` production.
3. Gunakan direktori staging kosong yang telah diverifikasi.
4. Jangan mengaktifkan auto-deployment sebelum command install/build dan perlindungan `.env`/media terbukti aman.
5. Catat commit ID dan hasil deployment dari deployment history.

Mengubah repository atau target deployment dapat menimpa isi direktori target. Karena itu koneksi pertama tidak boleh diarahkan ke website production lama.

## Gap yang harus dibuktikan pada staging

hPanel Git telah terbukti menjalankan `composer install`, tetapi tidak menjalankan `npm run build` atau migration Artisan. Pada repository ini, `public/build` memang tidak disimpan di Git. Deployment saat ini karena itu membutuhkan langkah release terkontrol untuk build/upload asset, migration, storage link, dan cache Laravel.

Deployment Git juga menimpa root `.htaccess`, sehingga aturan HTTP Basic Auth yang dikelola pada file tersebut harus dipasang ulang setelah publikasi. Sampai pipeline release tersedia, setiap manual deploy wajib diikuti pemeriksaan `401` tanpa credential dan `200` dengan credential. `.env` berhasil dibuat setelah deployment, tetapi persistensinya terhadap redeploy berikutnya belum diuji dan tidak boleh diasumsikan.

Sebelum memilih mekanisme final, lakukan proof berikut pada staging:

1. catat versi PHP dan extension yang tersedia dari hPanel;
2. verifikasi apakah hPanel menyediakan build/deploy command yang terdokumentasi untuk custom PHP;
3. pastikan hanya entry point Laravel `public` yang dapat diakses web;
4. buktikan cara memasang dependency dari `composer.lock` dan asset dari `package-lock.json`;
5. buktikan cara menjalankan migration secara terkontrol dan mencatat hasilnya;
6. lakukan redeploy commit yang sama untuk membuktikan proses idempotent;
7. lakukan rollback ke commit sebelumnya dan verifikasi aplikasi kembali sehat.

Jika built-in hPanel Git tidak menyediakan build hook, jangan commit `vendor`, `.env`, atau upload production sebagai jalan pintas. Pilih pipeline release terkontrol (misalnya GitHub Actions yang membangun artifact dan menjalankan deployment otomatis dengan credential khusus ber-scope minimum) atau evaluasi hosting yang mempunyai deployment hook Laravel. Keputusan ini harus dicatat setelah kemampuan paket Hostinger benar-benar terlihat di hPanel.

## Keputusan layout staging

Paket shared hosting mempertahankan `public_html` sebagai document root. Repository menggunakan `.htaccess` pada root untuk mengarahkan request ke `public/`, mengikuti pola deployment Laravel yang didokumentasikan Hostinger. File `public/.htaccess` tetap menjadi front controller Laravel. Layout ini harus diuji ulang setelah redeploy dan pemeriksaan akses file sensitif.

## Checklist deployment

### Sebelum deploy

- backup database dan media target tersedia;
- commit/branch target dicatat;
- CI hijau;
- dependency lockfile tidak berubah tanpa review;
- migration direview dan additive;
- maintenance/rollback decision disiapkan.

### Setelah deploy

- homepage, katalog, detail produk, login, admin, cart, dan WhatsApp inquiry diperiksa;
- asset CSS/JS dan media termuat;
- tidak ada `.env` atau file sensitif yang dapat diakses publik;
- migration status sesuai;
- log aplikasi tidak menunjukkan error baru;
- commit, waktu, operator, hasil, dan keputusan rollback dicatat.

## Rollback

Rollback source code menggunakan revision deployment sebelumnya. Rollback schema tidak dilakukan sembarangan; migration harus mempunyai forward-fix atau rollback yang sudah diuji. Database/media dipulihkan hanya dari backup terverifikasi dengan approval owner.

## Larangan

- Jangan deploy langsung dari working tree kotor.
- Jangan menjalankan `composer update` pada production.
- Jangan menjalankan seeder sebagai update data production.
- Jangan menyimpan `.env`, dump, atau upload dalam repository.
- Jangan menghubungkan GitHub ke direktori production lama sebelum staging dan backup siap.

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

Production workflow trigger guard: privileged deployment accepts only successful CI for a push to this repository's main, not a pull_request run from a fork named main. It also skips superseded main revisions before SSH secret use. GitHub documents that workflow_run uses the default-branch context and can access secrets even when the triggering run cannot: https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#workflow_run. Public activation/enable flags remain off; this is bounded pipeline hardening before launch, not a new backlog item.


## Production activation completed — 2026-10-06

Owner's continuation approves the concrete reviewed cutover. New qammarisparfum.id target now serves350public+95draft/1016photos. PR2 merged main87959623d03c33b99c400e242cb35856b7f1e7a9; mainCI37431709979 and automatic productionrelease37431774330 build/deploy succeeded; server revision matches. Production marker/enable flag active, main-only environment unchanged. Only new target static traversal/read enabled; env/config0600 and private runtime remain private. Old public folder retained as public_html_legacy_20261006, old app/database/staging unchanged, no backup/deletion. Only Node backend WEBSITE_WEBHOOK_URL switched to production; hPanel one-env-change restart completed on same921569b4 source. Actual HTTPS200catalog/detail/search/login/home; draft/private/fixture404 and env403; real Owner admin login/product list445 passed. Feed401/200,452snapshots/checkpoint457/has_morefalse; actual signed production receiver202, duplicate/valid older wakeups drained with oneworker/queue0/failed0/errornull. Chrome1440x900/390x844 photos loaded/nooverflow, native search2BSPKutaresults. No new app/schema/package/UI implementation. Source production stock-event latency and separately timed post-cutover30-minute tick remain Notconfirmed; staging10/13second proof is historical. P1-04 IN_REVIEW for Owner acceptance; no next item started. Evidence/limitations/recovery: docs/verification/p1-04/PRODUCTION_CUTOVER.md. Earlier pending activation statements are historical and superseded.
