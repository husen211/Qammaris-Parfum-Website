# ORD-02 — rencana: redesign Pesanan Online, Admin PWA, API integrasi

Status: **Tahap 0 direview Owner 2026-10-08; rencana diterima dengan revisi** (keputusan D1–D13 di [§8](#8-keputusan-owner), koreksi teknis di [§9](#9-koreksi-teknis-owner-2026-10-08)). ORD-02a (RBAC + manajemen pengguna) disetujui untuk dikerjakan. Kontrak [API v1](../integrations/QAMMARIS_ORDER_API_V1.md) **belum final** sampai agen Qammaris App melakukan contract review. Bukti audit: [audit 2026-10-08](../audits/2026-10-08-ord-02-online-orders.md).

Branch: `modernization/ord-02-online-order-redesign` (dari ORD-01 `57b3e93`). Tidak ada deploy tanpa persetujuan Owner.

## 1. Urutan pengerjaan

Setiap sub-item adalah commit/PR review tersendiri. Item berikutnya tidak dimulai sebelum item sebelumnya lolos tes.

| ID | Isi | Bergantung pada App? |
|---|---|---|
| ORD-02a | Role Super Admin/Staff Order, Gate, manajemen pengguna, audit akun, last login, nonaktif — **IN_REVIEW** ([bukti](../verification/ord-02a/README.md)); login `/admin/login` dipindah ke ORD-02b (PWA scope) | Tidak |
| ORD-02b | Admin PWA: manifest + service worker scope `/admin`, ikon, login di dalam scope, navigasi bawah, offline state, token kirim ganda — **IN_REVIEW** ([bukti](../verification/ord-02b/README.md), [ADR-039](../architecture/decisions/ADR-039-admin-pwa.md)) | Tidak |
| ORD-02c | Model status baru (pembayaran/persiapan/kurir/J&T/handover/issue), pelanggan + alamat, sumber order, keep, permintaan perubahan, penyesuaian harga + persetujuan, migrasi data ORD-01 — **IN_PROGRESS**: slice 1 selesai (kolom dimensi status + `public_id`/`source`, backfill dari `stage`, sinkron satu arah stage→dimensi, `OnlineOrderState` untuk queue/flags/completed). Berikutnya: issues + operasi status baru, pembayaran/penyesuaian, pelanggan/alamat, keep, permintaan perubahan | Tidak |
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
- Login dengan **username atau email** (D3). Akun nonaktif tidak bisa login. Tidak ada remember-me (D7).
- Reset akses: Super Admin menetapkan password sementara (tampil sekali) + wajib ganti saat login berikutnya. Tidak ada email reset (SMTP belum terverifikasi). Nonaktif, ganti role, dan reset password menaikkan `auth_version` user. Middleware admin mencocokkan versi itu dengan session, sehingga semua session lama user tersebut langsung tidak berlaku tanpa bergantung pada driver session.
- Sesi idle admin 12 jam (D7) dipaksa middleware. Agar tidak terpotong lebih dulu, `SESSION_LIFETIME` env harus ≥ 720 (catatan deployment; berdampak pada sesi keranjang publik yang jadi lebih panjang).
- Kemampuan pesanan (dipakai di ORD-02a untuk aksi ORD-01, lalu model status ORD-02c):
  - Staff Order boleh membuat/mengubah/menandai Lunas;
  - Staff Order boleh membatalkan order **belum Lunas dan belum diserahkan**, dengan alasan (D4); setelah itu hanya Super Admin;
  - Staff Order boleh mengubah alamat selama belum diserahkan dan tidak mengubah biaya (D5);
  - perubahan finansial (ongkir/total, refund, koreksi pembayaran, talangan/reimburse, revert langkah) hanya Super Admin.
- Audit akun: tabel `user_admin_changes` (aktor, target, field allowlist, before/after tanpa hash password).

## 3. Admin PWA (ORD-02b)

- Manifest `/admin/manifest.webmanifest`: `id: "/admin"`, `start_url: "/admin/orders?source=pwa"`, `scope: "/admin"` (tanpa garis miring akhir karena dashboard ada di `/admin`; diterapkan di ORD-02b), `display: "standalone"`, nama "Qammaris Admin", warna dari token brand. Ikon 192/512 + maskable + `apple-touch-icon` 180. Website publik tidak punya PWA, jadi tidak ada bentrok manifest.
- Login dipindah/ditambah di `/admin/login` agar tetap di dalam scope (iPhone standalone membuka URL di luar scope di Safari dan memutus session).
- Service worker `/admin/sw.js` (scope `/admin`):
  - cache-first hanya untuk `/build/*` (aset ber-hash), ikon, dan halaman offline statis;
  - navigasi **network-only**, dengan fallback "Tidak ada koneksi" yang statis (tanpa data);
  - tidak menyimpan HTML admin, JSON, upload, atau respons POST;
  - **Tidak memakai `Clear-Site-Data`.** Header ini berlaku untuk seluruh origin, tidak bisa dibatasi ke `/admin`. Audit 2026-10-08 menemukan website publik memakai `sessionStorage` (`catalog-navigation.js`) dan cookie session yang sama dengan keranjang. `"storage"` akan menghapus state katalog dan registrasi service worker origin; `"cookies"` mengosongkan keranjang dan session publik. Sebagai gantinya, saat logout halaman admin meminta service worker menghapus **hanya** cache bernama `qammaris-admin-*`. Cache itu memang hanya berisi aset statis. Data admin tidak pernah disimpan di Web Storage/IndexedDB.
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
| `completed` | `lifecycle=completed`, `delivery=delivered` |
| `cancelled` | `lifecycle=cancelled` |

Kolom `stage` dipertahankan read-only selama transisi untuk rollback, lalu dihapus di item terpisah setelah rilis stabil.

## 5. Aturan bisnis (keputusan Owner 2026-10-08)

Aturan final yang berlaku dicatat di BUSINESS_RULES saat masing-masing sub-item diimplementasikan. Di bawah ini aturan yang sudah diputuskan.

1. **Pembayaran**: Staff Order/Super Admin menandai Lunas dengan metode + sumber konfirmasi (`bukti di chat`, `bukti diunggah`, `Majoo`). Upload bukti di website opsional (D12). "Sudah dicatat di Majoo" adalah centang terpisah. Tidak ada field poin. Koreksi pembayaran, refund, dan penyesuaian total membutuhkan Super Admin + alasan.
2. **Dimensi status dipisah dan tidak saling mengunci**: pembayaran, progres packing, handover (penyerahan ke kurir/customer/J&T), delivered (konfirmasi diterima), serta kewajiban finansial terbuka (refund yang belum dibayar, reimburse/talangan yang belum diganti). Lunas bukan syarat packing.
3. **Selesainya tugas staf ≠ diterima customer** (D10): tugas staf selesai saat handover tercatat; `delivered` adalah konfirmasi terpisah dan opsional. Order `completed` bila Lunas + handover selesai + tanpa kendala + tanpa refund terbuka. Tidak menunggu `delivered`. **Keputusan Owner R8 (2026-10-08):** reimburse staf yang belum dibayar (`awaiting_proof`/`submitted`/`approved`) **tidak** menahan `completed`; ia tetap kewajiban terbuka di App dan tampil di detail order Website. Refund customer yang belum selesai tetap menahan `completed` dan ditandai sebagai kasus perlu penanganan.
4. **Kurir lokal**: `booking_responsibility` = toko atau customer. Bila toko, satu orang mengklaim tugas pemesanan agar tidak dobel. Bila customer, staf cukup mencatat penyerahan ke kurir customer.
5. **J&T**: request pickup, QR tersedia, dan dipickup adalah status terpisah. **Request pickup bukan konfirmasi berhasil.** Jam layanan 09.00–16.00 WITA hanya **peringatan**, bukan blokir (D8). Resi opsional dan bisa ditambahkan belakangan.
6. **Keep** (D9): default 24 jam, boleh belum dibayar, konfirmasi manual "stok sudah dipisahkan" oleh staf. Lewat batas → ditandai perlu tindakan, **tidak** otomatis dibatalkan. Tidak ada klaim reservasi stok.
7. **Form customer**: opsional. Admin bisa mengisi semuanya. Customer dapat meninjau data. Setelah packing dimulai, perubahan menjadi permintaan yang ditinjau admin. Staff Order boleh menangani perubahan alamat tanpa biaya sampai handover; perubahan biaya = Super Admin (D5).
8. **Pembatalan** (D4): Staff Order hanya untuk order belum Lunas dan belum diserahkan, wajib alasan. Setelah Lunas → Super Admin, dan kewajiban refund tercatat terpisah.
9. **Link tugas staf**: ke App setelah KOORDINASI-6. Sampai integrasi App selesai, progres dicatat lewat Admin PWA dengan akun terautentikasi (D6). Link bearer ORD-01 tidak lagi menjadi metode utama.
10. **Data pelanggan** (D13): tidak dihapus otomatis untuk sekarang. Akses hanya akun aktif dengan kemampuan pesanan (Super Admin/Staff Order/legacy admin) dan backend App lewat API bertanda tangan dengan minimisasi field. Kebijakan retensi didokumentasikan di BUSINESS_RULES dan ditinjau ulang di ORD-05.

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

Jawaban Owner 2026-10-08:

| ID | Keputusan |
|---|---|
| D1 | ORD-01 dan ORD-02 dirilis **bersama** setelah integrasi dan pengujian |
| D2 | Owner menjadi Super Admin lewat **bootstrap eksplisit**. Tidak ada migrasi akun production tanpa identitas terverifikasi |
| D3 | Login username **atau** email |
| D4 | Staff Order boleh membatalkan order belum Lunas dan belum diserahkan, dengan alasan. Setelah Lunas = Super Admin |
| D5 | Staff Order boleh mengubah alamat selama belum diserahkan dan tidak mengubah biaya. Perubahan finansial = Super Admin |
| D6 | Admin PWA menangani progres sementara sebelum integrasi App selesai |
| D7 | Sesi idle 12 jam, tanpa remember-me |
| D8 | Jam pickup J&T = peringatan, bukan hard block. Request pickup ≠ konfirmasi berhasil |
| D9 | Keep default 24 jam, boleh belum dibayar, tidak auto-batal |
| D10 | Handover dan delivered dipisah. Tugas staf selesai tanpa menunggu konfirmasi penerimaan |
| D11 | Ikon PWA dari logo Qammaris existing |
| D12 | Upload bukti pembayaran opsional |
| D13 | Data pelanggan tidak dihapus otomatis untuk sekarang. Kontrol akses dan kebijakan retensi didokumentasikan |

Usulan awal Tahap 0 (arsip, sudah digantikan tabel di atas):

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

Kontrak r4 (2026-10-08) menerapkan R1–R10 dari contract review App dan keputusan Owner R8 ([kontrak §15](../integrations/QAMMARIS_ORDER_API_V1.md#15-penerapan-r1r10)). Markdown dan OpenAPI divalidasi bersama oleh `tests/Unit/OrderApiContractTest.php`. Agen App memberi *conditional sign-off* atas r4. **r4.1** menerapkan koreksi wajib K-A (path HMAC webhook + vektor uji) dan K-B (timestamp/signature baru setiap retry webhook). Status: kandidat final sampai agen App mengonfirmasi r4.1. ORD-02e mengikuti kontrak final dan diuji bersama agen App.

## 9. Koreksi teknis Owner (2026-10-08)

1. `Clear-Site-Data` tidak dipakai karena berdampak ke seluruh origin publik. Diganti pembersihan cache SW `qammaris-admin-*` ([§3](#3-admin-pwa-ord-02b)).
2. Webhook/outbox adalah **jalur notifikasi utama** dan retry ditanggung Website. Audit App 2026-10-08: hosting App **tanpa cron**, sehingga rekonsiliasi App terjadi saat halaman dibuka/aktif, bukan polling berkala (kontrak r4 §2, §11).
3. Backend App **wajib** memvalidasi user, role, dan izin aksi sebelum mengirim request bertanda tangan. Website tetap membatasi aksi per klien sebagai lapisan kedua (kontrak §3–4).
4. Kontrak API **tidak difinalkan** sebelum contract review oleh agen Qammaris App (kontrak §0).
5. Pemisahan pembayaran, packing, handover, delivered, dan kewajiban refund/reimburse dipertahankan di model data dan API ([§5](#5-aturan-bisnis-keputusan-owner-2026-10-08), kontrak §7).
