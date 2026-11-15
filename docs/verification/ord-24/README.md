# ORD-24: tempel Sharelok / koordinat, peta Google siap (lokal, 2026-11-15)

Pesanan sintetis QAM-0029 dan admin sintetis sementara; keduanya beserta pelanggan sintetisnya dihapus setelah pengecekan (pesanan lokal kembali 21).

- Admin Data penerima, HP 375×812: menempel `https://maps.google.com/maps?q=-0.8877%2C119.8899&z=17&hl=en` langsung menampilkan "Titik dari tempelan: -0.8877, 119.8899" ([after-admin-paste-mobile](after-admin-paste-mobile.jpg)). Setelah Simpan, `location_url` tersimpan sebagai `https://www.google.com/maps?q=-0.887700,119.889900` dan `deliveryPin()` terbaca.
- Admin, desktop, tanpa `GOOGLE_MAPS_BROWSER_KEY`: peta tetap OpenStreetMap (Leaflet). Kotak cari menerima `-0.8951, 119.8561` → "Titik dari koordinat atau link yang ditempel" → peta pindah ke titik itu. Console tanpa error selain font dev server.
- Belum diuji: peta dan pencarian Google, karena key baru ada setelah billing Google Cloud aktif.

Tes: `MapsLink` lewat form admin (koordinat, link `%2C`, Sharelok pendek yang di-redirect ke `!3d…!4d…`, Sharelok tak terbaca, di luar Palu, teks bebas, host bukan Google, Buat pesanan) dan `pointFromText` di `tests/js/checkout-shipping.test.mjs`.
