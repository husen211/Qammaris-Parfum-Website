# ORD-26: Alamat dulu, baru pilih pengiriman (lokal, 2026-11-16)

## Bagian 1: checkout website

Dicek di laptop (`localhost`, Biteship kunci uji) dengan keranjang uji 1 produk; tidak ada pesanan yang dibuat. Produk lokal #87 sementara ditandai Tersedia untuk membuka checkout, lalu dikembalikan.

| Langkah | Gambar |
| --- | --- |
| Sebelum (HP): "Metode pengiriman: Dikirim ke alamat / Ambil di toko" ditanya dulu, lalu "Titik antar — untuk Palu" sebelum kecamatan | [before-mobile-checkout](before-mobile-checkout.jpg) |
| Sesudah (HP): langsung Alamat pengiriman, mulai dari kecamatan atau kota tujuan | [after-mobile-address-first](after-mobile-address-first.jpg) |
| Tujuan Mantikulore (Palu): titik antar muncul | [after-mobile-palu-asks-pin](after-mobile-palu-asks-pin.jpg) |
| Setelah titik: GrabExpress, GoSend, J&T dengan harga, lalu Ambil di toko dengan alamat toko | [after-mobile-options](after-mobile-options.jpg) |
| Ambil di toko dipilih: kotak alamat tertutup, ongkir "Tidak ada", pengiriman = pickup | [after-mobile-pickup](after-mobile-pickup.jpg) |
| Desktop, tujuan Menteng (Jakarta): tanpa titik dan tanpa kurir instan; J&T Rp26.500 (dari Rp38.000) | [after-desktop-jakarta](after-desktop-jakarta.jpg) |

Tes: `CheckoutShippingQuoteTest` (urutan halaman, Ambil di toko dikirim sebagai pilihan pengiriman tanpa alamat), `tests/js/*.mjs`.

## Bagian 2 dan 3: link pesanan dan admin

Pesanan WhatsApp sintetis QAM-0030 dibuat oleh akun admin sintetis sementara (`e2e-ord26@example.test`), Biteship kunci uji. Pesanan dan akun dihapus setelah pengecekan (jumlah pesanan lokal kembali 21).

| Langkah | Gambar |
| --- | --- |
| Link pesanan (HP): data penerima, lalu Alamat pengiriman mulai dari kecamatan atau kota | [after-link-mobile-address-first](after-link-mobile-address-first.jpg) |
| Mantikulore + titik: GrabExpress Rp24.500, GoSend Rp28.500, J&T Rp5.500, Ambil di toko; kirim tanpa pilihan ditahan ("Pilih salah satu pengiriman di bawah.") | [after-link-mobile-options-required](after-link-mobile-options-required.jpg) |
| GrabExpress dikirim: total Rp827.500 (produk + paperbag + ongkir), cara bayar langsung tampil | [after-link-mobile-pay-now](after-link-mobile-pay-now.jpg) |
| Link desktop (Ubah data): alamat tersimpan terisi, pengiriman dipilih lagi | [after-link-desktop-edit](after-link-desktop-edit.jpg) |
| Admin desktop: "GrabExpress · dipilih customer di link pesanan" | [after-admin-desktop-link-quote](after-admin-desktop-link-quote.jpg) |
| Admin desktop: Hitung ongkir otomatis → pilihan Biteship dengan Pakai | [after-admin-desktop-auto-ongkir](after-admin-desktop-auto-ongkir.jpg) |
| Admin desktop: GoSend dipakai, ongkir Rp28.500 "dihitung otomatis oleh admin"; riwayat "Ongkir dihitung otomatis: GoSend · ongkir Rp 24.500 → Rp 28.500" | [after-admin-desktop-ongkir-saved](after-admin-desktop-ongkir-saved.jpg) |
| Admin HP: langkah Pengiriman "Pesan driver Biteship setelah: ✓ Titik antar · Lunas belum · Packing belum" | [after-admin-mobile-driver-checklist](after-admin-mobile-driver-checklist.jpg) |

Data tersimpan setelah link: `details_received`, `active`, `local_delivery`, ongkir 24500 `added_to_transfer`, quote `grab` `chosen_by=customer_link`, kecamatan Mantikulore 94111, `location_url` titik, kurir `unassigned` oleh toko.

Tes: `BiteshipCourierBookingTest` (link dengan harga, opsi palsu ditolak, Ambil di toko, ongkir oleh admin dengan kecamatan/kode pos; link dengan ongkir manual staf tetap meminta titik; staf menghitung ongkir instan dan J&T kode pos, titik dipindah menghapus harga, daftar syarat driver), `CheckoutShippingQuoteTest`, `OnlineOrderTest`, `AdminOrderV2PagesTest`.
