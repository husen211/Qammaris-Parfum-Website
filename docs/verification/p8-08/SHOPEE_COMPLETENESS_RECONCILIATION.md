# Pemeriksaan lengkap impor Shopee — 6 Oktober 2026

**Update setelah izin Owner:** 21 kekurangan dalam laporan historis ini sudah dilengkapi dan dipublish di produksi melalui batch 5. Seluruh 371 baris kini terhubung/published, 1.076 foto hadir, 77 draft tersisa di luar ekspor. Ejaan dan deskripsi Cute diluruskan dengan URL lama tetap. Hasil, verifikasi, dan pemulihan: [Owner21 production](../p8-09/OWNER_21_PRODUCTION.md). Angka 350/97 dan status belum-terhubung di bawah adalah baseline sebelum operasi tersebut.

Permintaan Owner: periksa ulang semua produk dari ekspor Shopee agar foto, deskripsi, dan kategori yang tersedia tidak terlewat. **Pemeriksaan read-only; bukan impor, publikasi, pembetulan identitas, atau deployment.**

## Hasil aktual produksi

Diperiksa pada **2026-10-06T12:53:12+00:00** (19:53 WIB). Perbandingan seluruh 371 ID Shopee dilakukan di server terhadap katalog produksi saat ini; hanya agregat dan daftar kekurangan yang dikembalikan. Tidak ada ekspor penuh katalog produksi, harga internal, atau isi audit before/after.

| Pemeriksaan | Hasil |
| --- | ---: |
| Produk pada ekspor media | 371 |
| Produk pada ekspor informasi dasar | 371 |
| ID duplikat atau ID tidak berpasangan antarkedua file | 0 |
| Baris sumber tanpa sampul/deskripsi/kategori Shopee | 0 |
| Produk Shopee terhubung ke website | 350 |
| Produk terhubung yang published dan lolos seluruh syarat publikasi | 350 |
| Identitas UUID berubah dari referensi launching / identitas Shopee ganda | 0 / 0 |
| Foto aktif pada 350 produk; file ditemukan di disk Laravel | 1.016 / 1.016 |
| File foto hilang atau path hotlink | 0 / 0 |
| Jumlah foto berbeda dari kandidat sumber unik yang tidak kosong | 0 |
| Baris Shopee belum terhubung | **21** |
| Total draft website saat diperiksa | **97** |
| Draft dengan foto/deskripsi | 0 / 0 |

Readiness menggunakan kode produksi `EvaluateProductPublicationReadiness`: nama/slug, brand/kategori aktif, deskripsi, peruntukan, tepat satu ukuran/harga positif, tepat satu foto utama, dan file foto utama di storage. Ini pemeriksaan struktural, bukan verifikasi kebenaran setiap klaim parfum dalam deskripsi atau uji visual semua foto.

Ada 320 produk dengan 3 foto, 26 dengan 2 foto, dan 4 dengan 1 foto: total 1.016. Tiga slot input tidak selalu berarti tiga foto berbeda. Slot kosong dan URL identik tidak dihitung sebagai foto hilang. Riwayat akuisisi staging untuk seluruh 30 produk dengan kurang dari tiga foto menunjukkan kandidat yang diajukan berstatus stored/completed, bukan download gagal. Tidak perlu meminta Owner upload ulang foto tambahan yang memang kosong/duplikat di sumber.

349 deskripsi sama persis dengan teks sumber yang sudah dibersihkan. **Hawas Elixir** memiliki deskripsi berbeda dari sumber, tetap terisi (340 karakter, pemeriksaan 12:55:20 UTC); penyebab perbedaannya **Not confirmed**. Teks yang ada dipertahankan. Perbedaan hash tidak otomatis berarti deskripsi kurang lengkap.

Draft bertambah dari 95 menjadi 97 sejak referensi launching: **Qammaris Signature Swift** dan **Qammaris Signature Query**, keduanya draft dan belum terhubung Shopee. Nama ini tidak terdapat dalam ekspor yang diperiksa; isi listing Shopee yang lebih baru **Not confirmed**. Angka historis 95 tidak dipakai sebagai keadaan terkini.

## Semua 21 baris yang belum masuk

Foto sampul dan deskripsi tersedia pada **seluruh 21 baris** berikut di kedua ekspor. Angka baris sama untuk kedua file. Ini daftar penelusuran, **bukan daftar pasangan yang disetujui atau siap publish**. Kandidat nama yang ada diperiksa ulang terhadap produk/UUID di produksi; nama serupa saja tidak memberi izin mengikat produk atau menyalin data.

| Baris Sheet1 | Produk Shopee | Status CSV sebelumnya | Temuan / langkah yang diperlukan |
| --- | --- | --- | --- |
| 16 | Project 1945 Vanilla Floresia Hair & Body Mist 150ML | tidak_ketemu | Vanilla Floresia Hair & Body Mist — 150 ml; kategori mist belum dipilih. |
| 45 | Project 1945 Fields of Ubud Perfume Extrait de Parfum 100ML | tidak_ketemu | Fields of Ubud — 100 ml Extrait; kategori dan ukuran website belum diisi. |
| 49 | Emper Captcha 36 Eau de Parfum 100ML | tidak_ketemu | Emper Captcha 36 — 100 ml EDP; kategori dan ukuran website belum diisi. |
| 65 | Project 1945 Princess of Java Hair & Body Mist 150ML | tidak_ketemu | Princess of Java Hair & Body Mist — 150 ml. Jangan gunakan produk EDP 100 ml yang sudah terbit. |
| 68 | Project 1945 Heiress of Minahasa Perfume EDP 100ML | tidak_ketemu | Heiress of Minahasa — 100 ml EDP; kategori dan ukuran website belum diisi. |
| 87 | Project 1945 Arunika Citrus Hair & Body Mist Parfum 150ML | tidak_ketemu | Arunika Citrus Hair & Body Mist — 150 ml; kategori mist belum dipilih. |
| 100 | Aoera Majestic Eau de Parfum 50ML | kuat | Produk uji staging dikecualikan saat launching. UUID sumber ada tetapi belum memiliki produk website produksi. |
| 158 | Lattafa Ameerat Al Arab Prive Rose Eau de Parfum 100ML | tidak_ketemu | Calon: Ameerat Arab Pink; identitas versi Prive Rose belum terkonfirmasi. |
| 173 | Riiffs Freeze Extrait De Parfum 100ML | tidak_ketemu | Riiffs/Riffs Freeze — 100 ml Extrait; kategori sudah ada, ukuran website belum diisi. |
| 193 | Project 1945 Arumanis Extrait de Perfume 100ML | tidak_ketemu | Arumanis — 100 ml Extrait; kategori sudah ada, ukuran website belum diisi. |
| 244 | Saff & Co. S.O.T.B Extrait de Parfum 35ML | tidak_ketemu | S.O.T.B/SOTB — 35 ml Extrait; kategori sudah ada, ukuran website belum diisi. |
| 298 | Bali Surfers Perfume The Ubud Eau de Parfum 100ML | tidak_ketemu | Calon: BSP The Ubud 1 100ML; ukuran 100 ml sudah ada, tetapi kecocokan versi The Ubud perlu dipastikan. |
| 307 | Fragrance World Des Tentations For Men Extrait de Parfum 100ML | perlu_cek | Belum dipilih pada review sebelumnya. Calon FW Des Tentations Blue 100 ml tidak otomatis berarti versi For Men yang sama. |
| 323 | Rasasi Shuhrah Pour Homme 90ML | ambigu | Belum dipilih pada review sebelumnya. Ada draft Shuhra Pour Homme; ukuran dan kategori belum diisi. |
| 344 | Khadlaj Island Dreams Extrait de Parfum 100ML | perlu_cek | Belum dipilih pada review sebelumnya. Ada draft Island Dream; jangan tertukar dengan Island Extrait yang sudah terbit. |
| 348 | Parfum Pria Tahan Lama Woody Citrus Premium \| Qammaris Signature AUTH Extrait de Parfum 50ml | tidak_ketemu | Ada draft Qammaris Signature Auth 50 ml dengan kategori Extrait dan ukuran aktif 50 ml; foto/deskripsi belum masuk. |
| 352 | Project 1945 Velvet Toraja Extrait de Perfume 100ML | tidak_ketemu | Velvet Toraja — 100 ml Extrait; kategori sudah ada, ukuran website belum diisi. |
| 360 | Project 1945 Symphony of Borobudur EDP 100ML | tidak_ketemu | Symphony of Borobudur — 100 ml EDP; kategori sudah ada, ukuran website belum diisi. |
| 361 | Project 1945 Bamboe Roencing Extrait de Parfum 150ML | tidak_ketemu | Calon: Bamboe Extrait; sumber menyebut Bamboe Roencing 150 ml. Pastikan versinya sebelum mengisi ukuran. |
| 370 | Saff & Co. S.O.F.R Extrait de Parfum 35ML | tidak_ketemu | S.O.F.R/SOFR — 35 ml Extrait; kategori sudah ada, ukuran website belum diisi. |
| 375 | La Rive Cute Woman EDP 100ML | tidak_ketemu | Belum ditemukan target website pada pemeriksaan ini; keberadaan produk yang sesuai di feed belum dikonfirmasi. |

Pembagian tepat: **17** baris `tidak_ketemu` dari CSV awal, **3** pilihan manual yang tidak dikirim Owner (Des Tentations, Shuhrah, Island Dreams), dan **1** Majestic yang sengaja dikecualikan sebagai fixture uji. 298 persetujuan otomatis mencakup fixture tersebut; 297 produk launching + 53 pilihan Owner = 350 produk produksi. Seluruh 350 identitas sesuai referensi launching, tidak ada pasangan yang hilang setelah cutover.

Dari 17 baris awal, 13 memiliki kandidat nama yang telah ditemukan sebelumnya, ditambah kandidat AUTH, The Ubud, dan Ameerat Arab Pink pada penelusuran tambahan ini. **Keberadaan kandidat bukan konfirmasi versi/ukuran**; Bamboe Roencing, Prive Rose, dan The Ubud terutama perlu pemeriksaan versi. La Rive belum ditemukan targetnya. Jangan menganggap semua 97 draft tidak ada di Shopee atau meminta Owner mengisi ulang semuanya.

## Kategori dan ukuran

Kolom kategori Shopee berisi kategori marketplace `Beauty/Perfumes & Fragrances`, bukan kategori konsentrasi website. Kategori website saat ini: Eau de Parfum, Extrait de Parfum, bodyspray, Eau de Toilette, Perfume Oil; semuanya aktif saat pemeriksaan.

Nama Shopee menyediakan ukuran dan EDP/Extrait untuk sebagian besar baris yang terlewat. Informasi yang jelas dapat dipakai dalam **usulan pelengkapan**, dengan memastikan target/versi yang benar. Tiga Hair & Body Mist jangan otomatis diubah menjadi EDP atau dianggap identik dengan produk parfum berukuran lain. Penetapan kategori mist/bodyspray memerlukan keputusan yang sesuai produk.

AUTH sudah mempunyai kategori Extrait dan ukuran aktif 50 ml. Sejumlah draft lain sudah mempunyai kategori, tetapi belum mempunyai ukuran/harga aktif; itu berbeda dari tidak adanya harga pada aplikasi. Audit ini tidak mengubah harga atau memeriksa ulang semua harga terhadap live API.

## Sumber, reproducibility, dan batasan

- `mass_update_media_info_1853666049_20261005201835.xlsx`: Sheet1,371baris; E sampul,F/G dua foto pertama.
- `mass_update_basic_info_1853666049_20261006100429.xlsx`: Sheet1,371baris; D deskripsi.
- `shopee-majoo-mapping.csv`:371baris;9sku,289kuat,26perlu_cek,30ambigu,17tidak_ketemu.
- `qammaris-shopee-owner-choices.json`:53pilihan; hash dan jumlah sama dengan dokumen P8-07.
- Hash kedua XLSX/CSV/pilihan cocok dengan provenance P8-07. File sumber tidak diedit; ZIP/XML dibaca langsung karena metadata activePane sumber tidak diterima openpyxl.
- CSV cakupan 371 baris dibuat dari data ekspor Owner dan hasil status pemeriksaan. Tidak memuat harga, data karyawan, kredensial, atau ekspor seluruh rekaman produksi.
- SSH hanya membaca data, readiness, file-existence, dan status akuisisi. Tidak mengubah permission, env, checkpoint, database, media, queue, atau status produk.
- Tidak ada tests aplikasi/build/browser/screenshots baru karena tidak ada perubahan UI/kode aplikasi. Pemeriksaan sumber, identitas, readiness, file existence, dan riwayat akuisisi benar-benar dijalankan. File existence bukan pemeriksaan ulang checksum/decoding seluruh foto; bukti checksum cutover tersimpan pada P1-04.
- Pemulihan data tidak diperlukan: tidak ada perubahan server. Artefak lokal audit dapat dibuang tanpa memengaruhi website.

## Tindak lanjut yang direkomendasikan, belum dimulai

Pelengkapan **hanya untuk daftar sumber yang terlewat**, memakai ekspor yang sama. Buat usulan berisi target UUID saat ini, versi/ukuran, kategori, sumber deskripsi/foto, dan konflik. Pilih kandidat yang benar, validasi ulang identitas/ukuran/source price/media sebelum apply, simpan audit, dan gunakan downloader/disk Laravel yang sudah ada. Pertahankan 350 produk terbit serta deskripsi/media yang sudah ada. Jangan mengubah guard staging-only operasi pairing atau menerapkannya langsung ke produksi sebagai jalan pintas. Publikasi dilakukan setelah produk yang dilengkapi lolos readiness dan ada persetujuan untuk target konkret.

Admin import XLSX baru, pengubahan UI, replay feed, rilis PR6, dan publikasi massal bukan bagian pemeriksaan ini.

