# ADR-013 — Transactional Product Import Draft Apply

Status: Accepted — 2026-09-16

## Context

ADR-011 menetapkan canonical CSV dan preview deterministik. ADR-012 menyimpan preview sebagai batch immutable dengan fingerprint state katalog dan payload hash. Tahap berikutnya membutuhkan mutation yang cukup berguna untuk ratusan produk, tetapi tidak boleh menimpa perubahan manual atau mempublikasikan data belum lengkap.

## Decision

1. Apply hanya berasal dari `product_import_batches` persisted; file sumber tidak dibaca ulang dan tidak disimpan.
2. Admin harus memberi konfirmasi eksplisit. Service kemudian mengunci batch/row, memverifikasi versi kontrak, seluruh payload hash, dan fingerprint state katalog.
3. Seluruh mutation satu batch berjalan dalam satu database transaction. Unexpected failure membatalkan semua catalog write dan menandai batch failed untuk dipreview ulang.
4. Candidate create selalu membuat product draft/nonaktif, mapping provider+kode, dan single offer hanya bila harga+ukuran lengkap.
5. Candidate update hanya berlaku untuk product existing berstatus draft. Nilai CSV kosong mempertahankan nilai existing; slug, publication, dan availability status tidak diturunkan dari import.
6. Product published/archived, row error, dan conflict tidak diubah. Row tersebut dicatat blocked agar batch lain tetap dapat diterapkan secara deterministic.
7. Apply tidak membuat brand/category, tidak mengunduh URL gambar, tidak membuat media metadata, tidak publish, dan tidak menjanjikan live stock.
8. Batch applied bersifat idempotent. Request ulang hanya mengembalikan outcome existing.
9. Batch dan row menyimpan actor/waktu apply, applied/blocked counts, product hasil, pesan outcome, serta snapshot before/after terkontrol.
10. Idempotency preview tetap memakai batch existing saat status previewed/applied. Batch terminal stale/invalid/failed menghasilkan successor key deterministic agar file yang sama dapat dipreview ulang tanpa menciptakan duplikasi pada setiap upload.

## Consequences

- Owner dapat memproses dataset besar menjadi workspace draft tanpa memberi import kewenangan atas katalog publik.
- Baris review tetap berguna untuk draft, sementara publish readiness tetap menjadi quality gate terpisah.
- Update published/archived memerlukan workflow conflict resolution manual pada backlog lanjutan.
- Fingerprint dapat menginvalidasi batch karena perubahan katalog yang tidak terkait langsung; false invalidation diterima demi keselamatan.
- Unique constraint identity dan transaction melindungi race yang lolos di antara pemeriksaan state dan mutation.

## Rollback dan forward fix

- Migration P6-03 hanya menambah kolom outcome/FK/index pada tabel audit import; rollback menghapus kolom tersebut tanpa mengubah product yang sudah dibuat.
- Product mutation yang sudah applied tidak dihapus otomatis ketika kode di-rollback. Koreksi data memakai review manual atau workflow undo terpisah, bukan hard delete.
- Bila apply gagal atau stale/invalid, buat preview baru; jangan mengubah audit row agar bisa dipakai ulang.
- Keputusan ini tidak memberi izin staging, production, download media, atau bulk publish.
