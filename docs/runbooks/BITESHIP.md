# Biteship (ongkir otomatis di checkout, ORD-13)

Checkout menghitung ongkir GoSend, GrabExpress, dan J&T lewat Biteship. Keputusan dan batasnya: [ADR-057](../architecture/decisions/ADR-057-checkout-shipping-quotes-biteship.md).

## Akun

- Paket **Starter** (gratis): 100 pengiriman/bulan, 3 kurir aktif, 2 lokasi asal. API dibayar per permintaan (cek ongkir Rp5, lacak Rp10, peta Rp2) dari saldo Biteship.
- Kurir terpasang: **Gojek, Grab, J&T**. Alamat jemput: toko Qammaris, Palu Barat, pin di Jl. Sis Aljufri (kode area Biteship 94221).
- Verifikasi akun personal diajukan 2026-11-11. Pemesanan kurir lewat API (tahap B) baru bisa setelah disetujui.
- Pemilik akun: Owner. Agen tidak membuat akun, tidak menyalin kunci, dan tidak mengubah saldo.

## Kunci API

- Dibuat Owner di **Integrasi → Platforms → Kunci API → Tambah Kunci API** (centang Rates, Order, Tracking). Mode Testing menghasilkan kunci `biteship_test.…`; mode live menghasilkan `biteship_live.…`.
- Simpan hanya di `.env` server atau `.env` lokal: `BITESHIP_API_KEY=…`. Jangan masuk Git, chat, log, atau screenshot.
- Untuk menempel tanpa menampilkan kunci di layar, gunakan prompt tersembunyi (contoh skrip lokal 2026-11-11: `Read-Host -AsSecureString`, lalu tulis satu baris ke `.env` tanpa BOM).
- Rotasi: buat kunci baru, ganti di `.env`, cek checkout, lalu **Buang Kunci** yang lama di dashboard.

## Menyalakan

1. `.env`: `BITESHIP_API_KEY=biteship_live.…` dan `ORDERS_SHIPPING_QUOTES=true` (butuh `ORDERS_WEBSITE_CHECKOUT=true` dan `ORDERS_V2_ENABLED=true`).
2. `php artisan config:clear` (atau rilis biasa).
3. Cek checkout: pilih Kurir instan, tentukan titik, harga GoSend/GrabExpress muncul; pilih J&T, cari kota, harga muncul.
4. Isi saldo Biteship secukupnya untuk biaya API.

Matikan kapan saja dengan `ORDERS_SHIPPING_QUOTES=false`: checkout kembali ke "ongkir dikonfirmasi staf".

## Kalau ada masalah

- Customer melihat "Ongkir otomatis sedang tidak bisa dihitung": Biteship tidak menjawab (kunci salah/kedaluwarsa, saldo habis, gangguan). Pesanan tetap masuk dengan ongkir dikonfirmasi admin. Cek kunci dan saldo di dashboard Biteship.
- "Kurir instan belum menjangkau titik ini": GoSend/GrabExpress tidak melayani titik itu (jarak lebih dari sekitar 20 km atau di luar area). Customer bisa memilih J&T.
- Pengaturan diskon, berat per produk, titik toko, dan area layanan ada di `config/orders.php` → `shipping_quotes`.
