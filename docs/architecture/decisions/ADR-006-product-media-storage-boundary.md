# ADR-006 — Boundary Storage Media Produk

Status: Accepted — 2026-09-16

## Context

Upload dan pembacaan gambar produk sebelumnya mengakses disk `public` langsung dari controller dan model. Bentuk ini bekerja untuk local storage, tetapi membuat perpindahan ke storage S3-compatible seperti Cloudflare R2 membutuhkan perubahan di banyak caller.

Audit lokal 2026-09-16 menemukan 19 metadata gambar pada 14 produk, 14 primary image, tanpa primary ganda, dan 19 object key canonical `products/...`. Seluruh metadata mempunyai file lokal. Direktori local disk berisi 20 file produk sehingga satu file `products/IeCuw7MDgiwWOxJGaJk8j3ZCe5BxKzvpiKwifR4u.jpg` menjadi kandidat orphan. File tersebut dipertahankan dan tidak boleh dihapus hanya berdasarkan audit lokal ini.

Production belum diaudit penuh, sehingga pembacaan format path lokal legacy tidak boleh diputus pada tahap foundation. URL provider tetap bukan URL publik karena kontrak media melarang hotlink.

## Decision

1. Media produk memakai service `ProductMediaStorage` sebagai satu-satunya boundary upload, URL resolution, verifikasi object, dan rollback cleanup.
2. Nama disk dipilih melalui `PRODUCT_MEDIA_DISK` dan default ke `public`; direktori object key dipilih melalui `PRODUCT_MEDIA_DIRECTORY` dan default ke `products`.
3. Database tetap menyimpan object key relatif canonical, misalnya `products/abc.jpg`, bukan URL bucket atau binary image.
4. Reader menormalisasi prefix legacy `storage/` dan `public/` tanpa menulis ulang metadata.
5. Path kosong, traversal, file hilang, atau storage read failure menghasilkan placeholder.
6. URL HTTP/HTTPS pada metadata tidak di-hotlink dan menghasilkan placeholder. Import masa depan wajib mengunduh serta memverifikasi file sebelum membuat metadata canonical.
7. Kandidat orphan hanya dilaporkan. Cleanup membutuhkan inventory production, retention window, serta approval terpisah.

## Consequences

- Local/public storage tetap bekerja tanpa perubahan data.
- R2 kelak dapat dipilih melalui konfigurasi setelah disk S3, adapter, credential, dan bucket diverifikasi.
- Model, admin, import, dan API masa depan dapat menggunakan kontrak storage yang sama.
- Perubahan ini belum memasang adapter S3, menyalin media, atau menjadikan R2 aktif.

## Migration and rollback

- Tidak ada schema migration atau penulisan ulang metadata/file pada keputusan ini.
- Rollback kode dapat mengembalikan caller ke disk `public` karena object key existing tidak berubah.
- Migrasi cloud berikutnya wajib mengikuti copy -> verify -> switch -> retain -> cleanup terpisah.
- Staging dan production tetap memerlukan gate deployment terpisah; ADR ini bukan izin deployment.
