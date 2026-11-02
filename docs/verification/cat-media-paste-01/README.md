# CAT-MEDIA-PASTE-01 verification — paste product photos with Ctrl+V

Date: 2026-11-02. Branch `feat-paste-product-photos`. Local only (SQLite, `php artisan serve` + Vite); nothing ran against production.

Surface: admin panel, "Tambah produk" and "Edit produk" photo boxes (one shared partial `admin/products/_photo_picker`, script `resources/js/ui/product-photo-paste.js`, loaded only by admin pages through `admin-pwa.js`).

## Behaviour checked in the browser (1440, synthetic `ClipboardEvent` with a real `DataTransfer`)

| Case | Result |
|---|---|
| 24.55 MB noisy PNG with transparency, 3000×2400 (worst case) | `foto-tempel-1.webp`, 1.87 MB, 2000×1600, in 1.2 s; preview "Foto utama, belum disimpan"; 2 slots left |
| Realistic 2600×2600 and 1800×2400 PNGs | WebP 104 KB and 99 KB |
| Clipboard holds only an image address | "Yang ter-copy alamat gambar, bukan gambarnya. …" |
| Text pasted into the description field | not intercepted |
| Three images pasted with two free slots | two added, "Hanya 2 foto yang muat (maksimal 3 per produk)." |
| Paste with a full gallery | "Galeri penuh: maksimal 3 foto per produk. …" |
| Remove (×) | file leaves the input, slot returns, focus back on the drop zone |
| A small PNG that already fits | uploaded unchanged |
| "Simpan draft" | two `.webp` product images stored on the product disk, the first one primary |
| Leaving with unsaved pasted photos | the browser asks before leaving |

Found while testing: `Image.decode()` never resolved while the page was not painting, so the first version hung on "Merapikan foto…". Decoding now uses `createImageBitmap` (with EXIF orientation); and a result more than twice the limit shrinks the photo at once instead of trying every quality step.

## Screenshots

| Check | 390 | 1440 |
|---|---|---|
| Before: edit photo box | [before-edit-390](before-edit-390.jpg) | [before-edit-1440](before-edit-1440.jpg) |
| Before: add product photo box | — | [before-create-1440](before-create-1440.jpg) |
| After: edit with pasted photos | [after-edit-pasted-390](after-edit-pasted-390.jpg) | [after-edit-pasted-1440](after-edit-pasted-1440.jpg) |
| After: add product with a pasted photo | — | [after-create-pasted-1440](after-create-pasted-1440.jpg) |

## Tests

- `tests/js/product-photo-paste.test.mjs` (7): clipboard image detection, copied-address hint, 2000 px fit without upscaling, keep-as-is rule, quality steps, early stop above twice the limit, JPEG fallback.
- `AdminProductImageTransactionTest::test_pasted_webp_photos_save_next_to_existing_ones_within_the_three_photo_limit`.
- `AdminProductMediaManagementTest::test_product_editor_exposes_clear_media_states_and_safe_controls` updated for the new box.

Synthetic data (one draft, a temporary admin, the two stored WebP files) was removed afterwards. Not covered here: a real Ctrl+V from Google Images in the Owner's Chrome (clipboard permission and site behaviour), iPhone/Android.
