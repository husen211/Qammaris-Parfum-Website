# Product Media R2 Copy-Verify Runbook

Status: copy-verify dan public delivery rehearsal staging selesai; application cutover belum dijalankan.

## Environment staging yang sudah disiapkan

Pada 2026-09-16, setup berikut telah diverifikasi tanpa mencatat nilai credential:

- bucket private Cloudflare R2 `qammaris-website-media-staging` memakai lokasi otomatis Asia Pacific dan standard storage class;
- token S3 mempunyai permission `Object Read & Write` dan dibatasi hanya ke bucket tersebut;
- GitHub Environment `staging` menyimpan `R2_ACCESS_KEY_ID` dan `R2_SECRET_ACCESS_KEY` sebagai encrypted secrets;
- `R2_BUCKET`, `R2_ENDPOINT`, `R2_REGION`, `R2_URL`, dan `PRODUCT_MEDIA_TARGET_DISK` disimpan sebagai environment variables pada environment yang sama;
- `PRODUCT_MEDIA_DISK` tetap tidak diarahkan ke `r2`; public development URL hanya diaktifkan pada bucket staging untuk rehearsal;
- bucket operasional Qammaris App tidak diubah.

GitHub secrets tidak dapat dibaca kembali oleh runtime lokal. Credential operator lokal disimpan pada `.env` yang diabaikan Git dan nilainya tidak boleh dipindahkan melalui chat, commit, dokumentasi, command-line argument, atau shell history.

## Hasil copy-verify 2026-09-16

Copy dilakukan dari disk `public` lokal ke disk `r2` tanpa mengubah database atau disk aktif:

| Tahap | Manifest | Hasil |
| --- | --- | --- |
| Dry-run | `01M2MGXXYD0ET75X6PFCT0T8XC.json` | `19 planned_copy` |
| Apply | `01M2MGZ47P565TKDDQRHNW1GX6.json` | `19 copied_verified` |
| Rerun apply | `01M2MGZRVK74Q4Y0Y6YXJAMHBX.json` | `19 already_verified` |

Manifest terakhir membuktikan `19` object source dan target mempunyai total ukuran identik `15.339.002` byte serta `0` mismatch SHA-256. Seluruh referenced source masih tersedia. Database lokal tetap berisi `180` products dan `19` product images; disk aktif tetap `public`, target tetap `r2`, bucket tetap private, dan tidak ada staging/production traffic yang dialihkan.

Pemeriksaan keamanan menemukan `0` nilai credential pada tracked file. Bridge localhost sementara yang dipakai untuk menulis `.env` telah dihentikan dan dihapus setelah berhasil.

## Batas keamanan

- Command ini tidak mengubah database, `PRODUCT_MEDIA_DISK`, atau source file.
- Tanpa `--apply`, command hanya membaca source/target dan menulis manifest lokal.
- Dengan `--apply`, command hanya membuat target yang belum ada. Target mismatch tidak ditimpa.
- Command tidak mempunyai fitur delete.

## Prasyarat environment

1. Aktifkan Cloudflare R2 pada account owner. Aktivasi layanan/billing dilakukan owner; agent tidak melakukannya tanpa izin eksplisit.
2. Buat bucket R2 khusus Qammaris. Bucket private secara default dan tidak perlu dibuat public untuk tahap copy-verify.
3. Dari halaman R2, buat API token dengan permission `Object Read & Write` dan `Apply to specific buckets only` untuk bucket Qammaris tersebut. Jangan gunakan permission admin account-wide.
4. Salin Access Key ID dan Secret Access Key ke password manager saat token dibuat; Secret Access Key tidak dapat ditampilkan ulang.
5. Simpan credential ke environment operator/staging melalui secret manager, bukan chat, Git, dokumentasi, database, atau shell history.
6. Gunakan S3 endpoint yang ditampilkan Cloudflare. Untuk bucket jurisdiction default formatnya `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`; region tetap `auto`.
7. Simpan nilai berikut di environment staging/host, bukan Git:

```dotenv
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_REGION=auto
R2_BUCKET=
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_URL=
PRODUCT_MEDIA_TARGET_DISK=r2
```

Dokumentasi resmi: [Laravel S3-compatible filesystems](https://laravel.com/framework/docs/11.x/filesystem#amazon-s3-compatible-filesystems) dan [Cloudflare R2 S3 API](https://developers.cloudflare.com/r2/get-started/s3/).

Jangan aktifkan `r2.dev` atau custom domain pada tahap copy-verify. Public delivery dan cutover mempunyai gate terpisah setelah object target terbukti lengkap. `r2.dev` hanya boleh dipakai untuk staging/development dan bukan endpoint production.

## Public delivery rehearsal staging 2026-09-16

Public development URL diaktifkan hanya pada bucket `qammaris-website-media-staging`:

```dotenv
R2_URL=https://pub-f71b3243d61541f5a14dba6a479ded39.r2.dev
```

Hasil verifikasi:

- sample object hasil copy-verify merespons HTTPS `200`, MIME `image/jpeg`, ukuran `304.172` byte, dan SHA-256 yang identik dengan manifest;
- object PNG sintetis pada prefix `rehearsals/p4-05-*` berhasil ditulis dan dibaca melalui S3, dilayani sebagai `image/png` melalui URL publik, lalu dihapus;
- verifikasi akhir menunjukkan tidak ada object rehearsal P4-05 tersisa;
- 19 object produk, 19 source lokal, database, dan `PRODUCT_MEDIA_DISK=public` tidak berubah.

Custom domain tidak dipasang karena `qammarisparfum.id` belum menjadi zone yang dikelola Cloudflare pada account bucket. Cloudflare mensyaratkan custom domain bucket berada pada zone Cloudflare di account yang sama. Untuk production, pindahkan/kelola DNS zone secara terencana atau gunakan subdomain dari zone Cloudflare yang sesuai, kemudian hubungkan custom domain R2; jangan memakai `r2.dev` sebagai endpoint production.

Referensi resmi: [Cloudflare R2 public buckets](https://developers.cloudflare.com/r2/buckets/public-buckets/).

### Catatan DNS lokal

Pada mesin operator saat rehearsal, resolver IPv4 mengembalikan `202.169.44.80` untuk hostname `r2.dev` dan koneksi timeout. Verifikasi deterministik berhasil saat koneksi diarahkan ke edge Cloudflare `104.18.50.34` sementara hostname TLS/SNI tetap memakai hostname `r2.dev` yang benar. Sebelum application cutover staging, perbaiki resolver/jaringan dan ulangi fetch normal tanpa override. Jangan hardcode IP edge ke aplikasi atau environment.

## Dry-run

```powershell
C:\xampp\php\php.exe artisan product-media:copy-verify --source=public --target=r2
```

Review exit code, summary, dan manifest pada `storage/app/private/media-migrations/`. Dry-run tetap memerlukan target credential karena object existing harus diverifikasi.

## Copy dan verifikasi

Hanya setelah dry-run bersih:

```powershell
C:\xampp\php\php.exe artisan product-media:copy-verify --source=public --target=r2 --apply
```

Status sukses:

- `copied_verified`: object baru disalin lalu cocok ukuran dan SHA-256.
- `already_verified`: object target sudah identik; tidak ditulis ulang.
- `planned_copy`: hanya muncul pada dry-run untuk target yang belum ada.

Status yang memerlukan review:

- `invalid_path`
- `missing_source`
- `target_check_failed`
- `target_mismatch`
- `copy_failed`
- `verification_failed`

## Gate sebelum cutover

1. Seluruh referenced object berstatus `copied_verified` atau `already_verified` pada apply run terakhir.
2. Count referenced object, ukuran, dan checksum sesuai manifest.
3. Sample public URL/mobile/desktop diverifikasi pada staging.
4. Upload baru ke target diuji dan rollback ke `public` dipraktikkan.
5. Baru setelah approval terpisah, `PRODUCT_MEDIA_DISK` dapat diarahkan ke `r2`.
6. Local source tetap dipertahankan selama rollback window. Cleanup menjadi pekerjaan terpisah.
