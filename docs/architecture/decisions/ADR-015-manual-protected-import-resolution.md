# ADR-015 — Manual Protected Product Import Resolution

Status: Accepted — 2026-09-16

## Context

ADR-013 menahan mapping import yang mengarah ke produk published/archived agar bulk apply tidak menimpa halaman publik. Admin tetap memerlukan jalur terkontrol untuk menerima sebagian data hasil kurasi tanpa menyalin manual seluruh produk. Jalur tersebut harus mencegah lost update, mempertahankan state publikasi/availability, dan meninggalkan audit yang cukup untuk menelusuri keputusan manusia.

## Decision

1. Resolusi hanya tersedia untuk row `blocked_protected` milik batch `applied` dan product yang tercatat pada outcome row tersebut.
2. Admin membandingkan nilai current/import, memilih minimal satu field, lalu memberi konfirmasi eksplisit. Tidak ada tombol accept-all default.
3. Field mutable dibatasi pada nama, deskripsi, brand, kategori, gender, terlaris, stok snapshot, fragrance notes, dan single offer harga+ukuran.
4. Publication status, availability status, slug, identity provider, dan media tidak dapat dipilih atau diubah oleh workflow ini.
5. Nilai kosong tidak menghapus data existing. Taxonomy harus active dan cocok exact; harga+ukuran diterapkan sebagai satu unit atomik.
6. Snapshot saat row ditahan dibandingkan dengan snapshot live di dalam transaction dan row/product dikunci. Perbedaan apa pun dianggap stale dan resolusi ditolak.
7. Resolusi one-time idempotent. Submit setelah status resolved mengembalikan outcome existing tanpa mutation baru.
8. Import row menyimpan resolution status, selected fields, actor, waktu, pesan, serta snapshot before/after.
9. Error/conflict struktural tidak diberi override; recovery-nya adalah memperbaiki CSV dan membuat preview baru.
10. Protected row yang diresolusi tidak menjadi eligible untuk akuisisi gambar otomatis. Media tetap dikelola melalui editor produk atau workflow lain yang disetujui.

## Consequences

- Admin dapat menerima koreksi spesifik tanpa membuka bulk overwrite pada produk publik.
- Stale guard bersifat konservatif: perubahan pada field katalog mana pun setelah apply mewajibkan preview baru, meski field tersebut tidak dipilih.
- Batch counters tetap merepresentasikan hasil bulk apply awal; outcome resolusi dibaca dari audit per row.
- Workflow lebih lambat daripada accept-all, tetapi keputusan dan dampaknya jelas pada produk yang sudah tampil ke publik.

## Rollback dan forward fix

- Migration hanya menambah kolom audit resolusi pada `product_import_rows`; rollback menghapus metadata tersebut dan tidak membalik perubahan product yang sudah diterapkan.
- Koreksi setelah resolusi dilakukan lewat editor product atau preview import baru; jangan mengedit snapshot audit.
- Keputusan ini tidak memberi izin staging, production deployment, auto-publish, perubahan availability status, fuzzy merge, atau hard delete.
