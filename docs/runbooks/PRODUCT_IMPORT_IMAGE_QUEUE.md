# Runbook Antrean Gambar Import Produk

Runbook ini menyiapkan worker untuk tahap akuisisi gambar setelah batch import diterapkan. Ia tidak memberi izin deployment; jalankan pada staging/production hanya di dalam item cutover yang disetujui.

## Konfigurasi wajib

```dotenv
QUEUE_CONNECTION=database
PRODUCT_IMPORT_IMAGE_ALLOWED_HOSTS=down-id.img.susercontent.com,cf.shopee.co.id
PRODUCT_IMPORT_IMAGE_CONNECT_TIMEOUT=5
PRODUCT_IMPORT_IMAGE_TIMEOUT=20
PRODUCT_IMPORT_IMAGE_MAX_BYTES=8388608
PRODUCT_IMPORT_IMAGE_MAX_DIMENSION=12000
```

- Gunakan nama host exact tanpa skema, path, wildcard, atau IP.
- Tambah host hanya setelah URL sampel diverifikasi dan alasan bisnis dicatat.
- Pastikan migration queue jobs Laravel dan migration P6-04 sudah applied.
- Pastikan `PRODUCT_MEDIA_DISK` mengarah ke disk yang sudah diverifikasi (local/public pada development atau R2 setelah cutover resmi).

## Menjalankan worker

Development foreground:

```powershell
C:\xampp\php\php.exe artisan queue:work --queue=product-import-images,default --tries=2 --timeout=90
```

Environment deployed harus memakai process manager Hostinger/Supervisor yang me-restart worker setelah deploy atau crash. Contoh command worker:

```text
php artisan queue:work --queue=product-import-images,default --tries=2 --timeout=90 --sleep=2
```

Setelah kode atau konfigurasi berubah, jalankan `php artisan queue:restart`, lalu pastikan process manager membuat worker baru.

## Verifikasi sebelum dipakai

1. `php artisan migrate:status` menunjukkan migration P6-04 applied.
2. `php artisan config:show product_imports` menampilkan allowlist dan batas yang diharapkan tanpa secret.
3. Worker aktif dan membaca queue `product-import-images`.
4. Gunakan batch staging kecil berisi satu URL valid dan satu host yang tidak diizinkan.
5. Pastikan UI menunjukkan satu `tersimpan`, satu `gagal/ditahan`, product tetap draft, dan halaman publik tidak memakai URL provider.
6. Tekan retry; jumlah gambar sukses harus tetap sama dan hanya kandidat gagal yang diproses lagi.

## Troubleshooting

- **Semua job tetap queued:** cek process manager, queue name, koneksi database, dan restart worker.
- **Host belum diizinkan:** periksa exact hostname pada allowlist; jangan memakai wildcard atau menonaktifkan validasi.
- **MIME/dimensi/ukuran ditolak:** periksa file sumber. Jangan menaikkan limit tanpa review dampak storage dan keamanan.
- **Product tidak lagi draft:** review perubahan publication. Job memang harus berhenti tanpa download.
- **Attachment gagal:** cek log aplikasi dan media disk. File baru seharusnya sudah dibersihkan; verifikasi object key sebelum tindakan manual.
- **Job gagal setelah dua percobaan:** perbaiki penyebab, retry failed job bila masih relevan, lalu gunakan tombol retry batch untuk kandidat non-success.

## Rollback operasional

1. Hentikan worker queue `product-import-images`.
2. Jangan hapus product, image metadata, atau object yang sudah sukses.
3. Pertahankan outcome row untuk audit.
4. Lakukan forward fix konfigurasi/kode, restart worker, kemudian retry kandidat gagal.
