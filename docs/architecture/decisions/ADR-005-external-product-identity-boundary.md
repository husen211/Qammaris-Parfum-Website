# ADR-005 — Boundary Identitas Produk Eksternal

Status: Accepted — 2026-09-16

## Context

Dataset kurasi masa depan berasal antara lain dari export Shopee dan dapat membawa kode produk provider. Kode tersebut stabil selama listing provider tidak dihapus dan dibuat ulang, tetapi bukan identitas permanen milik katalog Qammaris. SKU offer juga opsional dan mempunyai arti berbeda dari kode listing provider.

Nama atau kemiripan brand tidak cukup aman untuk menentukan bahwa dua record adalah produk yang sama. Menggabungkan kode Shopee/Majoo ke kolom product atau SKU akan mengikat domain katalog pada satu provider dan berisiko menimpa product internal yang salah.

## Decision

1. ID `products.id` tetap menjadi identity utama dan permanen milik Qammaris.
2. Kode produk provider disimpan di tabel `product_external_identities` dengan `provider` dan `external_product_id`.
3. Satu kode hanya dapat dimiliki satu product dalam provider yang sama. Kode identik pada provider berbeda diperbolehkan.
4. Satu product hanya mempunyai satu identity untuk setiap provider.
5. Mapping yang sama bersifat idempotent. Mapping existing tidak boleh di-rebind ke kode lain secara otomatis.
6. Provider dinormalisasi ke lowercase dan dibatasi pada `shopee` serta `majoo` sampai provider baru disetujui.
7. Listing provider yang dihapus dan dibuat ulang dengan kode baru diperlakukan sebagai draft product baru. Kemiripan nama/brand hanya boleh menghasilkan conflict warning pada fase import.
8. Mapping dibuat melalui operasi `MapExternalProductIdentity` agar admin, import, dan API masa depan memakai invariant yang sama.

## Consequences

- Import masa depan dapat mencocokkan update secara deterministik dalam provider yang sama tanpa menyentuh ID internal, slug, atau SKU offer.
- Konflik identity menjadi baris review dan tidak diselesaikan dengan pencocokan nama otomatis.
- Penambahan provider baru memerlukan perubahan daftar provider terkontrol, tetapi tidak memerlukan perubahan schema.
- Tabel ini belum mengimplementasikan import, API provider, histori payload, sinkronisasi harga/stok, atau UI admin.

## Migration and rollback

- Migration hanya membuat tabel baru dan tidak membaca atau mengubah record product, variant, image, maupun media existing.
- Rollback menghapus tabel mapping baru. Setelah mapping nyata dipakai, export mapping wajib diambil sebelum rollback agar hubungan provider tidak hilang.
- Staging dan production tetap memerlukan gate deployment terpisah; ADR ini bukan izin deployment.
