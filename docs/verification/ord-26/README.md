# ORD-26: Alamat dulu, baru pilih pengiriman (lokal, 2026-11-16)

Bagian 1: checkout website. Dicek di laptop (`localhost`, Biteship kunci uji) dengan keranjang uji 1 produk; tidak ada pesanan yang dibuat. Produk lokal #87 sementara ditandai Tersedia untuk membuka checkout, lalu dikembalikan.

| Langkah | Gambar |
| --- | --- |
| Sebelum (HP): "Metode pengiriman: Dikirim ke alamat / Ambil di toko" ditanya dulu, lalu "Titik antar — untuk Palu" sebelum kecamatan | [before-mobile-checkout](before-mobile-checkout.jpg) |
| Sesudah (HP): langsung Alamat pengiriman, mulai dari kecamatan atau kota tujuan | [after-mobile-address-first](after-mobile-address-first.jpg) |
| Tujuan Mantikulore (Palu): titik antar muncul | [after-mobile-palu-asks-pin](after-mobile-palu-asks-pin.jpg) |
| Setelah titik: GrabExpress, GoSend, J&T dengan harga, lalu Ambil di toko dengan alamat toko | [after-mobile-options](after-mobile-options.jpg) |
| Ambil di toko dipilih: kotak alamat tertutup, ongkir "Tidak ada", pengiriman = pickup | [after-mobile-pickup](after-mobile-pickup.jpg) |
| Desktop, tujuan Menteng (Jakarta): tanpa titik dan tanpa kurir instan; J&T Rp26.500 (dari Rp38.000) | [after-desktop-jakarta](after-desktop-jakarta.jpg) |

Tes: `CheckoutShippingQuoteTest` (urutan halaman, Ambil di toko dikirim sebagai pilihan pengiriman tanpa alamat), `tests/js/*.mjs`.
