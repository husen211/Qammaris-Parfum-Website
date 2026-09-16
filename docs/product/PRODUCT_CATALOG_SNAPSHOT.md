# Kontrak Snapshot Katalog Qammaris

Status: Read-only snapshot contract v2 — 2026-09-16

## Tujuan

Snapshot membantu owner dan admin merekonsiliasi katalog, menilai kelengkapan data, dan menyiapkan pekerjaan bulk maintenance di luar aplikasi. File ini bukan template import dan tidak dapat langsung diterapkan kembali ke database.

## Format dan boundary

- Contract version: `qammaris-catalog-snapshot-v2`.
- Format: CSV UTF-8 dengan BOM dan delimiter koma.
- Satu baris per product, diurutkan menaik berdasarkan `product_id`.
- Draft, published, dan archived selalu disertakan.
- Response distream saat diminta, tidak membuat file permanen di server, dan memakai `Cache-Control: no-store`.
- Semua cell disanitasi terhadap spreadsheet formula injection.
- Snapshot bersifat point-in-time. `updated_at` memberi konteks, tetapi bukan optimistic lock dan bukan jaminan file masih current.

## Header tetap

```text
snapshot_version,product_id,slug,publication_status,availability_status,availability_effective,availability_source,availability_checked_at,external_identities,nama_produk,deskripsi_produk,harga,brand,gender,stok_snapshot,terlaris,kategori,ukuran_ml,top_notes,middle_notes,base_notes,active_image_count,has_primary_image,updated_at,row_fingerprint
```

## Semantik penting

| Kolom | Sumber dan arti |
|---|---|
| `product_id` | ID internal permanen; identitas utama untuk rekonsiliasi. |
| `slug` | Slug existing yang harus dipertahankan agar URL tidak rusak. |
| `publication_status` | Nilai tersimpan: `draft`, `published`, atau `archived`. |
| `availability_status` | Status availability yang tersimpan. |
| `availability_effective` | Status setelah aturan freshness 36 jam diterapkan. |
| `external_identities` | Mapping terurut berbentuk `provider:kode`, dipisahkan `|`; boleh kosong. |
| `harga`, `ukuran_ml` | Satu offer aktif. Keduanya kosong bila offer aktif tidak tersedia. |
| `stok_snapshot` | Quantity opsional; bukan klaim live inventory. |
| `top_notes`, `middle_notes`, `base_notes` | Nilai dalam satu group dipisahkan `|`. |
| `active_image_count` | Jumlah metadata gambar aktif; record soft-archived tidak dihitung. |
| `has_primary_image` | `ya` bila primary image aktif tersedia, selain itu `tidak`. |
| `updated_at` | Waktu update product; relasi dapat mempunyai timestamp sendiri. |
| `row_fingerprint` | SHA-256 deterministik atas state product yang dapat dimaintain dan offer aktif; stale guard, bukan credential. |

## Yang sengaja tidak diexport

- object key/path storage dan URL gambar;
- URL sumber provider dari file import;
- password, token, konfigurasi environment, atau data customer;
- snapshot internal audit import;
- field yang dapat dianggap sebagai perintah mutation.

## Hubungan dengan bulk maintenance

Snapshot boleh menjadi bahan kerja manusia atau AI, tetapi hasil olahannya harus masuk melalui kontrak `maintenance-v1` yang mempunyai preview, validation, conflict detection, idempotency, dan audit actor. Snapshot utuh tetap tidak dapat diupload langsung karena header dan boundary-nya berbeda.

Jika katalog berubah setelah download, ambil snapshot baru sebelum menyiapkan perubahan. Missing row tidak pernah berarti perintah hapus atau archive.

Snapshot v1 tetap merupakan export read-only historis. V2 menambah `row_fingerprint` karena `updated_at` product saja tidak selalu berubah ketika hanya offer yang berubah.
