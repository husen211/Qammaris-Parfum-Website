# Qammaris — aturan bisnis yang berlaku

Konsolidasi AUD-02, 2026-10-07: merangkum keputusan Owner yang sudah diterima, bukan keputusan bisnis baru. Implementasi aktual ada di [ARCHITECTURE](../architecture/ARCHITECTURE.md); status/verifikasi di [BACKLOG](../planning/BACKLOG.md). [Dokumen sebelum konsolidasi](../history/2026-10-07-context/BUSINESS_RULES.md) mempertahankan seluruh keputusan launching, daftar yang disetujui, dan aturan yang kemudian digantikan. Angka produk/release dalam riwayat adalah bukti bertanggal, bukan konfigurasi atau izin pekerjaan baru.

## Tes preferensi V1 — keputusan disetujui, belum live

Owner2026-10-09 approved [V1 preference plan](../planning/QAMMARIS_FRAGRANCE_PREFERENCE.md). Owner explicitly continued PREF-02 development2026-10-10; code/offline evaluation is IN_REVIEW, independent PREF-01/PREF-02 quality acceptance remains pending. Public six-question engine remains existing. Future V1: self-only, existing catalog-derived profiles with traceable evidence/unknowns/revisions, no full catalog refilling, no external AI. Detected avoids excluded; unknowns never allergy/free-from proof. Ready/Habis both eligible with Ready filter, max3 inside budget plus separate max1 ≤110% under the agreed relevance condition. Unisex eligible; verified identity only for size dedup. Eight core/two optional preference questions, no age/personality proxies, no unvalidated match percentages or performance promises.

Seven-day same-browser anonymous results and24h local drafts; structured relevance feedback overall/per-product, no post-smell claims, no automatic learning; answers/feedback retained without automatic deletion. No PII/free text/account required.30 Owner/staff-labelled cases, five held out;18/20≥1 relevant and16/20≥2 plus all edges, not a universal accuracy claim. Owner later delegated worksheet filling to Codex. Catalog-assisted agent proposals may populate a separate attributed reference, but do not constitute human sensory validation, passed engine tests or accuracy-target acceptance. Human review/phase gates remain separate; production release requires new permission after acceptance. V2 covers gifts/offline QR/tester/staff and post-smell feedback. Detailed authoritative contract/questions/security/retention/rollout: [approved plan](../planning/QAMMARIS_FRAGRANCE_PREFERENCE.md); [ADR-037](../architecture/decisions/ADR-037-fragrance-preference-review-first.md).

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
- Tidak ada order/customer table, payment gateway atau quantity reservation. Data penerima tidak masuk DB/log aplikasi; validasi gagal dapat memakai old input session sementara. Checkout/redirect no-store/no-referrer; proxy/APM/browser/WhatsApp retention tidak diasumsikan terverifikasi.
- Add-to-cart memberi pending lalu sukses hanya setelah server menerima; animasi menuju ikon opsional menurut reduced motion. Failed action dapat retry tanpa sukses palsu.

Keputusan current: [ADR-028](../architecture/decisions/ADR-028-whatsapp-order-checkout.md), menggantikan inquiry-only ADR-020. Filename checkout ADR-026 lama adalah alias, bukan keputusan kedua.

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
