# Audit ORD-02 — Pesanan Online, akses admin, PWA, dan integrasi

Tanggal: 2026-10-08. Basis: branch `modernization/ord-01-online-orders` (`57b3e93`, PR #27, **belum merge/deploy**) di atas `main` `c6e2dfd`.

Dokumen ini adalah bukti audit Tahap 0, bukan aturan bisnis. Rencana ada di [ORD-02 plan](../planning/ORD-02_PLAN.md), kontrak integrasi di [QAMMARIS_ORDER_API_V1](../integrations/QAMMARIS_ORDER_API_V1.md).

Tidak ada kode, data, atau konfigurasi yang diubah selama audit. Repo Qammaris App hanya diidentifikasi (nama repo, stack dari README); struktur internalnya tidak dipakai sebagai asumsi.

## 1. Fakta lingkungan

| Hal | Temuan | Sumber |
|---|---|---|
| ORD-01 di production | **Tidak ada.** PR #27 terbuka, migrasi `2026_10_08_000001` belum dijalankan di staging/production | `git log`, PR #27 |
| Qammaris App | Repo `qammaris-reimbursement-management-system`: Express + TypeScript + MongoDB, frontend React/Vite, autentikasi karyawan sendiri (JWT). Sudah punya feed produk read-only + webhook bertanda tangan ke Website | README App, commit `921569b`, [runbook integrasi](../runbooks/QAMMARIS_APP_INTEGRATION.md) |
| Pola integrasi existing | Website **menarik** feed App dengan `X-Api-Key`; App **membangunkan** Website lewat webhook HMAC (`X-Qammaris-Timestamp` + `X-Qammaris-Signature`, toleransi 300 detik, body ≤16 KiB, 202 setelah enqueue durable) | `config/qammaris_app.php`, `routes/integrations.php`, runbook |
| Queue | Database queue + satu worker (watchdog) di hosting; cron per menit | ARCHITECTURE |
| API Laravel | Tidak ada `routes/api.php`, Sanctum/Passport tidak terpasang. Route integrasi di `routes/integrations.php` tanpa web middleware | `bootstrap/app.php`, `composer.json` |

## 2. Autentikasi dan otorisasi admin (existing)

- Login: `AuthController` di `/login`. Pakai email + password, `Auth::attempt`, regenerasi session, throttle 5×/menit per email+IP. Tidak ada "remember me" di form.
- Otorisasi: satu middleware `admin` (`AdminMiddleware`) mengecek `users.role === 'admin'`. Role lain di DB hanya `customer` (default kolom, migrasi `2025_11_25`). Tidak ada Gate/Policy.
- Semua route `/admin/*` memakai `auth` + `admin`. Action/operasi (mis. `CreateOnlineOrder`, `OnlineOrderWorkflow`, `ChangeBlogPostArchive`) mengulang cek `role === 'admin'` di server.
- `users`: `name`, `email`, `password`, `role`, `remember_token`. **Tidak ada** `is_active`, `last_login_at`, `created_by`, `username`. `role` tidak mass-assignable (baik).
- Seeder lokal membuat satu admin dan satu customer. **Jumlah dan identitas akun admin di production tidak diketahui.** ADR-031 mencatat akun admin bisa dipakai bersama, sehingga audit tidak dapat membedakan orang.
- Session: driver `file` (`.env.example`), lifetime 120 menit, tidak expire saat browser ditutup, cookie `http_only`, `same_site=lax`, `secure` dari env.
- Tidak ada halaman manajemen user, reset password, aktivasi/nonaktif, atau riwayat login.

**Gap:** dua role baru (Super Admin, Staff Order), status aktif, last login, audit user, otorisasi per kemampuan (Gate/Policy), dan pencegahan menonaktifkan Super Admin terakhir. Jangan memetakan `admin` lama ke Super Admin secara otomatis (lihat rencana).

## 3. Katalog, harga, ketersediaan, checkout

- Satu produk = satu ukuran = satu harga. "Varian" secara teknis adalah satu `ProductVariant` aktif per produk; ukuran berbeda adalah produk berbeda. Jadi pemilihan "produk + varian" di ORD-02 = memilih produk (offer aktif).
- Harga jual = `product_variants.price` dalam rupiah bulat (AUD-06). Harga produk terhubung App diperbarui otomatis dari App.
- Availability `available` / `sold_out` / `unknown` dari App, tanpa angka stok. **Tidak ada mekanisme reservasi stok.** Keep tidak boleh diklaim mengurangi stok.
- Checkout keranjang (ADR-028) tidak menyimpan order/customer dan membuka WhatsApp. Tetap dipertahankan; penggabungan ke Pesanan Online adalah item terpisah.

## 4. ORD-01 — inventaris

| Area | Isi ORD-01 |
|---|---|
| Tabel | `online_orders` (satu kolom `stage` linear, data penerima, ongkir, talangan, token hash+terenkripsi, `revision`), `online_order_items` (snapshot harga), `online_order_events` (append-only) |
| Operasi | `CreateOnlineOrder` (snapshot offer published), `OnlineOrderWorkflow` (lock baris, guard `from`→`to`, replay idempotent, revision untuk edit admin, revert/cancel, talangan/reimburse, regenerate link) |
| Route publik | `/pesanan/{token}` (form + status, `diterima`), `/tugas-pesanan/{token}` (link tugas staf: langkah + talangan) |
| Admin | `admin/orders` index/create/show/update/advance/revert/cancel/reimburse/links/product-search |
| Support | `OnlineOrderMessages` (pesan grup/customer/lokasi), `OnlineOrderTimeline` (allowlist per audiens), komponen Blade timeline |
| Keamanan | Token 40 karakter, lookup via SHA-256, salinan `encrypted` (APP_KEY) untuk disalin ulang, 404 netral, no-store/no-referrer/noindex, throttle 30/10 per menit |
| Tes | `OnlineOrderTest` 14 tes / 142 assertion; suite 396 / 2808 |

## 5. Gap analysis terhadap ORD-02

| Kebutuhan ORD-02 | Status ORD-01 | Keputusan |
|---|---|---|
| Snapshot harga, item terkunci | Ada | **Pertahankan** |
| Link customer aman tanpa login | Ada (hash + encrypted) | **Pertahankan**, tambah expiry/rotasi existing |
| Form customer satu halaman, 3 cara terima, paperbag 2 opsi | Ada | **Pertahankan**; tambah tinjau data yang diisi admin dan permintaan perubahan setelah packing |
| Admin bisa menyelesaikan order tanpa form customer | Ada (centang "isi data sekarang") | **Perbaiki**: jadikan alur normal, plus pelanggan lama dan alamat lama yang dikonfirmasi |
| Sumber pesanan (WA/IG/Website/manual) | Tidak ada | **Tambah** kolom `source` |
| Pelanggan langganan + alamat sebelumnya | Tidak ada | **Tambah** `customers` + `customer_addresses` |
| Status terpisah: pembayaran / persiapan / pengiriman / masalah finansial | Satu `stage` linear (dibayar mendahului kirim) | **Ganti** dengan status per dimensi; `stage` lama dimigrasi lalu dipertahankan read-only selama transisi |
| Staff Order boleh menandai Lunas | Hanya `admin` | **Ganti** dengan otorisasi kemampuan; catat metode, sumber konfirmasi, waktu, akun |
| Koreksi pembayaran, refund, harga khusus = Super Admin | Revert langkah oleh admin mana pun; tidak ada refund/penyesuaian | **Tambah** penyesuaian bertanda alasan + persetujuan Super Admin |
| Siapa pesan kurir: owner/karyawan/customer | `admin`/`staff` saja | **Perbaiki**: tambah `customer`, penugasan per orang, cegah dobel pemesanan (claim) |
| J&T: request pickup, QR tersedia, dipickup, resi opsional | Resi **wajib** untuk Dikirim | **Ganti**: status terpisah, resi opsional, simpan referensi/QR di disk privat |
| Keep | Tidak ada | **Tambah** pencatatan keep tanpa klaim reservasi stok |
| Link tugas staf ke App dengan autentikasi | Link bearer + nama bebas | **Ganti**: link ke App. Link bearer ORD-01 dinonaktifkan sebagai metode utama (lihat keputusan transisi) |
| Talangan/reimbursement | Dicatat di Website oleh staf | **Ganti**: sumber kebenaran reimbursement di App; Website menyimpan ringkasan biaya + status yang dilaporkan App |
| Role Super Admin / Staff Order, manajemen user | Tidak ada | **Tambah** |
| Admin PWA | Tidak ada manifest/service worker; login di `/login` (di luar scope `/admin`) | **Tambah** manifest + SW khusus `/admin/`, login di dalam scope |
| API privat untuk App | Tidak ada; hanya webhook masuk produk | **Tambah** API v1 + outbox notifikasi |
| Idempotency mutasi | Replay langkah idempotent; tidak ada idempotency key | **Tambah** tabel idempotency untuk API |
| Audit | `online_order_events` | **Pertahankan + perluas** (aktor App, kunci idempotency, alasan) |

## 6. Risiko utama

1. Akun admin production belum diinventarisasi. Salah migrasi role dapat mengunci owner atau memberi akses terlalu luas.
2. Sebelum App siap, staf butuh cara kerja sementara. Jika link bearer ORD-01 dimatikan tanpa pengganti, operasional terhenti.
3. Data customer (alamat/HP) akan dikirim ke App. Perlu minimisasi dan kesepakatan retensi di App.
4. PWA di iPhone memakai penyimpanan cookie terpisah dari Safari. Login harus berada di dalam scope agar tidak terlempar ke browser.
5. Tidak ada reservasi stok. UI keep dan "stok sudah dipisahkan" harus jelas bahwa itu konfirmasi manual staf.
