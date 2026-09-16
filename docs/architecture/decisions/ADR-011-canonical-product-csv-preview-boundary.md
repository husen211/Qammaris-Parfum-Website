# ADR-011 — Canonical Product CSV dan Preview Read-only

Status: Accepted — 2026-09-16; persistence audit diperluas oleh ADR-012

## Context

Export Shopee adalah sumber mentah dengan struktur provider dan data yang tidak selalu lengkap. Owner akan memakai Claude untuk membersihkan nama/deskripsi, mengekstrak fragrance notes ketika tersedia, dan menata data ke format Qammaris. Aplikasi tetap harus memvalidasi seluruh nilai secara deterministik dan tidak boleh mempercayai hasil AI sebagai source of truth.

Laravel saat ini tidak mempunyai dependency pembaca XLSX. Menambahkan parser XLSX sebelum kontrak data dan workflow preview terbukti akan memperluas surface keamanan serta maintenance tanpa kebutuhan apply yang siap.

## Decision

1. Boundary tahap awal menggunakan canonical UTF-8 CSV dengan 17 header berurutan dan terdokumentasi.
2. Export XLSX provider tidak diterima langsung. Claude atau proses kurasi lain mengubahnya ke CSV canonical tanpa mengarang fakta.
3. Preview membaca maksimum 5 MB dan 1.000 baris, menghitung SHA-256 dari file asli, memvalidasi struktur/nilai, lalu membuang upload setelah request.
4. P6-01 preview sama sekali tidak menulis product, offer, external identity, taxonomy, media, file storage, atau batch history. ADR-012 kemudian mengizinkan write terisolasi hanya ke tabel audit import tanpa mengubah boundary katalog.
5. Pencocokan update hanya memakai provider dan external product code. Nama sama hanya menjadi peringatan; tidak ada fuzzy auto-merge.
6. Taxonomy dicocokkan exact case-insensitive terhadap record existing dan tidak dibuat otomatis.
7. URL gambar hanya divalidasi sebagai HTTPS candidate source. Download, checksum, storage, dan attach media berada pada backlog terpisah.
8. Apply baru boleh ditambahkan setelah batch persistence, idempotency, conflict handling, dan jaminan bahwa preview yang disetujui identik dengan perubahan yang diterapkan.

## Consequences

- Owner dan Claude mempunyai kontrak sederhana yang bisa diaudit serta mudah dibuka di Excel tanpa dependency aplikasi baru.
- CSV mempunyai keterbatasan typing; karena itu kode produk selalu diperlakukan sebagai teks dan angka memakai format canonical tanpa separator locale.
- Preview request belum dapat dilanjutkan menjadi apply karena hasilnya tidak dipersist. File atau state database yang berubah harus dipreview ulang pada tahap berikutnya.
- XLSX adapter dapat ditambahkan kelak di boundary input tanpa mengubah kontrak baris domain bila kebutuhan operasional terbukti.

## Rollback dan forward fix

- Item ini tidak mempunyai schema migration atau data migration.
- Rollback kode hanya menghapus route, controller, parser, view, dan navigasi import; tidak ada data bisnis atau file media yang perlu dipulihkan.
- Jika kontrak perlu berubah, buat versioned contract dan migration/compatibility strategy. Jangan mengubah arti header v1 diam-diam setelah batch nyata mulai disimpan.
- Keputusan ini tidak memberi izin apply, staging, production, atau akses provider API.
