# CAT-PUBLISH-CHECK-01 verification — publish with missing fields no longer looks stuck

Date: 2026-11-02. Branch `fix-publish-stuck`. Local only (SQLite, `php artisan serve` + Vite); nothing ran against production.

## Cause

Publishing with an empty gender or category sent the whole form (and any pasted photos, up to several MB) to the server, which rejected it. During that round trip the admin page-loading overlay covered the editor; on production and a phone connection this looked like a stuck page, and the returned error list sat at the top of the page. Locally the round trip took about 5–6 s with and without a pasted photo, then came back correctly, so nothing hung on the server.

## Change

`resources/js/ui/product-publish-check.js` now checks every publish requirement in the browser before the request: name, brand, category, description, gender, size, selling price (unless read-only from the App) and at least one photo (saved or pasted). Missing items are named together in `[data-publish-check-summary]` above the buttons ("Belum bisa publish. Lengkapi: kategori, gender, foto."); the first missing field gets the native message and focus, or the photo box is scrolled into view. A product that is already published is checked on "Simpan perubahan" too (`data-publish-check-always`). Drafts never block; the server keeps all its checks.

## Browser checks

| Case | Result |
|---|---|
| Edit, 1440: Publish with category, gender and photo missing | no request and no overlay; summary "Belum bisa publish. Lengkapi: kategori, gender, foto."; category focused with "Pilih kategori sebelum publish." ([publish-blocked-summary-1440](publish-blocked-summary-1440.jpg)) |
| Category and gender filled, Publish again | summary narrows to "Lengkapi: foto." |
| Photo pasted, Publish | published (status published, gender Unisex, category set, 1 image) |
| Add, 390: only the name filled, Publish | "Belum bisa publish. Lengkapi: brand, kategori, deskripsi, gender, ukuran (ml), harga jual, foto."; brand focused; no overflow ([publish-blocked-summary-390](publish-blocked-summary-390.jpg)) |

## Tests

- `tests/js/product-publish-check.test.mjs` (4): all missing items in page order, photo required, drafts never blocked, published save checked, whitespace counts as empty.
- `AdminProductImageTransactionTest::test_every_publish_requirement_is_marked_for_the_browser_check`.

Synthetic data (one draft that was published locally, a temporary admin, one stored WebP) was removed afterwards.
