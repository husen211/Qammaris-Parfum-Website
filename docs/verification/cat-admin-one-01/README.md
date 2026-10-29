# CAT-ADMIN-ONE-01 verification — one simple Products page

Date: 2026-10-29. Branch `feat-one-products-page`. Local only (SQLite, `php artisan serve` + Vite); nothing ran against production.

Surface: admin panel (task completion and status clarity, per `skills/qammaris-ui-review`).

## Audit (before)

- Two pages for one job: "Products" (website list) and "Produk dari aplikasi" (App snapshots with Semua / Draft / Perlu pasangan / Periksa harga / Disembunyikan aplikasi, sync box, legacy preview).
- A published product hidden in the App showed only "Tayang" although customers cannot open it; a draft without an offer showed a price although it cannot be ordered.
- No row said what a draft still needs; availability showed the raw source value `qammaris_app`.
- On a 390 px phone the first screen held only buttons, three links and four filter selects; no product was visible.
- The editor showed one generic sentence and a link to the other page; the App name, price and stock were not visible.

## Change (after)

- One list, `/admin/products`: tabs Semua · Perlu dicek · Belum tayang · Tayang · Diarsipkan with counts (same `publication` query parameter; `check` is new).
- Each row: status + "dari App" / "manual"; "Perlu dicek: Disembunyikan di aplikasi / Ukuran belum diisi / Harga belum ada di aplikasi"; for drafts "Kurang: foto, deskripsi, …" or "Siap ditayangkan"; "Ukuran & harga" column shows size with price, or "Belum ada ukuran & harga".
- Notices only when relevant: the ORD-12 waiting-drafts fill (moved here), App products held back as possible duplicates (link to review), sync stale/failed with "Sinkronkan sekarang". Footer: last sync, "Data aplikasi", "Impor CSV pelengkap".
- Editor panel: "Terhubung ke Qammaris App: <App name>" with Majoo/App source, App price, App stock, last sync, and a hidden-in-App warning; manual products say so.
- Navigation: "Produk dari aplikasi" removed; "Produk" stays active on the App data page, which is renamed "Data Qammaris App" and links back.

## Tests

- New: `QammarisAppCatalogWorkflowTest::test_one_product_list_shows_status_source_and_what_is_missing` (row notes, counts, `check` tab, empty state, unknown tab value).
- Updated for moved/renamed copy: the ORD-12 fill test (now on the Products page, redirects there), the merge test, the connected editor test.
- `ShopeeContentImportTest::test_product_editor_keeps_only_safe_import_return_context` caught a snapshot without `source`; the panel now tolerates missing fields.
- Full local suite: see the PR (known local-only failures: ReleaseCheck VAPID, two J&T QR tests without GD).

## Browser (local)

Synthetic data: seven "Uji Produk" products (App draft with offer, App draft without size, OTW without price, published hidden in App, published App product, manual draft, archived) plus a temporary admin; all removed afterwards and the sync state reset.

| Check | 390 | 1440 |
|---|---|---|
| Before: Products list | [before-products-390](before-products-390.jpg) | [before-products-1440](before-products-1440.jpg) |
| Before: editor top | — | [before-editor-1440](before-editor-1440.jpg) |
| After: Products list (first product at 475 px of 844 on the phone; no page overflow) | [after-products-390](after-products-390.jpg) | [after-products-1440](after-products-1440.jpg) |
| After: "Perlu dicek" tab | [after-check-tab-390](after-check-tab-390.jpg) | — |
| After: editor App panel (hidden-in-App warning on the phone) | [after-editor-hidden-390](after-editor-hidden-390.jpg) | [after-editor-1440](after-editor-1440.jpg) |

Keyboard: Tab reaches the status tabs with a visible focus outline; Enter opens the tab and keeps the search. The tab bar scrolls sideways inside itself on phones. Console: only the local Vite dev server's missing Inter font (404), unrelated.
