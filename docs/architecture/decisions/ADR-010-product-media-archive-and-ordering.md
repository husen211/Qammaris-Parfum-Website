# ADR-010 — Soft Archive dan Urutan Media Produk

Status: Accepted — 2026-09-16

## Context

Admin perlu mengeluarkan foto dari galeri, memilih cover, dan mengubah urutan tanpa kehilangan file yang masih mungkin dibutuhkan. Database dan object storage tidak mempunyai transaksi bersama, sehingga hard delete metadata dan file dalam satu request berisiko menghasilkan metadata yatim atau kehilangan file permanen ketika salah satu operasi gagal.

Produk published juga harus selalu memiliki tepat satu primary image. Perubahan urutan atau arsip tidak boleh membuat primary ganda, urutan berlubang, maupun mengubah foto milik produk lain melalui manipulasi ID pada request.

## Decision

1. Pengeluaran gambar dari galeri menggunakan soft delete pada `product_images`; object key, product ID, dan file storage tetap dipertahankan untuk recovery.
2. Relationship media normal hanya membaca record aktif, sehingga arsip tidak tampil di katalog dan tidak dihitung dalam batas maksimum tiga gambar.
3. Foto terakhir produk published tidak boleh diarsipkan. Draft atau produk archived boleh tidak mempunyai foto agar dapat dilengkapi kemudian.
4. Bila primary image diarsipkan dan masih ada foto aktif, foto aktif pertama menurut urutan menjadi primary dalam transaksi yang sama.
5. Setelah arsip atau reorder, `sort_order` gambar aktif dinormalisasi menjadi `0..n-1`.
6. Reorder memakai aksi Naik/Turun terhadap tetangga terdekat. Operasi primary, reorder, dan arsip mengunci product serta koleksi image dan selalu memverifikasi bahwa image dimiliki product pada request.
7. Hard delete file, restore UI, serta cleanup arsip berbasis retention bukan bagian keputusan ini dan membutuhkan item backlog serta audit storage tersendiri.

## Consequences

- Kesalahan admin dapat dipulihkan secara operasional tanpa mengambil ulang file dari provider.
- File arsip tetap memakai kapasitas storage sampai retention dan cleanup yang aman tersedia.
- Semua query bisnis normal otomatis mengecualikan record arsip melalui global scope `SoftDeletes`; audit atau recovery wajib memakai query explicit `withTrashed`/`onlyTrashed`.
- UI dapat membuka slot upload baru segera setelah arsip karena batas tiga hanya menghitung media aktif.
- Jalur legacy yang tidak mempunyai product scope tetap tidak diberi kemampuan delete.

## Migration dan rollback

- Migration hanya menambah nullable `deleted_at`; ID, object key, primary flag, urutan, foreign-key index, dan file existing tidak diubah. Koleksi per produk dibatasi tiga record aktif sehingga index tambahan belum diperlukan.
- Sebelum ada record arsip, migration dapat di-rollback dan dijalankan ulang tanpa perubahan data bisnis.
- Setelah record arsip dibuat, rollback schema bukan rollback fitur yang aman karena menghilangkan pembeda antara media aktif dan arsip. Gunakan forward fix atau restore record secara eksplisit setelah review.
- Keputusan ini tidak memberi izin perubahan staging, production, bucket, atau cleanup file.
