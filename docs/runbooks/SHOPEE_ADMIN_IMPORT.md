# Impor Shopee lewat admin — P8-09

Dirilis ke production 2026-10-07 pada `d7a5115` melalui PR13. Rujukan keputusan: ADR-026; bukti lokal: `docs/verification/p8-09/admin-import/README.md`; bukti release dan batas verifikasi: `docs/verification/releases/2026-10-07/README.md`.

## Cara Owner memakai

1. Produk website berasal dari Qammaris App. Tunggu draft masuk; impor Shopee tidak membuat produk catalog kedua.
2. Shopee: ekspor **Informasi Dasar** dan **Media** untuk pilihan produk yang sama. Gunakan XLSX asli, maksimum8MB/file dan1000produk. Format yang didukung memakai sheet pertama dan header kolom asli.
3. Admin -> Import Produk -> upload kedua file -> **Periksa file**. Tahap ini hanya menyimpan audit/usulan, belum mengubah katalog atau mengunduh foto.
4. Periksa produk website, ukuran, harga sumber, copy/peruntukan/kategori yang diusulkan dan jumlah foto baru. Filter **Perlu dipilih** untuk nama yang berbeda/ambigu; ketik nama untuk mempersempit pilihan. Identitas lama tidak boleh dialihkan. Penggantian copy lama hanya setelah mencentang pilihan khusus dan melihat usulan baru.
5. Centang konfirmasi -> **Terapkan … produk**. Angka mencakup seluruh halaman, bukan hanya25baris terlihat. Baris belum dikenali dilewati dan dapat dikerjakan kemudian. Harga/status aplikasi serta publication lama dipertahankan.
6. Worker mengunduh sampul + dua foto pertama ke disk website. Cover lama tidak diganti, foto lama tidak dihapus, maksimum3foto. Foto lokal terlihat di hasil; **Muat ulang hasil** untuk progres.
7. Filter **Foto gagal diunduh**, gunakan **Coba unduh foto lagi** atau **Lengkapi / upload foto manual**. Foto sukses tidak digandakan. Tanpa cover/harga/size/category/audience yang valid, produk tetap draft.
8. Pilih produk lengkap, atau **Pilih semua** pada daftar siap terbit; centang konfirmasi lalu **Terbitkan pilihan**. Apply bukan publikasi otomatis. Produk Habis boleh terbit dan tetap ditampilkan Habis.

Foto/deskripsi Shopee yang baru ditambahkan tidak otomatis terambil live. Ekspor ulang lalu lakukan alur di atas. Tidak perlu phpMyAdmin, File Manager, Cloudflare atau perubahan harga manual.

## Persiapan release yang harus diverifikasi

- PHP8.2+ dengan ZIP, SimpleXML, XMLReader. Composer/packages tidak berubah; CI memasang ekstensi tersebut pada runner.
- Tabel batch/baris, identities dan jobs existing tersedia; tidak ada migrasi baru.
- Worker **database** membaca `product-import-images`. Worker stock-only `qammaris-app` tidak cukup. Nilai retry_after harus melampaui timeout job90detik; verifikasi konfigurasi aktual sebelum memulai. Jangan jalankan dua watchdog untuk worker yang sama.
- Disk `media.product_disk` adalah storage persisten website dan URL/symlink existing sudah benar. Jangan mengganti provider/permission/env lain.
- Verifikasi koneksi hosting ke exact host CDN yang sudah disetujui menggunakan satu gambar Owner, bukan keseluruhan katalog. Pada release2026-10-07 satu gambar640×640JPEG berhasil diunduh/validasi lalu file sementara dihapus; ini tidak membuktikan seluruh URL Shopee. Manual upload memakai disk yang sama dapat menjadi fallback.
- Verifikasi uploader di browser dengan izin akses file lokal ekstensi, serta iPhone/touch sesuai gate UI. HTTP multipart feature test bukan bukti pemilih file Chrome.

Worker production yang sudah berjalan pada watchdog existing (jangan buat worker/cron kedua):

```text
php artisan queue:work database --queue=qammaris-app,product-import-images --sleep=1 --timeout=90 --tries=5
```

Database retry_after120detik terverifikasi; job foto tetap punya batas percobaan sendiri. Lock watchdog production yang sama menjaga satu worker, availability diproses lebih dahulu. Scheduler/watchdog minute cron existing dipertahankan. Native file chooser/upload production dan iPhone tetap belum terkonfirmasi; feature tests dan satu probe CDN bukan penggantinya.

## Error dan recovery

- File gagal: gunakan dua ekspor asli yang sama ID/nama; jangan mengubah kode produk atau menyisipkan formula. File terlalu besar/struktur ZIP/XML tidak aman ditolak.
- Usulan stale: harga, produk atau media berubah setelah pemeriksaan. Pilih produk kembali agar usulan diperbarui, periksa lalu apply. Tidak memaksa override atau reset cursor.
- Identitas sudah dipakai: jangan memilih ukuran/produk lain hanya agar lolos; koreksi sumber atau jadwalkan review identitas khusus.
- Antrean tidak tersedia: copy yang sudah applied tetap tersimpan; perbaiki worker/dispatch lalu retry foto. Pending/processing setelah worker hard-crash harus direkonsiliasi lewat prosedur jobs existing, bukan SQL bulk tanpa scope.
- Sebagian foto gagal: baca outcome; TLS/redirect/MIME/ukuran/dimensi/host yang tidak sesuai tetap ditolak. Jangan melemahkan validasi. Upload manual lewat editor jika sumber tidak bisa dihubungi.
- Rollback release: hentikan worker foto secara terkendali, revert kode lewat pipeline, pertahankan data/media/audit yang sudah berhasil. Forward fix dan retry lebih aman daripada menghapus seluruh batch/produk.
