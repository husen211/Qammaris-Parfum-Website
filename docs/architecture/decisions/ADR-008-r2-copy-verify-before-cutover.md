# ADR-008 — Copy-Verify R2 sebelum Cutover Media

Status: Accepted — 2026-09-16

## Context

Media produk saat ini berada pada local/public disk dan seluruh caller sudah menggunakan Laravel Filesystem boundary. Target storage adalah Cloudflare R2 melalui API S3-compatible, tetapi mengganti read/upload langsung ke R2 tanpa inventory dan verifikasi dapat menghasilkan gambar hilang serta rollback yang tidak jelas.

Laravel memerlukan adapter `league/flysystem-aws-s3-v3` untuk disk S3. Cloudflare R2 memakai endpoint account `https://<ACCOUNT_ID>.r2.cloudflarestorage.com` dan region `auto`. Credential harus berupa token dengan scope bucket minimum dan tidak boleh masuk Git, manifest, atau log.

## Decision

1. Disk `r2` dikonfigurasi terpisah melalui environment `R2_*`; disk aktif produk tetap `public` sampai cutover disetujui.
2. Command `product-media:copy-verify` default ke dry-run dan hanya menulis target jika operator memberikan `--apply`.
3. Hanya object key canonical yang direferensikan `product_images` yang diproses. File tanpa metadata tidak diasumsikan aman untuk disalin atau dihapus.
4. Target existing diverifikasi memakai ukuran byte dan SHA-256. Target yang berbeda dilaporkan sebagai conflict dan tidak ditimpa.
5. Setelah copy, object target dibaca ulang dan diverifikasi. Source tidak pernah dihapus oleh command.
6. Setiap run menulis manifest JSON lokal dengan batch ID, mode, disk, image/product IDs, path, ukuran, checksum, status, dan summary; tidak ada credential atau isi file.
7. Exit code non-zero digunakan bila ada invalid path, missing source, target mismatch, storage error, atau verification failure.
8. Aktivasi R2, public delivery URL, dan cleanup local source membutuhkan tahap/gate terpisah.

## Consequences

- Copy dapat diulang secara idempotent; object yang identik menjadi `already_verified`.
- Konflik target tidak merusak source maupun target existing.
- SHA-256 memerlukan pembacaan penuh source/target dan command sebaiknya dijalankan di luar request web.
- Credential/bucket R2 nyata belum dibuat atau diuji pada item ini.

## Rollback

- Command tidak mengubah database atau disk aktif dan tidak menghapus source, sehingga membatalkan tahap ini cukup dengan tidak melakukan cutover.
- Object target hasil percobaan tidak dihapus otomatis; cleanup target memerlukan manifest dan approval terpisah.
- Staging dan production tetap memerlukan gate deployment terpisah; ADR ini bukan izin deployment.
