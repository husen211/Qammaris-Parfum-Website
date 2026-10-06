# ADR-012 — Persistent Immutable Import Preview Batches

Status: Accepted — 2026-09-16

## Context

P6-01 membuktikan canonical CSV dan preview deterministik, tetapi hasil preview hanya hidup selama satu request. Apply yang aman membutuhkan evidence immutable mengenai file, actor, state katalog, hasil normalisasi, candidate action, dan issue yang benar-benar direview.

Menyimpan file sumber akan memperbesar risiko data retention dan storage lifecycle. Sebaliknya, hanya menyimpan summary tidak cukup untuk membuktikan bahwa apply masa depan memakai baris yang sama.

## Decision

1. Preview yang berhasil diparsing menyimpan satu `product_import_batch` dan satu `product_import_row` untuk setiap baris nonkosong.
2. File sumber tidak disimpan. Batch hanya menyimpan nama aman, ukuran, SHA-256, versi kontrak, actor, summary, dan fingerprint state katalog.
3. Row menyimpan normalized data, issue, candidate action, matched product saat preview, serta payload hash.
4. Idempotency key adalah hash versi kontrak, actor ID, fingerprint file, dan fingerprint state katalog. Request identik pada state identik memakai batch existing.
5. State fingerprint mencakup brand, kategori, product, dan external identity yang dapat memengaruhi hasil preview. Perubahan salah satu state membuat batch baru.
6. Structural failure sebelum preview lengkap tidak membuat batch kosong.
7. Batch audit tidak memberi izin apply dan tidak mengubah product, offer, taxonomy, identity, media, atau publication status.

## Consequences

- Riwayat preview dapat diaudit dan dibuka sebagai fondasi approval/apply berikutnya tanpa mempertahankan file mentah.
- JSON normalized payload dapat bertambah sampai batas file 5 MB/1.000 baris, tetapi tetap bounded dan berada pada tabel audit terpisah.
- Perubahan katalog yang tidak relevan terhadap file juga dapat menghasilkan fingerprint baru. False invalidation diterima karena lebih aman daripada menerapkan preview stale.
- Apply berikutnya harus memverifikasi batch masih `previewed`, state fingerprint masih sama, payload hash utuh, dan row error tidak diterapkan.

## Rollback dan forward fix

- Rollback menghapus tabel row lebih dahulu lalu batch; tidak mengubah tabel katalog.
- Batch yang sudah dipakai kelak tidak boleh diedit. Perubahan kontrak menggunakan versi baru.
- Retention dan hard cleanup batch harus menjadi lifecycle terpisah dengan kebijakan audit eksplisit.
