# ADR-026 — Impor konten Shopee berulang melalui admin

Status: Accepted; released2026-10-07 through PR13 (`d7a5115`).
Date: 2026-10-06.

## Masalah yang diselesaikan

`AdminProductImportController` menerima CSV kanonis yang harus disiapkan operator. File Informasi Dasar dan Media Shopee asli belum bisa dipakai admin. `PairQammarisShopeeDrafts` sengaja dibatasi staging dan impor launching; melonggarkan pembatasannya akan membuat operasi lama dapat diterapkan pada production tanpa kontrak baru yang jelas. Owner menyetujui uploader Shopee berulang setelah katalog aplikasi/otomatisasi harga tersedia.

## Keputusan

Pertahankan tabel batch/baris, identitas provider, Laravel filesystem dan operasi produk existing. Tambahkan kontrak manusia `shopee-content-v1`, bukan generalisasi operator launching. Auth/admin + CSRF melindungi `/admin/shopee-imports`; batch hanya boleh diakses akun pengunggahnya. Tidak ada endpoint publik, pola repository, package, migrasi atau perubahan kredensial.

`ShopeeContentXlsx` membaca kolom asli XLSX secara terbatas memakai ekstensi ZIP/XMLReader/SimpleXML. Batas ZIP/XML/baris/ukuran, penolakan makro/formula/entity dan pemeriksaan URL HTTPS allowlist diterapkan sebelum jaringan/mutasi produk. Gabungkan dua ekspor berdasarkan Kode Produk, bukan urutan atau SKU Shopee. Simpan sumber, fingerprint dan usulan immutable dalam audit existing; file upload asli tidak dipertahankan.

`ShopeeContentPreviewer` menggunakan identitas Shopee yang sudah terhubung terlebih dahulu. Produk baru hanya dicocokkan otomatis jika nama normalisasi + ukuran + konsentrasi kompatibel menghasilkan tepat satu produk aplikasi yang belum terhubung ke Shopee lain. Ketidakpastian memerlukan pilihan manusia. Identitas yang sudah ditempati tidak dapat direbind; UUID aplikasi tetap sumber identitas produk. Ketiadaan baris impor bukan penghapusan.

`ApplyShopeeContent` diperlukan karena CSV kanonis membuat draft provider, sementara kebutuhan ini melengkapi produk UUID yang sudah ada dan dapat sudah published. Operasi ini mengisi copy/peruntukan/kategori kosong yang didukung bukti, melengkapi offer kosong melalui `SyncSingleOffer`, serta memanggil `MapExternalProductIdentity` dan `PublishProduct`. Ia memvalidasi hash/fingerprint ulang, mengikuti checkpoint -> sumber -> produk untuk mutasi/publikasi dan menyimpan before/after/aktor. Import harga/availability/nama/slug/brand tidak diperkenankan. Penggantian deskripsi memerlukan pilihan eksplisit dan usulan baru sebelum apply. Audience yang belum jelas tidak mendapat default global.

Antrean foto existing mendapat guard hanya untuk kontrak baru: append ke draft atau published, tidak mengganti cover/menghapus foto, maksimum tiga. Baseline media + outcome tersimpan harus masih cocok saat enqueue dan attach; sumber hidden/merged/occupied ditolak. Foto yang sudah tersimpan dari URL tersebut tidak digandakan. Jobs kontrak baru selalu masuk koneksi database `product-import-images`, supaya setting default sync tidak membuat unduhan berlangsung di HTTP request. Worker dan downloader bounded existing dipakai; network/file IO berada di luar transaksi bisnis. Dispatch gagal mempertahankan konten yang sudah committed dan menyediakan retry outcome.

Publikasi memerlukan pilihan/konfirmasi terpisah dan readiness existing. Sold-out tetap dapat published. Baris belum dikenali dapat diselesaikan setelah subset lain diterapkan. Apply/publish replay tidak menggandakan mutasi/media.

## Tradeoff dan batasan

Ada pembaca XLSX khusus format Shopee, bukan pembaca Excel umum; header/sheet yang berubah harus menghasilkan error jelas dan membutuhkan forward fix. Dua ekspor harus memiliki himpunan ID/nama yang sama. Tidak mengadopsi pencocokan fuzzy karena pasangan salah berisiko memberi foto/copy produk lain. Re-upload tanpa perubahan masih dapat menghasilkan baris audit no-op; tidak mendownload ulang URL yang diketahui tersimpan.

Produk dengan tiga foto harus dikelola lewat editor untuk penggantian; perubahan URL Shopee tidak otomatis masuk tanpa ekspor/upload. Nama tanpa konsentrasi, audience konflik, dan kategori yang tidak tersedia tetap perlu review. Foto yang gagal masih membutuhkan retry/upload manual. Harga kosong tidak diperkirakan dari Shopee.

## Rilis dan pemulihan

Feature branch tidak mengaktifkan production. Sebelum release, pastikan ekstensi PHP dan worker database dengan retry_after lebih besar dari timeout90 detik, disk persisten, serta akses hosting ke CDN Shopee. Timeout CDN nyata pernah ditemukan pada batch21 production sebelumnya; mock lokal tidak membuktikan akses itu. Jangan melemahkan TLS/allowlist untuk mengatasinya.

Rollback kode mempertahankan audit, identitas, copy dan foto yang sudah berhasil. Hentikan worker foto secara terkendali untuk rollback; lanjutkan dengan forward fix lalu retry hanya kandidat gagal. Tidak ada checkpoint reset, database restore, penghapusan media atau unpublish otomatis.
