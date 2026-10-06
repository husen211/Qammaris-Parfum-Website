# ADR-007 — Lifecycle Attach dan Primary Image Produk

Status: Accepted — 2026-09-16

## Context

Controller admin sebelumnya membuat metadata `ProductImage` secara langsung dan menentukan primary image sendiri. Pola ini tidak dapat dipakai ulang dengan aman oleh import/API masa depan dan memungkinkan invariant maksimum tiga gambar atau tepat satu primary diterapkan berbeda pada setiap caller.

Audit lokal sebelum implementasi menemukan 19 metadata pada 14 produk. Tidak ada produk dengan lebih dari tiga gambar, tanpa primary, atau primary ganda; jumlah maksimum existing adalah tiga. Produk tanpa gambar tidak dimodifikasi karena draft boleh tidak lengkap dan reconciliation data bukan bagian pekerjaan ini.

## Decision

1. `AttachProductImage` menjadi operasi bersama untuk metadata gambar baru dan memverifikasi ulang bahwa object key canonical berada di direktori produk serta tersedia pada disk `ProductMediaStorage`.
2. Satu produk mempunyai maksimum tiga gambar. Attach keempat gagal tanpa mengubah metadata existing.
3. Gambar pertama otomatis primary. Caller boleh meminta gambar baru menjadi primary; primary sebelumnya hanya diturunkan dalam transaksi yang sama dengan pembuatan metadata baru.
4. `SetPrimaryProductImage` memilih image existing secara scoped terhadap product, mempertahankan ID/path/order, dan bersifat idempotent.
5. Kedua operasi mengunci row product sebelum membaca/mengubah koleksi image agar seluruh caller yang memakai operasi ini terserialisasi per product.
6. State legacy dengan primary ganda tidak diperbaiki diam-diam oleh attach dan harus masuk reconciliation.
7. Penghapusan, penggantian file lama, reorder UI, dan migrasi R2 tetap pekerjaan terpisah.

## Consequences

- Admin, import, dan API masa depan dapat memakai invariant media yang sama.
- Validasi request tetap memberi feedback cepat, sedangkan operasi domain menjadi guard terakhir terhadap race atau caller non-HTTP.
- Tidak ada partial unique constraint lintas MySQL/SQLite; write baru wajib melalui operasi domain ini.
- Existing metadata dan file tidak ditulis ulang atau dihapus.

## Rollback

- Tidak ada schema migration atau migrasi data.
- Rollback kode dapat mengembalikan controller ke pembuatan metadata lama karena object key dan record existing tidak berubah.
- Staging dan production tetap memerlukan gate deployment terpisah; ADR ini bukan izin deployment.
