# Koreksi pertanyaan PREF-03 setelah uji Owner

10 Oktober 2026. Dasar: [riset aroma](FRAGRANCE_FOUNDATIONS.md), audit beta, dan feedback Owner. Dokumen dibuat sebelum implementasi. Scope tetap PREF-03; tidak memulai PREF-04 atau menganggap kalibrasi manusia selesai.

## Bahasa pertanyaan dan jawaban

| Pertanyaan | Pilihan dan keputusan |
|---|---|
| Berapa budget untuk satu botol? | Sampai Rp100rb; Rp100–200rb; Rp200–300rb; Rp300–500rb; Rp500rb–1jt; Rp1jt ke atas; angka maksimal sendiri; budget tidak dibatasi. Range benar-benar mempunyai batas bawah/atas, bukan label palsu untuk maksimum saja. |
| Paling sering dipakai untuk apa? | Harian; Kantor/kuliah; Acara/date. Hapus overlap santai/bebas dari pertanyaan baru. |
| Biasanya dipakai di mana? | Ruangan ber-AC; Luar ruangan; Dalam dan luar ruangan. |
| Biasanya dipakai kapan? | Pagi/siang; Sore/malam; Keduanya. Menggunakan bukti pemakaian faktual katalog; tidak menganggap siang harus citrus atau AC harus manis. |
| Aroma apa yang kamu suka? | Maksimal tiga keluarga aroma; Belum tahu. |
| Aroma apa yang tidak kamu suka? | Keluarga aroma dan pilihan terpisah **Aroma manis**. Gourmand diberi contoh dessert. Tidak menyamakan seluruh rasa manis dengan dessert. |
| Kamu suka aroma yang manis? | Tidak manis; Sedikit manis; Manis sedang; Manis terasa jelas; Tidak punya pilihan khusus. **Tidak ditanyakan jika Aroma manis atau Gourmand sudah dihindari.** |
| Mau wanginya tercium seperti apa? | Saat orang dekat; Oleh orang di sekitar; Lebih kuat dan mudah tercium; Belum tahu. Helper tidak menggunakan jargon SPL. |
| Saat memilih parfum, ketahanan jadi pertimbangan? | Ya, cari yang punya info tahan lama; Aroma yang cocok lebih penting; Belum tahu. Menghapus “ingin beberapa jam” dan janji seharian; pilihan positif mempertimbangkan bukti rentang ≥8 jam, bukan menjamin performa. |
| Mau cari parfum pria, wanita, atau unisex? (opsional) | Pria; Wanita; Unisex; Tidak membatasi. Peruntukan hanya pertimbangan ringan. |
| Ada parfum di katalog kami yang pernah kamu suka? (opsional) | Pencarian nama; lewati. Tidak mengganti selera yang dipilih sekarang. |

Kalau tidak suka Aroma manis, sweetness efektif `none`; kandidat dengan kemanisan manis yang terdeteksi disaring sebelum ranking. Data kosong tidak menjadi bukti non-manis dan selalu diberi keterbatasan. Jika hanya menghindari Gourmand, sweetness tidak ditanyakan/efektif `any`: dessert tetap disaring, tetapi tidak diam-diam menyatakan customer menolak seluruh aroma buah/bunga yang manis. Ringkasan menjelaskan bahwa kemanisan tidak dipilih ulang karena jawaban hindari sudah dipakai. Customer yang tidak suka seluruh manis dapat memilih **Aroma manis** pada pertanyaan hindari.

## Keadaan dan kontrak server

- Budget bebas: input abu-abu, disabled, placeholder dan status jelas “Budget tidak dibatasi”; range tidak aktif. Input angka sendiri kembali ke mode maksimum tanpa batas bawah. Preset range mempunyai aria-pressed. +10% cukup disebut pada alternatif hasil, bukan pada pertanyaan.
- Progress menghitung pertanyaan aktif. Panel yang dilewati keluar dari tab order/reader/validasi; Back melewatinya, summary tidak meminta jawaban yang tak ditanyakan.
- Stale jawaban manis setelah mengubah pilihan hindari harus dibuang oleh **server** juga. Client saja tidak cukup. Kontradiksi suka/hindari tetap ditolak.
- Draft versi baru 24 jam; draft lama tidak dipindahkan diam-diam bila field semantik berubah. Edit hasil lama memetakan jawaban kompatibel, meminta waktu pemakaian baru sebelum submit. Recalculate hasil lama tetap kompatibel dengan time=`any` tanpa mengubah snapshot/feedback lama.
- Versi pertanyaan dan mesin naik. Perubahan PreferenceAnswers/ProfileBuilder membuat fingerprint parser berubah; rilis perlu preview/rebuild profil baru sebelum flag aktif. Tidak menambah migrasi atau field wajib form katalog.
- Harga/status, keamanan cookie 7 hari, CSRF, rate limit, akses lintas browser dan feedback idempotent tetap berlaku.
- Tiga pilihan utama dipertahankan; alternatif keempat bersyarat ≤110% tetap terpisah. Jangan menambah kartu untuk memenuhi jumlah.

## Koreksi parser terbatas

Tingkat `none` hanya berasal dari klaim faktual seperti “Kemanisan: tidak manis”. “Tidak terlalu manis” bukan “tidak manis”. Vanilla/EDP tidak boleh membentuk tingkat kemanisan/jam secara otomatis. Sillage berdiri sendiri dan tidak lagi dipakai sebagai bukti projection. Konteks pagi/siang dan sore/malam hanya dari kalimat/label pemakaian faktual. “Pagi–malam” berarti kedua waktu.

## Verifikasi yang diperlukan

Regresi: range bawah/atas/+10%; skip sweetness dan server canonicalization; non-manis vs unknown/light/sweet; negasi; sillage/projection; waktu/AC tanpa aturan wajib fresh; favorit tidak menimpa selera; hasil lama dan feedback lama; keyboard, Back/refresh/edit, disabled/retry. Browser 390/1440 dan overflow 320; genuine touch/Safari dilaporkan terpisah. Tes relevan dahulu, build/suite CI setelah stabil. Tidak mengklaim sasaran akurasi dari tes kode.

Rilis koreksi ini belum dilakukan ketika dokumen dibuat. Persiapan lokal/PR berada dalam izin implementasi; cutover produksi dan penulisan profil memakai izin rilis yang sesuai. Rollback flag mempertahankan tabel dan feedback. Revert code perlu rebuild profil dengan parser sebelumnya, sebab fingerprint berubah.

## Hasil implementasi branch

Pertanyaan `preference-v1.2`, mesin `pref-03.2-provisional`, dan seluruh aturan di atas sudah diimplementasikan serta diuji lokal. [Bukti verifikasi](../../../verification/pref-03/question-revision/README.md). Produksi masih beta sebelumnya. Coverage baru 381 produk: kemanisan 28, projection 54, ketahanan 119, konteks 137. Lima label sillage tidak lagi disamakan dengan projection; bertambah sembilan produk dengan konteks waktu terbaca. Tidak ada penyetelan bobot atau pembukaan lima holdout.
