# Pesanan Online — program

Owner meminta fitur ini pada 2026-10-07 dan menyetujui rencana ORD-01 di sesi yang sama. Fitur dikerjakan paralel dengan Qammaris Journal (Codex mengerjakan BLOG-02 di worktree terpisah). Program ini terpisah dari P9 dan Journal. Status current ada di [BACKLOG](BACKLOG.md), aturan di [BUSINESS_RULES](../product/BUSINESS_RULES.md), keputusan di [ADR-037](../architecture/decisions/ADR-037-online-order-links-and-tracking.md).

## Masalah

Order lewat WhatsApp (dari Instagram/katalog) membuat Owner mengumpulkan produk, nama, HP, sharelok/alamat, ongkir, pembayaran, dan paperbag dari chat. Setelah itu Owner membuat transaksi Majoo, menulis ulang ringkasan ke grup "Orderan Online Qammaris", dan menanyakan progres pengiriman lewat chat. Pekerjaan menjadi berulang dan tidak ada satu catatan pesanan.

## Tahapan

| ID | Scope | Status |
|---|---|---|
| ORD-01 | Link "lengkapi pesanan" untuk customer, catatan admin, status/timeline, pesan grup staf, link tugas staf, talangan ongkir | IN_REVIEW — branch `modernization/ord-01-online-orders`, belum deploy |
| ORD-02 | Redesign: role Super Admin/Staff Order + manajemen pengguna, Admin PWA, status terpisah (bayar/persiapan/kurir/J&T/handover), pelanggan langganan, keep, API v1 untuk Qammaris App | IN_PROGRESS — Tahap 0 selesai; ORD-02a (role + pengguna) dan ORD-02b (Admin PWA) IN_REVIEW; kontrak API r4.1 (kandidat final setelah conditional sign-off App); ORD-02c/02d boleh dimulai. [Rencana](ORD-02_PLAN.md), [audit](../audits/2026-10-08-ord-02-online-orders.md), [kontrak API](../integrations/QAMMARIS_ORDER_API_V1.md) |
| ORD-03 | Keep/titip barang | Diserap ke ORD-02 |
| ORD-04 | Akun staf terautentikasi | Diserap ke ORD-02 (role Staff Order + link tugas App) |
| ORD-06 | Checkout keranjang website membuat Pesanan Online yang sama (ADR-028 diganti terarah) | BACKLOG — perlu persetujuan Owner |
| ORD-05 | Kebijakan retensi/anonimisasi data customer | BACKLOG — saat ini tanpa hapus otomatis (keputusan Owner) |

Tidak ada tahap yang dimulai otomatis. Payment gateway, integrasi Majoo, dan pengiriman WhatsApp otomatis bukan bagian program ini.

## Alur ORD-01

1. Customer fix order di WA → admin **Buat pesanan**: cari produk, isi jumlah (harga dikunci).
2. Admin menyalin **pesan untuk customer** berisi link → customer mengisi nama, HP, cara terima (Ambil di toko / Kirim dalam Kota Palu / Kirim ke luar kota), alamat bila luar kota, paperbag, dan catatan.
3. Pengiriman dalam kota: customer menekan **Kirim lokasi lewat WhatsApp**, lalu share location seperti biasa. Admin boleh menempel link Google Maps ke pesanan.
4. Admin mengisi ongkir, siapa yang membayar ongkir, cara toko membayar driver, dan siapa yang memesan driver. Setelah dana masuk, admin menandai **Dibayar** (kunci data customer) dan mencentang "Sudah dicatat di Majoo".
5. **Salin untuk grup** → tempel di grup staf. Pesan berisi link tugas staf.
6. Staf membuka link → setelah driver/J&T dipesan dan barang diserahkan, menandai **Dikirim** (resi J&T wajib), dan mencatat talangan ongkir bila ada.
7. Customer menekan **Pesanan sudah saya terima** di link-nya; bila tidak, staf/admin menandai **Diterima**. Ambil di toko: staf/admin menandai **Sudah diambil**.
8. Admin mengganti talangan dan memantau timeline. Customer melihat status lewat link yang sama.

Panduan harian: [runbook](../runbooks/ONLINE_ORDERS.md). Bukti: [verifikasi ORD-01](../verification/ord-01/README.md).
