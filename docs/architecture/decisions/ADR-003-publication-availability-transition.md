# ADR-003 — Publication dan Availability sebagai State Terpisah

Status: Accepted — 2026-09-16

## Context

Schema legacy hanya mempunyai `products.is_active` dan `product_variants.stock`. Keduanya belum dapat membedakan apakah produk boleh tampil, apakah stoknya pernah diperiksa, atau apakah informasi stok masih segar. Website juga tidak menjanjikan live inventory.

## Decision

1. Product mempunyai `publication_status`: `draft`, `published`, atau `archived`.
2. Product mempunyai `availability_status`: `unknown`, `available`, atau `sold_out`.
3. `available` hanya efektif selama 36 jam sejak `availability_checked_at`; tanpa timestamp atau setelah melewati batas itu, tampilan domain membacanya sebagai `unknown`.
4. `sold_out` tetap eksplisit walaupun timestamp lama dan hanya berubah melalui pembaruan berikutnya.
5. `stock_quantity` adalah snapshot opsional dan bukan janji stok publik.
6. `availability_source` menyimpan sumber pemeriksaan tanpa menjadi credential atau identifier actor.
7. Selama transisi, public query mensyaratkan `publication_status=published` dan `is_active=true`. Field `is_active` dipertahankan sebagai compatibility guard sampai semua writer berpindah ke state baru.
8. Data existing dengan `is_active=true` dipetakan ke `published`; data existing dengan `is_active=false` dipetakan ke `archived`. Timestamp historis yang tidak diketahui tidak dikarang.
9. Admin archive/restore menyinkronkan kedua representasi selama masa transisi.

## Consequences

- Sold out tidak lagi perlu disamakan dengan archive.
- Future import/API dapat menulis status availability secara deterministik tanpa membuat produk otomatis published.
- UI availability, draft editor, dan filter belum berubah pada item ini.
- `is_active` tidak dihapus pada Phase 3 awal agar deploy dapat dilakukan bertahap.

## Migration and rollback

- Migration hanya menambah kolom/index dan backfill status publication berdasarkan `is_active`; ID, slug, relasi, harga, dan media tidak diubah.
- Sebelum migration pada environment berdata, catat jumlah product dan checksum identitas `id:slug` lalu bandingkan setelah migration.
- Rollback schema aman hanya sebelum field baru dipakai writer. Setelah status baru dipakai, utamakan forward fix agar metadata publication/availability tidak hilang.
- Staging dan production migration memerlukan gate deployment masing-masing; ADR ini bukan izin deployment production.
