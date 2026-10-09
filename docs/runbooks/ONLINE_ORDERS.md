# Pesanan Online — cara pakai harian

Untuk Owner/admin dan staf toko. Aturan: [ADR-037](../architecture/decisions/ADR-037-online-order-links-and-tracking.md). Menu admin: **Pesanan Online**.

## Alur baru (V2, setelah `ORDERS_V2_ENABLED=true` disetujui untuk dirilis)

Pesanan yang dibuat setelah saklar dinyalakan memakai alur baru. Pesanan lama tetap memakai alur di bawah sampai selesai.

1. **Buat pesanan** (menu bawah **Buat**):
   - pilih asal chat;
   - pilih produk;
   - cari pelanggan lama (nama/nomor WA) atau centang simpan sebagai pelanggan baru;
   - pilih **Data sudah lengkap** bila nama, HP, cara menerima, dan paperbag sudah jelas di chat. Bila belum, pilih **Kirim link ke customer**.
2. **Halaman pesanan**, kerjakan dari atas:
   - **Pembayaran:** catat nominal + metode + sumber konfirmasi; centang Majoo bila sudah dicatat.
   - **Packing:** isi jumlah yang benar-benar masuk paket untuk setiap barang. Kalau ada yang kurang, catat **Kendala**, jangan konfirmasi packing.
   - **Kirim:** atur siapa memesan kurir, tandai kurir dipesan/tiba, lalu **Sudah diserahkan**. Untuk J&T: pickup diminta → QR → **Sudah dipickup J&T** (sekaligus tercatat diserahkan). Resi boleh menyusul.
   - **Keep:** untuk pesanan yang disimpan dulu. Tandai stok sudah dipisahkan; perpanjang atau lepas saat lewat batas.
3. Pesan grup bisa disalin dari **Link & WA**. Staf membuka link pesanan di aplikasi Qammaris Admin dengan akun masing-masing.
4. **Super Admin** memakai panel **Keuangan** untuk keputusan refund, pembayaran refund, pembatalan entri yang salah catat, rekonsiliasi pesanan lama, dan menyetujui penyesuaian harga.

Bila muncul **"Pesanan berubah"**, orang lain baru saja mengubah pesanan yang sama. Periksa data terbaru dulu, lalu ulangi bila masih perlu.

## Owner/admin

1. **Customer sudah fix order** → Pesanan Online → **+ Buat pesanan** → cari produk → atur jumlah → **Buat pesanan & link**.
   - Bila customer kesulitan membuka link, centang "Saya isi data customer sekarang" lalu isi datanya dari chat.
2. Di halaman pesanan, tekan **Salin pesan** (atau **Buka WhatsApp**) di bagian *Link untuk customer*, lalu kirim ke customer.
3. Setelah customer mengisi, data muncul dan status menjadi **Perlu dibayar**. Pengiriman dalam kota: customer mengirim sharelok lewat WA. Bila ingin staf memesankan driver, buka sharelok → salin link Google Maps → tempel di *Link lokasi*.
4. Isi **Detail pesanan → Pengiriman**:
   - Siapa pesan driver/J&T: *Saya* atau *Minta staf pesankan*.
   - Kurir dan ongkir (boleh ditulis `11.500` atau `11500`).
   - Ongkir dibayar: ditambahkan ke transfer / customer bayar ke driver / gratis.
   - Toko bayar driver dengan: cash kasir / GoPay staf / staf talangi dulu.
   Lalu **Simpan detail**.
5. Kirim QRIS/rekening seperti biasa, dan buat transaksi/member di Majoo. Setelah dana masuk: pilih metode pembayaran → **Tandai: Dibayar**. Centang **Sudah dicatat di Majoo** lalu simpan.
   - Bukti transfer bukan otomatis lunas; pastikan dana benar-benar masuk.
6. **Salin untuk grup** → tempel di grup "Orderan Online Qammaris". Pesan sudah memuat link tugas staf.
7. Pantau **Perjalanan pesanan**. Filter "Talangan belum diganti" menunjukkan ongkir staf yang perlu diganti; setelah mengganti, tekan **Tandai sudah diganti**.
8. Salah tandai? Pakai **Koreksi: batalkan "…"** (tercatat di riwayat). Pesanan batal: **Batalkan pesanan…** dengan alasan; bisa dipulihkan.

Link customer yang kedaluwarsa (7 hari belum diisi) atau link yang tersebar ke orang lain: tekan **Buat link baru**; link lama langsung mati.

## Staf

1. Buka link tugas dari grup.
2. Isi nama Anda (HP akan mengingatnya).
3. Bila tertulis **Tugas: pesankan …** → pesan Maxim/GoSend ke lokasi penerima atau request pickup J&T ke alamat yang tertera.
4. Setelah driver/J&T dipesan dan barang diserahkan → pilih kurir → **Tandai: Dikirim** (J&T wajib nomor resi). Tombol ada paling atas halaman.
5. Customer bisa menekan "Pesanan sudah saya terima". Bila tidak, setelah dipastikan sampai tekan **Tandai: Diterima**. Ambil di toko: **Tandai: Sudah diambil**.
6. Bila Anda membayar ongkir dulu → buka **Saya menalangi ongkir driver** → isi nominal → **Catat talangan**.
7. Pembayaran dan harga tidak bisa diubah dari link staf; hubungi admin.

## Privasi dan operasional

- Data customer disimpan tanpa hapus otomatis (keputusan Owner). Jangan meneruskan link staf ke luar grup.
- Halaman link tidak diindeks, tidak di-cache browser, dan tidak mengirim referrer.
- Rilis memerlukan migrasi additive `2026_10_08_000001_create_online_orders_tables` pada target yang disetujui sebelum kode aktif. Rollback kode tidak menghapus tabel/isi pesanan; jangan `migrate:rollback` tabel yang sudah berisi data.
