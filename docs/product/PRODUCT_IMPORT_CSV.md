# Kontrak CSV Import Produk Qammaris

Status: Canonical preview + draft apply contract v1 — 2026-09-16

## Tujuan

File ini adalah format perantara antara export provider, kurasi Claude, dan preview deterministik Qammaris. Export Shopee mentah tidak diupload langsung ke halaman import.

Alur yang didukung:

1. Owner export data produk dari Shopee.
2. Claude membaca file mentah dan menghasilkan CSV sesuai kontrak ini.
3. Admin upload CSV ke halaman **Import Produk** untuk preview read-only terhadap katalog dan pencatatan batch audit.
4. Admin mereview batch, mencentang konfirmasi, lalu apply kandidat yang aman ke draft.
5. Admin melengkapi draft, gambar, dan publication secara manual pada alur terpisah.

## Format file

- Format: CSV dengan delimiter koma.
- Encoding: UTF-8. Template memakai BOM agar mudah dibuka di Excel.
- Maksimum: 5 MB dan 1.000 baris data.
- Header wajib sama persis dan urutannya tidak boleh berubah.
- Nilai yang mengandung koma atau baris baru harus mengikuti quoting CSV standar.
- Baris kosong dilewati dan dilaporkan.

```text
provider,kode_produk,nama_produk,deskripsi_produk,harga,brand,gender,stok,terlaris,kategori,ukuran_ml,top_notes,middle_notes,base_notes,foto_utama_url,foto_2_url,foto_3_url
```

## Definisi kolom

| Kolom | Wajib | Format dan aturan |
|---|---:|---|
| `provider` | Ya | `shopee` atau `majoo` |
| `kode_produk` | Ya | Teks maksimum 191 karakter; jangan diubah menjadi angka |
| `nama_produk` | Ya | Teks maksimum 255 karakter |
| `deskripsi_produk` | Untuk publish | Teks maksimum 20.000 karakter; boleh kosong pada draft |
| `harga` | Untuk publish | Angka positif, contoh `599000`; tanpa `Rp`, titik, koma, atau pemisah ribuan |
| `brand` | Untuk publish | Nama exact case-insensitive dari admin; tidak dibuat otomatis |
| `gender` | Untuk publish | `Unisex`, `Pria`, atau `Wanita` |
| `stok` | Tidak | Snapshot bilangan bulat `0–999999`; bukan klaim live stock |
| `terlaris` | Tidak | `ya/tidak`, `true/false`, atau `1/0`; kosong berarti tidak |
| `kategori` | Untuk publish | Nama exact case-insensitive dari admin; tidak dibuat otomatis |
| `ukuran_ml` | Untuk publish | Bilangan bulat `1–10000` |
| `top_notes` | Tidak | Beberapa nilai dipisahkan `|`; kosong bila tidak diketahui |
| `middle_notes` | Tidak | Beberapa nilai dipisahkan `|`; kosong bila tidak diketahui |
| `base_notes` | Tidak | Beberapa nilai dipisahkan `|`; kosong bila tidak diketahui |
| `foto_utama_url` | Untuk publish | URL sumber HTTPS; belum diunduh saat preview |
| `foto_2_url` | Tidak | URL sumber HTTPS |
| `foto_3_url` | Tidak | URL sumber HTTPS |

## Aturan pencocokan

- Update hanya ditentukan oleh pasangan `provider + kode_produk` yang sudah ada pada `product_external_identities`.
- Pasangan baru menjadi kandidat **Buat draft**.
- Pasangan existing menjadi kandidat **Update draft**.
- Pasangan duplikat dalam satu file menjadi **Error/Tahan**.
- Nama produk yang sama tanpa mapping hanya menghasilkan peringatan duplikat. Sistem tidak melakukan auto-merge.
- Mapping ke product draft menjadi kandidat update.
- Mapping ke product published/archived selalu ditahan untuk review manual dan tidak ditimpa.
- Produk yang tidak tercantum dalam file tidak dihapus atau diarsipkan.

## Status preview

- **Valid:** struktur dan nilai file memenuhi kontrak.
- **Perlu review:** baris masih dapat menjadi draft tetapi belum lengkap untuk publish, taxonomy belum ditemukan/nonaktif, nama mirip existing, atau mapping mengarah ke produk published/archived.
- **Error:** field struktural kosong, controlled value ilegal, angka/URL tidak valid, kolom rusak, atau identity duplikat dalam file.

Status valid bukan izin publish dan bukan jaminan gambar dapat diunduh. Seluruh import masa depan tetap membuat atau memperbarui draft terlebih dahulu.

## Instruksi untuk Claude

Gunakan aturan berikut saat mengubah export provider menjadi CSV Qammaris:

1. Pertahankan `kode_produk` persis sebagai teks, termasuk nol di depan dan digit panjang.
2. Jangan mengarang brand, kategori, gender, ukuran, fragrance notes, harga, stok, atau URL gambar.
3. Bila fakta tidak tersedia, biarkan sel kosong.
4. Ambil top, middle, dan base notes dari deskripsi hanya bila dinyatakan jelas. Pisahkan beberapa notes dengan `|`.
5. Bersihkan copy promosi, pengulangan, emoji berlebihan, serta informasi toko yang tidak menjelaskan produk. Jangan mengubah fakta produk.
6. Harga harus angka rupiah tanpa simbol atau pemisah ribuan.
7. Gunakan nama brand dan kategori yang sesuai daftar admin bila dapat dipastikan. Jangan membuat taxonomy baru.
8. Gunakan maksimal tiga URL gambar HTTPS dan urutkan cover sebagai `foto_utama_url`.
9. Keluarkan tepat satu baris untuk satu produk/satu ukuran. Ukuran berbeda menjadi baris terpisah.
10. Jangan menambah, menghapus, mengganti nama, atau mengubah urutan header canonical.

## Batch audit

Preview sukses menyimpan metadata dan hasil normalisasi ke `product_import_batches` serta `product_import_rows`. Idempotency ditentukan oleh actor, fingerprint file, versi kontrak, dan fingerprint state katalog. Upload yang identik pada state yang identik menunjuk batch yang sama; perubahan state katalog menghasilkan batch baru.

Batch terminal stale/invalid/failed tidak dipakai ulang sebagai sumber apply. Upload ulang file yang sama membuat satu successor preview deterministic, sedangkan upload berulang selama successor masih previewed/applied tetap kembali ke successor yang sama.

File CSV asli tidak disimpan. Batch menyimpan nama/ukuran/fingerprint file, actor, summary, normalized data, issue, matched product, candidate action, dan payload hash per baris. Catatan ini hanya dapat menjadi sumber apply setelah admin memberi konfirmasi eksplisit dan seluruh guard lulus.

## Apply ke draft

- Sistem memverifikasi ulang versi kontrak, fingerprint state katalog, dan hash payload setiap baris sebelum mutation.
- Satu batch diterapkan dalam satu transaksi. Kegagalan unexpected membatalkan seluruh mutation katalog.
- Baris `create` membuat product draft/nonaktif dan external identity. Single offer dibuat hanya bila harga dan ukuran sama-sama tersedia.
- Baris `update` hanya mengubah product draft. Field CSV kosong mempertahankan nilai existing; slug dan publication tidak diubah.
- Snapshot stok boleh disimpan, tetapi availability tetap `unknown`; nilai tersebut bukan klaim live stock.
- Baris error/conflict serta mapping published/archived dicatat sebagai blocked.
- URL gambar tidak diunduh, di-hotlink, atau dibuat menjadi metadata media pada tahap apply ini.
- Request ulang pada batch yang sudah applied menampilkan hasil existing tanpa membuat mutation kedua.

## Batas tahap ini

Belum tersedia conflict resolution per baris, update product published/archived, download gambar, taxonomy auto-create, bulk publish, undo batch, export katalog, queue, staging, atau production apply.
