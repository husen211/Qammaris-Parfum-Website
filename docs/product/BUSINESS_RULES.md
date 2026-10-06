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
6. Untuk produk terhubung aplikasi Qammaris (`availability_source=qammaris_app`), status diterima apa adanya: tidak ada kedaluwarsa, downgrade otomatis, atau pesan publik tentang waktu pemeriksaan. Gangguan koneksi mempertahankan status terakhir.
7. Customer menggunakan WhatsApp untuk inquiry. Produk terhubung tidak meminta verifikasi ulang status yang sudah dikirim aplikasi; legacy/manual tetap memakai konfirmasi stok. Jumlah minat bukan reservasi atau jumlah stok aktual.
8. Bila quantity tersedia, quantity boleh disimpan tanpa ditampilkan publik.
9. Freshness window 36 jam hanya berlaku pada produk legacy/manual yang belum menggunakan sumber aplikasi Qammaris. Ia tidak berlaku pada produk terhubung.
10. Status sold_out tidak otomatis berubah menjadi unknown karena dianggap konfirmasi eksplisit; perubahan menjadi available atau unknown harus berasal dari pembaruan berikutnya.
11. Produk tanpa pemeriksaan stok yang valid menggunakan status unknown, bukan available.
12. Quantity dari file kurasi/import diperlakukan sebagai snapshot opsional, bukan janji live stock.
13. Keputusan Owner 2026-10-05: sumber aplikasi `available` -> “Tersedia”, `sold_out` -> “Habis”, `unknown` -> “Tanyakan ketersediaan”. OTW memberi informasi “Restok segera” tanpa mengubah Habis menjadi Tersedia. P8-02 menerapkan label ini pada kartu, detail, daftar/drawer inquiry, dan pesan WhatsApp; waktu pemeriksaan tidak ditampilkan pada produk terhubung. P8-01 adalah backend.
14. Feed nonaktif/merged/departemen lainnya tetap dikirim sebagai hidden tombstone. Website menyimpan visibility guard terpisah, tanpa menghapus data, mengarsipkan publication, atau memindahkan mapping otomatis.
15. UUID aplikasi adalah identity provider `qammaris_app`; SKU hanya bantuan review. Mapping harus eksplisit. Harga sumber menjadi usulan, bukan update offer otomatis. Departemen tidak menggantikan kategori.
16. Webhook HMAC adalah jalur utama; rekonsiliasi feed setiap 30 menit adalah jaring pengaman. Revision/checkpoint/audit wajib idempotent dan transactional; kredensial mesin hanya baca dan terpisah dari akun karyawan.

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
8. URL GET adalah source of truth state katalog publik. Search, filter, sort, pagination, dan kembali dari detail wajib mempertahankan parameter yang valid.
9. Perubahan search, filter, atau sort mengembalikan pagination ke halaman pertama; pagination hanya mengganti parameter `page`.
10. Sort “Populer” memakai sinyal `view_count` yang dapat dijelaskan. Label “Terlaris” hanya berasal dari merchandising flag `is_best_seller`, bukan dari view count.
11. Harga filter dan sort berasal dari satu offer aktif. `base_price` tidak boleh diam-diam menjadi fallback authority untuk row published yang belum direkonsiliasi.
12. Detail product tidak boleh menerima arbitrary return URL. Context kembali ke katalog hanya dibangun dari query katalog allowlisted dan tervalidasi.

## Katalog dan inquiry publik

1. Primary journey publik adalah discovery menuju inquiry, bukan checkout pembayaran atau reservasi stok.
2. Kartu minimum menampilkan brand, nama, satu ukuran, satu harga, dan effective availability; best seller hanya tampil bila flag merchandising aktif.
3. Copy availability publik: fresh `available` berarti “Tersedia saat diperiksa”, `unknown` berarti “Konfirmasi stok”, dan `sold_out` berarti “Sold out”.
4. Produk sold out tetap discoverable dan action utamanya menjadi inquiry restock.
5. Quantity snapshot dan stock variant tidak boleh ditampilkan atau diterjemahkan sebagai live inventory.
6. Daftar/cart customer-facing adalah daftar inquiry. Memasukkan produk tidak menjanjikan pembayaran, reservasi, atau stok.
7. Pesan WhatsApp inquiry harus membawa konteks produk dan intent stok/restock tanpa mengarang informasi availability.
8. Row legacy tanpa offer atau primary image adalah data-quality state yang harus direkonsiliasi; public UI tidak boleh menebak harga, ukuran, atau gambar.
9. Route dan session `/cart` boleh dipertahankan sebagai detail implementasi kompatibilitas, tetapi seluruh istilah customer-facing wajib memakai `Daftar Inquiry`.
10. Snapshot produk di session bukan sumber kebenaran untuk tampilan atau pesan. Sebelum merender drawer/halaman dan sebelum membuka WhatsApp, sistem mengambil ulang brand, nama, offer, harga, media, URL, publication, dan effective availability dari database.
11. Produk dengan effective availability `sold_out` tidak dapat ditambahkan sebagai inquiry stok biasa; detail produk menyediakan inquiry restock kontekstual secara langsung.
12. Quantity pada daftar inquiry hanya menyatakan jumlah yang diminati, dibatasi defensif 1–99, dan tidak divalidasi terhadap stock variant.
13. Daftar inquiry tidak meminta nama, nomor customer, atau alamat pengiriman. Catatan admin bersifat opsional; pesan dan UI wajib menegaskan bahwa stok/harga masih perlu dikonfirmasi dan inquiry belum menjadi transaksi atau reservasi.

## Media

Owner update 2026-10-06 (P8-07): the app team's supplied Shopee→Majoo SKU CSV is the approved matching evidence. `sku`/`kuat` may pair only when the exact current feed SKU identifies one UUID and one visible website draft, with no provider conflict. `perlu_cek`/`ambigu` require explicit Owner selection; unmatched/missing/nonunique SKUs are not forced. This replaces the earlier name-matcher restriction for this supplied dataset. Existing descriptions/media and published records remain protected; no automatic publication. The previous 266-photo-gap count is a historical baseline, not a fixed manual-upload cohort. Owner subsequently supplied 53 explicit choices from the 56-row review: only unchanged, nonconflicting selected targets may receive supplemental copy/photos via a fresh audited preview. Three unselected rows remain held; choosing a conflicting candidate does not authorize rebinding.

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
33. Snapshot katalog adalah export read-only seluruh product dalam urutan ID internal dan mencakup status draft, published, serta archived. Snapshot bukan file import dan tidak memberi izin write-back.
34. Harga dan ukuran snapshot berasal dari satu offer aktif; bila offer aktif tidak ada, nilainya kosong dan tidak diisi dari `base_price`. External identity diurutkan dan diagregasi tanpa menggantikan ID internal.
35. Snapshot media hanya memuat jumlah gambar aktif dan keberadaan primary image. Object key, path storage, URL publik, serta URL sumber provider tidak diexport.
36. Semua cell snapshot disanitasi terhadap formula spreadsheet, response tidak disimpan server-side, dan timestamp product dipakai sebagai konteks freshness—bukan jaminan file masih current saat dibuka.
37. Snapshot katalog v2 menambahkan row fingerprint deterministik yang mencakup field maintenance product dan satu offer aktif. Fingerprint bukan credential dan hanya dipakai sebagai optimistic stale guard.
38. Preview bulk maintenance hanya menerima product existing berdasarkan `product_id`. Nama, slug, SKU, atau external identity tidak boleh menjadi fallback pencocokan.
39. `expected_updated_at` dan `expected_row_fingerprint` wajib disalin dari snapshot terbaru. Perubahan product atau offer setelah snapshot menahan row tanpa mutation.
40. Field maintenance v1 yang boleh diusulkan hanya nama, deskripsi, brand, gender, stok snapshot, best seller, kategori, harga+ukuran, serta fragrance notes. Publication, availability, slug, media, dan external identity tidak menjadi input.
41. Cell maintenance kosong selalu berarti mempertahankan nilai current. Kontrak v1 tidak menyediakan semantics clear/delete.
42. Harga dan ukuran wajib diisi bersama. Taxonomy yang diusulkan harus exact case-insensitive dan aktif; missing/nonaktif menjadi error preview.
43. Missing product, product ID duplikat, stale guard, controlled value ilegal, atau angka invalid ditahan sebagai error. Row tanpa perubahan menjadi review/no-op.
44. Preview maintenance disimpan sebagai batch audit immutable dan idempotent untuk actor+file+state yang sama, tetapi tidak memberi izin apply. Batch maintenance dan import provider wajib dipisahkan dengan contract version pada seluruh query/route.
45. Apply maintenance hanya boleh dijalankan admin secara eksplisit pada batch `maintenance-v1` berstatus previewed dengan konfirmasi manusia.
46. Sebelum mutation, apply memvalidasi ulang contract version, payload hash, catalog-state fingerprint, matched product, timestamp product, row fingerprint, taxonomy aktif, serta pasangan harga+ukuran.
47. Hanya row valid yang mempunyai perubahan dapat ditulis. Row review/no-op dilewati dan row error/conflict ditahan dengan outcome audit; keduanya tidak boleh mengubah katalog.
48. Apply maintenance bersifat satu transaksi dan idempotent per batch. Kegagalan unexpected membatalkan seluruh catalog write; request ulang pada batch applied hanya menampilkan hasil tersimpan.
49. Mutation maintenance terbatas pada allowlist preview. Publication, availability, slug, media, external identity, dan product yang tidak dicantumkan wajib tetap tidak berubah.
50. Setiap row apply menyimpan outcome, product hasil, waktu, serta before/after snapshot; batch menyimpan actor apply, waktu, jumlah diterapkan, jumlah dilewati/ditahan, dan failure yang aman.

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


## Launch preparation from Qammaris app — Owner approved 2026-10-06

- Launch source is synchronized app feed. Visible UUIDs may be explicitly batch-prepared into draft/nonactive website products; one qammaris_app identity per product, never auto-publish. Webhook worker itself still changes availability only.
- Initial new-draft name, brand and selling price come from source. Existing catalog name/price/slug/media are retained; later source prices remain proposals.
- Size ml and concentration are parsed only when explicit/unambiguous in source name. Unknown values, descriptions and audience remain blank for review; department is not a category.
- Source brand and explicitly parsed concentration may create missing taxonomy during this Owner-authorized preparation. Existing inactive/ambiguous taxonomy is not activated or guessed.
- Old local 180-product catalog is a read-only matching reference for existing slugs/media, never launch data. An unmatched current website candidate blocks automatic draft creation pending review.
- Shopee media source is Owner-owned. Only unique strong name/size matches may map provider shopee and acquire cover plus first two additional images; source SKUs across providers are not identity keys. Download through approved host/MIME/dimension/size checks and website disk, never hotlink. Cover must succeed before additions. Existing product media is untouched.
- Unmatched/ambiguous/no-photo records remain drafts for Owner upload. source=app always has an explicit review issue. Publication readiness remains a separate human step.
- Machine/CLI preparation uses a distinct persisted contract version and Owner-authorized source label with no fabricated human user. Before/after, source revision, row issues and file acquisition outcomes are audited using existing import tables.
- Owner provided `mass_update_basic_info_1853666049_20261006100429.xlsx` as the current Shopee description source on 2026-10-06. Description-only proposals may use existing exact Shopee IDs on visible website drafts, with the previously matched source name checked for changes. Retain source text and provenance; no guessed audience/size/notes, fuzzy new pairing, overwrite of existing descriptions, apply or publication is authorized by source submission alone.
- Subsequent explicit Owner direction authorizes cleaned description and clear audience evidence on the 113 photographed, structurally complete staging drafts. Remove emoji, READY STOCK, shipping information and hashtags; retain product facts. Missing/conflicting audience stays blank for Owner review; no default Unisex. This approval permits guarded draft-copy apply, not publication before review.
- Launch is staged: review wave one first; the 266 image-less drafts remain invisible until Owner uploads/reviews photos. Size/concentration corrections outside the wave are separate and do not block the first approved subset. Production database backup occurs immediately before separately approved cutover, not during this draft curation.

## Current launch-wave continuation (2026-10-06)

Owner confirms that image-less products may remain drafts and requests continuation of ready photographed products. The original 113-row review is a historical first cohort; the current structurally complete photographed cohort may be refreshed after approved pairing. Clear-source blank audience can be filled using existing guarded maintenance; existing audience and ambiguous/conflicting facts are retained for review. Category/offer gaps do not block a smaller ready launch wave and are not guessed or automatically priced. Exact current publication readiness and Owner review determine the staging subset; production cutover remains separately approved.

Owner explicitly approved publication of the concrete 122-product staging list on 2026-10-06 (CSV SHA256 `c9eda30849a7e439fdcdeb6be4cd958e6f5f71a146da81b0550b652d35b48013`). This permits only that reviewed subset through the existing publication gate. The remaining 323 products, including 95 without photos, stay drafts; the pre-existing AOERA integration fixture is retained separately. This is not automatic future publication or production cutover approval.

Owner named-product approval2026-10-06: Bali Cliff1 is Pria for existing37/100 ml records; Toffee Coffee is EDP Unisex100 ml; Royal Blend Nero is Unisex100 ml with existingExtrait category; ILIAD is Unisex30 ml with existingExtrait category. The five specific staging records may be corrected and published after readiness checks. Prices, names, original matched descriptions, URLs, media, stock and provider identities are retained; no blanket default/audience/size inference for other drafts. Current result127 launching products + unchangedfixture,318 drafts/95 withoutphotos. Owner accepts observed staging OTW display; production cutover remains separate.

Owner approval2026-10-06 permits correction and staging publication of the exact49 enrichment candidates previously reviewed. Fill only missing concentration/audience/size from Owner-accepted supplied verified/input fields; preserve current brands/names/descriptions/prices/URLs/media/status/provider IDs. Eight missing offers use existing current selling prices. Supplied research labels do not automatically authorize other estimates/defaultUnisex or category-conflict overrides/newtaxonomy. Result176launchproducts+fixture/269drafts; remaining121 research-review drafts and95 no-photo drafts untouched. No blanket future publication or production-cutover approval.

## Exact121 accepted-proposal publication — Owner decision2026-10-06

Owner now explicitly accepts the remaining121 enrichment proposals, including estimated sizes/concentrations and eight existing Extrait->EDP category conflicts. For this exact list only, the52 audience proposals labeled estimated/default become Unisex; other supplied genders retain the accepted value. This supersedes earlier no-estimate/default restrictions for this list, not future imports or the separate53 audience-only drafts. Allow missing taxonomy names bodyspray and Perfume Oil exactly as proposed.65 missing size offers use the unchanged current website/source selling price. Preserve current names/brands/descriptions/slugs/media/availability/provider IDs and all old offers. Existing name/copy may still say Extrait where Owner accepted EDP category; no automatic copy rewrite is authorized.

After guarded staging publication all121 are published:297 real launching products plus unchanged AOERA fixture;148 drafts include all95 without photos and53 other audience-only products. No automatic future publication or production cutover approval. The proposal provenance remains supplied/Owner-accepted, including uncertain values; it is not independent fact verification.
