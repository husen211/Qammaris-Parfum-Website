# Qammaris — aturan bisnis yang berlaku

Konsolidasi AUD-02, 2026-10-07: merangkum keputusan Owner yang sudah diterima, bukan keputusan bisnis baru. Implementasi aktual ada di [ARCHITECTURE](../architecture/ARCHITECTURE.md); status/verifikasi di [BACKLOG](../planning/BACKLOG.md). [Dokumen sebelum konsolidasi](../history/2026-10-07-context/BUSINESS_RULES.md) mempertahankan seluruh keputusan launching, daftar yang disetujui, dan aturan yang kemudian digantikan. Angka produk/release dalam riwayat adalah bukti bertanggal, bukan konfigurasi atau izin pekerjaan baru.

## Identitas, ukuran, dan publication

- Satu halaman mewakili satu produk, satu ukuran, satu harga. Ukuran berbeda biasanya produk terpisah. `ProductVariant` tetap detail teknis satu offer aktif; tidak ada penghapusan model/ID variant.
- ID internal permanen; slug existing dipertahankan. Perubahan URL memerlukan redirect. Nama bukan satu-satunya identifier integrasi. SKU opsional untuk input manusia.
- Identitas sumber adalah UUID provider `qammaris_app`. SKU Majoo hanya membantu pencocokan awal yang diperiksa; SKU Shopee tidak sama dengan SKU Majoo. Identitas provider tidak boleh direbind diam-diam.
- `draft`, `published`, dan `archived` berbeda dari availability. Draft/arsip tidak tampil publik. Hapus admin berarti arsip/nonaktif yang dapat dipulihkan; ID, offer, slug, metadata, dan media tetap disimpan.
- Draft minimal mempunyai nama kerja. Publish membutuhkan brand, nama, slug unik, kategori, ukuran ml, harga valid, deskripsi, peruntukan, satu offer aktif, dan tepat satu primary image. Readiness diperiksa kembali saat publish.
- Produk Habis tetap dapat published dan ditemukan. Hidden upstream adalah guard visibility terpisah; tidak menghapus produk atau otomatis mengubah publication/mapping.
- Produk baru dari feed/import tidak auto-publish. Human publication/bulk publish adalah langkah eksplisit dengan readiness dan scope tervalidasi.
- Brand/kategori yang dipakai tidak boleh dihapus cascade; nonaktifkan/arsipkan atau pindahkan relasinya secara terkontrol.

Keputusan: [ADR-004](../architecture/decisions/ADR-004-drafts-and-single-offer-price-authority.md), [ADR-005](../architecture/decisions/ADR-005-external-product-identity-boundary.md), [ADR-025](../architecture/decisions/ADR-025-recurring-app-catalog-admin-workflow.md).

## Qammaris App: availability, harga, dan draft otomatis

- Majoo tetap sistem operasional; aplikasi internal menyediakan feed website. Website tidak membaca MySQL sumber secara langsung dan tidak mendapat jumlah stok aktual.
- Status produk terhubung diterima apa adanya: `available` → **Tersedia**, `sold_out` → **Habis**, `unknown` → **Tanyakan ketersediaan**. ETA/OTW menambah **Restok segera** pada Habis, bukan mengubahnya menjadi Tersedia.
- Tidak ada kedaluwarsa 36 jam/N hari, downgrade otomatis, atau teks waktu pemeriksaan pada produk terhubung. Koneksi putus mempertahankan status terakhir. Freshness 36 jam legacy/manual hanya berlaku pada produk yang tidak memakai sumber aplikasi; sold_out tetap eksplisit sampai pembaruan berikutnya.
- Feed nonaktif/merged/departemen lainnya tetap menjadi hidden tombstone. `active`, `merged_into`, dan `hidden` tidak boleh diabaikan. Departemen bukan kategori website.
- Webhook HMAC adalah pemicu utama; rekonsiliasi setiap 30 menit jaring pengaman. Revision lama/ganda no-op; checkpoint hanya maju bersama transaksi perubahan yang diterima. Retry tidak boleh menggandakan produk/identitas/audit.
- Kredensial mesin hanya baca, terpisah dari akun karyawan. Bukti foto karyawan, harga modal, dan keuangan bukan bagian kontrak website. Nilai secret tidak masuk Git/chat/log.
- **Harga produk lama ikut otomatis:** revisi sumber lebih baru dengan harga jual integer positif dalam rentang website memperbarui offer terhubung. Harga invalid/null/nol/di luar rentang atau sumber hidden mempertahankan harga terakhir dan membutuhkan review. Harga terhubung tidak boleh ditimpa form/import; koreksi dilakukan di aplikasi internal.
- Feed tidak mengganti nama/slug/media/publication produk existing. `base_price` disinkronkan sebagai mirror offer; comparison price yang tidak lagi lebih besar dibersihkan.
- UUID visible baru otomatis menjadi draft melalui worker existing. Nama, brand, dan harga awal berasal dari sumber; ukuran/konsentrasi hanya diurai bila eksplisit dan tidak ambigu. Deskripsi, foto, dan peruntukan menunggu pelengkapan/review.
- Kandidat produk lama dengan kemiripan nama/SKU yang ambigu ditahan untuk review agar tidak membuat duplikat/rebind. `source=app` ditandai untuk review; harga/foto tidak diasumsikan lengkap.
- Snapshot lama yang belum terhubung tidak otomatis direplay massal: admin memakai preview actor-bound dan apply eksplisit. Tidak reset checkpoint untuk mengulang harga/katalog historis.

[ADR-025](../architecture/decisions/ADR-025-recurring-app-catalog-admin-workflow.md) menggantikan bagian harga-usulan/worker-stock-only ADR-021/022. [Runbook integrasi](../runbooks/QAMMARIS_APP_INTEGRATION.md) menjelaskan operasi; bukti produksi bertanggal ada di [P9-01](../verification/p9-01/README.md).

## Harga dan klasifikasi website

- `product_variants.price` pada satu offer aktif adalah harga jual website; `products.base_price` bukan sumber independen. Draft tanpa ukuran/offer boleh menyimpan harga sumber sebagai data persiapan, bukan offer fiktif.
- Harga published wajib valid/positif; “hubungi kami” tidak menggantikannya. Nilai negatif/di luar presisi ditolak. `compare_at_price` opsional dan harus lebih besar dari harga jual.
- Harga produk manual tetap dapat diedit; bulk maintenance memakai preview/conflict guard. Perubahan schema tidak memberi izin membetulkan harga massal.
- Kategori adalah jenis/konsentrasi (misalnya EDP, Extrait, EDT, mist/oil), bukan brand/departemen. Peruntukan memakai Unisex/Pria/Wanita; SKU, notes, comparison price, meta description dan flag terlaris opsional.
- Ukuran/peruntukan/konsentrasi tidak ditebak. Persetujuan estimasi/default Unisex untuk gelombang launching tertentu hanya berlaku pada daftar historis itu, bukan default impor berikutnya.
- Keputusan final Owner AUD-06: **harga baru menggunakan rupiah bulat**, ditampilkan tanpa `,00` (contoh `Rp 175.000`). Form harga jual/coret dan impor CSV menolak pecahan bukan nol; representasi database `175000.00` tetap valid. Ini menggantikan pilihan desimal sebelumnya dalam task yang sama.
- Tidak ada pembulatan/rewrite harga existing. Jika harga lama berpecahan ditemukan, editor menandainya untuk koreksi; tampilan dan perhitungan tetap mempertahankan nilainya sampai koreksi eksplisit. Checkout tidak boleh membulatkan atau memangkas harga diam-diam. Schema decimal(10,2) dipertahankan. [ADR-030](../architecture/decisions/ADR-030-whole-rupiah-and-current-cart-totals.md).

## Media

- File melalui Laravel Filesystem/product disk; konfigurasi terakhir terverifikasi adalah public storage website yang persisten. Cloudflare R2 tersedia sebagai pilihan adapter/rehearsal, bukan migrasi wajib.
- Database menyimpan key/path dan metadata; Git tidak menyimpan upload produksi. Upload manual dari editor memakai disk yang sama dengan hasil unduhan impor.
- Maksimum tiga gambar aktif: satu primary dan dua tambahan. Foto existing/cover tidak diganti diam-diam. URL Shopee hanya sumber akuisisi; file diunduh, divalidasi dan disimpan lokal, tidak hotlink.
- Downloader hanya menerima HTTPS exact-host allowlist dari konfigurasi, tanpa kredensial/port asing/IP/redirect; timeout/byte/MIME JPEG-PNG-WebP/dimensi dibatasi. Jangan melemahkan TLS atau allowlist saat retry.
- Network/file IO di luar transaksi bisnis; metadata attachment tervalidasi dan failure storage diperiksa. File baru dibersihkan bila attachment gagal; kegagalan satu kandidat tidak menghapus foto lain atau memberi izin publish.
- Arsip gambar mempertahankan file/metadata untuk recovery; primary pengganti dipromosikan atomik. Foto terakhir produk published tidak boleh diarsipkan. Cleanup fisik pekerjaan retensi terpisah.
- Migrasi media: copy → verify → switch → retain → cleanup yang disetujui terpisah. Tidak memindahkan/menghapus sumber dahulu.
- URL/kandidat sukses tidak diunduh/digandakan saat retry; outcome/checksum/actor dicatat. Foto gagal perlu retry atau upload manual; tanpa primary tetap draft.
- Kontrak CSV lama hanya mengakuisisi foto pada draft eligible setelah apply eksplisit. Kontrak Shopee berulang boleh append pada draft/published dengan guard identitas/media; pengecualian ini tidak melonggarkan kontrak lama.

Keputusan: [ADR-006](../architecture/decisions/ADR-006-product-media-storage-boundary.md), [ADR-010](../architecture/decisions/ADR-010-product-media-archive-and-ordering.md), [ADR-014](../architecture/decisions/ADR-014-safe-imported-image-acquisition.md), [ADR-026](../architecture/decisions/ADR-026-recurring-shopee-content-import.md).

## Impor Shopee berulang

- Admin mengunggah ekspor native Informasi Dasar + Media XLSX, bukan wajib konversi CSV. Maksimum 8 MB/file dan 1.000 produk; pasangan ekspor harus memiliki set ID/nama yang sama. Join memakai Kode Produk, bukan urutan/SKU lintas provider.
- Produk berasal dari UUID aplikasi; Shopee memperkaya konten/foto. Harga, availability, nama, slug, brand, dan publication tidak dikendalikan file Shopee.
- Identitas Shopee exact existing didahulukan. Pasangan baru hanya otomatis dikenali bila kandidat nama/ukuran/konsentrasi konservatif unik; ambigu dipilih manusia. Pencarian typo tidak boleh mengubah aturan pairing.
- Preview menyimpan source/provenance, usulan/hash dan actor. Upload file saja tidak mengizinkan apply/publish; tidak auto-rebind identitas occupied/hidden.
- Deskripsi existing dipertahankan kecuali admin memilih penggantian dalam preview. Pelengkapan peruntukan/kategori/offer kosong memerlukan bukti jelas/taksonomi aktif dan operasi harga sumber. Deskripsi tidak mengarang fakta; emoji, READY STOCK, ongkir, dan hashtag dibuang pada curation launching yang disetujui.
- Foto append sampai tiga tanpa menghapus/mengganti cover. Ganti foto saat kapasitas penuh melalui editor dengan review, bukan overwrite impor.
- Salah ukuran Shopee boleh dikonfirmasi **per baris**: simpan sumber, produk, kedua ukuran dan actor; gunakan ukuran website tanpa mengganti harga/URL/identitas. Deskripsi berukuran salah ditahan untuk editor, termasuk saat replacement dipilih. Tidak auto-konfirmasi/wariskan ke ukuran lain; multi-offer/occupied/hidden tetap blocked.
- Review default hanya pekerjaan: enrichment, pilihan manual, recovery, atau draft belum lengkap. Produk published lengkap/no-change tetap dapat dibuka melalui filter complete/all.
- Perubahan harga/stok/timestamp tidak membuat usulan konten stale. Perubahan identitas/ukuran/konten/media yang relevan menahan baris tersebut; baris aman tetap dapat diterapkan. Payload korup tetap error/rollback.
- No-op tidak mengubah produk/mapping/antrean foto. Recheck actor-owned memperbarui usulan/audit unfinished dari sumber tersimpan, bukan produk; apply tetap aksi terpisah.
- Job foto memakai antrean database `product-import-images`, baseline media/identity guards dan bounded downloader. Retry download bukan solusi baseline media stale: review kondisi current melalui inspeksi baru/manual editor, jangan force overwrite.
- Apply tidak auto-publish; selected publish terpisah dan readiness-checked. Produk tanpa foto/konten yang cukup tetap draft. Ketiadaan baris dalam ekspor bukan penghapusan.

Panduan: [SHOPEE_ADMIN_IMPORT](../runbooks/SHOPEE_ADMIN_IMPORT.md); kontrak/recovery: [ADR-026](../architecture/decisions/ADR-026-recurring-shopee-content-import.md). Persetujuan CSV tim aplikasi `sku`/`kuat` dan pilihan Owner pada launching tetap tercatat di [riwayat](../history/2026-10-07-context/BUSINESS_RULES.md) / ADR-024; tidak menjadi izin rebind umum.

## CSV, bulk maintenance, dan audit

Kontrak CSV existing tetap tersedia di samping uploader Shopee. Tidak boleh mencampur contract version, actor, ataupun semantics keduanya.

- CSV provider kanonis UTF-8 memakai header/urutan tetap: provider/kode/nama struktural wajib; kode teks; harga angka positif tanpa simbol/pemisah; notes `|`; URL hanya kandidat HTTPS. Baris ambigu/error/conflict tidak dipaksa. File raw provider selain jalur XLSX Shopee native perlu kurasi ke kontrak.
- Preview read-only terhadap katalog, boleh menyimpan audit immutable/source fingerprint/normalized rows tanpa file sumber. Idempotent untuk actor+file+contract+state; perubahan state memerlukan preview baru. Terminal stale/invalid/failed dapat menghasilkan successor preview deterministic.
- Apply eksplisit admin pada batch previewed/current dengan hash/fingerprint ulang; transaksi dan replay idempotent. Produk baru draft/nonaktif; tidak auto-taxonomy/publish/network. Update otomatis CSV pada draft; published/archived ditahan sebagai protected.
- Sel kosong mempertahankan current, bukan clear/delete. Resolusi protected explicit field-level/one-time dengan optimistic guard, taxonomy exact aktif dan pasangan harga+ukuran; publication, availability, slug, identity dan media tidak ikut berubah. Harga terhubung tetap dilindungi sumber.
- Bulk maintenance hanya mencocokkan `product_id` existing, tanpa fallback nama/SKU/slug. Timestamp + row fingerprint dari snapshot terbaru wajib; duplikat/missing/stale/angka/taxonomy/controlled-value invalid ditahan.
- Allowlist maintenance: nama, deskripsi, brand, peruntukan, stok snapshot, terlaris, kategori, harga+ukuran dan notes. Harga+ukuran bersama; empty mempertahankan current. Tidak mengubah publication/availability/slug/media/external identity.
- Apply maintenance revalidasi contract/hash/catalog+row fingerprint/product/taxonomy/price-size; valid changes saja, no-op dilewati dan conflict ditahan dengan audit. Unexpected error rollback transaksi; applied replay hanya hasil tersimpan.
- Import/maintenance menyimpan actor, waktu, source/batch/hash, outcome/errors aman dan before/after allowlisted. Tidak log whole body/secrets/customer data. AUD-07 menambah riwayat terbatas untuk create/edit produk, metadata galeri, dan arsip/restore admin: actor dari akun yang login, transaksi bersama mutasi, no-op tidak menambah baris. Deskripsi/notes/SKU/key foto hanya hash; tanpa request body, URL/file foto, kredensial atau data penerima. Owner menyetujui retensi tanpa penghapusan otomatis. Tidak backfill atau mencatat seluruh taxonomy/blog/direct SQL; akun bersama tidak membedakan manusia dari automation. [ADR-031](../architecture/decisions/ADR-031-admin-product-change-history.md). Implementasi masih di branch review, bukan bukti rilis.
- Export audit/snapshot read-only, formula-safe BOM UTF-8 dan allowlist. Audit bukan snapshot current; snapshot semua publication berdasarkan ID, harga/ukuran dari offer aktif (kosong bila tidak ada), identities teragregasi, media count/primary saja tanpa path/URL. Snapshot bukan izin write-back.

Kontrak detail: [ADR-011](../architecture/decisions/ADR-011-canonical-product-csv-preview-boundary.md) sampai [ADR-019](../architecture/decisions/ADR-019-transactional-maintenance-apply.md), tersedia di [indeks ADR](../architecture/decisions/README.md).

## Katalog, search, dan beranda

- GET allowlist menjadi state katalog: search, brand, kategori, peruntukan, rentang harga, availability, sort, page. Pagination mengganti page saja; perubahan search/filter/sort kembali ke page pertama. Detail/Kembali mempertahankan context dan posisi untuk query sama; tidak menerima arbitrary return URL.
- Brand pertama; label gender **Peruntukan**; pilihan availability baru Semua/Tersedia/Habis. Domain unknown/URL lama tetap kompatibel. Ukuran bukan filter utama.
- Search memakai nama, brand dan ukuran offer aktif, bukan deskripsi. Semua term harus cocok; exact/partial didahulukan, typo alfabetis konservatif. Angka/SKU/UUID exact; tidak menebak integrasi. Eligibility/actor scope berlaku sebelum matching.
- Default tanpa search **Terlaris dahulu**, flag `is_best_seller` lalu latest stabil. Search default mengutamakan relevansi, merchandising pada tie; sort explicit tetap berlaku. Populer memakai view_count, bukan definisi terlaris.
- Harga/ukuran berasal dari satu offer aktif, bukan fallback base_price untuk published yang rusak. Nama/gambar membuka detail existing; sumber tidak lengkap tidak ditebak.
- Beranda memilih maksimal enam terlaris eligible secara circular berdasarkan tanggal Asia/Makassar, berganti harian tanpa cron/write. **Harga tidak tampil di section beranda**, tetap tampil pada katalog/detail.

Keputusan: [ADR-020](../architecture/decisions/ADR-020-public-catalog-state-and-inquiry-boundary.md) untuk state, [ADR-027](../architecture/decisions/ADR-027-relevant-typo-tolerant-search.md) untuk search.

## Keranjang dan checkout WhatsApp

- Ikon keranjang langsung ke `/cart`, tanpa drawer. Produk published/visible dengan offer valid dan effective availability Tersedia dapat dipesan; Habis/OTW tetap terlihat dengan kontak restok, unknown kontak ketersediaan.
- Checkout wajib nama penerima, nomor HP dan alamat lengkap; kode pos/catatan opsional. Quantity defensif 1–99, bukan reservasi atau bukti jumlah stok aktual.
- Server membaca ulang product/offer/harga/status/publication; session bukan authority harga. Review fingerprint mengikat isi/jumlah/harga/status; perubahan sebelum submit meminta review ringkasan terbaru.
- Composer WhatsApp berisi item/jumlah/harga/subtotal/penerima. Customer menekan Kirim sendiri; membuka composer bukan bukti pesan terkirim/order diterima/pembayaran. Ongkir/pembayaran diselesaikan di WA, tidak diasumsikan gratis/lunas. Keranjang tetap untuk retry.
- Checkout keranjang tidak membuat order/customer record (Pesanan Online di bawah adalah jalur terpisah); tidak ada payment gateway atau quantity reservation. Data penerima checkout keranjang tidak masuk DB/log aplikasi; validasi gagal dapat memakai old input session sementara. Checkout/redirect no-store/no-referrer; proxy/APM/browser/WhatsApp retention tidak diasumsikan terverifikasi.
- Add-to-cart memberi pending lalu sukses hanya setelah server menerima; animasi menuju ikon opsional menurut reduced motion. Failed action dapat retry tanpa sukses palsu.

Keputusan current: [ADR-028](../architecture/decisions/ADR-028-whatsapp-order-checkout.md), menggantikan inquiry-only ADR-020. Filename checkout ADR-026 lama adalah alias, bukan keputusan kedua.

## Pesanan Online dari WhatsApp (ORD-01, branch review)

Keputusan Owner 2026-10-07; implementasi belum dirilis. [ADR-037](../architecture/decisions/ADR-037-online-order-links-and-tracking.md), [program](../planning/ONLINE_ORDERS.md).

- Admin membuat pesanan dari produk published dengan offer aktif berharga positif. Status availability hanya informasi karena stok dikonfirmasi di chat. Harga menjadi snapshot dan tidak berubah oleh katalog. Tidak ada reservasi stok, diskon, atau edit item setelah dibuat (batalkan lalu buat ulang).
- **Data customer (nama, HP, alamat, catatan) disimpan di DB tanpa hapus otomatis**, hanya terlihat oleh admin dan link tugas staf pesanan itu. Ini pengecualian terarah dari aturan checkout keranjang.
- Link customer: tanpa login. Wajib nama, HP, cara menerima (ambil di toko / kirim dalam Kota Palu / luar kota), dan paperbag. Alamat lengkap wajib hanya untuk luar kota; kode pos opsional. Lokasi dalam kota dikirim lewat share location WhatsApp, bukan GPS browser.
- Customer boleh mengubah data sampai admin menandai **Dibayar**; sesudahnya hanya status read-only. Link yang belum diisi kedaluwarsa 7 hari; link baru mematikan link lama.
- Langkah dibuat singkat karena tracking manual: Pesanan dibuat → Data diterima → Dibayar → **Dikirim** (driver/J&T sudah dipesan dan barang jalan; wajib kurir, J&T wajib resi) → **Diterima**. Ambil di toko: Dibayar → **Sudah diambil**. Customer boleh menandai Diterima dari link-nya hanya saat status Dikirim; bila tidak, staf/admin yang menandai. Dibatalkan bersifat terminal tapi bisa dipulihkan admin. Dibayar hanya oleh admin setelah dana benar-benar diterima; bukti transfer bukan otomatis lunas.
- Link tugas staf: tanpa login; pemegang link dapat menandai langkah pengiriman dan mencatat talangan ongkir, tetapi tidak dapat mengubah pembayaran, harga, atau data customer. Nama staf diketik dan tidak terautentikasi.
- Ongkir memisahkan sisi customer (ditambahkan ke transfer / bayar ke driver / gratis) dari dana toko ke driver (cash kasir / GoPay staf dari admin / talangan staf → diganti admin).
- Majoo tetap untuk QRIS, member, struk, dan poin; website hanya menandai "Sudah dicatat di Majoo". Pesan grup disalin manual, tidak dikirim otomatis. Customer tidak melihat nama staf, talangan, atau catatan internal.

## Akses admin dan data pelanggan (ORD-02a, branch review)

Keputusan Owner 2026-10-08; belum dirilis. [ADR-038](../architecture/decisions/ADR-038-admin-roles-and-user-management.md), [runbook](../runbooks/ADMIN_ACCESS.md).

- Role admin: **Super Admin** (akses penuh, mengelola pengguna, menyetujui perubahan finansial), **Staff Order** (hanya Pesanan Online), dan **Admin (lama)** untuk akun sebelum ORD-02. Akun lama tetap bekerja seperti sebelumnya tanpa menu pengguna sampai dikonversi. Role lama tidak bisa diberikan ke akun baru.
- Super Admin pertama hanya lewat bootstrap eksplisit di server setelah Owner memverifikasi identitas. Tidak ada promosi otomatis.
- Login dengan email atau username, tanpa remember-me; sesi admin berakhir setelah 12 jam tidak aktif. Akun nonaktif tidak bisa masuk; nonaktif, ganti role, dan reset password mengakhiri semua sesi akun tersebut.
- Super Admin aktif terakhir tidak dapat dinonaktifkan atau diturunkan.
- Staff Order: membuat/mengubah pesanan, menandai Lunas, membatalkan pesanan yang belum Lunas dan belum diserahkan dengan alasan, dan mengubah alamat tanpa biaya sampai diserahkan. Perubahan ongkir/pendanaan, koreksi langkah, penggantian talangan, dan pembatalan setelah Lunas hanya Super Admin.
- Role Staff Order website dan izin operasional App (`orders.handle`) adalah dua hal terpisah. Memiliki salah satu tidak otomatis memberi yang lain.
- **Data pelanggan (D13):** nama, HP, alamat, dan catatan pesanan hanya dapat dilihat akun admin aktif dengan kemampuan pesanan (Super Admin, Staff Order, Admin lama). Customer melihat datanya sendiri lewat link pesanan. App menerima data penerima secukupnya untuk pengiriman sesuai kontrak API. **Tidak ada penghapusan otomatis untuk sekarang**; kebijakan retensi/anonimisasi ditinjau di ORD-05. Riwayat perubahan akun dan pesanan disimpan tanpa purge.

### Status pesanan terpisah (ORD-02c, branch review)

- Pembayaran, persiapan, kurir/J&T, penyerahan, dan diterima dicatat sebagai status terpisah.
- Pesanan **selesai** bila sudah diserahkan, Lunas, tanpa kendala terbuka, dan tanpa refund terbuka.
- Talangan/reimburse staf yang belum diganti **tidak** menahan status selesai, tetapi tetap tampil sebagai kewajiban terbuka (keputusan Owner R8).
- **Refund tidak pernah diasumsikan** (koreksi Owner 2026-10-09):
  - Pembatalan setelah Lunas tidak otomatis berarti ada utang refund.
  - Super Admin menetapkan nominal yang harus dikembalikan (boleh 0, wajib alasan).
  - Setiap pengembalian dicatat. Refund sebagian maupun penuh didukung.
  - Pesanan tampil sebagai **refund belum selesai** hanya selama masih ada sisa yang benar-benar terutang.
- **Pesanan ORD-01 yang dibatalkan setelah Lunas** ditandai **perlu rekonsiliasi**, karena riwayat refund-nya tidak tercatat. Super Admin mengisi nominal diterima, nominal yang harus dikembalikan, dan yang sudah dikembalikan. Bila belum diketahui, tanda tetap ada.
- Catatan pembayaran/refund tidak pernah diubah atau dihapus. Salah catat dibatalkan dengan entri pembatalan beralasan.
- Keputusan refund, pembayaran refund, pembatalan entri, dan rekonsiliasi hanya oleh **Super Admin**, dan semuanya tercatat di riwayat pesanan. Staff Order boleh mencatat pembayaran masuk.
- Packing baru dianggap selesai bila **setiap barang dikonfirmasi dengan jumlah persis sesuai pesanan**. Barang kurang dicatat sebagai **kendala stok**, bukan packing.
- J&T: minta pickup, QR tersedia, dan dipickup adalah langkah terpisah. Saat dipickup, pesanan otomatis tercatat diserahkan ke J&T. Resi boleh ditambahkan kapan saja.
- Kendala terbuka menahan status selesai. Kendala hanya bisa ditutup oleh pembukanya atau Owner/Super Admin.
- Pesanan yang **sudah ada pembayaran** (walau sebagian) hanya bisa dibatalkan Super Admin. Saat membatalkan, ia wajib menetapkan nominal yang dikembalikan (boleh 0).
- **Pelanggan langganan:**
  - Admin menghubungkan pesanan ke pelanggan secara sadar. Pesanan tidak pernah otomatis digabung berdasarkan nomor, dan satu nomor boleh dimiliki beberapa pelanggan (misalnya keluarga).
  - Alamat disimpan setelah dikonfirmasi admin dan dipakai ulang dengan memilihnya. Alamat itu disalin ke pesanan; mengubah pesanan tidak mengubah alamat tersimpan.
  - Alamat diarsipkan, tidak dihapus, dan tidak bisa diganti setelah pesanan diserahkan.
- Peralihan ke model status baru terjadi per pesanan saat dibuat. Pesanan lama menyelesaikan alurnya sendiri; dua alur tidak pernah mengubah satu pesanan yang sama.

### Aplikasi Qammaris Admin di HP toko (ORD-02b, branch review)

- Admin bisa dipasang sebagai aplikasi "Qammaris Admin" dan dibuka dari `/admin/login`. Website publik tidak berubah dan tidak dipasang sebagai aplikasi.
- Data pesanan dan customer **tidak disimpan di HP**. Setiap halaman admin diambil langsung dari server; tanpa internet hanya muncul halaman "Tidak ada koneksi".
- Perubahan hanya bisa dikirim saat online. Satu formulir Buat pesanan menghasilkan paling banyak satu pesanan, walaupun tombol ditekan dua kali atau dikirim ulang.
- HP toko dipakai bersama:
  - nama akun yang sedang masuk selalu terlihat;
  - **Keluar** selalu tersedia;
  - setelah keluar, tombol Kembali tidak menampilkan halaman admin lagi.
- [ADR-039](../architecture/decisions/ADR-039-admin-pwa.md).

## UI, akses, dan batas program

- Mobile-first, premium cream/charcoal/gold dengan whitespace; jangan memperluas focused task menjadi redesign/SPA/framework atau dependency baru.
- Hover hanya `(hover: hover) and (pointer: fine)`, tanpa scale/translate/resizing/reveal action; kartu/menu langsung tampil tanpa stagger. Touch memakai active, manipulation, transparent tap highlight; navigasi click/native link, bukan awal gesture scroll.
- Feedback pending langsung, reset pageshow/history. Destination skeleton menjaga native Blade navigation/query; bukan fetch/swap router. Invalid/prevented/external/new-tab/download tidak dipaksa loading. Recovery tidak replay POST; reduced motion tetap mendapat feedback.
- Loading/empty/validation/error/success/disabled/retry jelas; keyboard/focus/label/contrast wajib. Admin kembali ke context daftar valid setelah edit/cancel. UI evidence mobile+desktop dan genuine touch/Safari harus dibedakan.
- Waiver touch PR4/PR5 hanya release tersebut; bukan gate waiver permanen. Batas P9 masih [terbuka](../verification/p9-01/README.md).
- Session admin dan automation identity terpisah; credential minimal/revocable, tidak memakai akun/password karyawan/admin untuk machine client. Website write API/AI/payment system belum dibangun.
- Scope destructive production, perubahan credential/permission, publikasi/deployment dan keputusan material memerlukan arahan sesuai [AGENTS](../../AGENTS.md). Waiver backup legacy 2026-10-06 hanya cutover itu, bukan izin destructive work berikutnya.
