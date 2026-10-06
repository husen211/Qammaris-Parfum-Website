# ADR-019 — Transactional Bulk Maintenance Apply

Status: Accepted — 2026-09-16

## Context

P6-08 menyimpan preview maintenance immutable berbasis internal product ID, timestamp, row fingerprint, dan catalog-state fingerprint. Preview tersebut belum memberi jalan write. Apply harus mempertahankan hubungan satu-ke-satu antara perubahan yang direview dan mutation yang terjadi, termasuk ketika katalog berubah atau satu row gagal saat batch diproses.

## Decision

1. Apply hanya tersedia untuk admin pada batch `maintenance-v1` berstatus `previewed` setelah checkbox konfirmasi eksplisit.
2. Seluruh batch dijalankan dalam satu database transaction dengan batch, row, product, offer, dan taxonomy relevan dilock saat mutation.
3. Contract version, payload hash, catalog-state fingerprint, matched product, expected timestamp, row fingerprint, taxonomy aktif, serta harga+ukuran divalidasi ulang sebelum atau saat mutation.
4. Hanya row `valid` dengan `_changes` allowlisted yang diterapkan. Row review/no-op memakai outcome `skipped_no_changes`; row error/conflict memakai `blocked_error`.
5. Apply tidak membuat product dan tidak mengubah publication, availability, slug, media, external identity, atau taxonomy.
6. Before/after snapshot, product hasil, status, pesan, actor, dan waktu disimpan pada audit existing. Request ulang pada batch `applied` mengembalikan outcome tersimpan tanpa mutation ulang.
7. Perubahan state setelah preview menjadikan batch `stale`. Contract/payload rusak menjadikannya `invalid`. Unexpected failure me-rollback catalog write dan menjadikan batch `failed` melalui update terpisah.
8. Tidak ada migration baru; kolom outcome P6-03 dan isolasi `contract_version` direuse.

## Consequences

- Admin memperoleh bulk update yang cepat tanpa melewati preview atau guard optimistic concurrency.
- Global catalog-state guard bersifat konservatif: perubahan katalog lain setelah preview meminta preview ulang, tetapi mencegah batch lama diterapkan pada konteks berbeda.
- Row error tidak menghalangi row valid yang sudah direview, sedangkan kegagalan kode/storage/database yang unexpected membatalkan seluruh batch.
- Undo otomatis tidak tersedia; before snapshot menjadi bukti audit dan dasar forward-fix terkontrol.

## Rollback dan forward fix

- Sebelum commit aplikasi, rollback cukup menghapus route, request, action, UI apply, dan status outcome baru; tidak ada schema yang perlu diturunkan.
- Setelah batch diterapkan, rollback kode tidak membalikkan data. Gunakan before snapshot untuk membuat maintenance forward-fix baru yang melewati preview dan konfirmasi normal.
- Batch stale/invalid/failed tidak diaktifkan ulang; admin membuat preview successor dari snapshot terkini.
