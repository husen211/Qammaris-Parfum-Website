# Rilis koreksi tes preferensi v1.2 — 10 Oktober 2026

Owner menyetujui secara khusus rilis PR #51 ke staging, preview/rebuild/replay profil, lalu produksi setelah lolos. PR #51 sudah merged dan live pada `ec3999367c8f94c70ad1ef60f4ce4b9486754a1f`: pertanyaan `preference-v1.2`, mesin `pref-03.2-provisional`, flag aktif. Ini koreksi beta PREF-03, bukan penerimaan kualitas akhir atau dimulainya PREF-04/V2. [Receipt tersanitasi](release-receipt.json).

## CI dan deployment

[PR #51](https://github.com/husen211/Qammaris-Parfum-Website/pull/51), [CI main 38027974630](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/38027974630) dan [deployment 38028011115](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/38028011115) sukses. Main CI menjalankan 542 Laravel tests / 4112 assertions, 34 Node tests dan Vite build. Tidak mengulang suite luas di produksi. Warning ukuran bundle About/editor existing tetap di luar scope.

[Candidate workflow 38027567411](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/38027567411) membuat paket resmi dari branch; job production deploy **skipped**. Guard workflow remote diverifikasi sebelum dispatch setelah review otomatis awal menolak dugaan deployment sebelum staging. Paket yang sama diperiksa checksum lokal/server; staging bootstrap/Blade compile berhasil pada MySQL terpisah. Canonicalization input tersembunyi mengubah stale `sweet` menjadi `none`; result/feedback JSON roundtrip dan replay berhasil dalam transaksi yang di-rollback penuh. Jumlah baris kembali nol dan fingerprint katalog/media/users tetap. Tidak membuat actor staging; staging tidak memiliki admin existing, jadi **staging admin apply tidak diuji**, bukan diam-diam membuat akun.

## Data dan recovery

Recovery dump privat dibuat sebelum cutover, completion marker/ukuran/SHA256 diverifikasi, mode direktori 0700. Dump tidak diunduh atau masuk Git. Referensi dan checksum ada dalam receipt. Release sebelumnya `1a33c707830f88d845cf433849cf329345890e50` serta paket candidate dipertahankan. Hanya ekstraksi app candidate privat dibersihkan setelah validasi path/revision/checksum agar deployment punya ruang; tidak memangkas release, backup, atau shared media.

Flag dimatikan sementara dan legacy tampil selama cutover. Preview produksi 381 profil, apply 381 perubahan pada tabel profil/revision menggunakan admin existing ID 1, replay 0 perubahan / 381 unchanged. Catalog IDs/slugs/notes/deskripsi/harga/offers/publication/media dan akun/peran terverifikasi tidak berubah. Hasil 8 / feedback 1 sebelum probe tetap utuh. Tidak ada migrasi tambahan atau perubahan schema/credential/permission. Flag aktif kembali setelah seluruh 381 profil current, stale 0. Koreksi manual terikat sumber/parser lama tetap membutuhkan review.

Rollback operasional: flag `FRAGRANCE_PREFERENCE_ENABLED=false`, refresh config cache melalui proses PHP baru, lalu verifikasi legacy. Pertahankan tabel, revision, jawaban dan feedback; jangan migrate-down/purge. Revert ke beta v1.1 memerlukan preview/rebuild parser lama sebelum flag diaktifkan. Restore dump di atas jawaban customer baru membutuhkan rencana terpisah. [Runbook](../../../runbooks/FRAGRANCE_QUESTION_REVISION.md).

## Pemeriksaan live terbatas

Satu probe anonim operasional menyelesaikan range 200–300rb, kantor/AC/siang, citrus, hindari Aroma manis, skip kemanisan, projection dekat, ketahanan tidak diprioritaskan, unisex, tanpa favorit. Back/refresh mempertahankan jawaban. Ringkasan dan hasil memakai sweetness=`none`, tiga parfum dalam budget, foto asli termuat dengan contain, harga/ukuran/Ready terlihat. Ketiganya memiliki data kemanisan kosong, dan **peringatan belum diketahui tampil**, bukan klaim terbukti non-manis.

Feedback keseluruhan `partly` tersimpan, refresh membuka hasil yang sama, update dengan feedback produk 532 `neutral/unfamiliar` tetap menghasilkan **satu** catatan feedback. Hasil/feedback probe dipertahankan sesuai kebijakan retensi dan diatribusikan dengan hash UUID dalam receipt; **harus dikecualikan dari label manusia dan evaluasi kualitas**. Tidak menyimpan cookie/browser hash mentah atau raw request/log pada bukti.

Health dan quiz HTTP 200; hasil yang diakses tanpa cookie pemilik HTTP 404. Quiz/hasil mempunyai `no-store, private` dan `noindex, nofollow`. Pada pemeriksaan 05:46:10Z, satu file Laravel log diperiksa secara tersanitasi: nol ERROR/CRITICAL/ALERT/EMERGENCY sejak 05:34Z. Nol console error pada tab uji. Ini pemeriksaan terbatas saat rilis, bukan jaminan tidak akan pernah ada error.

Alur penuh pertama memakai tab background berukuran konten 1265px; override 390 tidak diterapkan pada tab tersembunyi itu sehingga **tidak diklaim sebagai uji mobile**. Tab terlihat kemudian benar-benar memakai nominal 390 / 1440 / 320: konten 375 / 1425 / 305px karena scrollbar, tanpa horizontal overflow. Result mobile, edit jawaban range dan input budget bebas nonaktif diperiksa. Ini mouse/keyboard+resize; genuine touch/iPhone Safari tetap belum terverifikasi. Viewport direset dan hanya tab sementara agen ditutup.

- [Budget bebas live 390](live-budget-free-390.png)
- [Hasil live 390](live-result-390.png)
- [Hasil live 1440](live-result-1440.png)

## Kualitas dan langkah selanjutnya

Riset primer, index sumber 381 produk dan perbandingan 15 kasus development tersimpan pada [bukti implementasi](README.md). Proxy agent tidak berubah (9/15 ≥1, 2/15 ≥2); ini bukan label sensory atau bukti akurasi 80–90%. Lima holdout tidak dibuka. Data kemanisan tersedia 28/381, sehingga 353 unknown tetap diungkapkan. Tidak ada AI eksternal, pengayaan SKU internet, rewrite katalog atau pembelajaran otomatis.

PREF-03 tetap IN_REVIEW. Langkah berikut yang disarankan: Owner/staf menilai rekomendasi beta dan kasus ambigu secara terarah, kemudian review manusia pada matriks 30 skenario sebelum penerimaan akhir. Tidak memulai PREF-04 atau QR/offline V2 otomatis.
