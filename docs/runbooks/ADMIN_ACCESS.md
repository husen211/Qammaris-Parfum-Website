# Akses admin — Super Admin, Staff Order, dan akun lama

Keputusan: [ADR-038](../architecture/decisions/ADR-038-admin-roles-and-user-management.md). Runbook ini tidak memberi izin deploy; rilis tetap menunggu persetujuan Owner (D1: ORD-01 + ORD-02 dirilis bersama).

## Sebelum/sesudah rilis

1. Env production (tanpa menaruh nilai rahasia di Git):
   - `SESSION_LIFETIME=720` atau lebih, supaya sesi admin tidak berakhir sebelum batas idle 12 jam. Sesi keranjang publik ikut lebih panjang.
   - `ADMIN_IDLE_MINUTES=720` (default sudah 720).
2. Jalankan migrasi additive `2026_10_08_100001_add_admin_access_controls` di target yang disetujui. Akun lama **tidak berubah**: role `admin` tetap bekerja seperti sebelumnya, tetapi tidak bisa membuka menu Pengguna & Role.
3. Owner memverifikasi akun miliknya, lalu di server:
   ```bash
   php artisan qammaris:grant-super-admin owner@domain            # pratinjau
   php artisan qammaris:grant-super-admin owner@domain --confirm
   # atau akun baru:
   php artisan qammaris:grant-super-admin pemilik --create --name="Nama Owner" --confirm
   ```
   Password sementara (bila `--create`) tampil sekali di terminal dan wajib diganti saat login. Jangan menyalin password ke chat/grup.
4. Owner masuk, buka **Pengguna & Role**:
   - buat akun **Staff Order** per karyawan (username cukup, email opsional);
   - ubah setiap akun `Admin (lama)` menjadi Super Admin atau Staff Order;
   - nonaktifkan akun bersama yang tidak dipakai lagi.

## Aplikasi Qammaris Admin (ORD-02b)

Keputusan: [ADR-039](../architecture/decisions/ADR-039-admin-pwa.md).

- Env: `ADMIN_PWA_ENABLED=true` (default). Halaman masuk sekarang `/admin/login`; `/login` dialihkan ke sana.
- Migrasi additive `2026_10_08_200001_add_submission_token_to_online_orders` ikut dijalankan bersama migrasi ORD-02 lainnya di target yang disetujui.
- Pasang di HP toko:
  - Android Chrome: buka `/admin/login` lalu menu ⋮ → **Instal aplikasi**.
  - iPhone Safari: Bagikan → **Tambah ke Layar Utama**.
  Ikon bernama "QAM Admin".
- Setiap karyawan masuk dengan akunnya sendiri dan menekan **Keluar** setelah selesai. Jangan simpan password di browser HP bersama.
- **Matikan aplikasi** bila ada masalah: set `ADMIN_PWA_ENABLED=false` lalu muat ulang konfigurasi. HP yang sudah memasang aplikasi akan menghapus service worker dan cache admin saat membuka admin berikutnya. Admin tetap bisa dipakai lewat browser biasa.

## Operasional

- **Karyawan baru**: Pengguna & Role → Tambah pengguna → Staff Order. Berikan password sementara secara langsung.
- **Lupa password**: Super Admin → buka akun → Reset password (password sementara baru, semua sesi lama berakhir).
- **Karyawan berhenti / HP hilang**: Nonaktifkan akun. Semua sesi langsung berakhir; riwayat tetap tersimpan.
- **Super Admin terakhir** tidak bisa dinonaktifkan atau diturunkan. Tambahkan Super Admin lain dulu.
- Riwayat siapa membuat/mengubah akun ada di halaman akun dan di daftar "Perubahan akun terbaru".

## Rollback

Untuk ORD-02b: matikan dulu `ADMIN_PWA_ENABLED`, tunggu HP staf membuka admin sekali, lalu revert kode. Kolom `submission_token` boleh dibiarkan.

Untuk ORD-02a: revert kode. Kolom baru dan tabel `user_admin_changes` boleh dibiarkan; kode lama tidak membacanya. Akun yang sudah diubah menjadi `super_admin`/`staff_order` tidak bisa masuk admin dengan kode lama (kode lama hanya mengenali `admin`). Bila rollback diperlukan setelah konversi, kembalikan role akun Owner ke `admin` lewat perintah database yang disetujui. Jangan menjalankan `migrate:rollback` untuk kolom yang sudah berisi data tanpa rencana yang disetujui.
