# ADR-004 — Draft Minimal dan Single Offer sebagai Price Authority

Status: Accepted — 2026-09-16

## Context

Kontrak produk Qammaris menetapkan satu halaman katalog untuk satu ukuran dan satu harga. Schema legacy masih mewajibkan seluruh field produk, mengizinkan banyak variant, dan membuat SKU acak dari controller. Harga juga ditulis ke `products.base_price` dan `product_variants.price` secara terpisah.

Audit lokal sebelum migration menemukan 180 produk dan 64 offer. Tidak ada produk yang mempunyai lebih dari satu offer dan tidak ada SKU duplikat. Sebanyak 116 produk tanpa offer serta konflik harga existing tetap menjadi pekerjaan reconciliation terpisah; arsitektur ini tidak menebak ukuran atau memperbaiki harga lama.

## Decision

1. Draft hanya membutuhkan nama kerja; brand, kategori, deskripsi, gender, harga, offer, dan gambar boleh belum tersedia.
2. Slug tetap dibuat sistem dari nama dan ID internal tetap menjadi identitas utama.
3. `ProductVariant` dipertahankan sebagai detail teknis offer selama consumer cart masih membutuhkannya, tetapi database membatasi maksimum satu offer per produk.
4. Harga offer (`product_variants.price`) adalah sumber harga jual. `products.base_price` tetap ada sebagai mirror compatibility dan hanya disinkronkan oleh operasi `SyncSingleOffer`.
5. SKU offer bersifat opsional, disimpan sebagai `null` bila kosong, dan tidak pernah dibuat acak.
6. Form admin legacy masih membuat produk lengkap dan published. Workflow simpan draft serta publish gate dibuat pada Admin Panel V2.

## Consequences

- Import masa depan dapat membuat draft parsial tanpa mengarang data.
- Admin, import, dan API masa depan mempunyai operasi offer yang dapat dipakai bersama.
- ID offer existing dipertahankan saat ukuran atau harga diedit.
- Constraint satu offer mencegah struktur baru kembali menjadi multi-variant, tetapi tidak membuat offer untuk 116 produk legacy yang belum memilikinya.

## Migration and rollback

- Migration hanya melonggarkan nullability dan menambahkan unique constraint `product_id`; tidak melakukan update terhadap record existing.
- Sebelum migration pada environment berdata, verifikasi tidak ada produk dengan lebih dari satu offer dan catat checksum identitas serta nilai harga/SKU.
- Rollback ke kolom wajib hanya aman sebelum draft atau SKU null ditulis. Setelah schema baru dipakai, gunakan forward fix atau lengkapi draft terlebih dahulu agar data parsial tidak hilang.
- Staging dan production tetap memerlukan gate deployment terpisah; ADR ini bukan izin deployment.
