# P7-07 — Catalog photography and mobile presentation

Status: IN_REVIEW. Implemented locally on `codex/catalog-product-presentation`; no staging/production deployment or main merge. Date: 2026-10-06.

## Scope and rationale

The live `/products` page and source were inspected before editing. Square product assets were being fitted into portrait 3:4 frames with `object-cover` and hover enlargement. This clips packaging and magnifies differences in the whitespace supplied by each photo. Mobile price/size rows were cramped; search required opening the filter.

References inspected: [CNF unisex catalog](https://cnfstore.com/fragrance/for-unisex), [official Afnan USA catalog](https://us.afnan.com/collections/all), [official Zimaya UAE catalog](https://uae.zimayaperfumes.com/collections/all). The adopted principles are consistent photo space, calm brand/name hierarchy, predictable price position and clear discovery controls. Qammaris's cream/charcoal palette, restrained gold accents, fonts and existing navigation remain.

The catalog now uses square centered contain frames and full wrapped names. Size, price and availability have separate lines, with price positions aligned within each grid row. Badges sit outside the photograph. The image and text share one accessible detail link; there are no nested buttons or links. Mobile retains two columns, a visible search field and the existing Filter/Sort toolbar. Tablet and ordinary desktop use three columns; wide desktop uses four. Pagination fits at 320px.

White/transparent margin analysis is intentionally conservative: every perimeter sample must be blank, all detected nonblank content is retained, extra safety margin is added, and any adjustment is limited to 2x. Only display pixels are redrawn onto a decorative canvas. Colored scenes, edge-touching products, unsupported canvas or CORS restrictions keep the complete source in a contain frame. No server-side crop, image overwrite, hotlink change, data field or package was introduced. This improves supplied whitespace; it cannot promise identical bottle size for every differently composed photograph.

## Files

- `resources/views/products/index.blade.php`: catalog composition and visible mobile search; existing GET query controls retained.
- `resources/views/products/_catalog-card.blade.php`: catalog-only card. Existing `_card.blade.php` and detail page remain unchanged.
- `resources/css/catalog.css`, imported from `resources/css/app.css`: scoped layout, typography, photo geometry and focus states.
- `resources/js/ui/catalog-image-bounds.js`, `catalog-images.js`, imported from `resources/js/app.js`: conservative display balancing and image error fallback.
- `resources/js/ui/catalog-discovery.js`: quick-search input feeds the existing state synchronization.
- `tests/js/catalog-image-bounds.test.mjs`: crop-safety cases; `tests/Feature/PublicCatalogHardeningTest.php`: square reserved image dimensions.
- `docs/planning/BACKLOG.md` and this evidence directory: scope, status and verification.

## Test data isolation

Preview: `http://127.0.0.1:8001/products`. This is an isolated local SQLite UI fixture copied from the existing frozen launch reference: 350 published products, 95 drafts, local photo copies and zero users. It is **not a claim about the latest production catalog**. Only public catalog tables were copied; no credentials, provider feeds, queue workers or synchronization were activated. The original local 180-product database/media and port8000 process remain intact. No product business data, price, stock, API, application schema, production environment or original media file changed.

Ignored fixture helpers/media live under `storage/app/private/p7-07-preview/`; they are not committed. Existing migrations were run only against that new disposable fixture; no migration source was created or edited. Browser detail visits can update view counts only in that disposable copy.

Baseline local screenshots use the same fixture/photos and the previous committed catalog view from `045d4fb`. This avoids comparing different catalog populations. The live production desktop screenshot is contextual evidence only (actual viewport1536; not the 1440 local comparison).

## Browser checks actually performed

Chrome responsive viewport overrides; these are not physical-device Safari tests. Dimensions were read from `innerWidth`/`innerHeight`, rather than inferred from screenshot names. Scrollbars occupy part of the viewport. Measurements are saved in [browser-layout-checks.json](browser-layout-checks.json).

| Viewport | Columns | Horizontal/card overflow | Clipped names | Square frame mismatch | Nested actions |
| --- | --- | --- | --- | --- | --- |
| 320 x 844 | 2 | 0 / 0 | 0 | 0 | 0 |
| 375 x 844 | 2 | 0 / 0 | 0 | 0 | 0 |
| 390 x 844 | 2 | 0 / 0 | 0 | 0 | 0 |
| 768 x 900 | 3 | 0 / 0 | 0 | 0 | 0 |
| 1440 x 900 | 3 | 0 / 0 | 0 | 0 | 0 |
| 1920 x 900 | 4 | 0 / 0 | 0 | 0 | 0 |

- Ceremony, Hibiscus Magic, Singel Glow, Hot Ice Voyage, Aurum and assorted lower-row bottle/box scenes retain their complete visible product composition. White margin balancing and unchanged original framing both exercised.
- Long Fatima Velvet Love name wraps fully at320; price and availability do not overlap. Last-row frames, sticky toolbar and pagination checked while scrolled.
- Mobile filter dialog at320: panel fits inside viewport, brand names wrap, Escape closes and returns focus to Filter.
- Mobile search `afnan`:51 results. Sorting by price_low retains search; first four prices169k,185k,189k,289k. Native next-page action retains search/sort/page2.
- Native click in the photo of Mazaaj Rhythm opens its correct detail with search/sort/page2. The visible Kembali ke hasil link returns to that exact query context.
- Mobile brand Afnan + audience Unisex applies correctly:7 results, all Afnan, no carried page2. Desktop availability Tersedia adds to the same state:6 results, all available.
- Desktop search with a deliberately absent term produces0 results and the existing empty-state explanation. Hapus semua filter restores the catalog.
- Native name click for Hibiscus Magic opens the correct detail. Keyboard focus shows a charcoal outline; Shift+Tab and Enter open Ceremony. Existing detail inquiry controls remain visible; no WhatsApp message was sent.
- Deliberate fixture-only404 for Ceremony's photo: fallback placeholder/alt and “Foto sedang dilengkapi” appear. Frame stays158x158 at375 and no horizontal overflow. Removing the private failure flag and reloading restores balanced photo in the same158x158 frame. Original image file never altered.
- Fixed frame/aspect/dimensions reserve space during lazy loading and failure. No CLS performance score was measured.
- Fresh tab console warning/error query after recovery returned an empty list. The deliberate failed-image request is an expected test404. A browser attachment interruption was recovered with a fresh local tab; it was not treated as an application failure.

## Screenshots

Same-fixture comparisons:

| Before | After |
| --- | --- |
| [Mobile375](before-local-mobile.png) | [Mobile375](after-375.png) |
| [Desktop1440](before-local-desktop.png) | [Desktop1440](after-1440.png) |

Additional: [320](after-320.png), [390](after-390.png), [768](after-768.png), [1920](after-1920.png), [filter320](filter-320.png), [long name and pagination320](long-name-pagination-320.png), [keyboard focus](keyboard-focus-desktop.png), [empty state](empty-desktop.png), [image failure375](image-failure-375.png), [live baseline1536](before-production-desktop.png).

## Automated checks

- `node --test tests/js/catalog-image-bounds.test.mjs`: **5 passed**, including faint glass, off-center packaging, colored/edge-touching scenes, invalid/empty data and transparent/non-square sources.
- `C:/xampp/php/php.exe artisan test --compact --filter='PublicCatalogHardeningTest|PublicCatalogDiscoveryTest|ConnectedAvailabilityPresentationTest'`: **14 passed,115 assertions**. Catalog query/state, accessible reserved media, public hardening and connected availability remain covered.
- `C:/xampp/php/php.exe vendor/bin/pint --test tests/Feature/PublicCatalogHardeningTest.php`: passed.
- `npm run build`: passed. Existing DaisyUI `@property --radialprogress` optimization warning and large lazy about-lanyard chunk warning persist; unrelated code was not changed.
- `git diff --check`: passed.

## Limits, acceptance and rollback

Physical iOS/Safari, remote media CORS failure and complete production asset-by-asset visual review are **Not confirmed**. Code safely keeps contain when pixel access is unavailable. Product artwork with colored/edge-touching backgrounds intentionally retains its original composition and may still look different in scale. No API/stock latency or production integration verification is claimed by this UI task.

Owner visual acceptance and an explicitly approved release are next for **P7-07**. No further backlog phase has been started; P1-04's wider launch acceptance remains separate. Do not merge main until deployment is approved: the existing main workflow auto-deploys.

Rollback, if subsequently released: revert this frontend patch and rebuild assets through the existing release process. No database restore, image rollback, schema migration or API change is required.
