# Biteship (ongkir otomatis ORD-13, pesan kurir ORD-14)

Checkout menghitung ongkir GoSend, GrabExpress, dan J&T lewat Biteship; staf memesan kurir instan dari halaman pesanan admin. Keputusan dan batasnya: [ADR-057](../architecture/decisions/ADR-057-checkout-shipping-quotes-biteship.md), [ADR-058](../architecture/decisions/ADR-058-admin-courier-booking-biteship.md).

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

Matikan kapan saja dengan `ORDERS_SHIPPING_QUOTES=false`: checkout kembali ke "ongkir dikonfirmasi staf" dan tombol pesan kurir Biteship hilang.

## Pesan kurir dari admin (ORD-14)

1. `.env` server: `BITESHIP_WEBHOOK_SECRET=` teks acak minimal 32 karakter (huruf, angka, `-`, `_`), misalnya hasil `php -r "echo bin2hex(random_bytes(24));"`. Opsional: `BITESHIP_ORIGIN_NAME`, `BITESHIP_ORIGIN_PHONE`, `BITESHIP_ORIGIN_ADDRESS` (kontak jemput untuk driver; kosong = nama, telepon, dan alamat di Info Toko).
2. Dashboard Biteship → **Pengaturan → API → Webhook URL**: isi `https://qammarisparfum.id/integrations/biteship/webhook/<rahasia>`. Biteship mengirim tes dengan isi kosong; situs menjawab 200. Jangan menyalin URL ini ke chat atau screenshot.
3. Cek: pesanan website kurir instan yang lunas dan dipacking menampilkan **Pesan kurir sekarang**. Setelah memesan, status "Mencari driver" dan tombol **Lacak driver** muncul; halaman customer menampilkan kurir yang sama.
4. Saldo Biteship harus cukup untuk ongkir kurir; bila kurang, admin menampilkan alasan dari Biteship dan pesanan tidak berubah.

Mode tes: kunci `biteship_test.…` membuat pesanan tes tanpa memotong saldo dan tanpa driver sungguhan (driver tes "John Doe"). Status pesanan tes dimajukan dari dashboard Biteship (Mode Testing aktif): **Shipping → Pesanan**, kolom Aksi, tombol kuning **Update Status** pada baris pesanan (misalnya `QAM-0022-1`), lalu pilih status berikutnya (Start picking up, lalu seterusnya, atau Cancel). Biteship tidak bisa mengirim webhook ke localhost, jadi setelah itu tekan **Perbarui status** di halaman pesanan admin. Dicek 2026-11-11: Teralokasi di dashboard terbaca di admin sebagai "Driver ditemukan".

## Kalau ada masalah

- Customer melihat "Ongkir otomatis sedang tidak bisa dihitung": Biteship tidak menjawab (kunci salah/kedaluwarsa, saldo habis, gangguan). Pesanan tetap masuk dengan ongkir dikonfirmasi admin. Cek kunci dan saldo di dashboard Biteship.
- "Kurir instan belum menjangkau titik ini": GoSend/GrabExpress tidak melayani titik itu (jarak lebih dari sekitar 20 km atau di luar area). Customer bisa memilih J&T.
- Pengaturan diskon, berat per produk, titik toko, dan area layanan ada di `config/orders.php` → `shipping_quotes`.
- "Kurir belum terpesan: …": alasan dari Biteship (saldo, akun belum terverifikasi, kurir menolak). Perbaiki di dashboard, lalu tekan Pesan kurir sekarang lagi.
- "Biteship tidak menjawab. Cek dashboard Biteship sebelum memesan ulang": jawaban tidak sampai, padahal pesanan mungkin sudah dibuat. Cari kode pesanan (misalnya `QAM-0023-1`) di dashboard Biteship sebelum memesan ulang supaya tidak dobel.
- Status tidak berubah: webhook tidak sampai (URL/rahasia salah, server mati). Tekan **Perbarui status**: admin membaca status terbaru langsung dari Biteship. Untuk kurir Biteship tidak ada tombol "Sudah diserahkan ke kurir"; penyerahan tercatat dari status Biteship.
