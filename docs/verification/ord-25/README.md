# ORD-25: Perjalanan pesanan website yang jelas (lokal, 2026-11-15)

Dicek pada pesanan website lokal yang sudah ada (data uji): QAM-0020 (J&T, 1 pcs, bisa langsung bayar) dan QAM-0021 (J&T, 2 pcs, perlu cek stok). Hanya dibaca, tidak ada data yang diubah.

| Kasus | Sebelum | Sesudah |
| --- | --- | --- |
| J&T 1 pcs, HP | [before-jnt-1pcs-mobile](before-jnt-1pcs-mobile.jpg): "Dikonfirmasi toko — Admin mengonfirmasi stok, ongkir, dan pembayaran lewat WhatsApp", padahal kotak atas meminta bayar sekarang | [after-jnt-1pcs-mobile](after-jnt-1pcs-mobile.jpg): "Pembayaran — Bayar lewat transfer atau QRIS, lalu kirim bukti bayar di WhatsApp. Admin mengecek pembayaran dan stok." |
| J&T 1 pcs, desktop | – | [after-jnt-1pcs-desktop](after-jnt-1pcs-desktop.jpg) |
| J&T 2 pcs, HP | (teks lama sama dengan di atas) | [after-jnt-2pcs-mobile](after-jnt-2pcs-mobile.jpg): "Dikonfirmasi toko — Admin mengecek stok lewat WhatsApp. Setelah itu Anda bisa membayar." |

Tes: `CheckoutShippingQuoteTest` (1 pcs langsung bayar, 2 pcs setelah dikonfirmasi, ongkir dihitung staf).
