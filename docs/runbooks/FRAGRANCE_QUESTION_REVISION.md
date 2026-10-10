# Rilis koreksi pertanyaan preferensi v1.2

Ini persiapan operasional, **belum dieksekusi di produksi**. [Bukti dan batas kualitas](../verification/pref-03/question-revision/README.md). Ikuti [runbook beta](FRAGRANCE_PREFERENCE_BETA.md) untuk backup/cutover dan izin; gunakan preview/aplikasi profil existing, actor admin existing, bukan SQL mass update.

1. Pastikan izin rilis koreksi dan rebuild tabel profil rekomendasi, CI atas commit final, backup/recovery terverifikasi, serta beta lama/legacy masih bisa dipulihkan. Tidak memerlukan migrasi tambahan.
2. Matikan `FRAGRANCE_PREFERENCE_ENABLED` dan refresh config cache untuk sementara menampilkan legacy. Tabel hasil/feedback tetap ada; jangan menghapus jawaban lama.
3. Deploy revision melalui workflow existing setelah staging. Gunakan actor admin existing untuk preview rebuild. Verifikasi source fingerprint, jumlah produk publik terkini dan stale overrides. Preview ulang bila fingerprint berubah.
4. Apply tepat fingerprint preview ke **tabel profil/revision** saja. Rebuild yang sama harus menghasilkan 0 perubahan pada replay. Catalog IDs/slugs/notes/deskripsi/harga/media/publication tidak ditulis. Koreksi manual yang terikat parser lama tetap memerlukan review, tidak diaktifkan diam-diam.
5. Verifikasi seluruh produk yang memang layak memiliki profil source/parser terkini, jalur wizard/hasil/feedback/edit/recalculate, status/error dan rate limits. Hasil lama harus tetap punya akses/feedback masing-masing; pertanyaan time yang belum ada tidak diisi dengan tebakan.
6. Aktifkan flag/cache, lakukan smoke live secukupnya dengan hasil operasional yang diatribusikan; jangan menyebutnya label kualitas manusia. Periksa 390/1440, genuine touch/iPhone Safari bila tersedia dan error log yang sudah disanitasi.
7. Jika gagal, flag `false` mengembalikan tes enam pertanyaan. Pertahankan tabel/data baru. Jika ingin beta v1.1 kembali, deploy code lama lalu preview/rebuild parser lama sebelum flag `true`, sebab fingerprint parser berbeda. Jangan memulihkan dump produksi di atas jawaban pelanggan baru tanpa rencana terpisah.

Dokumentasikan release SHA, CI, snapshot/preview/apply/replay, backup receipt, actor ID dan hasil smoke. Tidak memulai PREF-04 atau mengklaim 80–90% dari smoke ini.
