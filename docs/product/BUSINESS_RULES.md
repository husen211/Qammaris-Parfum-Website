# Qammaris — aturan bisnis yang berlaku

Konsolidasi AUD-02, 2026-10-07: merangkum keputusan Owner yang sudah diterima, bukan keputusan bisnis baru. Implementasi aktual ada di [ARCHITECTURE](../architecture/ARCHITECTURE.md); status/verifikasi di [BACKLOG](../planning/BACKLOG.md). [Dokumen sebelum konsolidasi](../history/2026-10-07-context/BUSINESS_RULES.md) mempertahankan seluruh keputusan launching, daftar yang disetujui, dan aturan yang kemudian digantikan. Angka produk/release dalam riwayat adalah bukti bertanggal, bukan konfigurasi atau izin pekerjaan baru.

## Tes preferensi V1 — beta live, kualitas final masih ditinjau

Owner2026-10-09 approved [V1 preference plan](../planning/QAMMARIS_FRAGRANCE_PREFERENCE.md). Owner explicitly continued PREF-02 development2026-10-10; code/offline evaluation is IN_REVIEW, independent PREF-01/PREF-02 quality acceptance remains pending. Authorized labelled PREF-03 beta is live; legacy six-question engine remains available by flag rollback. V1 beta: self-only, existing catalog-derived profiles with traceable evidence/unknowns/revisions, no full catalog refilling, no external AI. Detected avoids excluded; unknowns never allergy/free-from proof. Ready/Habis both eligible with Ready filter, max3 inside budget plus separate max1 ≤110% under the agreed relevance condition. Unisex eligible; verified identity only for size dedup. Current v1.2 uses nine core questions (eight when sweetness is skipped) and two optional questions, no age/personality proxies, no unvalidated match percentages or performance promises.

Seven-day same-browser anonymous results and24h local drafts; structured relevance feedback overall/per-product, no post-smell claims, no automatic learning; answers/feedback retained without automatic deletion. No PII/free text/account required.30 Owner/staff-labelled cases, five held out;18/20≥1 relevant and16/20≥2 plus all edges, not a universal accuracy claim. Owner later delegated worksheet filling to Codex. Catalog-assisted agent proposals may populate a separate attributed reference, but do not constitute human sensory validation, passed engine tests or accuracy-target acceptance. Human review/phase gates remain separate; Owner2026-10-10 subsequently authorizes a labelled beta release for testing before independent human acceptance; final V1 quality targets remain pending. [ADR039](../architecture/decisions/ADR-039-preference-website-beta.md) records this exact exception. V2 covers gifts/offline QR/tester/staff and post-smell feedback. Detailed authoritative contract/questions/security/retention/rollout: [approved plan](../planning/QAMMARIS_FRAGRANCE_PREFERENCE.md); [ADR-037](../architecture/decisions/ADR-037-fragrance-preference-review-first.md).

Owner2026-10-10 meminta koreksi bahasa dan alur setelah mencoba beta: rentang budget, keadaan disabled yang jelas, pilihan kegiatan lebih sederhana, waktu siang/malam, skip kemanisan berdasarkan hindari manis/gourmand, pilihan tidak manis, dan label pria/wanita/unisex. [Kontrak koreksi v1.2](../planning/fragrance-preference/question-revision/QUESTION_FLOW_REVISION.md) dan [riset](../planning/fragrance-preference/question-revision/FRAGRANCE_FOUNDATIONS.md) membedakan aroma dari performa. Koreksi v1.2 sudah live melalui PR51 setelah izin rilis terpisah, staging dan preview/rebuild/replay381 profil; [bukti rilis](../verification/pref-03/question-revision/RELEASE.md). Sembilan pertanyaan inti (delapan saat kemanisan dilewati) plus dua opsional. Tidak ada klaim target80–90% sudah tercapai, rewrite katalog, AI eksternal atau pembelajaran otomatis. Aturan hasil3+1 tetap.

## Lokasi dan jam operasional toko

- Keputusan Owner 2026-10-08: toko Palu buka **Sabtu–Kamis, 09.00–21.00 WITA**; **Jumat tutup**. Halaman lokasi harus menampilkan jadwal ini, bukan Senin–Sabtu/Minggu tutup.
- Hari libur nasional mengikuti pengumuman terbaru toko di Instagram; jadwal ini tidak mengubah alur checkout atau menjanjikan balasan chat seketika.

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
- Tidak ada kedaluwarsa 36 jam/N hari atau downgrade otomatis pada produk terhubung. Sejak ORD-04 (Owner 2026-10-11): label tidak mengklaim stok terkini bila feed basi (sinkron sukses terakhir > 120 menit atau gagal) → **Tersedia · stok belum diperbarui**, tetap bisa dipesan; produk manual menampilkan tanggal dicek. Koneksi putus mempertahankan status terakhir. Freshness 36 jam legacy/manual hanya berlaku pada produk yang tidak memakai sumber aplikasi; sold_out tetap eksplisit sampai pembaruan berikutnya.
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
- Tanpa saklar ORD-04 (default): checkout keranjang tidak membuat order/customer record (Pesanan Online di bawah adalah jalur terpisah); tidak ada payment gateway atau quantity reservation. Data penerima checkout keranjang tidak masuk DB/log aplikasi; validasi gagal dapat memakai old input session sementara. Checkout/redirect no-store/no-referrer; proxy/APM/browser/WhatsApp retention tidak diasumsikan terverifikasi.
- Add-to-cart memberi pending lalu sukses hanya setelah server menerima; animasi menuju ikon opsional menurut reduced motion. Failed action dapat retry tanpa sukses palsu.

Keputusan current: [ADR-028](../architecture/decisions/ADR-028-whatsapp-order-checkout.md), menggantikan inquiry-only ADR-020. Filename checkout ADR-026 lama adalah alias, bukan keputusan kedua.

### Checkout keranjang menjadi Pesanan Online (ORD-04, branch review, saklar `ORDERS_WEBSITE_CHECKOUT`)

Saat saklar aktif, aturan di bawah menggantikan larangan simpan data pada butir checkout di atas. Saat saklar mati, checkout tetap seperti ADR-028.

- **Tanpa login.** Customer checkout tanpa login/OTP. Pesanan langsung disimpan di domain Pesanan Online yang sama, dengan nomor `QAM-xxxx`, sebelum WhatsApp dibuka. Tidak ada akun admin pengganti: pembuatnya kosong dan riwayat mencatat "Customer".
- **Pilihan pengiriman:**
  - Ambil di toko (tanpa alamat).
  - Pengiriman Instan — Kota Palu: kurir online (ojol) dipilih staf; info perkiraan 1–2 jam setelah pembayaran dikonfirmasi.
  - Pengiriman Luar Kota — J&T.

  Alamat hanya wajib untuk pengiriman. Kelurahan, kecamatan, dan kode pos opsional.
- **Pilihan lain:** paperbag wajib dipilih. Preferensi pembayaran Transfer bank atau QRIS hanyalah preferensi, tidak menandai lunas. Ongkir tampil "Menunggu konfirmasi staf".
- **Status awal "Menunggu konfirmasi website"** (lifecycle `draft`):
  - belum menjadi tugas packing di Qammaris App;
  - belum bisa dicatat pembayarannya;
  - data tidak bisa diubah customer lewat link (perubahan lewat chat).

  Staff Order boleh mengisi ongkir dan memperbaiki data. Setelah chat, Staff Order menekan **Konfirmasi pesanan**; pesanan lalu mengikuti alur biasa (pembayaran → packing → pengiriman → reimburse). Pesanan yang tidak jadi dibatalkan dengan alasan.
- **Membuka WhatsApp bukan konfirmasi**, bukan bukti pesan terkirim, dan bukan pembayaran.
  - Halaman sukses (link status pesanan, berlaku 7 hari selama belum dikonfirmasi) membuka WhatsApp sekali dan menyediakan **Buka WhatsApp lagi**.
  - Pesanan tetap tersimpan walau WhatsApp gagal atau tidak dikirim.
  - Pesan WhatsApp hanya berisi nomor pesanan, nama, cara pengiriman, dan subtotal.
- **Katalog dicek ulang di server.** Saat simpan, harga dan status Tersedia dicek ulang. Bila berubah, pesanan tidak dibuat dan customer meninjau ringkasan terbaru.
- **Kirim ganda aman.** Tekan dua kali, refresh, atau kirim ulang dengan sesi checkout yang sama menghasilkan satu pesanan yang sama.
- **Batas kirim:** 6 checkout per menit dan 30 per jam per perangkat/IP.
- **Pelanggan langganan.** Nomor HP checkout tidak otomatis membuat pelanggan langganan; menghubungkan pelanggan tetap aksi Staff Order.
- **Privasi.** Data penerima checkout (nama, HP, alamat, kelurahan/kecamatan, catatan) disimpan seperti Pesanan Online lain: hanya untuk mengurus pesanan, terlihat oleh tim Qammaris dan detail tugas App setelah aktif. Belum ada penghapusan otomatis (ORD-05).
- **Tahap 2 (belum dihitung):**
  - Kolom estimasi ongkir dan sumbernya (manual/zona/jarak/API) serta berat produk/item tersedia tetapi kosong.
  - Berat yang belum diketahui tetap kosong, tidak diisi 1 kg.
  - Tidak ada panggilan Maxim, GoSend, atau RajaOngkir.

Keputusan: [ADR-044](../architecture/decisions/ADR-044-website-checkout-orders.md).

### Pembayaran sebelum packing dan pengiriman; tahapan pesanan (ORD-04 revisi, Owner 2026-10-11)

- **Wajib lunas.** Packing (mulai/konfirmasi), klaim tugas App, pesan kurir, request/QR J&T, pickup J&T, dan serah terima hanya bisa setelah status pembayaran **Lunas**. Ditolak di server (Admin PWA dan API App), bukan hanya tombol yang disembunyikan.
  - Bayar sebagian, refund tertunda, atau pembayaran yang dibatalkan = belum lunas; langkah berikutnya menunggu lagi.
  - Keep boleh tanpa bayar (stok dipisahkan), tetapi tidak pernah membolehkan packing atau pengiriman.
  - Tidak dibatasi: lepas klaim, catat kendala, catat biaya ongkir/reimburse, konfirmasi diterima, dan nomor resi J&T setelah pickup.
  - Karena serah terima butuh lunas, pesanan biasanya **Selesai** saat diserahkan. Tombol customer "Pesanan sudah saya terima" mengikuti status pengiriman, bukan status selesai.
- **Tahapan (sama di Website dan App):** Konfirmasi pesanan → Pembayaran → Siapkan pesanan → Pengiriman → Selesai, dihitung dari data server. Halaman pesanan hanya membuka satu tahap aktif dengan satu tombol utama; tahap selesai diringkas satu baris; kendala, keep, link, opsi lanjutan, dan riwayat lewat disclosure.
- **Instruksi pembayaran.** Setelah ongkir dan pembayarnya diisi (atau ambil di toko), staf menyalin "Total & Instruksi Pembayaran" (nomor pesanan, subtotal, ongkir, total, rekening resmi, cara kirim bukti) atau mengirimnya ke chat customer. Pilihan Transfer/QRIS customer hanya preferensi; boleh berubah lewat chat. Tidak ada tanda Lunas otomatis.
- **Rekening resmi** diatur Super Admin di Pesanan Online → Rekening pembayaran: tambah → verifikasi (ketik ulang nomor + centang) → baru dipakai. Rekening tidak bisa diubah/dihapus, hanya dinonaktifkan. Rekening belum terverifikasi tidak pernah ditampilkan.
- **Halaman customer:** setelah total final, tampil total pembayaran, rekening resmi terverifikasi (dengan Salin nomor), dan tombol **Kirim bukti lewat WhatsApp**. Ada tautan **Katalog** dan **Pesanan Saya**.
- **Pesanan Saya tanpa login (10 hari):** maksimal 20 pesanan terbaru yang dibuat atau dibuka di browser ini, disimpan 10 hari lewat cookie aman + referensi di server (tanpa token pesanan atau data penerima di cookie/localStorage). Tidak muncul di browser/HP lain. Detail tetap lewat link aman per pesanan dan aturan masa berlakunya. Bisa dihapus lewat **Hapus Riwayat dari Perangkat**. Tombol Pesanan Saya ada di navigasi katalog (termasuk HP) dan halaman status.
- **WhatsApp:** production menyimpan pesanan dulu, lalu membuka chat nomor resmi (6285144924931) dengan pesan terisi sekali dan menampilkan tombol utama **Kirim ke WhatsApp**; muat ulang tidak membuka lagi; customer tetap menekan Kirim sendiri. UAT/staging tidak pernah membuka nomor toko untuk pengunjung biasa (nomor uji atau pratinjau + Salin pesan). Super Admin boleh mengaktifkan uji nomor resmi **hanya di browsernya sendiri** selama 2 jam; semua pesan dari lingkungan uji diawali **[UAT]**.
- **Paperbag:** Pakai paperbag = **Rp4.000 per pesanan**, tanpa paperbag = Rp0, sebagai komponen biaya tersendiri dalam total customer (checkout, halaman status, admin, instruksi pembayaran). Nilainya dikunci di pesanan saat paperbag dipilih/diubah; kirim ulang tidak menggandakan. Mengubah paperbag setelah ada pembayaran = koreksi total oleh Super Admin (lebih bayar → keputusan refund). Catatan kasir Majoo harus memuat Rp4.000 yang sama.
- **Copy pengiriman:** "Pengiriman Dalam Kota" — "Tiba 1–2 jam setelah pesanan diproses" (enum `local_delivery` tetap). Halaman status: tombol **Pesanan sudah saya terima** di atas, ikon motor (dalam kota) atau truk (J&T) dengan teks; timeline "Menunggu konfirmasi penerimaan dari Anda."
- **Link tugas grup staf:** "Buka di Qammaris App" langsung ke detail pesanan App ({URL App}/orders/{id}) di lingkungan yang sama; tidak pernah dari UAT ke App production atau sebaliknya. Login App kembali ke pesanan; tanpa PWA terbuka di browser.

Keputusan: [ADR-045](../architecture/decisions/ADR-045-payment-gate-and-order-stepper.md), [ADR-046](../architecture/decisions/ADR-046-order-ux-polish-history-paperbag.md) (Pesanan Saya, paperbag, WhatsApp, link App; paperbag di API = [r4.4](../integrations/R4_4_DRAFT_PACKAGING_FEE.md), ditandatangani kedua agen); kontrak App: [r4.3](../integrations/R4_3_DRAFT_PAYMENT_GATE.md) (teks saja, ditandatangani kedua agen 2026-10-11; skema OpenAPI tidak berubah).

## Pesanan Online dari WhatsApp (ORD-01, branch review)

Keputusan Owner 2026-10-07; implementasi belum dirilis. [ADR-048](../architecture/decisions/ADR-048-online-order-links-and-tracking.md), [program](../planning/ONLINE_ORDERS.md).

- Admin membuat pesanan dari produk published dengan offer aktif berharga positif. Status availability hanya informasi karena stok dikonfirmasi di chat. Harga menjadi snapshot dan tidak berubah oleh katalog. Tidak ada reservasi stok, diskon, atau edit item setelah dibuat (batalkan lalu buat ulang).
- **Data customer (nama, HP, alamat, catatan) disimpan di DB tanpa hapus otomatis**, hanya terlihat oleh admin dan link tugas staf pesanan itu. Ini pengecualian terarah dari aturan checkout keranjang.
- Link customer: tanpa login. Wajib nama, HP, cara menerima (ambil di toko / kirim dalam Kota Palu / luar kota), dan paperbag. Alamat lengkap wajib hanya untuk luar kota; kode pos opsional. Lokasi dalam kota dikirim lewat share location WhatsApp, bukan GPS browser.
- Customer boleh mengubah data sampai admin menandai **Dibayar**; sesudahnya hanya status read-only. Link yang belum diisi kedaluwarsa 7 hari; link baru mematikan link lama.
- Langkah dibuat singkat karena tracking manual: Pesanan dibuat → Data diterima → Dibayar → **Dikirim** (driver/J&T sudah dipesan dan barang jalan; wajib kurir, J&T wajib resi) → **Diterima**. Ambil di toko: Dibayar → **Sudah diambil**. Customer boleh menandai Diterima dari link-nya hanya saat status Dikirim; bila tidak, staf/admin yang menandai. Dibatalkan bersifat terminal tapi bisa dipulihkan admin. Dibayar hanya oleh admin setelah dana benar-benar diterima; bukti transfer bukan otomatis lunas.
- Link tugas staf: tanpa login; pemegang link dapat menandai langkah pengiriman dan mencatat talangan ongkir, tetapi tidak dapat mengubah pembayaran, harga, atau data customer. Nama staf diketik dan tidak terautentikasi.
- Ongkir memisahkan sisi customer (ditambahkan ke transfer / bayar ke driver / gratis) dari dana toko ke driver (cash kasir / GoPay staf dari admin / talangan staf → diganti admin).
- Majoo tetap untuk QRIS, member, struk, dan poin; website hanya menandai "Sudah dicatat di Majoo". Pesan grup disalin manual, tidak dikirim otomatis. Customer tidak melihat nama staf, talangan, atau catatan internal.

## Akses admin dan data pelanggan (ORD-02a, branch review)

Keputusan Owner 2026-10-08; belum dirilis. [ADR-049](../architecture/decisions/ADR-049-admin-roles-and-user-management.md), [runbook](../runbooks/ADMIN_ACCESS.md).

- Role admin: **Super Admin** (akses penuh, mengelola pengguna, menyetujui perubahan finansial), **Staff Order** (hanya Pesanan Online), dan **Admin (lama)** untuk akun sebelum ORD-02. Akun lama tetap bekerja seperti sebelumnya tanpa menu pengguna sampai dikonversi. Role lama tidak bisa diberikan ke akun baru.
- Super Admin pertama hanya lewat bootstrap eksplisit di server setelah Owner memverifikasi identitas. Tidak ada promosi otomatis.
- Login dengan email atau username, tanpa remember-me; sesi admin berakhir setelah 12 jam tidak aktif. Akun nonaktif tidak bisa masuk; nonaktif, ganti role, dan reset password mengakhiri semua sesi akun tersebut.
- Super Admin aktif terakhir tidak dapat dinonaktifkan atau diturunkan.
- Staff Order: membuat/mengubah pesanan, menandai Lunas, membatalkan pesanan yang belum Lunas dan belum diserahkan dengan alasan, dan mengubah alamat tanpa biaya sampai diserahkan. Perubahan ongkir/pendanaan, koreksi langkah, penggantian talangan, dan pembatalan setelah Lunas hanya Super Admin.
- Role Staff Order website dan izin operasional App (`orders.handle`) adalah dua hal terpisah. Memiliki salah satu tidak otomatis memberi yang lain.
- **Data pelanggan (D13):** nama, HP, alamat, dan catatan pesanan hanya dapat dilihat akun admin aktif dengan kemampuan pesanan (Super Admin, Staff Order, Admin lama). Customer melihat datanya sendiri lewat link pesanan. App menerima data penerima secukupnya untuk pengiriman sesuai kontrak API. **Tidak ada penghapusan otomatis untuk sekarang**; kebijakan retensi/anonimisasi ditinjau di ORD-05. Riwayat perubahan akun dan pesanan disimpan tanpa purge.

### Layar pesanan V2 (ORD-02d, branch review)

- Staff Order membuat pesanan dari chat WhatsApp/Instagram: pilih produk, pilih pelanggan lama (dan alamat tersimpan) atau pelanggan baru, lalu pilih **data sudah lengkap** atau **kirim link ke customer**.
- Bila data sudah lengkap dari chat, customer **tidak perlu** membuka form. Link tetap bisa dipakai customer untuk melihat status.
- Form customer tetap sederhana: produk sudah terisi, tiga cara menerima, dua pilihan paperbag. Setelah Lunas atau packing dimulai, perubahan dari customer menjadi permintaan yang ditinjau toko.
- Staff Order boleh menandai Lunas dengan sumber konfirmasi (bukti di chat, bukti diunggah, Majoo).
- Keputusan refund, pembayaran refund, pembatalan entri, dan rekonsiliasi hanya untuk Super Admin. Penyesuaian harga diterapkan langsung oleh siapa pun dengan akses pesanan (ADM-01, Owner 2026-10-17).
- Pesanan V2 dikerjakan staf lewat aplikasi Qammaris Admin dengan akun masing-masing. Link tugas tanpa login (ORD-01) tidak berlaku untuk pesanan V2 (D6).

### Operasional pesanan yang disederhanakan (ORD-03, branch review)

- Halaman pesanan menampilkan satu langkah saat ini (link, kendala, pembayaran, packing, atau pengiriman) dengan satu tombol utama. Langkah lain dilipat.
- Pembayaran dicatat dengan nominal, metode, dan centang "Sudah dicatat di Majoo". Sumber konfirmasi tidak dipilih lagi; sistem mencatatnya secara internal (Majoo, atau "dicatat admin").
- Packing: centang setiap barang sesuai jumlah pesanan, lalu konfirmasi. Barang yang kurang dilaporkan lewat "Ada masalah".
- J&T: request pickup, menunggu kurir, lalu sudah di-pickup. QR dan nomor resi opsional dan tidak menghalangi pickup.
- Ongkir (saklar UAT `ORDERS_SIMPLE_UX`): Staff Order boleh mengisi ongkir yang ditagihkan ke customer selama belum ada pembayaran sama sekali. Setelah ada pembayaran, perubahan total lewat penyesuaian harga (langsung diterapkan sejak ADM-01). Pendanaan driver dan refund tetap Super Admin.
- Customer boleh memilih metode pembayaran (Transfer bank atau QRIS) di link pesanan. Pilihan ini hanya preferensi: tidak menandai lunas dan tidak membuat QRIS Majoo.
- Link pesanan dibagikan lewat Salin Link, Salin Pesan, atau menu bagikan bawaan HP. WhatsApp tidak wajib.

### Integrasi pesanan dengan Qammaris App (ORD-02e, branch review)

- Website tetap menjadi sumber data pesanan. App mengerjakan pesanan V2 lewat API v1 privat (kontrak r4.1); setiap perubahan dari App tercatat dengan nama staf App dan ID request.
- Di App, staf harus **mengklaim** tugasnya lebih dulu (packing, pesan kurir/J&T, serah ke kurir). Satu tugas hanya dipegang satu orang.
  - Admin PWA adalah jalur cadangan dan tidak dibatasi klaim.
  - Super Admin bisa melepas klaim yang macet dengan alasan, dan alasan itu tercatat.
- Link pesanan di grup WhatsApp membuka Admin PWA. Link baru membuka halaman pesanan di App setelah saklar `QAMMARIS_ORDER_APP_TASK_LINKS` dinyalakan atas persetujuan Owner. Link ini tidak pernah berisi token akses.
- Selama integrasi aktif, **kendala baru dicatat dari App**. Kendala yang sudah ada tetap bisa diselesaikan di Website. Aturan ini berlaku sampai kontrak r4.2 berlaku dan App mendukungnya (draf r4.2: kendala dari Website dikirim dengan `opened_by_source=website`).
- **Pengecualian bukti reimburse (keputusan Owner 2026-10-09, draf r4.2):** hanya Owner App yang boleh membuat atau menyetujui pengecualian bukti. Backend App boleh mengirim ulang keputusan yang sudah sah tanpa Owner sedang melakukan request itu. Website memisahkan pemberi persetujuan, aktor request, dan jalur sinkronisasi. Keputusan baru tidak diterima hanya karena payload memuat nama atau ID Owner: Owner itu sendiri yang harus mengirim, dan ID-nya harus terdaftar di allowlist Website.
- Bila App bermasalah, pesanan tetap dikerjakan di Admin PWA. Pemberitahuan ke App menunggu dan dicoba ulang. Setelah 24 jam, event yang gagal tampil di **Integrasi App** untuk dikirim ulang oleh Super Admin.

### Ongkir kurir dan talangan dicatat sekali di Website (ORD-08, Owner 2026-10-17, branch review)

- Semua pekerjaan pesanan dilakukan di Website. Qammaris App hanya tampilan cadangan dan tempat reimburse masuk.
- Peran:
  - **Super Admin (Owner, dari Jakarta)** membuat pesanan dan mengirim link ke customer. Owner tidak perlu mengonfirmasi pembayaran, packing, atau uang siapa;
  - **Staff Order** (Andi/Ikrar, satu akun bersama di laptop toko) mengerjakan pembayaran → packing → pesan kurir (ongkir + uang siapa) → serah terima.
- Pesanan Online di App hanya untuk dilihat: tanpa "Dilakukan oleh", tombol langkah, atau "Catat ongkir", dengan tombol "Buka di Website". Link pesanan di grup WhatsApp membuka halaman pesanan Website ("Buka pesanan"), dan saklar `QAMMARIS_ORDER_APP_TASK_LINKS` dimatikan saat rilis.
- Langkah kurir dalam kota: **Kurir sudah dipesan → Sudah diserahkan ke kurir** (tanpa langkah "Kurir tiba").
- Saat **Kurir sudah dipesan** (atau **Request pickup J&T**), Staff Order wajib mengisi:
  - **Ongkir ke kurir**: nominal yang benar-benar dibayar ke kurir;
  - **Uang siapa**: Andi / Ikrar / Kasir / Customer bayar driver.
- Ongkir kurir bisa diubah lewat "Ubah ongkir kurir". Satu pesanan hanya punya satu catatan ongkir kurir. Setelah reimburse dibayar, koreksi lewat Owner di App.
- Uang Andi/Ikrar otomatis menjadi reimburse karyawan itu di Qammaris App, **langsung disetujui, bukti opsional** (keputusan Owner). Owner tinggal membayar.
  - Pilihan karyawan diambil langsung dari karyawan aktif di Qammaris App dan disambungkan lewat ID akun. Karyawan baru atau yang berhenti otomatis ikut berubah. Ganti nama akun di App langsung terlihat di halaman pesanan Website, reimburse, dan ongkir pesanan orang itu.
  - Nama yang tidak cocok tidak membuat reimburse dan memberi notifikasi ke Owner.
- Super Admin (Owner) yang memesan kurir dari Jakarta memilih **Uang saya (GoPay Owner)** / Kasir / Customer bayar driver, tanpa nama karyawan. Super Admin tidak mengerjakan packing.
- Uang Owner, Kasir, dan Customer bayar driver tidak membuat reimburse.
- Semua isian rupiah di admin memakai pemisah ribuan (15.000).
- Ongkir yang ditagih ke customer (ditambahkan ke transfer / bayar ke driver / gratis) tetap terpisah dari ongkir yang dibayar ke kurir.
- Pilihan lama "Toko bayar driver dengan" tidak ada lagi di halaman pesanan V2.

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
- **Keep (D9):**
  - Default 24 jam (1–168 jam), boleh belum dibayar.
  - Staf mengonfirmasi manual bahwa stok sudah dipisahkan.
  - Lewat batas waktu, keep ditandai **perlu tindakan** dan tidak pernah dibatalkan otomatis.
  - Keep berakhir sendiri saat pesanan Lunas atau diserahkan, dan dilepas bila pesanan dibatalkan.
- **Penyesuaian harga:**
  - Owner 2026-10-17 (ADM-01): siapa pun dengan akses pesanan (termasuk Staff Order) menerapkan potongan/tambahan langsung, dengan alasan, tanpa persetujuan Super Admin. Total langsung berubah; siapa yang menerapkan tercatat di Riwayat. Total tidak boleh di bawah 0.
  - Pengajuan lama yang masih menunggu tetap diputuskan Super Admin.
  - Bila customer jadi **lebih bayar**, hal itu ditampilkan. Pengembaliannya tetap butuh keputusan refund Super Admin.
- **Permintaan perubahan customer:**
  - Customer mengubah data langsung hanya selama belum Lunas dan packing belum dimulai. Setelah itu perubahan menjadi permintaan yang ditinjau admin.
  - Staff Order menyetujui perubahan penerima/alamat sampai diserahkan. Perubahan cara pengiriman (bisa mengubah biaya) hanya oleh Super Admin, dan tidak bisa bila kurir/J&T sudah diminta.
- Peralihan ke model status baru terjadi per pesanan saat dibuat. Pesanan lama menyelesaikan alurnya sendiri; dua alur tidak pernah mengubah satu pesanan yang sama.

### Aplikasi Qammaris Admin di HP toko (ORD-02b, branch review)

- Admin bisa dipasang sebagai aplikasi "Qammaris Admin" dan dibuka dari `/admin/login`. Website publik adalah aplikasi terpisah (PWA-01, di bawah).
- Data pesanan dan customer **tidak disimpan di HP**. Setiap halaman admin diambil langsung dari server; tanpa internet hanya muncul halaman "Tidak ada koneksi".
- Perubahan hanya bisa dikirim saat online. Satu formulir Buat pesanan menghasilkan paling banyak satu pesanan, walaupun tombol ditekan dua kali atau dikirim ulang.
- HP toko dipakai bersama:
  - nama akun yang sedang masuk selalu terlihat;
  - **Keluar** selalu tersedia;
  - setelah keluar, tombol Kembali tidak menampilkan halaman admin lagi.
- [ADR-050](../architecture/decisions/ADR-050-admin-pwa.md).

### Aplikasi website Qammaris Perfumes (PWA-01, branch review, saklar `PUBLIC_PWA_ENABLED`)

- Pengunjung bisa memasang website sebagai aplikasi "Qammaris Perfumes" (ikon logo hitam di atas krem). Aplikasi ini terpisah dari "Qammaris Admin".
- Katalog, keranjang, checkout, status pesanan, dan Pesanan Saya **selalu diambil langsung dari server**; data pesanan dan penerima tidak disimpan oleh aplikasi.
- Tanpa internet hanya muncul halaman "Tidak ada koneksi". Pesanan tidak bisa dikirim, diantrikan, atau dikirim ulang otomatis saat offline.
- Tidak ada notifikasi atau permintaan izin notifikasi.
- Saklar mati (default): website tampil seperti sebelumnya dan aplikasi yang sudah terpasang melepas dirinya sendiri tanpa menghapus login admin, keranjang, atau Pesanan Saya.
- [ADR-047](../architecture/decisions/ADR-047-public-website-pwa.md).

## UI, akses, dan batas program

## Journal — implemented; production agent activation separate

BLOG-05 installed via approved PR31 release2026-10-08 ([evidence](../verification/blog-06/README.md)): identitas mesin Sanctum terpisah dari admin/feed; token hash, ability terbatas dan kedaluwarsa 90 hari. Agent hanya membaca/menulis draft miliknya; tayang/jadwal/arsip/aktor lain ditolak, termasuk setelah lock dan saat replay. Owner memberi/mencabut akses per draft di admin. Harga/stok/katalog/taxonomy tidak ditulis agent. Input publication/actor/unknown ditolak; PATCH membutuhkan revision dan mempertahankan field yang tidak dikirim. Foto melalui upload file milik draft dan marker ID, bukan fetch/hotlink arbitrer; HTML legacy yang tidak dikirim tetap dipertahankan. Create/upload memakai Idempotency-Key, pointer/revision replay atomik dengan mutasi/audit; key/body/token raw tidak disimpan. Audit membedakan admin/machine dan hash isi. API tetap default OFF untuk environment baru. Owner mengizinkan aktivasi produksi dan token lokal2026-10-08; satu actor/token lokal aktif dengan hak draft-only ([bukti](../verification/journal-activation/README.md)). [ADR-036](../architecture/decisions/ADR-036-journal-draft-automation.md), [kontrak](../api/JOURNAL_AUTOMATION.md).

Program [Qammaris Journal](../planning/QAMMARIS_JOURNAL.md) disetujui terpisah dari P9. Nama tampilan Journal, URL `/blog/...`/ID/slug/konten/author/media existing dipertahankan. Editor visual + HTML dengan sanitasi server; admin boleh langsung memperbarui artikel tayang. API agent draft-only milik actor, tanpa hak publish atau perubahan katalog, kode/schema sudah terpasang pada produksi BLOG-05; kredensial produksi belum diaktifkan.

BLOG-01 implemented: penggantian gambar simpan → verifikasi → ubah referensi → pertahankan gambar lama; arsip bukan hapus, pulihkan sebagai draft. Konflik revision tidak menimpa perubahan sesi lain, no-op/view count tidak mengubah revision/waktu editorial. Upload JPEG/PNG/WebP max5MB/max6000px per sisi lewat Laravel disk (default public/blog). Default author baru Qammaris Editorial, author lama tidak diubah massal. Audit admin artikel dalam transaksi mutasi, field allowlisted dan hash isi/key gambar, tanpa body/request/token/URL; tidak ada purge/backfill otomatis. Rilis/migrasi MySQL terverifikasi dalam BLOG-06; lihat batas evidence. [ADR-032](../architecture/decisions/ADR-032-blog-write-foundation.md).

BLOG-02 mengimplementasikan draft belum lengkap; publish/jadwal baru membutuhkan judul, kategori aktif, ringkasan, teks bermakna, author, gambar utama dan alt. Artikel yang sudah published/scheduled tidak otomatis diturunkan karena metadata gambar baru; setelah menjadi draft/arsip, publish berikutnya memenuhi guard baru. Excerpt/ringkasan dan subtitle/pengantar terpisah. Empat kategori existing/URL tidak diubah atau dibackfill; taxonomy/tag baru dikelola Owner melalui tambah dan status aktif, tanpa rename/delete. Read time minimum1menit/200kata. Preview admin tidak menyimpan artikel/file atau menambah view count. Editor visual/HTML memelihara sumber sampai perubahan nyata, sanitasi server tetap wajib. Marker produk hanya ID tervalidasi; nama/tautan mengikuti katalog publik, isi harga/stock dari marker kiriman dibuang. Kartu lengkap/harga/status, media resize, SEO lengkap dan API disediakan BLOG-03–05. Harga/stok dalam body lama tidak diubah otomatis; pemeriksaan editorial existing menjadi penerimaan BLOG-06. [ADR-033](../architecture/decisions/ADR-033-blog-editorial-cms.md).

BLOG-03 implemented: nama publik Qammaris Journal, URL lama tetap. Search memprioritaskan judul/ringkasan/tag aktif/nama produk dan brand terkait yang layak publik; typo hanya metadata, isi artikel berupa frasa tepat sekunder. Daftar isi muncul setelah tiga H2 nonempty; anchor unik/tabel contained dibuat saat render tanpa rewrite body. Share memakai URL bersih, tidak mengirim pesan otomatis. View count tetap internal dan tidak tampil publik.

SEO/canonical/OG tersimpan lewat write/revision/audit existing. Canonical/OG image override hanya HTTPS valid tanpa credential/fragment; gambar override metadata saja, tidak diunduh. Fallback gambar sosial adalah gambar utama. Index/follow tidak mengubah visibility; sitemap hanya artikel tayang indexable dan self-canonical. Lastmod editorial, fallback tanggal publish untuk waktu editorial legacy null, bukan view count. Cache berakhir pada jadwal terdekat dan diperbarui setelah edit. SEO-only edit tidak mengubah waktu editorial. Schema mencerminkan fakta terlihat, tidak menjamin indexing/rich results. Kartu produk lengkap/media/API tetap BLOG-04/05, bukan tahap ini. [ADR-034](../architecture/decisions/ADR-034-public-journal-search-seo.md).

BLOG-04 menambahkan media milik artikel, metadata hak pakai, checksum original, resize WebP tanpa upscale dan crop16:9/4:3/1:1 dengan titik fokus. Original/crop lama dipertahankan; arsip media tidak menghapus file dan gambar utama harus diganti dahulu. Batas5MB/6000px/12MP/kapasitas memori; GD/WebP tidak aktif memakai original dengan peringatan. Komponen gambar/galeri2–8/callout/CTA/YouTube/artikel hanya marker tervalidasi, bukan iframe/script bebas. Pilihan berurutan maksimal12produk/3artikel/12FAQ/20referensi; maksimal50komponen isi. Kartu membaca harga/ukuran/status/gambar/tautan katalog saat render, tanpa perubahan katalog. Draft/hidden/arsip tidak direkomendasikan; Habis tetap boleh. FAQ/referensi teks biasa/HTTPS. Publik dan preview memakai renderer yang sama; media/relasi editorial mengubah revision/waktu dan audit hash, no-op tidak. Tidak ada konversi massal artikel/foto; rilis kode tidak mengaktifkan kredensial API. [ADR-035](../architecture/decisions/ADR-035-journal-media-and-components.md), [panduan media](../runbooks/JOURNAL_MEDIA.md).

## UI, akses, dan batas program (current)

- Mobile-first, premium cream/charcoal/gold dengan whitespace; jangan memperluas focused task menjadi redesign/SPA/framework atau dependency baru.
- Hover hanya `(hover: hover) and (pointer: fine)`, tanpa scale/translate/resizing/reveal action; kartu/menu langsung tampil tanpa stagger. Touch memakai active, manipulation, transparent tap highlight; navigasi click/native link, bukan awal gesture scroll.
- Feedback pending langsung, reset pageshow/history. Destination skeleton menjaga native Blade navigation/query; bukan fetch/swap router. Invalid/prevented/external/new-tab/download tidak dipaksa loading. Recovery tidak replay POST; reduced motion tetap mendapat feedback.
- Loading/empty/validation/error/success/disabled/retry jelas; keyboard/focus/label/contrast wajib. Admin kembali ke context daftar valid setelah edit/cancel. UI evidence mobile+desktop dan genuine touch/Safari harus dibedakan.
- Waiver touch PR4/PR5 hanya release tersebut; bukan gate waiver permanen. Batas P9 masih [terbuka](../verification/p9-01/README.md).
- Session admin dan automation identity terpisah; credential minimal/revocable, tidak memakai akun/password karyawan/admin untuk machine client. Journal draft API sudah diimplementasikan/default OFF; katalog/order/payment machine-write API tidak disediakan program Journal.
- Scope destructive production, perubahan credential/permission, publikasi/deployment dan keputusan material memerlukan arahan sesuai [AGENTS](../../AGENTS.md). Waiver backup legacy 2026-10-06 hanya cutover itu, bukan izin destructive work berikutnya.

## About — Owner-approved store identity (2026-10-08)

- Qammaris is an experience store: every in-store product has a tester; staff help recommend according to preferences/needs. The store welcomes exploration and discussion, serving customers wholeheartedly. These are Owner-approved claims, not derived from review examples.
- Founder story is based on the two first-party LinkedIn posts signed Husein; Jakarta university/fragrance exploration, difficulty trying before buying on return to Palu, then design/renovation/store experience. Owner subsequently confirmed (2026-10-08) that the journey begins in2024 and the store opened February2026. Intermediate design/build steps remain a named sequence without invented exact dates; current-store images illustrate the concept, not a dated2024 photo archive.
- Nine Owner-supplied store/build/design/tester assets may be published. Keep original files; deploy optimized responsive derivatives through GitHub as curated site assets, independent of product/blog uploaded storage.
- Google rating is a dated observation, not automatically live. Reviewer name/excerpt/stars require verified source; no fabricated demo testimonials/count or self-serving review structured data.
- Public hours: Saturday–Thursday09:00–21:00WITA, Fridayclosed. Owner is authority where Google Maps differs; Google account editing is outside this implementation.
- Instagram Reel is opt-in click-to-load, portrait and always offers a direct source link. No dependency on video availability for reading/using About. Existing publicURL`/store/about` remains.

Implementation/release status: [ABOUT-01](../planning/BACKLOG.md), [provenance](../verification/about-experience/source-notes.md).

Owner About presentation correction2026-10-09 (ABOUT-04): replace Hero10 with actual ravikatiyar162/hero-section-2 and match its nested stagger/20px fade-up plus1200ms circOut diagonal reveal. Keep one previously supplied storefront photo and real Qammaris logo/contact/address; cream/charcoal/gold, plain copy, touch-safe native navigation and reduced-motion/no-JS. Other About sections/facts/hours/source media remain; no stock imagery or framework migration from the pasted setup prompt. [Current source/motion mapping](../verification/about-split-hero/README.md); previous Hero10/cinematic evidence retained as history. No business/order/catalog/database rule change.
## Optional visitor city statistics (Owner, 2026-10-12)

Owner approved GA4 city statistics for public discovery pages. Visitors may allow or skip Google Analytics and change the choice later. Do not collect customer/order/payment identities, private bearer links, checkout/form data or Admin activity. City reports describe approximate network location of consenting visitors, not verified customer location. Advertising personalization and automatic enhanced measurement remain off; Search Console remains the source of Google search keywords. See [analytics runbook](../runbooks/ANALYTICS.md).
