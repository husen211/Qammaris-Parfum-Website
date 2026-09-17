# Kontrak UX Katalog Publik Qammaris

Status: baseline dan kontrak implementasi Phase 7 — 2026-09-17

## Tujuan

Katalog publik membantu customer menemukan parfum, memahami identitas produk, ukuran, harga, dan status ketersediaan secara jujur, lalu memulai inquiry. Website bukan sumber live inventory dan tidak menjanjikan reservasi atau pembayaran online.

Surface ini tetap mobile-first, server-rendered, dan memakai identitas Qammaris yang sudah ada: black, ivory, gold, fotografi produk, serta tone editorial yang tenang. Phase 7 tidak memigrasikan framework atau membuat SPA.

## Baseline 2026-09-17

### Data lokal read-only

- 180 produk berstatus published.
- 180 produk mempunyai effective availability `unknown`.
- 115 produk published belum mempunyai offer aktif.
- 166 produk published belum mempunyai primary image.
- Audience terdiri dari 35 Pria, 23 Wanita, dan 122 Unisex.

Angka ini adalah utang data lokal, bukan izin menebak harga, ukuran, gambar, atau stok. Publication completeness tetap menjadi aturan target. Data reconciliation/import berlangsung pada pekerjaan terpisah.

### Listing saat ini

- Desktop memakai sidebar search, satu kategori, multi-brand, sort, grid empat kolom, dan 10 item per halaman.
- Mobile memakai dua kolom, sticky action untuk filter/sort, serta bottom dialog untuk search, kategori, dan brand.
- Kartu hanya menampilkan gambar, best-seller flag, brand, nama, dan harga. Ukuran serta availability tidak terlihat.
- Filter gender, harga, dan availability belum tersedia.
- Label `Showing 10 products` menampilkan jumlah item halaman, bukan jumlah hasil keseluruhan.
- Pagination berikutnya membuang search, brand, dan sort; bukti URL berubah dari state terfilter menjadi `/products?page=2`.
- Pilihan sort mobile selalu merender `Newest` walaupun URL memakai sort lain.
- Tautan produk dan breadcrumb katalog tidak membawa context hasil sebelumnya.

### Detail saat ini

- Pada `390x844`, gambar utama mendorong identitas, harga, dan action ke bawah initial viewport.
- Detail memakai selector `Select Size` walaupun domain target adalah satu produk/satu ukuran/satu harga.
- Teks `In Stock` berasal dari stock variant dan selalu dirender sebelum JavaScript mengevaluasi variant. Ini tidak mewakili effective availability.
- Produk tanpa offer tetap menampilkan quantity dan `Add to Cart`, lalu baru gagal sebagai `Varian belum tersedia`.
- Tombol WhatsApp memakai link toko generik; konteks produk belum dijamin ada pada pesan.
- Cart secara teknis masih memakai istilah belanja, padahal outcome bisnis adalah inquiry melalui WhatsApp.

### Browser baseline

| Viewport | Hasil |
|---|---|
| `390x844` | Tidak ada horizontal overflow; grid dua kolom dan dialog filter dapat dibuka. Header logo 28 px, cart/filter/sort sekitar 40 px sehingga belum memenuhi target sentuh 44 px. |
| `1440x900` | Tidak ada horizontal overflow; sidebar dan grid empat kolom tampil. Hero memakan ruang besar untuk halaman task-oriented dan hasil baru mulai terlihat jauh di bawah header. |

Console browser tidak mempunyai warning/error pada listing dan detail yang diaudit. Banyak kartu memakai placeholder karena utang primary image lokal.

## Primary journey

1. Customer membuka katalog dan langsung melihat jumlah hasil serta produk tanpa hero yang mendominasi tugas.
2. Customer mencari atau memfilter berdasarkan brand, kategori, audience, harga, dan availability.
3. Customer mengurutkan hasil, berpindah halaman, dan melihat filter aktif tanpa kehilangan state.
4. Kartu menjelaskan brand, nama, satu ukuran, satu harga, best seller bila benar, dan status availability yang jujur.
5. Customer membuka detail lalu dapat kembali ke posisi/context katalog yang sama.
6. Customer menambahkan produk ke daftar inquiry atau langsung menghubungi WhatsApp dengan konteks produk.

## Kontrak query katalog

Query publik yang didukung:

| Parameter | Bentuk | Aturan |
|---|---|---|
| `search` | string | Trim, collapse whitespace, maksimum 100 karakter; mencari nama dan brand, deskripsi sebagai sinyal sekunder. |
| `brand[]` | ID taxonomy | Multi-select; hanya brand aktif yang valid. ID numeric existing tetap kompatibel. |
| `category` | ID taxonomy | Single-select tahap awal; hanya kategori aktif yang valid. |
| `gender` | enum | Kosong, `Unisex`, `Pria`, atau `Wanita`. |
| `price_min` | integer IDR | Opsional, tidak negatif, dibandingkan dengan harga offer aktif. |
| `price_max` | integer IDR | Opsional, tidak negatif dan tidak lebih kecil dari `price_min`. |
| `availability` | enum | Kosong, `available`, `sold_out`, atau `unknown`; memakai effective availability, bukan raw status. |
| `sort` | enum | `latest`, `price_low`, `price_high`, atau `popular`. |
| `page` | integer | Minimal 1; reset ke 1 setiap filter/search/sort berubah. |

Aturan state:

- Input invalid/unknown diabaikan secara deterministik dan tidak diteruskan ke URL hasil.
- Pagination mempertahankan seluruh query allowlisted selain nilai `page` lama.
- Desktop dan mobile membaca state yang sama; label kontrol harus sesuai URL aktual.
- Clear satu filter hanya menghapus parameter itu. Clear all kembali ke `/products`.
- Semua link kartu membawa context allowlisted ke detail. Detail mempunyai canonical URL tanpa query dan tombol kembali membangun URL katalog hanya dari context tervalidasi, bukan arbitrary return URL.
- Browser back harus tetap bekerja secara natural. Tidak ada state penting yang hanya disimpan di JavaScript memory.
- Urutan selalu deterministic dengan tie-breaker ID agar item tidak meloncat antarhalaman.
- Target page size awal adalah 24 produk agar seimbang untuk grid 2/3/4 kolom dan katalog 300–500 produk; perubahan nilai harus disertai pengukuran performa.

Definisi sort:

- `latest`: `published_at` terbaru, lalu ID terbaru.
- `price_low`/`price_high`: harga satu offer aktif; row tanpa offer tidak boleh mendapat harga tebakan dari `base_price`.
- `popular`: `view_count` tertinggi, lalu ID terbaru. Copy memakai “Populer”, bukan klaim penjualan “Terlaris”.

## Kontrak informasi kartu

Urutan minimum kartu:

1. Foto produk atau placeholder lokal stabil dengan rasio tetap.
2. Brand.
3. Nama produk.
4. Ukuran offer aktif, misalnya `100 ml`.
5. Harga offer aktif dalam Rupiah.
6. Availability truth.
7. Best seller hanya bila `is_best_seller=true`.

Aturan:

- Seluruh area identitas kartu dapat membuka detail, tetapi elemen interaktif tidak boleh nested.
- Gambar tidak boleh memakai provider hotlink atau placeholder eksternal.
- Nama panjang dibatasi secara visual tanpa menghilangkan accessible name.
- Produk sold out tetap tampil; visual boleh lebih tenang tetapi tetap terbaca dan tidak tampak disabled total.
- Legacy row tanpa offer/image memakai state netral “Data sedang dilengkapi” dan tidak mengarang ukuran/harga. Publication data debt harus terlihat pada audit admin dan diselesaikan sebelum cutover.
- `is_best_seller` adalah flag merchandising. Sort “Populer” tidak boleh diberi label “Terlaris”.

## Kontrak availability dan action

| Effective status | Copy publik | Primary action |
|---|---|---|
| `available` | `Tersedia saat diperiksa` | `Tanya stok via WhatsApp` atau tambah ke daftar inquiry; bukan jaminan reservasi. |
| `unknown` | `Konfirmasi stok` | `Tanya stok via WhatsApp` atau tambah ke daftar inquiry. |
| `sold_out` | `Sold out` | `Tanya restock`; tidak dapat ditambahkan sebagai order biasa. |

- Waktu pemeriksaan dapat ditampilkan pada detail dengan bahasa manusia, tanpa mengekspos source internal.
- Raw `available` yang stale lebih dari 36 jam dirender sebagai `unknown`.
- Quantity snapshot tidak ditampilkan sebagai live quantity.
- Variant stock tidak menentukan copy availability publik.
- WhatsApp message minimal membawa nama, brand, ukuran, URL canonical product, dan intent `stok` atau `restock`. Nomor/URL tetap berasal dari konfigurasi toko.
- “Cart” diperlakukan sebagai daftar inquiry, bukan checkout pembayaran atau reservasi. Rename customer-facing dan perubahan flow dikerjakan pada item terpisah tanpa wajib mengganti route internal sekaligus.

## Layout mobile-first

### Listing

- Header katalog ringkas; hasil dan kontrol discovery muncul pada initial useful viewport.
- Sticky toolbar menjelaskan jumlah filter aktif dan current sort.
- Filter memakai native dialog/drawer dengan focus trap, close label, Apply, Clear, serta state selected yang terlihat.
- Dua kolom hanya dipakai bila nama, ukuran, harga, dan status tetap terbaca; fallback satu kolom diperbolehkan untuk breakpoint sangat sempit.
- Semua action utama minimal `44x44` CSS pixel dengan jarak yang tidak memicu salah tekan.

### Detail

- Brand, nama, ukuran, harga, availability, dan action inquiry harus terlihat lebih awal; media tidak boleh mendorong semua informasi tersebut jauh ke bawah.
- Satu offer ditampilkan sebagai informasi, bukan selector ukuran palsu.
- Gallery mendukung swipe/thumbnail tanpa kehilangan alt text atau focus state.
- Sticky mobile action boleh digunakan bila tidak menutupi konten, safe area, atau action accessibility.

### Desktop

- Sidebar/drawer mengikuti state yang sama dengan mobile.
- Lebar konten, density grid, dan fotografi menjadi fokus; dekorasi hero tidak mengalahkan discovery.
- Hover bersifat enhancement. Semua action tersedia lewat keyboard dan tidak hanya muncul saat hover.

## State wajib

- Populated default.
- Search/filter aktif dengan result count dan removable filter summary.
- No result dengan copy spesifik serta clear/recovery action.
- Invalid/stale query yang dinormalisasi aman.
- Produk tanpa image, tanpa offer, dan taxonomy legacy yang hilang.
- `available`, `unknown`, serta `sold_out`.
- Long name/description/notes.
- WhatsApp configuration unavailable: action disabled dengan penjelasan, bukan link rusak.
- Server error memakai error page existing; tidak memalsukan empty result.

Server-rendered page tidak membutuhkan skeleton loading pada initial request. JavaScript enhancement harus mempertahankan form GET yang tetap berfungsi tanpa JavaScript.

## Accessibility

- Satu `h1`, urutan heading logis, landmark `main`, dan result summary yang dapat diumumkan.
- Semua icon-only control mempunyai accessible name.
- Visible focus memakai token kontras existing dan tidak dihapus.
- Dialog mengembalikan fokus ke trigger saat ditutup.
- Input mempunyai label; error price range terhubung melalui `aria-describedby`.
- Selected filters/sort tidak hanya dibedakan warna.
- Target sentuh utama minimal 44 px; reduced motion mematikan scale/transition non-esensial.
- Bahasa utama UI konsisten Bahasa Indonesia. Nama brand/produk tidak diterjemahkan.

## Performa dan media

- Gambar memakai storage abstraction Qammaris, dimensi/reserved aspect ratio, format yang didukung, dan `loading=lazy` setelah initial viewport.
- Primary image initial viewport dapat diprioritaskan; related product dan gambar lanjutan tetap lazy.
- Tidak menambah font, icon, animation, atau UI dependency untuk Phase 7.
- Query listing eager-load brand/category/primary image/offer dan harus bebas N+1.
- Setiap implementasi memeriksa layout shift, horizontal overflow, console, ukuran build, dan warning chunk existing secara terpisah.

## Urutan implementasi yang disarankan

1. `P7-02` — query/state discovery: validation, gender/price/availability filters, sort deterministic, pagination, result summary, mobile/desktop state parity.
2. `P7-03` — card trust layer: ukuran, harga authority, availability, missing-data state, local placeholder, serta density mobile-first.
3. `P7-04` — detail mobile-first: information hierarchy, single-offer display, gallery, availability, dan return-to-results context.
4. `P7-05` — inquiry list dan WhatsApp: sold-out/restock behavior, contextual message, terminology, serta cart compatibility.
5. `P7-06` — accessibility/performance hardening dan end-to-end public catalog regression.

Setiap item mengambil baseline sebelum edit, memverifikasi `390x844` dan `1440x900`, serta berhenti setelah acceptance criteria item tersebut selesai.

## Non-goals Phase 7

- Live stock, sinkronisasi Shopee/Majoo, payment, reservation, checkout transaksi, rekomendasi AI, API agentic, framework rewrite, dan deployment production.
- Menghapus atau menebak data legacy agar screenshot terlihat penuh.
- Mengganti identitas visual Qammaris dengan template atau efek generik.
