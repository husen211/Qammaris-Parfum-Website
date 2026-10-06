# Runbook Backup dan Restore Lokal

## Prinsip

Backup harus berada di luar repository dan tidak boleh masuk Git. Credential serta `.env` tidak dimasukkan ke archive. Backup dianggap valid hanya setelah checksum dan restore test berhasil.

## Baseline Phase 1

Snapshot lokal dibuat pada 2026-09-14 di folder Qammaris yang terpisah dari repository. Folder tersebut mempunyai `MANIFEST.md` yang mencatat file, checksum SHA-256, tabel yang disertakan, serta hasil restore.

Isi snapshot:

- dump tabel bisnis, migration, dan autentikasi;
- archive `storage/app/public`;
- patch perubahan tracked milik user;
- snapshot dokumentasi governance.

## Hasil verifikasi

Dump database berhasil direstore ke database sementara dengan hasil:

- products: 180
- product_variants: 64
- product_images: 19
- brands: 21
- categories: 5
- blog_posts: 5

Database sementara kemudian dihapus. Database sumber tidak diubah.

Archive media berisi 38 file. Seluruh path relatif dan SHA-256 cocok dengan sumber saat snapshot dibuat.

## Pengecualian tabel runtime

Full dump awal gagal karena MariaDB crash ketika membaca tabel `sessions`. Log menunjukkan indikasi page corruption. Karena session/cache/job bukan data katalog dan dapat dibuat ulang dari migration, baseline terverifikasi mengecualikan:

- sessions
- cache
- cache_locks
- jobs
- job_batches
- failed_jobs

Jangan melakukan repair atau drop tabel runtime sebagai bagian dari restore data katalog.

## Prosedur sebelum perubahan data/schema berisiko

1. Pastikan database target lokal, bukan production.
2. Buat folder backup baru di luar repository; jangan menimpa snapshot sebelumnya.
3. Dump tabel bisnis menggunakan transaksi konsisten.
4. Archive `storage/app/public` secara terpisah.
5. Buat SHA-256 untuk setiap artifact.
6. Restore dump ke database sementara dengan nama unik.
7. Cocokkan jumlah record penting.
8. Verifikasi archive media berdasarkan jumlah file dan checksum.
9. Hapus hanya database verifikasi yang dibuat oleh proses tersebut.
10. Catat hasil dan pengecualian pada manifest backup.

## Restore sebenarnya

Restore ke database development atau staging memerlukan persetujuan scope target. Jangan restore langsung ke database existing. Gunakan database kosong, cocokkan schema/count, lalu ubah koneksi aplikasi hanya setelah verifikasi.

Restore production dan penghapusan database lama selalu membutuhkan approval owner, backup production terbaru, serta rollback plan teruji.
