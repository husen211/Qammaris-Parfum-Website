# PREF-03 — koreksi pertanyaan dan dasar aroma

10 Oktober 2026. **Siap ditinjau pada branch, belum deploy.** Koreksi setelah Owner mencoba beta; PREF-03 tetap satu item aktif. PREF-04 dan penerimaan kualitas manusia belum dimulai/diselesaikan oleh koreksi ini.

## Perilaku

Budget berupa range yang benar-benar disaring bawah/atas; bebas memperlihatkan input nonaktif. Harian/kantor-kuliah/acara-date tidak tumpang tindih. Lingkungan dan waktu pemakaian dipisahkan. Pilihan Aroma manis terpisah dari dessert/gourmand; pertanyaan kemanisan dinamis dan canonicalization server membuang jawaban tersembunyi. Pilihan non-manis tersedia. Projection dijelaskan dengan bahasa sehari-hari; pertanyaan ketahanan meminta prioritas informasi, bukan memilih durasi pendek yang tidak diinginkan. Pria/wanita/unisex sederhana. Tetap tiga hasil utama dan alternatif bersyarat, tanpa persentase kecocokan.

Sillage tidak lagi dihitung sebagai projection. “Tidak manis” yang faktual bisa dibaca; “tidak terlalu manis” tidak disamakan dengannya. Waktu siang/malam mengacu konteks katalog; tidak memaksa fresh/manis berdasarkan cuaca. Data kemanisan kosong terlihat langsung di kartu bila customer meminta non-manis, agar hasil tidak mengaku sudah terbukti non-manis.

## Dasar dan data

[Riset primer](../../../planning/fragrance-preference/question-revision/FRAGRANCE_FOUNDATIONS.md), [kontrak pertanyaan](../../../planning/fragrance-preference/question-revision/QUESTION_FLOW_REVISION.md), [indeks bukti seluruh 381 produk](../../../planning/fragrance-preference/question-revision/catalog-family-index.json). Snapshot produksi baca saja 2026-10-10T04:42:33Z; produk, harga, media, publication, akun dan credential produksi tidak diubah. Indeks merekam parser **sebelum** revisi sebagai acuan; coverage baru di receipt. Tidak ada migrasi baru atau pengisian ulang katalog.

## Pemeriksaan

- PHPUnit terkait parfum pada kode final: **72 tes / 453 assertions**, lulus tanpa warning. CI penuh tercatat pada checks PR setelah PR dibuka.
- Tiga tes JS percabangan/budget lulus. Vite final lulus, Pint dirty lulus. Warning ukuran bundle About/editor existing tidak ditangani oleh scope quiz.
- Browser lokal, fixture 381 produk dan actor sementara: 390x844, 1440x900, overflow 320x844 (konten 305px karena scrollbar). Disabled, budget range/edit, waktu, skip/unskip, Back/refresh, ringkasan/validasi, Space/Enter, hasil, feedback dan hitung ulang berhasil. Panel tidak aktif memiliki hidden/inert. Tidak ada error console. Ini resize+keyboard/mouse; **bukan genuine touch atau iPhone Safari**.
- Fixture menggunakan placeholder foto; tes ini tidak mengklaim memverifikasi ulang media produksi. Sebelum/akhir [390](before-390.png) → [390 akhir](after-budget-final-390.png), [desktop sebelum](before-1440.png) → [desktop akhir](after-budget-1440.png). [Budget bebas](after-budget-free-390.png), [waktu](after-time-390.png), [non-manis](after-sweetness-390.png).

## Kualitas belum diklaim

Pada 15 kasus pengembangan dengan snapshot 378 produk yang sama, overlap proposal agent tetap 9/15≥1 dan 2/15≥2, sama dengan mesin beta pref-02.3. Legacy enam pertanyaan 3/15≥1 dan 1/15≥2. Tidak ada penurunan angka proxy akibat koreksi ini, tetapi **tidak ada bukti kenaikan akurasi manusia**. Agent reference bukan gold label. Lima holdout tidak dibuka. Seluruh 49 kartu yang dipilih memenuhi audit budget/avoid/score/bukti alasan. [Perbandingan](comparison.json), [receipt](receipt.json).

Revisi ini memperbaiki pemahaman pertanyaan dan ketepatan pemakaian data. Target 80–90% tetap memerlukan evaluasi relevansi independen; tidak dapat dinyatakan tercapai dari kode, riset internet, atau jumlah deskripsi yang lengkap. Tidak ada perubahan bobot otomatis dari feedback.

[ADR-040](../../../architecture/decisions/ADR-040-adaptive-preference-questions.md) merekam alasan percabangan, batas bukti dan kompatibilitas.

## Rilis dan recovery

Kode belum digabung ke main. Produksi tetap beta v1.1/pref-02.3 di 1a33c70. Release revisi membutuhkan preview/rebuild profil dengan parser baru; deploy kode saja saat flag masih aktif akan membuat fingerprint lama tidak layak dan hasil kosong. Urutan rinci ada di [runbook koreksi](../../../runbooks/FRAGRANCE_QUESTION_REVISION.md). Tidak menambah tabel, tidak mengubah schema existing, dan tidak menulis ulang produk. Rollback flag menampilkan tes legacy dan menjaga feedback; revert ke beta lama memerlukan rebuild parser lama.

Langkah berikut masih PREF-03: review patch/hasil dan persiapan rilis koreksi setelah izin yang sesuai. PREF-04 tidak dimulai otomatis. Tidak meminta Owner mengisi ulang ratusan atribut; review sumber dapat difokuskan pada kandidat yang muncul dan kasus ambigu.

Fixture SQLite khusus pengujian beserta actor sintetis dan hasil/feedback lokal sudah dihapus setelah verifikasi. Server preview dihentikan, tab sementara ditutup, dan viewport browser direset. Bukti screenshot tetap disimpan.
