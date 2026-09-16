# ADR-016 — Import Batch Audit CSV Report

Status: Accepted — 2026-09-16

## Context

P6-02 sampai P6-05 menyimpan preview, apply outcome, image outcome, dan resolusi manual sebagai audit per batch/row. Riwayat tersebut dapat dibaca pada admin, tetapi review operasional ratusan baris dan arsip di luar aplikasi memerlukan report yang dapat dibuka di spreadsheet. Mengekspor normalized payload atau snapshot mentah akan membuat kontrak tidak stabil, terlalu besar, dan berisiko membawa URL/data internal yang tidak dibutuhkan.

## Decision

1. Setiap batch existing mempunyai report CSV read-only, admin-only, dengan contract `qammaris-import-audit-v1`.
2. Report memakai UTF-8 BOM dan satu baris per import row dalam urutan line source.
3. Kolom di-allowlist untuk kebutuhan operasional: identitas batch/baris, candidate, outcome apply, product/status hasil, resolusi, agregat gambar, issue, actor, dan timestamp.
4. Deskripsi produk, URL gambar provider, normalized payload penuh, dan snapshot before/after tidak diexport.
5. Setiap cell disanitasi terhadap spreadsheet formula injection sebelum ditulis dengan CSV escaping standar.
6. Response distream langsung, memakai safe generated filename, `no-store`, dan tidak membuat object/file report permanen.
7. Report merepresentasikan audit batch pada waktu download. Report bukan canonical catalog export dan tidak menjadi input re-import.

## Consequences

- Admin dapat mereview outcome di spreadsheet tanpa akses database atau menyalin tabel UI.
- Kolom fixed memudahkan otomasi audit masa depan, tetapi perubahan kontrak memerlukan version baru.
- Query memuat maksimum 1.000 row sesuai batas import; output distream dan tidak menambah lifecycle file storage.
- Menghilangkan payload/snapshot mentah membatasi forensic detail di file, tetapi detail lengkap tetap tersedia pada record audit aplikasi.

## Rollback dan forward fix

- Tidak ada migration atau data mutation. Rollback menghapus route, exporter, dan link UI tanpa memengaruhi batch existing.
- Kolom baru yang kompatibel sebaiknya memakai version report baru bila makna kontrak berubah.
- Keputusan ini tidak memberi izin export katalog, share/email otomatis, retention cleanup, staging, production deployment, atau akses non-admin.
