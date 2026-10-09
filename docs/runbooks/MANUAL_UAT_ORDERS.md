# Manual UAT — Pesanan Online (ORD-01/ORD-02, kontrak r4.2)

Untuk Owner: mencoba sendiri seluruh alur pesanan lewat browser di laptop dan HP sebelum rilis production.

Semua data **sintetis**. Lingkungan ini terpisah dari production: database, akun, secret, dan penyimpanan file sendiri. Tidak ada API, database, atau webhook production yang dihubungi.

## Ringkasan lingkungan

| Bagian | Isi |
|---|---|
| Folder UAT | `C:\projects\Qammaris App\Qammaris-UAT-Local` (di luar repository) |
| Kode Website | worktree terkunci pada commit yang sudah diuji, di `...\Qammaris-UAT-Local\website` |
| Database | MariaDB 11.8.9 khusus UAT di `127.0.0.1:3397`, database `qam_uat` |
| Alamat laptop | `http://127.0.0.1:8141/admin` (lewat gerbang akses lokal) |
| Alamat HP dan laptop (HTTPS) | alamat `https://….trycloudflare.com` yang muncul saat server dijalankan, juga ada di `runtime\tunnel-url.txt` |
| Login dan kunci akses | `secrets\LOGIN-UAT.txt`. Hanya bisa dibaca akun Windows Owner; tidak ada di repository atau chat. |
| Qammaris App UAT | backend lokal `127.0.0.1:8000`. Frontend memakai alamat HTTPS dari agen App. |

**Gerbang akses UAT.** Sebelum halaman apa pun terbuka, browser meminta **kunci akses UAT** sekali (ada di `LOGIN-UAT.txt`). Ini mencegah orang lain yang menemukan alamat tunnel membuka admin atau link customer. API internal tidak bisa dibuka lewat tunnel sama sekali.

Satu-satunya pengecualian adalah **link tugas WhatsApp** (`/admin/orders/task/<id>`). Link ini hanya meneruskan ke halaman pesanan di Qammaris App UAT dan tidak menampilkan data.

## Menjalankan dan menghentikan

Buka PowerShell di `C:\projects\Qammaris App\Qammaris-UAT-Local\scripts`:

| Perintah | Fungsi |
|---|---|
| `.\start-uat.ps1` | Menyalakan database, tunnel HTTPS, Website, worker antrean, scheduler, dan gerbang akses. Alamat HTTPS tetap sama selama tunnel masih hidup. |
| `.\start-uat.ps1 -NewTunnel` | Sama, tetapi dengan alamat HTTPS baru |
| `.\start-uat.ps1 -NoTunnel` | Hanya laptop (`http://127.0.0.1:8141/admin`) |
| `.\status-uat.ps1` | Alamat aktif, proses yang hidup, koneksi ke App, dan hasil pemeriksaan integrasi |
| `.\stop-uat.ps1` | Menghentikan Website dan tunnel; database tetap hidup |
| `.\stop-uat.ps1 -All` | Menghentikan semuanya, termasuk database UAT |
| `.\reset-uat.ps1 -Yes` | Menghapus dan mengisi ulang **hanya** data UAT (lihat bawah) |

Catatan:
- Alamat tunnel `trycloudflare.com` berganti setiap kali tunnel dibuat ulang. Setelah alamat berganti, buka alamat baru di HP dan masukkan kunci akses lagi.
- Alamat App UAT untuk link tugas WhatsApp juga perlu diperbarui oleh agen App.
- Qammaris App UAT dijalankan oleh agen App. Kedua server harus hidup bersamaan; `status-uat.ps1` menampilkan baris "Backend Qammaris App".

## Reset data dummy

`.\reset-uat.ps1 -Yes`:
1. Menghentikan proses Website UAT.
2. Menghapus dan membuat ulang database `qam_uat`. Nama dan port database ditulis tetap di skrip dan diperiksa lagi sebelum migrasi; database lain tidak pernah disentuh.
3. Mengosongkan folder penyimpanan UAT dan menjalankan migrasi.
4. Mengisi ulang data:
   - 2 akun (Super Admin, Staff Order);
   - 6 produk;
   - 3 pelanggan, 2 di antaranya dengan alamat tersimpan;
   - 6 pesanan contoh;
   - 7 pesanan fixture untuk App.
5. Server dinyalakan lagi dengan `.\start-uat.ps1`.

Setelah reset, data di Qammaris App UAT juga perlu di-reset oleh agen App supaya sinkron.

## Data awal

| Pesanan | Keadaan | Dipakai untuk |
|---|---|---|
| QAM-0001 | Menunggu customer mengisi link | Kirim link customer, isi dari HP |
| QAM-0002 | Pelanggan lama (UAT Budi), alamat Palu tersimpan, belum dibayar | Tandai Lunas, packing, kirim lokal |
| QAM-0003 | Lunas, packing berjalan, luar kota | J&T: pickup, QR, serah ke kurir |
| QAM-0004 | Keep 24 jam | Konfirmasi stok, perpanjang, lepas keep |
| QAM-0005 | Dibatalkan setelah bayar, refund belum dikirim | Panel Keuangan: catat refund |
| QAM-0006 | Selesai (diambil di toko) | Riwayat, hanya baca |
| QAM-0007 … QAM-0013 | Fixture `E2E` untuk Qammaris App | Dikerjakan staf dari App; Website menampilkan klaim dan perubahan |

Produk: UAT Amber Oud, UAT Citrus Bloom, UAT Velvet Rose, UAT Sandal Noir, UAT Musk Blanc, UAT Vanilla Smoke. Ketik `uat` di pencarian produk.

Pelanggan: UAT Budi Langganan (2 alamat: Palu dan Makassar), UAT Sari Pelanggan (1 alamat), UAT Dewi Baru (tanpa alamat).

## Checklist uji manual

Tandai ✓ / ✗ dan catat kendalanya. Uji di **laptop** (Chrome) dan **HP**.

### A. Akses
- [ ] Laptop: buka alamat HTTPS, masukkan kunci akses, lalu login **Super Admin**. Halaman Pesanan Online tampil.
- [ ] HP: buka alamat HTTPS yang sama, masukkan kunci akses, login. Tampilan pas di layar tanpa geser ke samping.
- [ ] HP (Chrome Android): menu ⋮ → **Instal aplikasi** / **Tambahkan ke layar utama**. Buka dari ikon: tampil tanpa bilah alamat. Login dan halaman pesanan tetap di dalam aplikasi.
  - Di iPhone (Safari: Bagikan → Tambahkan ke Layar Utama), kunci akses diminta sekali lagi di dalam aplikasi.
- [ ] Logout, lalu login sebagai **Staff Order**. Menu Pengguna, Keuangan/refund, dan Integrasi App **tidak** ada.

### B. Membuat pesanan
- [ ] **Buat** → pilih asal chat (WhatsApp/Instagram) → cari `uat` → pilih 2 produk dan ubah jumlah.
- [ ] Cari pelanggan lama `Budi` (nama atau nomor 0800…1001) → pilih alamat tersimpan **Rumah Palu**. Data penerima terisi.
- [ ] Pilih paperbag / tanpa paperbag.
- [ ] Pilih **Data sudah lengkap** → simpan. Halaman detail pesanan tampil.
- [ ] Buat pesanan kedua dengan **Kirim link ke customer**. Tekan **Buat** dua kali dengan cepat: hanya satu pesanan yang tercipta.

### C. Link customer
- [ ] Di pesanan QAM-0001 → **Link & WA** → **Salin link**: muncul konfirmasi tersalin.
- [ ] **Buka WhatsApp**: WhatsApp terbuka dengan teks terisi. Di laptop, WhatsApp Web; di HP, aplikasi WhatsApp. Kirim ke nomor sendiri saja.
- [ ] Buka link customer di HP, masukkan kunci akses bila diminta, lalu isi nama, HP, cara menerima, dan paperbag. Status pesanan tampil.
- [ ] Kembali ke admin: data customer sudah masuk, dan pesanan pindah dari "Menunggu customer".

### D. Pembayaran dan detail
- [ ] QAM-0002 → **Ubah detail** (misalnya catatan atau ongkir) → simpan.
- [ ] **Pembayaran**: catat nominal penuh, metode, dan sumber konfirmasi (bukti di chat / unggah / Majoo) → status **Lunas**.
- [ ] Coba catat pembayaran dari dua tab sekaligus: tab kedua menampilkan **"Pesanan berubah"**, tidak tercatat ganda.
- [ ] Sebagai Staff Order: bisa menandai Lunas, tetapi tidak bisa mengubah ongkir/pendanaan.

### E. Packing dan pengiriman
- [ ] QAM-0002 → **Packing**: isi jumlah yang benar → konfirmasi. Jumlah kurang ditolak dan diminta mencatat kendala.
- [ ] **Kirim** (lokal): pilih siapa pesan kurir → kurir dipesan → **Sudah diserahkan** → **Diterima**.
- [ ] QAM-0003 (J&T): pickup diminta → **Sudah dipickup J&T** (sekaligus tercatat diserahkan). Resi boleh menyusul.
- [ ] Pesanan pickup: **Sudah diambil**.

### F. Keep
- [ ] QAM-0004: tandai stok sudah dipisahkan → **Perpanjang** → **Lepas keep** dengan alasan.

### G. Refund dan keuangan (Super Admin)
- [ ] QAM-0005 → panel **Keuangan**: catat refund sebagian, lalu sisanya. Status berubah dari menunggu refund ke selesai refund.
- [ ] Coba **Batalkan entri** yang salah catat: entri pembalik tercatat, bukan dihapus.
- [ ] **Penyesuaian harga**: ajukan sebagai Staff Order, lalu setujui sebagai Super Admin.

### H. Kendala dan klaim
- [ ] Catat **kendala** di sebuah pesanan dari Website, lalu tandai selesai.
- [ ] Saat staf App mengklaim tugas, blok **"Dipegang di Qammaris App"** tampil di Website. Super Admin bisa **Lepas klaim** dengan alasan.

### I. Integrasi App
- [ ] Menu **Integrasi App**: semua saklar aktif, konfigurasi "Terisi" (nilai secret tidak tampil), dan hitungan webhook terkirim/menunggu/gagal.
- [ ] Ubah pesanan di Website → di App UAT perubahan muncul. Ubah dari App → muncul di Website (riwayat menunjukkan nama staf App).
- [ ] Bila App dimatikan sebentar: perubahan di Website tetap tersimpan, webhook menunggu lalu terkirim setelah App hidup lagi. Bila gagal 24 jam, event muncul di daftar gagal dengan tombol **Kirim ulang**.
- [ ] **Link tugas WhatsApp** (pesan grup dari **Link & WA**) membuka **Qammaris App UAT** (`https://….trycloudflare.com/orders/<id>`, alamat dari agen App, bukan `qammarisapp.com`). Login App UAT memakai akun App dari agen App. Bila alamat App UAT belum diisi, link membuka Admin PWA Website.

### J. Lain-lain
- [ ] Mode pesawat di HP: halaman offline Admin tampil; tidak ada data pesanan lama yang tampil dari cache.
- [ ] Riwayat pesanan mencatat setiap langkah beserta pelakunya.

## Masalah yang sudah diketahui

- Halaman Integrasi App masih bertuliskan "baseline r4.1"; kontrak yang berlaku adalah r4.2. Hanya teks.
- Pemeriksaan integrasi (`status-uat.ps1`) menandai `QAMMARIS_ORDER_APP_TASK_LINKS` sebagai FAIL. Itu benar untuk staging, tetapi di UAT saklar ini sengaja dinyalakan agar link tugas membuka App UAT.
- Pengiriman WhatsApp hanya membuka aplikasi dengan teks terisi. Website tidak mengirim pesan sendiri.
- Foto produk dummy tidak ada (tampil placeholder).
- Alamat tunnel `trycloudflare.com` bersifat sementara dan berganti setiap tunnel dibuat ulang.
