# PREF-03 — UI Multistep Form dari 21st.dev

10 Oktober 2026. Owner meminta adaptasi komponen dan animasinya ke tes existing. Implementasi lokal/PR; **belum mengganti UI produksi**. PREF-03 tetap satu item aktif dan IN_REVIEW; quality gates serta PREF-04/V2 tidak berubah.

## Komponen yang benar-benar dipakai

[Multistep Form oleh arihantcodes_1f7b8c4d](https://21st.dev/@arihantcodes_1f7b8c4d/components/multistep-form), demo4883, diambil melalui MCP `get_component` menggunakan akses paid Owner. Lampiran Owner dibaca; yang berisi form lengkap adalah `demoCode`, sementara `componentCode` berisi utilitas `cn`. [Provenance dan hash sumber](source-provenance.json). Tidak hanya meniru screenshot.

Struktur progress dots/current ring/completed states, progress track, panel rounded, footer Back/Next dan exit-before-enter dipindahkan dari demo ke template Blade dan modul JS existing. Timing sumber: exit200ms dengan x=-50, enter300ms x=50→0/opacity0→1, progress300ms. Kembali membalik arah; tinggi panel mengikuti konten agar footer tidak meloncat. Native Web Animations menjalankan urutan tersebut dan membersihkan height/animation setelah selesai atau cancel. Fallback tanpa API dan reduced motion langsung menampilkan panel.

Palet cream/charcoal/gold dan tipografi website dipertahankan. Enam indikator merupakan **kelompok tahap**, bukan enam pertanyaan: Budget; Pemakaian; Aroma; Karakter; Pilihan; Ringkasan. Counter pertanyaan tetap11 atau10 saat kemanisan dilewati. Mobile memakai enam target ≥44px, label tahap aktif dan counter; desktop menampilkan semua label. Tahap berikut belum dapat diklik; tahap sebelumnya dapat dibuka untuk mengubah jawaban. Native radios/checkboxes, safe text rendering dan route/security contract tetap.

Pilihan terpilih mempunyai checkbox/radio serta border/background gold; budget range sekarang memakai aria-pressed yang juga terlihat. Field bebas disabled tampak abu-abu. Panel tersembunyi inert/hidden, fokus menuju judul setelah transisi. Navigation/submit dikunci selama transisi; saat POST, input dan stage/back terkunci dengan feedback langsung. Kesalahan validasi tetap tampil setelah animasi kembali ke pertanyaan. SVG existing diambil dari komponen icon Qammaris/Lucide dengan attribution21st; check/spinner SVG tidak menjadi emoji.

Sesuai kontrak touch Qammaris, hover hanya warna/outline pada pointer fine. Hover scale dan opsi stagger dari demo tidak diaktifkan; semua pilihan tampil bersama. Tidak menambah dependency, shadcn/framer-motion runtime atau mengubah stack/API/engine. Keadaan hasil/feedback existing tetap tersedia.

## Scope dan recovery

Patch hanya Blade wizard, CSS preference, JS navigation/motion dan regresi JS. Tidak mengubah backend, questions/engine/parser version, bobot, fingerprint, katalog, media, migration atau retensi. UI-only ini **tidak memerlukan rebuild profil atau migrasi**. Produksi tetap rilisPR51/ec39993 sampai izin deploy revisi UI terpisah. Revert patch UI/code release sebelumnya mengembalikan tampilan lama; tabel/hasil/feedback tetap dipertahankan. Flag false tetap rollback keseluruhan tes ke legacy bila diperlukan.

## Verifikasi dan batas

13 HTTP quiz tests/161 assertions di SQLite lulus. Tujuh JS focused tests lulus (tiga percabangan/budget, empat stage/motion/reduced-motion/cancel). Build Vite final lulus; warning bundle About/editor existing tidak berubah. Full CI tercatat pada PR, bukan dianggap lulus sebelum selesai. Tes tidak menilai ulang akurasi sensory.

Browser preview fixture lokal381 produk/satu actor sintetis: sebelum/sesudah390×844 dan1440×900; overflow320×844. Budget validation/free-disabled/range, tahapan sebelumnya, mouse serta Enter, refresh/back, skip dan unskip kemanisan, pencarian favorit/clear, ringkasan/edit, submit hasil dan feedback diperiksa. Receipt mencatat hasil aktual terakhir; screenshot hanya dari UI lokal, foto fixture tidak mengklaim verifikasi media produksi. Browser resize/mouse/keyboard bukan genuine touch/iPhone Safari. Reduced motion/cancel fallback diverifikasi pada tes modul; emulasi OS reduced-motion langsung tidak diklaim.

[Receipt browser](browser-receipt.json), [sebelum mobile](before-390.png), [sesudah mobile](after-390.png), [sebelum desktop](before-1440.png), [sesudah desktop](after-1440.png), [320px](after-320.png), dan [pilihan aroma mobile](after-aroma-390.png). Screenshot final budget diambil setelah animasi selesai; screenshot320 sebelum koreksi padding terakhir tetap membuktikan layout tanpa overflow, sementara transisi final diperiksa lagi pada390/1440. Fixture/database/actor/results/sessions lokal dihapus dan server/tab sementara dihentikan; preview screenshot tetap tersedia. Langkah berikut tetap review UI dan rekomendasi beta dalam PREF-03, kemudian rilis UI dengan izin tersendiri. Tidak menjalankan fase selanjutnya otomatis.
