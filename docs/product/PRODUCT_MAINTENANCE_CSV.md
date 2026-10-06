# Kontrak CSV Bulk Maintenance Qammaris

Status: Preview dan transactional apply contract `maintenance-v1` — 2026-09-16

## Tujuan

File maintenance membawa usulan perubahan untuk product yang sudah ada. File dibuat dari snapshot katalog v2, dapat dirapikan oleh manusia atau AI, lalu wajib melewati preview deterministik sebelum apply eksplisit oleh admin.

## Alur

1. Admin mengunduh snapshot katalog terbaru.
2. Admin atau AI memilih row dan field yang perlu diubah.
3. `product_id`, `expected_updated_at`, dan `expected_row_fingerprint` dipertahankan persis.
4. Data dipindahkan ke template maintenance; field yang tidak ingin diubah dikosongkan.
5. Admin upload dan mereview perubahan, error, serta stale conflict.
6. Admin mencentang konfirmasi apply pada batch preview yang masih fresh.
7. Sistem memvalidasi ulang contract, payload, state katalog, timestamp, row fingerprint, taxonomy, dan offer di dalam satu transaksi.
8. Hanya row valid dengan perubahan yang ditulis; review/no-op dan error dicatat sebagai dilewati atau ditahan.

## Header tetap

```text
product_id,expected_updated_at,expected_row_fingerprint,nama_produk,deskripsi_produk,harga,brand,gender,stok_snapshot,terlaris,kategori,ukuran_ml,top_notes,middle_notes,base_notes
```

## Aturan field

| Kolom | Aturan |
|---|---|
| `product_id` | Wajib; bilangan bulat positif dan harus menunjuk product existing. |
| `expected_updated_at` | Wajib; ISO 8601 dari snapshot terbaru. |
| `expected_row_fingerprint` | Wajib; 64 karakter hex dari snapshot terbaru. |
| `nama_produk` | Opsional; maksimum 255 karakter. |
| `deskripsi_produk` | Opsional; maksimum 20.000 karakter. |
| `harga` | Opsional; angka positif maksimum dua desimal, wajib bersama `ukuran_ml`. |
| `brand` | Opsional; nama exact case-insensitive dari taxonomy aktif. |
| `gender` | Opsional; `Unisex`, `Pria`, atau `Wanita`. |
| `stok_snapshot` | Opsional; integer 0–999999 dan bukan klaim live inventory. |
| `terlaris` | Opsional; `ya/tidak`, `true/false`, atau `1/0`. |
| `kategori` | Opsional; nama exact case-insensitive dari taxonomy aktif. |
| `ukuran_ml` | Opsional; integer 1–10000, wajib bersama `harga`. |
| fragrance notes | Opsional; beberapa nilai dipisahkan `|`. |

Cell kosong berarti preserve. V1 tidak dapat mengosongkan field existing dan tidak mempunyai delete/archive semantics.

## Status row

- **Valid:** product ditemukan, stale guard cocok, nilai valid, dan minimal satu field benar-benar berubah.
- **Perlu review:** file valid tetapi tidak menghasilkan perubahan (no-op).
- **Error:** struktur/value invalid, product hilang/duplikat, taxonomy invalid, offer tidak lengkap, atau stale guard gagal.

Row error tidak menjadi kandidat apply. Row review/no-op juga tidak ditulis. Missing row dari file tidak mempunyai makna apa pun terhadap product yang tidak dicantumkan.

## Audit dan idempotency

Preview sukses menyimpan batch/row immutable pada tabel audit import existing dengan `contract_version=maintenance-v1`. Query maintenance dan import provider selalu difilter berdasarkan contract version agar kedua flow tidak dapat dibuka silang.

Idempotency menggunakan actor, fingerprint file, contract version, dan fingerprint state katalog. Upload identik pada state identik membuka batch yang sama. File sumber tidak disimpan.

Apply menyimpan actor dan waktu batch, status serta pesan per row, product hasil, dan before/after snapshot. Request ulang pada batch applied tidak mengulang mutation. Batch yang stale, invalid, atau failed bersifat terminal; admin harus membuat preview baru.

## Batas apply

- Seluruh row valid diterapkan dalam satu transaksi; unexpected failure me-rollback semua catalog write.
- Publication, availability, slug, media, external identity, serta product yang tidak ada di file tidak berubah.
- `stok_snapshot` hanya memperbarui quantity snapshot; tidak mengubah availability atau freshness status.
- Tidak ada clear/delete/archive, create product, partial retry, atau undo otomatis pada `maintenance-v1`.

## Field yang dilarang

Slug, publication, availability, media/path/URL gambar, external identity, SKU, product creation, taxonomy creation, delete, dan archive tidak tersedia pada kontrak ini.
