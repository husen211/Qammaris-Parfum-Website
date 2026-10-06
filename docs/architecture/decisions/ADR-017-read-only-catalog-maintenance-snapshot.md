# ADR-017 — Read-only Catalog Maintenance Snapshot

Status: Accepted — 2026-09-16

## Context

Qammaris membutuhkan gambaran seluruh katalog untuk rekonsiliasi sekitar ratusan produk dan persiapan bulk maintenance. Kontrak import existing berorientasi provider dan mutation draft, sedangkan mayoritas data legacy belum mempunyai external identity. Menjadikan export katalog langsung re-importable akan mendorong pencocokan ambigu, stale overwrite, atau perubahan published product tanpa preview khusus.

## Decision

1. Sediakan snapshot CSV admin-only, read-only, versioned sebagai `qammaris-catalog-snapshot-v1`.
2. Export memuat satu row per product berdasarkan urutan ID internal dan mencakup draft, published, serta archived.
3. ID internal dan slug menjadi jangkar rekonsiliasi; external identity hanya agregat informasional yang diurutkan deterministik.
4. Harga dan ukuran berasal dari offer aktif. Tidak ada fallback ke `products.base_price` karena field tersebut hanya kompatibilitas/cache selama transisi.
5. Media direpresentasikan sebagai jumlah aktif dan boolean primary. Path, object key, serta URL tidak diexport.
6. Availability recorded dan effective diexport terpisah agar freshness tidak disalahartikan sebagai live stock.
7. Semua cell memakai sanitizer formula spreadsheet bersama report audit import. Output memakai BOM UTF-8, streaming response, safe filename, dan `no-store` tanpa file permanen.
8. Snapshot bukan input import. Write-back bulk maintenance memerlukan kontrak dan backlog item terpisah dengan preview, conflict handling, idempotency, serta audit.

## Consequences

- Admin mempunyai dataset lengkap dan stabil untuk audit tanpa akses database atau storage.
- Data legacy tanpa offer, taxonomy, identity, atau media tetap terlihat sebagai cell kosong/indikator kelengkapan; sistem tidak menebak fakta.
- File dapat stale setelah download. `updated_at` membantu review, tetapi tidak menggantikan revalidation pada future write path.
- Reusable formula sanitizer kini mempunyai dua caller nyata: report batch dan snapshot katalog.

## Rollback dan forward fix

- Tidak ada migration atau mutation data. Rollback menghapus route, exporter, tombol admin, dan dokumentasi tanpa mengubah katalog.
- Perubahan makna/header wajib memakai contract version baru.
- Future bulk update tidak boleh menggunakan endpoint ini sebagai write API; buat preview contract terpisah dan pertahankan snapshot v1 sebagai read-only.
