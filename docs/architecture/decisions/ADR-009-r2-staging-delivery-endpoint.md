# ADR-009 — Endpoint Delivery R2 Staging dan Production

Status: Accepted — 2026-09-16

## Context

P4-04 membuktikan 19 object media produk identik pada bucket R2 staging, tetapi aplikasi masih memakai disk `public`. Sebelum application cutover, endpoint delivery perlu membuktikan bahwa object existing dan upload baru dapat dibaca melalui HTTPS.

Cloudflare hanya menerima custom domain R2 dari zone yang dikelola Cloudflare pada account yang sama. Percobaan menghubungkan hostname Qammaris ditolak karena `qammarisparfum.id` belum berada pada account/DNS Cloudflare tersebut. Memindahkan DNS production bukan bagian rehearsal media dan memerlukan rencana serta approval tersendiri.

Cloudflare menyediakan public development URL `r2.dev`, tetapi endpoint ini ditujukan untuk development, mempunyai rate limit, dan tidak memperoleh Cloudflare Cache atau WAF. Karena itu endpoint tersebut tidak memenuhi kontrak production. Lihat [dokumentasi resmi public buckets Cloudflare R2](https://developers.cloudflare.com/r2/buckets/public-buckets/).

## Decision

1. Bucket `qammaris-website-media-staging` memakai public development URL `r2.dev` hanya untuk P4-05 dan staging sementara.
2. `R2_URL` menyimpan base URL delivery; object key database tetap relatif dan tidak ditulis ulang menjadi URL provider.
3. `PRODUCT_MEDIA_DISK` tetap `public` sampai cutover aplikasi staging menjadi backlog item terpisah.
4. Production wajib memakai custom domain R2 pada zone Cloudflare yang dikelola dengan benar. `r2.dev` tidak boleh menjadi endpoint production.
5. Perubahan nameserver/DNS, custom domain production, application cutover, dan cleanup source masing-masing memerlukan gate terpisah.
6. Object key tetap immutable/unik agar cache dan rollback tidak bergantung pada overwrite object existing.

## Verification

- Sample image existing berhasil dilayani sebagai `image/jpeg` dengan status `200`, ukuran, dan SHA-256 identik dengan manifest P4-04.
- Object PNG sintetis berhasil ditulis/dibaca melalui S3, dilayani melalui HTTPS dengan MIME dan checksum identik, lalu dihapus dari prefix rehearsal terisolasi.
- Setelah cleanup, tidak ada object P4-05 tersisa; 19 object migrasi dan seluruh source lokal tetap tersedia.
- Database tetap 180 products dan 19 product images; disk aktif tetap `public`.

## Consequences

- Delivery R2 dapat diuji tanpa memindahkan DNS atau traffic production.
- Bucket staging kini public melalui URL yang tidak boleh dipakai untuk data privat atau production.
- Browser pada jaringan operator masih memerlukan perbaikan resolver karena IPv4 lokal mengarah ke alamat non-Cloudflare dan timeout. Aplikasi tidak boleh mengatasi masalah ini dengan hardcode IP.
- P4-06 harus menyelesaikan akses DNS normal dan rehearsal cutover aplikasi staging sebelum R2 menjadi disk aktif.

## Rollback

- Sebelum application cutover, rollback cukup dengan menonaktifkan public development URL; aplikasi tetap membaca disk `public`.
- Setelah staging cutover kelak, rollback mengembalikan `PRODUCT_MEDIA_DISK=public` dan membersihkan cache konfigurasi. Source lokal tetap dipertahankan sampai cleanup terpisah disetujui.
- ADR ini bukan izin deployment production, perubahan DNS, atau penghapusan media.
