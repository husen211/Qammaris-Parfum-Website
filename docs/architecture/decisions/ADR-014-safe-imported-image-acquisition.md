# ADR-014 — Safe Imported Image Acquisition

Status: Accepted — 2026-09-16

## Context

ADR-013 memisahkan apply data produk dari media: URL gambar dalam CSV hanya kandidat sumber dan apply tidak melakukan network request. Katalog berisi ratusan produk, sehingga admin memerlukan cara bounded, dapat diulang, dan auditable untuk memindahkan maksimal tiga gambar per produk ke storage Qammaris tanpa hotlink atau memperluas kewenangan import menjadi publish.

URL provider adalah input tidak tepercaya. Download langsung dari request apply berisiko timeout, SSRF, file palsu/terlalu besar, duplikasi saat retry, serta object yatim bila metadata database gagal dibuat.

## Decision

1. Akuisisi gambar adalah aksi admin eksplisit setelah batch berstatus `applied`; apply data tetap transactional dan tanpa network/storage side effect.
2. Sistem membuat paling banyak satu job bounded per row eligible. Row harus berstatus apply `created/updated`, mempunyai product hasil apply, dan product masih `draft` saat dispatch maupun saat job berjalan.
3. Kandidat dibatasi tiga URL canonical dari preview. Media existing menghitung kapasitas maksimum tiga gambar dan tidak pernah di-overwrite.
4. Downloader hanya menerima HTTPS dari exact host allowlist environment. URL dengan credential, port selain 443, alamat IP, atau redirect ditolak.
5. Connect timeout, total timeout, byte limit, actual MIME JPEG/PNG/WebP, dimensi, dan checksum SHA-256 diverifikasi sebelum file masuk media storage.
6. File disimpan melalui `ProductMediaStorage` lalu metadata dilampirkan melalui `AttachProductImage`. File baru dihapus bila attachment gagal.
7. Primary existing dipertahankan. Gambar pertama otomatis primary hanya pada product tanpa gambar aktif.
8. Outcome per kandidat disimpan pada import row bersama status, checksum/path/image ID atau error aman. Actor serta waktu request/completion juga dicatat.
9. Retry hanya memproses kandidat non-success. Kandidat sukses tidak diunduh atau ditautkan ulang; kegagalan tidak menghapus draft atau media lain.
10. Worker production bukan bagian dari perubahan kode ini. Environment deployed wajib memakai queue durable dan worker terkelola sebelum fitur dipakai untuk batch besar.

## Consequences

- Halaman publik selalu membaca media dari storage Qammaris dan tidak bergantung pada umur URL provider.
- Kegagalan parsial dapat direview dan diulang tanpa menduplikasi keberhasilan sebelumnya.
- Exact allowlist sengaja membatasi fleksibilitas; host provider baru memerlukan perubahan konfigurasi operasional.
- Database dan object storage tidak mempunyai transaksi bersama. Cleanup object baru pada failure mengurangi orphan, sedangkan audit outcome menjadi sumber recovery.
- Dengan `QUEUE_CONNECTION=sync`, perilaku tetap benar untuk local verification tetapi request menunggu job. Deployment nyata harus memakai `database` atau backend queue durable lain dan worker aktif.

## Rollback dan forward fix

- Migration hanya menambah kolom status/request/outcome pada `product_import_rows`; rollback menghapus kolom tersebut tanpa menghapus product atau media yang sudah berhasil disimpan.
- Media yang sudah attached tidak dihapus otomatis ketika kode di-rollback. Koreksi memakai media archive/lifecycle terpisah agar tidak terjadi kehilangan data.
- Job gagal dapat diperbaiki lewat konfigurasi allowlist/worker lalu tombol retry; jangan mengubah outcome sukses menjadi pending.
- Keputusan ini tidak memberi izin staging, production deployment, bulk publish, hard delete, atau update product published/archived.
