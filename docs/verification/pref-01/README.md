# PREF-01 — Audit katalog dan paket acuan kualitas

2026-10-09. **IN_REVIEW**: atas permintaan Owner yang tidak punya waktu, Codex sudah mengisi30 proposal review berbasis katalog. Isian tersedia; penerimaan independen Owner/staf tetap belum dilakukan. PREF-02–04 belum dimulai. Website dan mesin lama tidak berubah.

## Bukti baca saja

Capture `2026-10-09T09:54:52+00:00` pada release `71a4c2e66c7405b48fd8afe3a77bfcf3a12bb213`. Export operator memakai transaksi MySQL enforced READ ONLY, hanya SELECT katalog publik dan baseline mesin existing, lalu rollback. Tidak membuat file server, login, record, migrasi atau perubahan katalog. Source snapshot lokal tidak dikomit; hasil audit mencantumkan fingerprint/hash, tidak menyimpan description penuh atau media keys. Hash menunjukkan integritas/versi, bukan tanda tangan keaslian server.

| Pemeriksaan | Hasil |
|---|---:|
| Produk publik / deskripsi / seluruh lapisan notes terisi | 378 / 378 / 378 |
| Offer tunggal dengan harga/ukuran invalid | 0 |
| Produk dengan placeholder notes | 25 |
| Bentuk penulisan notes / entri notes | 750 / 3306 |
| Entri dikenali / sebagian / belum dipetakan / placeholder | 3237 / 7 / 26 / 36 |
| Istilah residual perlu review | 29 |
| Petunjuk teks sebaran / ketahanan | 89 / 198 |
| Ready / Habis | 260 / 118 |
| Kelompok notes identik, identitas belum diverifikasi | 19 |

“Dikenali” hanya kecocokan grup pencarian eksplisit, bukan validasi bahan, keluarga aroma, kemanisan atau performa. Modifier/jenis bahan yang dikelompokkan tidak dianggap bahan identik. Placeholder/istilah asing tidak ditebak. Petunjuk performa hanya pencarian teks; kutipan tetap menunggu pemisahan fakta/promosi/negasi/konflik. EDP/Extrait tidak membuktikan ketahanan dan vanilla tidak otomatis berarti sangat manis.

## Pengisian yang didelegasikan ke Codex

[Agent reference](agent-reference.json) mengisi20 profil normal dengan40 kandidat utama, satu alternatif di N20 (Sorrento50ml, Rp259.000; +3,6% pada budgetRp250.000), pilihan penolakan, raw-note/description evidence dan source fingerprint. Sepuluh fixture batas sudah diperiksa terhadap kontrak plan, **bukan dijalankan pada mesin baru**. Reviewer selalu Codex; `owner_review` tidak dipalsukan menjadi reviewed.

Harga/status mengikuti capture bertanggal; source/catalog hash tetap. `acceptable_empty_proposal=false` berarti ada kandidat aroma dalam snapshot, bukan bukti setiap kebutuhan performa terpenuhi. Field penilaian kosong untuk seluruh preferensi tetap unknown. Sebaran/ketahanan/tingkat manis yang belum punya bukti tetap disebutkan sebagai keterbatasan. Penolakan preferensi lunak tidak dijadikan hard filter global. N16–N20 tetap reserved from tuning; proposal dari analyst/catalog yang sama tidak menjadi benchmark sensoris independen.

[Empat potensi konflik sumber](agent-semantic-review.json): Shiyaaka Silver455 (narasi versus notes), Royal Blend887 (rentang ketahanan dan komposisi), Blue Point Him668 (peruntukan narasi versus field), Rhea834 (bukaan narasi versus notes). Ini temuan yang perlu review, bukan pembetulan fakta atau izin rewrite katalog.

Verifikasi pengisian:20+10 kasus lengkap,41 kandidat punya ID/source fingerprint/budget yang valid, seluruh kutipan benar-benar ada dalam sumber, receipt hashes konsisten, reviewer/hold-out/pending-human dan not-started engine status dipertahankan. Tidak ada skor/persentase akurasi atau tes sensory yang dikarang. Batas kualitas penerimaan awal belum dipenuhi oleh proposal ini.

## Paket yang dapat diperiksa

- [REVIEW Owner/staf](../../planning/fragrance-preference/REVIEW.md) dan [30 skenario](../../planning/fragrance-preference/scenarios.json): 20 normal, 10 fixture batas, N16–20 hold-out. **Semua label pending**, semua tes mesin baru not started.
- [Ringkasan dan hash receipt](catalog-summary.json), [378 indeks produk CSV aman](product-index.csv), [profil pembacaan notes per produk](product-review.json), [750 bentuk notes dan provenance](note-vocabulary.json), [placeholder/istilah/identitas ambigu](ambiguities.json), [kutipan deskripsi kandidat](description-review.json).
- [20 baseline mesin lama](legacy-baseline.json) memakai pemetaan jawaban mendekati enam pertanyaan lama. Budget/hindari/families/performa/favorit V1 tidak dapat direpresentasikan; baseline bukan label benar atau ukuran akurasi.
- [Kamus proposal](../../../resources/data/fragrance-note-aliases.json), [normalizer](../../../app/Support/FragranceNoteNormalizer.php), [runbook audit](../../runbooks/FRAGRANCE_PREFERENCE_AUDIT.md), [plan](../../planning/QAMMARIS_FRAGRANCE_PREFERENCE.md), [ADR-037](../../architecture/decisions/ADR-037-fragrance-preference-review-first.md).

## Verifikasi dan batas

Dua belas tes / 77 assertions (11 unit dan regresi publication quiz existing) lolos sebelum pemeriksaan akhir: exact alias/compound/boundary, dedup per layer, placeholder/opaque/invalid data, collision, input hash, CSV formula-safe, output-existing/cleanup, dan kontrak 30 skenario pending. Hasil pemeriksaan terakhir serta CI dicatat di PR; tidak mengklaim tes ranking/feedback/browser yang belum diimplementasi.

Tidak ada UI/JS/schema/DB/media/dependency/credential change; screenshot before/after tidak berlaku. Tidak ada prosentase akurasi, hasil kalibrasi, pasangan ukuran terverifikasi atau acceptance Owner yang dikarang. Draft PR tidak untuk merge/deploy sebagai rilis V1. Rollback tahap ini cukup membatalkan file tooling/data/docs baru; mesin publik tetap lama. Tabel rekomendasi/flag akan dibuat pada fase berikutnya setelah acuan diterima.

Berikutnya yang diperlukan adalah review manusia, bukan deployment. Sesudah label dan kamus/bukti dibekukan, PREF-01 dapat diterima; PREF-02 memerlukan lanjut eksplisit sesuai aturan satu item aktif.
