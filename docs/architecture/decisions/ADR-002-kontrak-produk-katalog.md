# ADR 002: Kontrak Produk Katalog

**Status:** Accepted
**Tanggal:** 2026-09-14

## Konteks

Qammaris adalah katalog digital dan alur pertanyaan melalui WhatsApp, bukan sistem checkout atau live inventory. Pengalaman admin harus sederhana: satu halaman katalog mewakili satu parfum, satu ukuran, dan satu harga. Struktur existing masih menyimpan harga pada `products.base_price` dan `product_variants.price`, sementara cart dan beberapa tampilan bergantung pada variant.

Audit agregat database lokal menemukan 180 produk: 116 tanpa variant dan 64 dengan tepat satu variant. Tidak ada produk dengan lebih dari satu variant. Dari 64 relasi tersebut, 26 mempunyai harga variant yang berbeda dari `base_price` dan enam harga tidak valid. Data lokal juga berisi 122 produk Unisex, 35 Pria, 23 Wanita, serta lima kategori berbasis jenis/konsentrasi.

## Keputusan

1. Pertahankan `ProductVariant` pada masa transisi sebagai detail teknis satu-offer-per-product agar cart dan consumer existing tidak rusak.
2. Admin mengelola satu ukuran dan satu harga tanpa UI multi-variant.
3. Satu produk published harus mempunyai tepat satu offer aktif.
4. `product_variants.price` menjadi sumber harga jual selama variant dipertahankan. `products.base_price` hanya field kompatibilitas/cache yang disinkronkan dari offer aktif.
5. Harga valid wajib tampil pada produk published; draft boleh belum mempunyai harga.
6. SKU opsional, nullable, dan tidak dibuat secara acak untuk identitas integrasi.
7. Publication memakai draft, published, dan archived. Availability memakai unknown, available, dan sold_out sebagai dimensi terpisah.
8. Status available berlaku 36 jam sejak pemeriksaan. Setelah itu tampilan publik menjadi unknown. Sold_out bertahan sampai pembaruan eksplisit berikutnya.
9. Field wajib publish: brand, nama, slug unik, kategori, ukuran, harga valid, deskripsi, gender/audience, dan tepat satu primary image.
10. Filter publik awal: brand, kategori/jenis, gender/audience, rentang harga, dan availability. Ukuran bukan filter utama.
11. Brand dan kategori yang masih digunakan tidak boleh dihapus secara cascade melalui admin.

## Konsekuensi Positif

- Form admin tetap sederhana tanpa membongkar consumer variant secara mendadak.
- Harga publik, sorting, cart, import, dan API masa depan mempunyai satu sumber yang jelas.
- Draft dapat disimpan bertahap tanpa mempublikasikan data yang tidak lengkap.
- Status sold out dapat ditampilkan tanpa menjanjikan live inventory.
- Kategori/filter mengikuti data dan kebutuhan discovery yang nyata.

## Risiko dan Mitigasi

- **Backfill offer:** 116 produk lokal tidak mempunyai variant. Buat reconciliation dan additive migration teruji; jangan mengarang ukuran atau harga yang tidak diketahui.
- **Konflik harga:** 26 produk mempunyai dua harga berbeda. Tampilkan conflict report dan minta keputusan manusia sebelum apply.
- **Harga tidak valid:** enam record memerlukan review dan tidak boleh dipublish sampai valid.
- **Kompatibilitas:** consumer existing mungkin membaca `base_price`. Pertahankan field sebagai mirror selama transition window dan lindungi dengan test.
- **Freshness:** pembaruan stok harian dapat terlewat. Status available otomatis ditampilkan unknown setelah 36 jam agar tidak memberi kepastian palsu.

## Hal yang Belum Diputuskan

- Nasib akhir tabel `product_variants` setelah semua consumer dipetakan dan dimigrasikan.
- Mekanisme sumber availability pertama sebelum integrasi Majoo/Shopee/agent tersedia.
- Rentang harga preset pada UI; harus ditentukan dari distribusi harga valid saat implementasi katalog.
- Taksonomi fragrance accords/notes untuk filter lanjutan.

## Persetujuan

Owner menyetujui kontrak produk ini pada 2026-09-14. Implementasi tetap dilakukan bertahap sesuai backlog, migration additive, dan aturan perlindungan data program.
