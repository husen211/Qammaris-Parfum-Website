# Qammaris Catalog Business Rules

Status: kontrak awal disetujui owner pada 2026-09-14 (`P0-02`); aturan kurasi import dan maksimum tiga gambar ditambahkan dari keputusan owner pada 2026-09-15.

## Produk dan identitas

1. Satu halaman katalog mewakili satu produk, satu ukuran, dan satu harga.
2. Ukuran berbeda biasanya menjadi produk terpisah.
3. ID database bersifat permanen dan tidak boleh diregenerasi.
4. Slug existing dipertahankan untuk menjaga URL; perubahan membutuhkan redirect.
5. SKU/kode katalog tidak wajib diisi admin dan tidak perlu ditampilkan kepada customer.
6. External ID/SKU tidak menggantikan ID internal.
7. Nama produk tidak boleh digunakan sebagai satu-satunya identifier sinkronisasi.
8. Selama masa transisi, `ProductVariant` dipertahankan sebagai detail teknis satu-offer-per-product; admin tidak perlu melihat UI multi-variant.
9. Satu produk published harus mempunyai tepat satu offer aktif yang menyimpan ukuran dan harga.
10. Draft boleh belum lengkap, tetapi minimal mempunyai nama kerja agar dapat ditemukan kembali oleh admin.

## Publication

1. Draft tidak dapat diakses publik.
2. Published dapat muncul di katalog, search, related products, sitemap, dan detail page.
3. Archived tidak tampil dalam penelusuran normal tetapi datanya dipertahankan.
4. Aksi hapus produk pada admin legacy diperlakukan sebagai archive/nonaktif yang dapat dipulihkan; product, variant, metadata, slug, dan media tidak dihapus.
5. Produk baru dari bulk import selalu draft atau needs review.
6. Product publish membutuhkan field minimal: brand, nama, slug unik, kategori, ukuran, harga valid, deskripsi, gender/audience, dan tepat satu primary image.
7. Draft boleh disimpan tanpa harga, ukuran, deskripsi lengkap, atau gambar; sistem harus menampilkan alasan mengapa draft belum dapat dipublish.
8. Publication tidak boleh diturunkan dari availability: produk sold out tetap dapat berstatus published.
9. Brand atau kategori yang masih dipakai produk tidak boleh dihapus secara cascade dari admin; gunakan nonaktif/archive atau tolak penghapusan sampai relasinya dipindahkan.

## Availability

1. Website tidak menjanjikan live inventory.
2. Availability berbeda dari publication.
3. Nilai availability: unknown, available, sold_out.
4. Sold out tidak menghapus atau mengarsipkan produk.
5. Availability harus menyimpan source dan checked time.
6. Available yang melewati freshness window ditampilkan sebagai unknown.
7. Customer diarahkan mengonfirmasi stok melalui WhatsApp.
8. Bila quantity tersedia, quantity boleh disimpan tanpa ditampilkan publik.
9. Freshness window untuk status available adalah 36 jam sejak `availability_checked_at`, sesuai target pembaruan setiap tutup toko dengan toleransi keterlambatan satu siklus.
10. Status sold_out tidak otomatis berubah menjadi unknown karena dianggap konfirmasi eksplisit; perubahan menjadi available atau unknown harus berasal dari pembaruan berikutnya.
11. Produk tanpa pemeriksaan stok yang valid menggunakan status unknown, bukan available.
12. Quantity dari file kurasi/import diperlakukan sebagai snapshot opsional, bukan janji live stock.

## Harga

1. Harga pada satu offer aktif (`product_variants.price`) menjadi sumber harga jual selama model variant masih dipertahankan.
2. Perubahan harga massal wajib melalui preview.
3. Data lama atau import tidak boleh menimpa perubahan manual yang lebih baru tanpa conflict warning.
4. Nilai negatif, nol yang tidak valid, atau melebihi presisi database harus ditolak.
5. Update arsitektur dan update harga adalah batch pekerjaan terpisah.
6. `products.base_price` diperlakukan sebagai field kompatibilitas/cache selama transisi dan harus disinkronkan dari offer aktif; controller atau form tidak boleh menetapkan dua harga secara independen.
7. Produk published selalu menampilkan harga jual yang valid. Teks seperti "hubungi kami" tidak menjadi pengganti harga untuk produk published.
8. `compare_at_price` opsional dan hanya valid jika lebih besar daripada harga jual.
9. Enam harga lokal yang saat ini nol/tidak valid dan 26 ketidaksesuaian antara `base_price` dan harga variant harus masuk reconciliation, bukan diperbaiki diam-diam.

## Field produk

1. Field minimum untuk menyimpan draft: nama kerja.
2. Field wajib sebelum publish: brand, nama, slug unik, kategori, ukuran dalam ml, harga jual lebih dari nol, deskripsi, gender/audience, dan primary image.
3. Field opsional: SKU/kode katalog, compare-at price, fragrance notes, meta description khusus, label best seller, quantity stok, serta external provider mapping.
4. ID internal, slug, publication status, availability metadata, timestamp, dan audit metadata dikelola sistem; bukan input bebas tanpa validasi.
5. SKU harus dapat bernilai null dan tidak boleh dibuat dengan angka acak sebagai identitas integrasi.
6. Gender/audience menggunakan pilihan terkontrol: Unisex, Pria, atau Wanita sampai ada keputusan taksonomi baru.

## Kategori, filter, dan urutan katalog

1. Kategori mewakili jenis/konsentrasi produk yang dikelola admin, seperti EDP, Extrait de Parfum, EDT, Hair & Body Mist, atau Parfum Oil.
2. Filter publik tahap awal: brand, kategori/jenis, gender/audience, rentang harga, dan availability.
3. Search publik mencakup nama produk dan brand; pencarian deskripsi boleh dipertahankan tetapi bukan satu-satunya mekanisme discovery.
4. Sort tahap awal: terbaru, harga terendah, harga tertinggi, dan populer berdasarkan data yang dapat dijelaskan.
5. Ukuran tidak menjadi filter utama karena satu halaman sudah mewakili satu ukuran dan data lokal didominasi 100 ml; dapat ditambah kelak jika kebutuhan customer terbukti.
6. Best seller adalah merchandising flag yang dikelola admin, bukan kategori dan bukan hasil AI otomatis.
7. Kategori dan brand dapat dinonaktifkan tanpa menghapus produk atau merusak URL.

## Media

1. Database menyimpan object key/path dan metadata, bukan binary image.
2. Source code repository tidak menyimpan upload produk production.
3. Setiap produk published mempunyai tepat satu primary image.
4. Penggantian gambar harus upload dan verifikasi gambar baru sebelum menghapus gambar lama.
5. Menghapus record harus tidak meninggalkan file yatim; kegagalan storage tidak boleh dilaporkan sebagai sukses.
6. Migrasi media menggunakan copy, verify, switch, retain, lalu cleanup terpisah.
7. Gambar hasil pencarian/AI selalu membutuhkan review sumber, varian, watermark, dan kualitas sebelum publish.
8. Satu produk menggunakan maksimum tiga gambar total: satu primary/cover dan maksimal dua gambar tambahan.
9. URL gambar Shopee atau provider lain hanya menjadi sumber akuisisi saat import. File harus diunduh, divalidasi, dan disimpan ke storage Qammaris; halaman publik tidak melakukan hotlink ke URL provider.
10. Kegagalan download gambar tidak membatalkan data draft yang valid, tetapi produk harus ditandai membutuhkan review gambar dan tidak boleh dipublish tanpa primary image.
11. Mengeluarkan gambar dari galeri admin memakai soft archive metadata. File tetap disimpan untuk recovery; hard delete dan cleanup fisik hanya boleh dilakukan melalui lifecycle retensi terpisah yang terverifikasi.
12. Mengarsipkan primary image dengan gambar aktif lain harus mempromosikan gambar aktif berikutnya secara atomik dan menormalkan urutan aktif. Foto terakhir produk published tidak boleh diarsipkan.
13. Batas maksimum tiga gambar hanya menghitung gambar aktif. Record arsip tetap mempertahankan product ID dan object key sebagai jejak recovery.
14. Akuisisi URL gambar import hanya boleh dimulai melalui aksi admin eksplisit setelah batch selesai di-apply. Satu job bounded dipakai per row; request web tidak mengerjakan seluruh batch secara inline pada environment deployed.
15. Downloader hanya menerima HTTPS dari exact host allowlist terkonfigurasi, menolak credential, port selain 443, alamat IP, dan redirect, serta membatasi waktu, byte, MIME aktual JPEG/PNG/WebP, dan dimensi sebelum storage write.
16. Akuisisi hanya boleh menambah media pada product hasil apply yang masih draft. Gambar existing dan primary existing dipertahankan; gambar pertama menjadi primary hanya ketika draft belum mempunyai gambar aktif.
17. Setiap kandidat menyimpan outcome, checksum, actor/waktu request, product image hasil, atau error aman. Retry hanya memproses kandidat yang belum tersimpan dan tidak boleh membuat metadata/file duplikat untuk kandidat sukses.
18. File baru wajib dibersihkan bila attachment metadata gagal. Kegagalan satu kandidat tidak membatalkan draft, menghapus media lain, atau memberi izin publish.

## Import/export

1. Import harus idempotent.
2. Preview harus sama dengan perubahan yang diterapkan atau proses dibatalkan.
3. Baris ambigu tidak boleh ditulis otomatis.
4. Produk yang tidak muncul dalam sebuah export tidak boleh dianggap terhapus.
5. Setiap import memiliki batch ID, source file fingerprint, actor, waktu, hasil, dan error report.
6. Raw import diperlakukan sebagai input tidak tepercaya dan divalidasi.
7. File Shopee adalah bahan mentah. Dataset yang dapat diimport adalah template Qammaris hasil kurasi/normalisasi, tetapi seluruh field tetap divalidasi secara deterministik oleh aplikasi.
8. AI boleh membersihkan teks dan mengekstrak fragrance notes dari deskripsi, tetapi tidak boleh mengarang fakta yang tidak tersedia; nilai yang tidak ditemukan dibiarkan kosong atau ditandai untuk review.
9. Kode produk provider dapat dipakai untuk mencocokkan update dalam provider yang sama, tetapi tidak menggantikan ID internal. Kode baru pada produk yang dihapus/dibuat ulang diperlakukan sebagai draft baru dan kemiripan nama/brand hanya menghasilkan peringatan duplikat.
10. Kontrak file tahap awal adalah UTF-8 CSV hasil kurasi dengan header dan urutan kolom tetap. XLSX mentah provider harus dikurasi/diubah ke kontrak ini sebelum preview.
11. Field `provider`, `kode_produk`, dan `nama_produk` wajib secara struktural. Field publish yang belum tersedia dibiarkan kosong dan ditandai perlu review; aplikasi maupun AI tidak boleh mengarang nilainya.
12. Harga CSV ditulis sebagai angka positif tanpa simbol mata uang atau pemisah ribuan. Kode produk selalu diperlakukan sebagai teks agar nol di depan dan digit panjang tidak berubah.
13. Fragrance notes dalam satu sel dipisahkan dengan `|`. URL gambar hanya menjadi kandidat sumber HTTPS; preview tidak mengunduh, menyimpan, atau menampilkan URL provider sebagai media publik.
14. Preview read-only terhadap katalog tidak memberi izin apply. Sistem boleh menyimpan fingerprint, hasil normalisasi, issue, actor, dan summary sebagai batch audit immutable tanpa menyimpan file sumber.
15. Batch preview idempotent untuk kombinasi actor, isi file, versi kontrak, dan state katalog yang sama. Perubahan brand, kategori, product, atau external identity menghasilkan state fingerprint baru dan mewajibkan preview baru. Batch terminal stale/invalid/failed boleh menghasilkan satu successor preview deterministic agar file yang sama tidak terkunci selamanya.
16. Apply hanya boleh dijalankan secara eksplisit oleh admin pada batch persisted yang masih berstatus previewed, versi kontraknya current, fingerprint state katalog masih sama, dan seluruh payload hash valid.
17. Baris baru yang diterapkan selalu membuat product draft/nonaktif. Apply tidak boleh auto-publish, menandai ready, membuat taxonomy, atau mengunduh gambar.
18. Mapping ke product existing hanya boleh diperbarui otomatis ketika product masih draft. Mapping ke product published atau archived ditahan untuk review manual tanpa perubahan data.
19. Pada update draft, nilai CSV kosong mempertahankan nilai existing. Field kosong tidak berarti perintah menghapus data.
20. Baris error/conflict tidak diterapkan. Baris review dapat diterapkan sebagai draft setelah konfirmasi admin karena kelengkapan publish tetap divalidasi terpisah.
21. Apply bersifat transactional dan idempotent per batch. Kegagalan unexpected membatalkan seluruh catalog write; request ulang pada batch applied tidak mengulang mutation.
22. Outcome apply mencatat actor, waktu, product hasil, status created/updated/blocked, pesan, serta snapshot before/after terkontrol per baris.
23. Download gambar adalah tahap terpisah setelah apply. Hanya row `created/updated`, batch `applied`, dan product yang masih draft yang eligible; apply sendiri tetap tidak melakukan network request atau storage write.
24. Dispatch akuisisi gambar harus eksplisit dan auditable. Rerun hanya memproses kandidat non-success; kandidat sukses tetap final dan dihitung dalam kapasitas maksimum tiga gambar.
25. Host sumber import dikelola melalui environment allowlist. Menambah host adalah keputusan operasional terpisah dan tidak boleh berasal dari nilai CSV.
26. Row `blocked_protected` hanya dapat diubah melalui resolusi manual setelah batch applied. Admin wajib membandingkan nilai current/import, memilih minimal satu field, dan memberi konfirmasi eksplisit.
27. Resolusi protected bersifat field-level dan one-time idempotent. Publication status, availability status, slug, external identity, serta media tidak boleh berubah sebagai efek resolusi.
28. Harga dan ukuran adalah satu pilihan atomik. Nilai import kosong tidak boleh menghapus nilai existing dan taxonomy hanya dapat dipilih bila record aktif exact tersedia.
29. Snapshot produk saat apply menjadi optimistic concurrency guard. Bila katalog berubah setelah row ditahan, resolusi ditolak dan admin harus membuat preview baru dari state terkini.
30. Actor, waktu, field terpilih, pesan, serta snapshot before/after resolusi disimpan pada import row. Error/conflict struktural tetap tidak dapat dipaksa dan harus diperbaiki pada CSV.
31. Laporan audit batch adalah export read-only dari record persisted, satu row per import row. Report tidak boleh mengubah katalog, membuat file permanen, atau mengklaim sebagai export katalog terkini.
32. Report audit memakai kolom allowlist, BOM UTF-8, urutan line source, dan sanitasi formula spreadsheet. URL gambar sumber, deskripsi panjang, serta snapshot before/after internal tidak diexport.

## Admin dan automation

1. Human admin dan automation menggunakan identity terpisah.
2. Automation tidak menggunakan password admin manusia.
3. Kredensial harus revocable dan mempunyai scope minimum.
4. Bulk publish, delete, dan destructive migration membutuhkan approval manusia.
5. Semua perubahan penting mencatat before/after, actor, source, dan batch ID bila relevan.

## UX

1. Mobile adalah pengalaman utama.
2. Visual identity Qammaris tetap premium, tenang, editorial, hitam/ivory/gold.
3. Hindari gradient generik, glassmorphism tanpa fungsi, card berlebihan, pill berlebihan, dan copy bergaya AI.
4. Admin mengutamakan kecepatan, kejelasan status, dan pencegahan kesalahan.
5. Informasi harga, ukuran, availability, dan primary action harus terlihat tanpa scroll berlebihan pada viewport umum.
6. Semua loading, empty, error, validation, success, dan retry state harus dirancang.
7. Perubahan UX wajib diverifikasi pada mobile dan desktop.
8. Setelah menyimpan atau membatalkan edit dari catalog manager, admin kembali ke konteks daftar sebelumnya selama masih valid, termasuk page, search, filter, dan sort; alur kerja berulang tidak boleh selalu di-reset ke halaman pertama.

## Non-goals program saat ini

- Menggantikan Majoo.
- Membangun payment checkout.
- Menjanjikan live stock.
- Membangun AI recommendation atau autonomous agent.
- Memigrasikan Laravel ke framework lain.
- Mengubah public website menjadi SPA.
