# Runbook Rencana Deployment Hostinger melalui GitHub

**Status:** P1-03 sedang berjalan — discovery lokal selesai, perubahan hPanel belum dilakukan

## Tujuan

Menjadikan repository GitHub sebagai source of truth source code Qammaris tanpa menjadikan Git sebagai penyimpanan `.env`, database, atau upload media.

## Batas saat ini

- Belum ada repository yang dihubungkan ke hPanel oleh program modernisasi.
- Belum ada auto-deployment yang diaktifkan.
- Belum ada staging Hostinger yang diverifikasi.
- Tidak ada migration atau cutover production yang diizinkan oleh runbook ini.

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

hPanel Git mendokumentasikan clone/redeploy source dari branch GitHub, tetapi panduan yang diperiksa belum menjamin eksekusi otomatis `composer install`, `npm run build`, atau `php artisan migrate`. Pada repository ini, `vendor` dan `public/build` memang tidak disimpan di Git. Akibatnya, clone source mentah saja belum cukup untuk menjalankan aplikasi.

Sebelum memilih mekanisme final, lakukan proof berikut pada staging:

1. catat versi PHP dan extension yang tersedia dari hPanel;
2. verifikasi apakah hPanel menyediakan build/deploy command yang terdokumentasi untuk custom PHP;
3. pastikan hanya entry point Laravel `public` yang dapat diakses web;
4. buktikan cara memasang dependency dari `composer.lock` dan asset dari `package-lock.json`;
5. buktikan cara menjalankan migration secara terkontrol dan mencatat hasilnya;
6. lakukan redeploy commit yang sama untuk membuktikan proses idempotent;
7. lakukan rollback ke commit sebelumnya dan verifikasi aplikasi kembali sehat.

Jika built-in hPanel Git tidak menyediakan build hook, jangan commit `vendor`, `.env`, atau upload production sebagai jalan pintas. Pilih pipeline release terkontrol (misalnya GitHub Actions yang membangun artifact dan menjalankan deployment otomatis dengan credential khusus ber-scope minimum) atau evaluasi hosting yang mempunyai deployment hook Laravel. Keputusan ini harus dicatat setelah kemampuan paket Hostinger benar-benar terlihat di hPanel.

## Keputusan layout yang masih diperlukan

Sebelum staging dibuat, tentukan salah satu layout Hostinger yang didukung dan uji bahwa hanya folder `public` yang dapat dilayani web. Jangan mengarang path dari kondisi lokal. Document root, symlink, Composer behavior, dan kemampuan build harus dibuktikan pada staging.

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
