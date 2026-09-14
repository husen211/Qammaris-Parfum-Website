# ADR 001: Legacy Production and Clean Redeployment

**Status:** Accepted
**Tanggal:** 2026-09-06

## Konteks

Dalam pelaksanaan item tugas `P0-01 Production baseline`, inspeksi pada server Hostinger (production) menemui kendala karena keterbatasan verifikasi koneksi dan isolasi sesi *secure shell* yang tidak diekspos secara otomatis ke *agent environment*. Untuk mencegah modifikasi atau akses ke Hostinger yang tidak disengaja dan tidak terverifikasi secara sempurna, owner telah mengambil keputusan secara tegas mengenai perlakuan sistem production lama (legacy).

## Keputusan

Sesuai instruksi owner, ditetapkan dua belas poin keputusan berikut:

1. Audit production melalui SSH atau File Manager tidak dilanjutkan pada tahap ini.
2. Deployment Hostinger saat ini diperlakukan sebagai sistem legacy dan bukan *source of truth* untuk pengembangan arsitektur baru.
3. Pengembangan dilakukan dan diselesaikan terlebih dahulu di environment lokal, kemudian staging, sebelum deployment production baru.
4. Deployment baru ditargetkan terhubung ke repository GitHub.
5. GitHub hanya menjadi *source of truth* untuk source code. Database, file media, credential, dan konfigurasi environment tetap dikelola terpisah.
6. Database production baru boleh dibuat pada tahap deployment nanti, tetapi belum dibuat dalam tugas ini.
7. Database dan media lama tidak boleh dihapus sekarang.
8. Sebelum cutover atau penghapusan sistem lama, harus tersedia backup terakhir melalui mekanisme Hostinger/hPanel atau mekanisme lain yang disetujui, dan deployment baru harus sudah diverifikasi.
9. Data lokal lama dipertahankan sebagai referensi dan bahan migrasi/kurasi, bukan otomatis dianggap data production lengkap.
10. Produk dapat dikurasi atau diunggah ulang secara bertahap, tetapi URL/slug publik existing sebisa mungkin dipertahankan. Perubahan URL yang disengaja membutuhkan *redirect*.
11. Media production baru nantinya tidak disimpan sebagai upload di repository GitHub; target storage tetap menggunakan Laravel Filesystem dengan storage terpisah seperti S3-compatible/Cloudflare R2 pada fase yang sesuai.
12. Fakta production yang tidak berhasil diperiksa tetap ditulis sebagai *unknown* dan tidak boleh ditebak.

## Konsekuensi Positif

- **Kecepatan Pengembangan:** Tim dan agen AI dapat langsung beralih ke pengembangan lokal tanpa harus terblokir oleh kendala konektivitas *production*.
- **Pemisahan Kekhawatiran (Decoupling):** Kode terpisah secara aman dari *hosting platform legacy*.
- **Keamanan:** Meminimalisir risiko intervensi langsung ke *production* atau modifikasi file penting sebelum kode dan arsitektur benar-benar matang.

## Risiko dan Mitigasi

- **Risiko Data Diskrepansi:** Data lokal yang ada saat ini mungkin tertinggal dibandingkan *production*.
- **Mitigasi:** Data lokal akan terus dipertahankan hanya sebagai referensi kurasi. Migrasi sesungguhnya, apabila dilakukan kelak, akan memerlukan backup terbaru dari *production* atau manual kurasi ulang tanpa mengesampingkan keutuhan slug eksisting.

## Hal yang Tidak Diputuskan

- Cara spesifik memigrasi data secara mekanis dari Hostinger lama ke instance atau *storage bucket* baru.
- Penjadwalan spesifik dari tindakan *cutover*.

