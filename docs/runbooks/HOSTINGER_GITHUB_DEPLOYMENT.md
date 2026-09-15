# Runbook Rencana Deployment Hostinger melalui GitHub

**Status:** P1-03 dalam review — staging terpisah sudah aktif dan health check dasar lulus

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
