# ORD-23: pin titik antar untuk pesanan WhatsApp (lokal, 2026-11-15)

Pesanan sintetis QAM-0028 (dibuat oleh akun admin sintetis sementara), Biteship mode tes, Vite dev server. Pesanan, pelanggan, dan akun sintetis sudah dihapus setelah pengecekan (jumlah pesanan lokal kembali 21).

| Layar | Sebelum | Sesudah |
| --- | --- | --- |
| Link pesanan, HP 375×812 | [before-link-mobile](before-link-mobile.jpg): "Lokasi lewat Sharelok", tidak ada peta | [after-link-mobile](after-link-mobile.jpg): kotak **Titik antar** wajib; [kirim tanpa titik](after-link-mobile-required.jpg) ditahan dengan "Tentukan titik antar di peta."; [titik dipilih](after-link-mobile-chosen.jpg) |
| Link pesanan, desktop | – | [after-link-desktop](after-link-desktop.jpg) (Ubah data, titik tersimpan) |
| Admin Data penerima, desktop | [before-admin-desktop](before-admin-desktop.jpg): hanya kolom Link Google Maps | [after-admin-desktop](after-admin-desktop.jpg), [peta](after-admin-map-desktop.jpg) |
| Admin Data penerima, HP | – | [after-admin-mobile](after-admin-mobile.jpg) |
| Admin Buat pesanan → Isi sekarang, HP | – | [after-create-mobile](after-create-mobile.jpg) |

Alur yang dicoba di browser:
1. Customer memilih Pengiriman Dalam Kota, lalu kirim tanpa titik: formulir tidak terkirim, fokus ke tombol peta.
2. Peta → Mantikulore → "Pakai titik ini": status "Titik antar dipilih…", lalu kirim. Pesanan tersimpan `details_received`, `location_url=https://www.google.com/maps?q=-0.865000,119.905000`, `deliveryPin()` terbaca.
3. Admin Data penerima → Ubah titik → Palu Utara → Simpan. `location_url` pindah ke `-0.794981,119.878006`.

Console: hanya 404 font `inter-latin.woff2` dari Vite dev server (sudah ada sebelumnya, tidak terkait).
