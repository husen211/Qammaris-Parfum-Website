# Product Media R2 Copy-Verify Runbook

Status: foundation lokal; bucket/credential R2 nyata dan cutover belum dijalankan.

## Batas keamanan

- Command ini tidak mengubah database, `PRODUCT_MEDIA_DISK`, atau source file.
- Tanpa `--apply`, command hanya membaca source/target dan menulis manifest lokal.
- Dengan `--apply`, command hanya membuat target yang belum ada. Target mismatch tidak ditimpa.
- Command tidak mempunyai fitur delete.

## Prasyarat environment

1. Buat bucket R2 khusus Qammaris.
2. Buat API token Read & Write yang dibatasi hanya ke bucket tersebut.
3. Simpan nilai berikut di environment staging/host, bukan Git:

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
