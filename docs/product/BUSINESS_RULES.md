# Qammaris Catalog Business Rules

Status: disetujui owner pada 2026-09-14 (`P0-02`); perubahan material berikutnya harus dicatat dan disetujui.

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
4. Produk baru dari bulk import selalu draft atau needs review.
5. Product publish membutuhkan field minimal: brand, nama, slug unik, kategori, ukuran, harga valid, deskripsi, gender/audience, dan tepat satu primary image.
6. Draft boleh disimpan tanpa harga, ukuran, deskripsi lengkap, atau gambar; sistem harus menampilkan alasan mengapa draft belum dapat dipublish.
7. Publication tidak boleh diturunkan dari availability: produk sold out tetap dapat berstatus published.
8. Brand atau kategori yang masih dipakai produk tidak boleh dihapus secara cascade dari admin; gunakan nonaktif/archive atau tolak penghapusan sampai relasinya dipindahkan.

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

## Import/export

1. Import harus idempotent.
2. Preview harus sama dengan perubahan yang diterapkan atau proses dibatalkan.
3. Baris ambigu tidak boleh ditulis otomatis.
4. Produk yang tidak muncul dalam sebuah export tidak boleh dianggap terhapus.
5. Setiap import memiliki batch ID, source file fingerprint, actor, waktu, hasil, dan error report.
6. Raw import diperlakukan sebagai input tidak tepercaya dan divalidasi.

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

## Non-goals program saat ini

- Menggantikan Majoo.
- Membangun payment checkout.
- Menjanjikan live stock.
- Membangun AI recommendation atau autonomous agent.
- Memigrasikan Laravel ke framework lain.
- Mengubah public website menjadi SPA.
