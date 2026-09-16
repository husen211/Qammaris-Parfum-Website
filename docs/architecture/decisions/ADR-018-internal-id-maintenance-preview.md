# ADR-018 — Internal-ID Bulk Maintenance Preview

Status: Accepted — 2026-09-16

## Context

Snapshot P6-07 memberi dataset katalog lengkap, tetapi sengaja tidak re-importable. Qammaris tetap membutuhkan jalur aman untuk menyiapkan koreksi harga dan metadata ratusan product. External identity belum tersedia pada mayoritas data legacy dan nama produk tidak aman sebagai identifier. Selain itu, `updated_at` product saja tidak selalu berubah ketika hanya offer yang diedit.

## Decision

1. Kontrak maintenance `maintenance-v1` hanya meng-update kandidat product existing melalui ID internal.
2. Snapshot katalog dinaikkan ke `qammaris-catalog-snapshot-v2` dengan `row_fingerprint` yang mencakup state product allowlisted dan satu offer aktif.
3. File maintenance wajib membawa `product_id`, `expected_updated_at`, dan `expected_row_fingerprint`. Mismatch mana pun menghasilkan error stale tanpa mutation.
4. Field allowlist terbatas pada nama, deskripsi, taxonomy, gender, stok snapshot, best seller, offer harga+ukuran, dan fragrance notes. Cell kosong berarti preserve; clear/delete tidak tersedia.
5. Preview menghitung diff current vs proposed, menahan missing/duplicate/stale/invalid row, dan menyimpan before snapshot serta normalized payload immutable.
6. Preview tidak mempunyai endpoint apply. Apply transactional, revalidation, actor confirmation, dan outcome audit menjadi backlog terpisah.
7. Tabel `product_import_batches/rows` direuse karena struktur auditnya sesuai. Flow dipisahkan secara wajib melalui `contract_version` pada query, route, history, serta action provider.
8. Upload actor+file+state identik bersifat idempotent dan kembali ke batch yang sama.

## Consequences

- Product legacy dapat dipetakan stabil tanpa SKU/external identity.
- Perubahan offer-only terdeteksi walaupun timestamp product tidak berubah.
- Reuse tabel menghindari schema paralel, tetapi contract isolation menjadi invariant yang harus dites.
- Tidak ada mutation atau migration pada item ini; admin hanya memperoleh dataset review yang persisted.

## Rollback dan forward fix

- Rollback menghapus controller, previewer, recorder, routes, view, dan link maintenance. Batch `maintenance-v1` yang telanjur tercatat dapat dipertahankan sebagai audit atau di-cleanup melalui pekerjaan lokal/operasional terpisah.
- Snapshot v1 tetap read-only; consumer maintenance harus memakai v2.
- Future apply wajib memverifikasi ulang contract version, payload hash, row fingerprint, state katalog, serta actor confirmation dalam satu transaksi.
