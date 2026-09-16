# Kontrak CSV Import Produk Qammaris

Status: Canonical preview contract v1 — 2026-09-16

## Tujuan

File ini adalah format perantara antara export provider, kurasi Claude, dan preview deterministik Qammaris. Export Shopee mentah tidak diupload langsung ke halaman import.

Alur yang didukung:

1. Owner export data produk dari Shopee.
2. Claude membaca file mentah dan menghasilkan CSV sesuai kontrak ini.
3. Admin upload CSV ke halaman **Import Produk** untuk preview read-only.
4. Admin memperbaiki baris error/perlu review. Apply database akan dibuat pada backlog berikutnya.

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
- Produk yang tidak tercantum dalam file tidak dihapus atau diarsipkan.

## Status preview

- **Valid:** struktur dan nilai file memenuhi kontrak.
- **Perlu review:** baris masih dapat menjadi draft tetapi belum lengkap untuk publish, taxonomy belum ditemukan/nonaktif, nama mirip existing, atau mapping mengarah ke produk archived.
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

## Batas tahap ini

Preview tidak menyimpan file upload, tidak menulis database, tidak membuat taxonomy, tidak membuat mapping identity, dan tidak mengunduh gambar. Fingerprint SHA-256 hanya ditampilkan sebagai identitas isi file untuk fondasi batch berikutnya.
