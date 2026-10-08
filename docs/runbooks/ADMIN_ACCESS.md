# Akses admin — Super Admin, Staff Order, dan akun lama

Keputusan: [ADR-035](../architecture/decisions/ADR-035-admin-roles-and-user-management.md). Runbook ini tidak memberi izin deploy; rilis tetap menunggu persetujuan Owner (D1: ORD-01 + ORD-02 dirilis bersama).

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

## Operasional

- **Karyawan baru**: Pengguna & Role → Tambah pengguna → Staff Order. Berikan password sementara secara langsung.
- **Lupa password**: Super Admin → buka akun → Reset password (password sementara baru, semua sesi lama berakhir).
- **Karyawan berhenti / HP hilang**: Nonaktifkan akun. Semua sesi langsung berakhir; riwayat tetap tersimpan.
- **Super Admin terakhir** tidak bisa dinonaktifkan atau diturunkan. Tambahkan Super Admin lain dulu.
- Riwayat siapa membuat/mengubah akun ada di halaman akun dan di daftar "Perubahan akun terbaru".

## Rollback

Revert kode. Kolom baru dan tabel `user_admin_changes` boleh dibiarkan; kode lama tidak membacanya. Akun yang sudah diubah menjadi `super_admin`/`staff_order` tidak bisa masuk admin dengan kode lama (kode lama hanya mengenali `admin`). Bila rollback diperlukan setelah konversi, kembalikan role akun Owner ke `admin` lewat perintah database yang disetujui. Jangan menjalankan `migrate:rollback` untuk kolom yang sudah berisi data tanpa rencana yang disetujui.
