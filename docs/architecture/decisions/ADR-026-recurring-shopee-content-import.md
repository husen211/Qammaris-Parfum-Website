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

## P8-10 — Pemulihan impor berulang (released 2026-10-07 through PR15)

Batch Owner375/368dikenali/7review gagal karena fingerprint katalog umum mencakup harga, stok dan updated_at, dan satu konflik membatalkan semua baris. Kontrak `shopee-content-v1` tetap dipakai untuk batch/job lama; proposal baru menambahkan marker `content-v2`. Fingerprint khusus konten melindungi nama/URL/taksonomi/copy, ukuran/identitas offer, identitas eksternal, publikasi/hidden dan baseline media. Harga/stok/bestseller/timestamps dikecualikan karena tidak ditulis impor ini. Fingerprint umum untuk operasi bulk lain tidak diubah.

POST actor-owned/CSRF/throttled `/{batch}/refresh` mengaudit pemeriksaan ulang baris pending/blocked dari source asli. Batch lama tanpa marker wajib diperiksa ulang secara eksplisit sebelum apply; tidak ada auto-refresh baseline di tengah apply. Produk/media tidak berubah. Pilihan ukuran yang belum dikonfirmasi tetap tersimpan sebagai kandidat, tetapi `matched_product_id` null sehingga tidak dapat diterapkan. Konfirmasi ukuran hanya milik satu source/product/ukuran website dan tidak melewati guard multi-offer/hidden/occupied. Deskripsi dengan ukuran berbeda ditahan untuk editor, bukan dipotong atau diganti ukurannya secara heuristik.

Apply memvalidasi integritas payload semua baris dahulu; korupsi tetap rollback. Konflik target yang dapat diperiksa ulang disimpan sebagai `blocked_protected` tanpa mutasi produk tersebut; baris lain melanjutkan di transaksi existing. No-op dicatat `skipped_no_changes`, tanpa pemetaan/simpan produk/antrean foto. Konflik baseline media pada enqueue Shopee menandai outcome baris tersebut blocked, menjaga foto lama dan membiarkan baris aman antre; aturan kontrak impor lain tidak dilonggarkan. Publikasi pilihan tetap terpisah dan atomik.

Filter default `work` menyembunyikan produk published yang memenuhi readiness dan tanpa perubahan/outcome foto tertunda/gagal. Filter complete/all menjaga akses untuk penggantian deskripsi eksplisit. Daftar bukan otomatis hanya produk baru: tambahan foto/copy untuk produk lama tetap pekerjaan yang perlu terlihat. Tidak ada migrasi/package/pola arsitektur baru. Batch sampai1.000baris memakai pemeriksaan metadata/readiness bounded; file/disk/CDN live tetap membutuhkan verifikasi rilis tersendiri. Code rollback menjaga sumber/audit/media, tetapi proposal content-v2 perlu forward fix/recheck yang sesuai sebelum dapat diterapkan lewat kode lama; jangan menghapus hasil impor untuk rollback.

## AUD-03 — Ringkasan review dan pemulihan media (branch, belum dirilis)

Controller dan Blade sebelumnya menghitung status yang sama secara terpisah; review375baris lokal melakukan408query, termasuk375pemeriksaan slug. `ShopeeContentReview` kini menghasilkan satu ringkasan read-only per baris untuk counts/filter/tampilan. Readiness snapshot mengevaluasi setiap produk sekali dengan slug query berkelompok, menurunkan fixture yang sama ke25query tanpa perubahan6work/369complete. Ini mengurangi duplikasi tanpa repository/framework impor baru. Snapshot bukan otoritas mutasi: apply/publish tetap memeriksa keadaan terbaru.

Outcome Shopee blocked baru menyertakan `reason`: cover dependency dapat dicoba setelah unduhan sampul gagal; target conflict, produk tidak tersedia dan kapasitas memerlukan review. Batch historis tidak ditulis ulang: hanya pesan cover dependency lama yang dikenal diperlakukan retryable; blocked tanpa reason lainnya dan terminal error tanpa outcome diarahkan ke review. Campuran failed+protected juga meminta review. Guard antrean/attach tetap berlaku dan retry batch boleh melanjutkan baris aman, tidak menghapus/mengganti foto tersimpan.

Filter **Foto bermasalah** mencakup kedua jenis error. Per baris ditampilkan tindakan yang sesuai; tombol retry batch hanya muncul bila ada outcome retryable. **Periksa ulang perubahan** hanya memperbarui usulan konten pending/blocked, bukan baseline foto dari baris applied. Untuk media tersebut, gunakan editor atau upload kedua ekspor baru untuk usulan baru. Tidak ada migrasi, perubahan `content-v2`, perluasan otorisasi atau auto-publish. Bukti dan batas: [AUD-03](../../verification/aud-03/README.md).
