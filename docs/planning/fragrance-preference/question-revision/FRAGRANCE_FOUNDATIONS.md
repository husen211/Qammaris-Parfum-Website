# Dasar aroma dan revisi tes preferensi Qammaris

Riset 10 Oktober 2026. Scope: koreksi PREF-03 setelah Owner mencoba beta. Dokumen disusun sebelum perubahan logika. Sumber internet dipakai untuk memahami istilah, bukan mengisi ulang katalog atau mengimpor klaim performa produk. Riset ini bukan pengujian aroma di kulit dan bukan bukti akurasi 80–90%.

## Notes, karakter, dan bukti

Notes menjelaskan unsur kesan aroma. Top/heart/base membantu membaca perkembangan komposisi; seluruh lapisan perlu dipertimbangkan. Daftar notes bukan resep, persentase bahan, atau hasil ukur ketahanan. Komposisi perlu dicoba dan diberi waktu berkembang. [The Perfume Society, FAQ](https://perfumesociety.org/discover-perfume/an-introduction/faq/).

Qammaris memakai dua tingkat bukti: karakter yang disebut secara faktual dalam deskripsi, lalu petunjuk notes. Keduanya harus tercatat asalnya. Nama produk, cerita pemasaran, dan satu note tidak otomatis menentukan karakter dominan. Sinonim dideduplikasi; istilah asing yang belum dikenali tetap masuk review.

## Kamus keluarga aroma

Ini pengelompokan praktis Qammaris, bukan salinan persis Fragrance Wheel. Ringkasan citrus sampai amber mengacu pada [Michael Edwards, Fragrance Wheel](https://www.fragrancesoftheworld.com/FragranceWheel). Contoh notes adalah petunjuk facet; tidak otomatis menetapkan pemakaian, kemanisan, atau performa seluruh parfum.

| Pilihan | Kesan dan contoh petunjuk | Penerapan yang dibolehkan |
|---|---|---|
| Citrus/segar | Jeruk: bergamot, lemon, mandarin | Kandidat untuk penyuka kesegaran jeruk; bukan sinonim seluruh aroma fresh |
| Aquatic | Air/laut/udara basah: marine accord, calone | Cari bukti aquatic yang jelas; nama “blue”/“aquatica” saja tidak cukup |
| Hijau/herbal | Daun, rumput, herba: violet leaf, mint | Untuk preferensi hijau/aromatik; teh atau lavender dapat punya facet lain |
| Buah | Buah non-citrus: apel, berry, peach | Tidak otomatis dessert atau sangat manis |
| Bunga | Rose, jasmine, bouquet | Tidak otomatis hanya wanita atau hanya manis |
| Kayu | Cedar, sandalwood, vetiver, patchouli | Tidak otomatis kering, non-manis, atau tahan seharian |
| Amber/resin | Resin, hangat, balsamic; benzoin/incense | Tidak identik gourmand; perlu lihat karakter komposisi |
| Gourmand | Kesan makanan/dessert: karamel, gula, cokelat, susu | Bedakan kesan makanan dengan tingkat manis; tidak semua vanilla otomatis dessert dominan |
| Oud | Nuansa agarwood/oud | Jangan menebak dari “Arabic”; tetap gunakan bukti notes/karakter |
| Leather/asap | Kesan kulit, smoky, tobacco accord | Leather dan smoke dikelompokkan untuk UI V1; bukan bahan yang selalu sama |
| Musk | Facet musky, bisa clean/powdery/animalic | Tidak otomatis lemah atau dekat kulit |
| Powdery/bedak | Kesan bedak, iris/orris atau accord powdery | Tidak otomatis manis; beda dari aroma bunga secara umum |

Contoh primer gourmand yang memiliki sisi manis **dan pahit** adalah cocoa accord Fève Gourmande. Ini mendukung pemisahan kategori gourmand dari skala kemanisan, bukan transfer atribut produk tersebut ke SKU Qammaris. [Guerlain, Fève Gourmande](https://www.guerlain.com/us/en-us/p/lart-la-matiere-feve-gourmande-eau-de-parfum-P014799.html).

Contoh bahan musk yang oleh produsennya disebut kuat dan powdery adalah Velvione. Facet musk/powdery bisa beririsan; nama facet tidak mengukur projection parfum jadi. [Givaudan, Velvione](https://www.givaudan.com/fragrance-beauty/fragrance-ingredients-business/fragrance-molecules/velvionetm).

Aquatic bisa dibentuk dengan bahan sintetis dan dikombinasikan dengan citrus, herba, kayu, musk, atau bunga. Preferensi aquatic tidak membuktikan semua kandidat “tidak manis”. [The Perfume Society, aquatic](https://perfumesociety.org/dive-into-aquatic-fragrances/).

## Konteks dan SPL

Istilah operasional: **sillage** adalah jejak aroma yang tertinggal saat bergerak; **projection** adalah bagaimana aroma tercium di sekitar pemakai; **longevity** adalah lamanya aroma masih tercium. Ini bukan tiga nama untuk atribut yang sama. Referensi jejak sillage adalah wawancara perfumer [Juliette Karagueuzoglou](https://perfumesociety.org/perfume-nose/juliette-karagueuzoglou/); pemisahan ukuran adalah definisi operasional Qammaris, bukan hasil pengukuran SKU.

Lingkungan panas dapat membuat aroma terasa lebih intens; pemakaian siang/malam tidak punya aturan universal. Kulit, formula, dan kondisi pemakaian ikut memengaruhi hasil. [The Perfume Society, FAQ](https://perfumesociety.org/discover-perfume/an-introduction/faq/). Maka **outdoor + siang → fresh** adalah panduan eksplorasi yang masuk akal, bukan hard filter. Jawaban suka/hindari tetap menang. AC tidak wajib manis; malam tidak wajib oud.

Ketahanan dipengaruhi formula, volatilitas, konsentrasi dan bahan; ketahanan panjang bukan ukuran tunggal kualitas. [Les Bains Guerbois, Tenue ≠ qualité](https://lesbainsguerbois.com/tenue-parfum-fixateurs-qualite/). EDP/Extrait saja tidak membuktikan jumlah jam atau projection. Informasi faktual yang belum ada tetap kosong, bukan diisi berdasarkan keluarga aroma.

## Audit katalog aktual, baca saja

Snapshot publik produksi 2026-10-10T04:42:33Z, release `1a33c707830f88d845cf433849cf329345890e50`, 381 produk. Hash sumber publik: `92186947b4f1b9ccba1cfc24ed17d272c1ae17cf9b44d8bf329be9a735fedd9f`. Export memakai transaksi MySQL READ ONLY; tidak membaca pelanggan, jawaban atau credential. Parsing dilakukan lokal, tanpa perubahan produk/profil produksi.

Pembaca sebelum revisi `pref-02.3`, fingerprint `e5d6c2e86bafd83fc54df5925306cca8e98d4cc2f7b627264856460ae608bed8`:

| Atribut dapat dibaca jelas | Produk |
|---|---:|
| Kemanisan | 28 |
| Projection/intensitas | 59 |
| Ketahanan dengan rentang jam | 119 |
| Konteks pemakaian | 128 |

Ini coverage pembaca faktual, bukan hasil uji sensorik. Petunjuk teks yang belum diparse bukan otomatis data salah. Harga publik saat snapshot Rp69.000–889.000; preset di atasnya tetap berguna untuk katalog berikutnya, tanpa menyatakan ada produk di setiap range.

| Aroma target terbaca | Produk, dapat beririsan |
|---|---:|
| Citrus | 222 |
| Aquatic | 28 |
| Hijau/herbal | 142 |
| Buah | 171 |
| Bunga | 230 |
| Kayu | 242 |
| Gourmand | 74 |
| Amber/resin | 138 |
| Oud | 17 |
| Leather/asap | 38 |
| Musk | 162 |
| Powdery | 14 |

[Indeks katalog](catalog-family-index.json) menyimpan ID, URL, fingerprint, bukti keluarga, fakta performa, dan atribut kosong untuk semua produk. Indeks adalah daftar kandidat untuk evaluasi, bukan daftar rekomendasi yang selalu cocok. Ranking tetap bergantung seluruh jawaban dan metadata katalog terbaru.

Kasus Owner: **Rayhaan Aquatica EDP 100 ml, ID 498**. Notes katalog memuat coconut milk, sugar cane, rum, citrus dan bunga. Label aroma memuat “Sweet”; ketahanan tertulis 8 jam. Maka label aquatic/fresh tidak menjamin tanpa manis. Favorit ini tetap petunjuk sekunder saat customer secara eksplisit mencari aroma lain. Ini pembacaan katalog Qammaris, bukan verifikasi formulasi brand. [Produk katalog](https://qammarisparfum.id/products/rayhaan-aquatica-edp-100-ml).

## Batas evaluasi dan pekerjaan data

- Pertahankan 30 skenario acuan: 20 normal/10 batas, dengan lima normal held out. Jangan menyetel bobot dari holdout.
- Target 18/20 mempunyai ≥1 pilihan relevan (90%) dan 16/20 mempunyai ≥2 (80%) adalah penerimaan skenario yang dinilai manusia. Bukan janji 80–90% setiap pelanggan akan menyukai aroma setelah mencium.
- Label agent dan tes aturan tidak menggantikan penilaian Owner/staf. Feedback website mengukur relevansi menurut pengguna, bukan bukti sensorik atau pembelajaran otomatis.
- Review terarah cukup difokuskan pada sumber ambigu dan produk yang masuk hasil; tidak meminta Owner mengisi ulang 381 produk. Tidak menebak non-manis saat kemanisan belum diketahui.
- Belum ada pilot/offline QR pada koreksi ini. Pengujian langsung setelah mencium tetap V2.

Semua sumber diakses 10 Oktober 2026. Klaim spesifik brand/bahan tidak digeneralisasikan ke seluruh katalog; rekomendasi desain di dokumen ini diberi status aturan produk, bukan fakta ilmiah yang menjamin preferensi individu.
