# ORD-02 — rencana: redesign Pesanan Online, Admin PWA, API integrasi

Status: **Tahap 0 selesai (usulan).** Implementasi menunggu jawaban [keputusan Owner](#8-keputusan-owner) dan kontrak [API v1](../integrations/QAMMARIS_ORDER_API_V1.md) yang disepakati dengan agen Qammaris App. Bukti audit: [audit 2026-10-08](../audits/2026-10-08-ord-02-online-orders.md).

Branch: `modernization/ord-02-online-order-redesign` (dari ORD-01 `57b3e93`). Tidak ada deploy tanpa persetujuan Owner.

## 1. Urutan pengerjaan

Setiap sub-item adalah commit/PR review tersendiri. Item berikutnya tidak dimulai sebelum item sebelumnya lolos tes.

| ID | Isi | Bergantung pada App? |
|---|---|---|
| ORD-02a | Role Super Admin/Staff Order, Gate/Policy, manajemen pengguna, audit akun, login `/admin/login`, last login, nonaktif | Tidak |
| ORD-02b | Admin PWA: manifest + service worker scope `/admin/`, ikon, mobile-first daftar pesanan, error/loading/offline state | Tidak |
| ORD-02c | Model status baru (pembayaran/persiapan/kurir/J&T/handover/issue), pelanggan + alamat, sumber order, keep, permintaan perubahan, penyesuaian harga + persetujuan, migrasi data ORD-01 | Tidak |
| ORD-02d | UI pembuatan & detail pesanan baru (Staff Order dan Super Admin), form customer diperbarui, instruksi WhatsApp | Tidak (link tugas pakai placeholder sampai KOORDINASI-6) |
| ORD-02e | API v1: auth HMAC, idempotency, endpoint baca + mutasi, outbox notifikasi, rekonsiliasi | **Ya**, setelah kontrak disetujui |
| ORD-02f | Staging rehearsal bersama App, panduan owner/karyawan, catatan rilis | Ya |

## 2. Role dan otorisasi (ORD-02a)

- Nilai `users.role`: `super_admin`, `staff_order`, `admin` (legacy), `customer`. Tambah kolom `is_active` (default true), `last_login_at`, `created_by_id`, `updated_by_id`, `username` (nullable unik) untuk staf tanpa email.
- **Tidak ada auto-promosi.** Akun `admin` lama tetap punya akses admin yang sama seperti hari ini (menu katalog, blog, pesanan) supaya tidak ada yang terkunci, tetapi **tidak** mendapat menu Pengguna & Role atau persetujuan Super Admin. Owner menjadi Super Admin lewat perintah eksplisit di server:
  `php artisan qammaris:grant-super-admin owner@example.com --confirm`. Perintah ini mencatat audit dan menolak bila akun tidak aktif.
- Setelah Super Admin pertama ada, akun `admin` lama dikonversi manual lewat menu Pengguna & Role (pilih Super Admin atau Staff Order). Item tindak lanjut opsional: menghapus akses legacy `admin` setelah semua dikonversi.
- Kemampuan (Gate) dipakai di route, controller, FormRequest, dan action. Contoh: `orders.manage`, `orders.mark-paid`, `orders.approve-adjustment`, `orders.refund`, `orders.cancel`, `users.manage`, `catalog.manage`, `blog.manage`, `settings.manage`. Menu hanya cerminan Gate.
- Penjaga Super Admin terakhir: menolak nonaktif/turun role bila tinggal satu Super Admin aktif. Diuji dengan race (lock baris).
- Reset akses: Super Admin menetapkan password sementara (tampil sekali) + wajib ganti saat login berikutnya. Tidak ada email reset (SMTP belum terverifikasi). Nonaktif = semua session user itu dicabut (session driver database/file dihapus berdasarkan user id; bila file, dibuat versi `session_version` di user yang dicek middleware).
- Audit akun: tabel `user_admin_changes` (aktor, target, field allowlist, before/after tanpa hash password).

## 3. Admin PWA (ORD-02b)

- Manifest `/admin/manifest.webmanifest`: `id: "/admin/"`, `start_url: "/admin/orders?source=pwa"`, `scope: "/admin/"`, `display: "standalone"`, nama "Qammaris Admin", warna dari token brand. Ikon 192/512 + maskable + `apple-touch-icon` 180. Website publik tidak punya PWA, jadi tidak ada bentrok manifest.
- Login dipindah/ditambah di `/admin/login` agar tetap di dalam scope (iPhone standalone membuka URL di luar scope di Safari dan memutus session).
- Service worker `/admin/sw.js` (scope `/admin/`):
  - cache-first hanya untuk `/build/*` (aset ber-hash), ikon, dan halaman offline statis;
  - navigasi **network-only**, dengan fallback "Tidak ada koneksi" yang statis (tanpa data);
  - tidak menyimpan HTML admin, JSON, upload, atau respons POST;
  - logout → `Clear-Site-Data: "cache", "storage"` untuk scope admin.
- Mutasi wajib online. Form memakai pending state, tombol dinonaktifkan selama kirim, dan **token idempotency form** (UUID tersembunyi) agar double-tap/retry tidak membuat order ganda.
- Session: lifetime admin dikonfigurasi terpisah (usulan 12 jam idle untuk HP toko), tanpa "remember me", logout jelas di header PWA. Super Admin dapat mencabut session pengguna.
- Staff Order membuka PWA langsung ke `/admin/orders` dengan tombol besar **Buat Pesanan**. Target 320–430 px tanpa scroll horizontal; navigasi bawah (Pesanan, Buat, Akun) untuk Staff Order.

## 4. Model data (ORD-02c, additive)

Semua perubahan memakai migrasi baru; migrasi ORD-01 tidak diubah.

| Tabel/kolom | Isi |
|---|---|
| `online_orders` + kolom | `public_id` (ULID unik), `source`, `lifecycle`, `payment_status`, `payment_confirmation_source`, `payment_confirmed_at`, `payment_confirmed_by`, `preparation_status`, `courier_status`, `courier_booking_responsibility` (`store`/`customer`), `courier_assignee` (json aktor), `courier_provider`, `courier_reference`, `jnt_status`, `jnt_pickup_requested_at`, `jnt_qr_path` (disk privat), `jnt_picked_up_at`, `handover_status`, `handed_to`, `handed_over_at`, `delivered_at`, `customer_id`, `customer_address_id`, `adjustments_total`, `keep_until`, `keep_status` |
| `customers` | nama, HP ternormalisasi (index, tidak unik), catatan, dibuat_oleh |
| `customer_addresses` | label, tipe (lokal/luar kota), alamat, kode pos, link lokasi, last_used_at; dipilih ulang **setelah dikonfirmasi** |
| `online_order_adjustments` | jumlah ±, alasan, status `pending/approved/rejected`, diminta oleh, disetujui oleh (Super Admin) |
| `online_order_payments` | event pembayaran/koreksi/refund (jumlah, metode, sumber, aktor, alasan, persetujuan) |
| `online_order_change_requests` | permintaan perubahan customer setelah packing dimulai (field diminta, status, peninjau) |
| `online_order_issues` | kendala (tipe, catatan, pembuka, status) |
| `online_order_costs` | `expense_ref` App, jenis, jumlah, dibayar oleh, status reimbursement (dari App) |
| `online_order_claims` | tugas, aktor, waktu, lepas/override |
| `online_order_events` + kolom | `actor_app_user_id`, `actor_display_name`, `source` (`website`/`app`/`customer`), `idempotency_key`, `reason` |
| `integration_idempotency_keys` | client, key, hash payload, status, body respons, kedaluwarsa 7 hari |
| `integration_outbox` | event ULID, order, revision, tipe, attempts, next_attempt_at, delivered_at, last_error |
| Bukti bayar (opsional) | disk privat, hanya Super Admin/Staff Order, tidak di-cache |

Pemetaan data ORD-01 (hanya data lokal/staging; production belum punya data ORD-01):

| `stage` ORD-01 | ORD-02 |
|---|---|
| `awaiting_customer` | `lifecycle=awaiting_customer`, `payment=unpaid` |
| `details_received` | `lifecycle=active`, `payment=unpaid` |
| `paid` | `lifecycle=active`, `payment=paid` |
| `shipped` | + `handover=handed_over`, `handed_to` sesuai kurir (`jnt` → `jnt_status=picked_up`) |
| `completed` | `lifecycle=completed`, `handover=delivered` |
| `cancelled` | `lifecycle=cancelled` |

Kolom `stage` dipertahankan read-only selama transisi untuk rollback, lalu dihapus di item terpisah setelah rilis stabil.

## 5. Aturan bisnis yang diusulkan (difinalkan setelah keputusan)

1. **Pembayaran**: Staff Order/Super Admin menandai Lunas dengan metode + sumber konfirmasi (`bukti di chat`, `bukti diunggah`, `Majoo`). "Sudah dicatat di Majoo" adalah centang terpisah. Tidak ada field poin. Koreksi, refund, dan penyesuaian total membutuhkan Super Admin + alasan.
2. **Persiapan tidak menunggu pembayaran**; Lunas bukan syarat packing. `completed` = Lunas + diserahkan + tanpa kendala terbuka.
3. **Kurir lokal**: `booking_responsibility` = toko atau customer. Bila toko, satu orang mengklaim tugas pemesanan agar tidak dobel. Bila customer, staf cukup mencatat penyerahan ke kurir customer.
4. **J&T**: request pickup (jam layanan 09.00–16.00 WITA ditampilkan sebagai peringatan), QR tersedia, dipickup adalah status terpisah. Resi opsional dan bisa ditambahkan belakangan.
5. **Keep**: produk/jumlah, batas waktu, status bayar, konfirmasi manual "stok sudah dipisahkan" oleh staf. Lewat batas → status `expired` + muncul di daftar perlu tindakan (tidak otomatis dibatalkan). Tidak ada klaim reservasi stok.
6. **Form customer**: opsional. Admin bisa mengisi semuanya. Customer dapat meninjau data. Setelah packing dimulai, perubahan menjadi permintaan yang ditinjau admin.
7. **Link tugas staf**: ke App setelah KOORDINASI-6. Link bearer ORD-01 **dinonaktifkan** sebagai metode utama (lihat keputusan D6 untuk masa transisi).

## 6. Rollback dan deployment

- Rilis production hanya dengan persetujuan Owner. Jalankan dulu di staging bersama App staging.
- Migrasi additive. Rollback kode tidak menghapus tabel/data. Kolom `stage` lama tetap terisi selama transisi, sehingga kode ORD-01 masih bisa membaca order bila perlu revert.
- Feature flag di config: `ORDERS_V2_ENABLED`, `ADMIN_PWA_ENABLED`, `QAMMARIS_ORDER_API_ENABLED` (API mati = 503 `unavailable`; outbox tertahan, tidak hilang).
- Service worker punya "kill switch": versi SW baru yang langsung `unregister` bila PWA perlu dimatikan.
- Rotasi secret API mendukung dua secret aktif.

## 7. Rencana tes (ringkas)

Tes otomatis (SQLite sintetis) untuk setiap poin Tahap 8:
- akses Staff Order ditolak di halaman/route/API Super Admin;
- CRUD akun + penjaga Super Admin terakhir;
- manifest/SW: header, scope, dan tidak meng-cache navigasi;
- order dari IG/WA + form customer prefilled + pelanggan langganan tanpa form;
- Lunas dengan sumber konfirmasi; tidak ada field poin; paperbag dua opsi;
- customer pesan kurir sendiri; J&T tanpa resi; QR vs pickup;
- perubahan alamat setelah packing dan keep tanpa status ambigu;
- idempotency/outbox tanpa event ganda; regresi checkout, katalog, blog.

Verifikasi PWA install di perangkat nyata (Android Chrome, iPhone Safari) adalah langkah manual. Hasilnya dicatat apa adanya.

## 8. Keputusan Owner

| ID | Pertanyaan | Usulan |
|---|---|---|
| D1 | Rilis ORD-01 dulu, atau langsung rilis gabungan ORD-01+02? | **Gabungan**. ORD-01 belum dipakai dan alurnya berubah besar; PR #27 tetap terbuka sebagai basis |
| D2 | Akun admin production siapa saja, dan siapa Super Admin? | Owner jadi Super Admin lewat perintah eksplisit; akun lain dikonversi manual |
| D3 | Login staf: email atau username? | Keduanya diterima; username untuk staf tanpa email |
| D4 | Staff Order boleh membatalkan order? | Hanya order belum Lunas; setelah Lunas = Super Admin |
| D5 | Perubahan data customer setelah packing: siapa menyetujui? | Staff Order boleh; perubahan yang memengaruhi total = Super Admin |
| D6 | Masa transisi sebelum App siap: staf menandai progres di mana? | Di Admin PWA dengan akun Staff Order (terautentikasi); link bearer ORD-01 dimatikan |
| D7 | Durasi session HP toko | 12 jam idle, tanpa remember-me |
| D8 | Jam J&T di luar 09.00–16.00 WITA | Peringatan, tidak memblokir |
| D9 | Keep: durasi default, wajib bayar? | Default 24 jam; boleh belum bayar; lewat batas → ditandai, tidak auto-batal |
| D10 | `completed` = diserahkan atau diterima customer? | Diserahkan + Lunas + tanpa kendala; "diterima" opsional |
| D11 | Ikon PWA | Pakai logo Qammaris existing (ikon persegi dibuat dari file logo) atau Owner kirim ikon resmi |
| D12 | Upload bukti bayar di website | Opsional, JPG/PNG/PDF ≤5 MB, disimpan privat tanpa hapus otomatis |
| D13 | Retensi data customer & daftar pelanggan langganan | Tetap tanpa hapus otomatis (keputusan ORD-01) |

Koordinasi dengan agen Qammaris App: KOORDINASI-1 sampai 9 di [kontrak API](../integrations/QAMMARIS_ORDER_API_V1.md#daftar-koordinasi).
