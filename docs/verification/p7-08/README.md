# P7-08 — Catalog navigation and mobile gallery

Status: IN_REVIEW, 2026-10-06. Owner approved catalog priorities 1–5, then added mobile photo swipe/arrows. No deployment, admin implementation or homepage change.

## Outcome and changed files

- `resources/views/products/index.blade.php`: Brand first, mobile Brand disclosure, Peruntukan, three normal availability choices; legacy unknown URLs keep a hidden selected compatibility option. Server-normalized catalog URL is provided to the browser.
- `resources/css/catalog.css`: independently scrolling desktop rail, bounded to the visible viewport; gallery allows vertical pan/pinch while horizontal gestures choose photos.
- `resources/js/ui/catalog-discovery.js`: restored GET controls re-enable on pageshow, retaining query synchronization.
- `resources/js/ui/catalog-navigation*.js`, `resources/js/app.js`, `_catalog-card.blade.php`: per-tab return position for the exact server-normalized URL, including sidebar/brand scroll and focused product; new form submissions clear it. Storage errors keep ordinary GET navigation working. No arbitrary return destination, browser-only filter authority or backend change.
- `resources/views/products/show.blade.php`, `resources/js/ui/product-gallery.js`: Kembali, concise Jumlah, mobile swipe and 44px arrow buttons, synchronized thumbnails/counter, disabled boundary arrows, loading/error/retry; existing inquiry logic and image order remain.
- `tests/Feature/PublicProductDetailTrustTest.php`, `tests/js/catalog-navigation-state.test.mjs`: updated detail regression and context-isolation/corrupt-position checks.
- `docs/planning/BACKLOG.md`, `docs/product/BUSINESS_RULES.md`, this directory: scope, approved copy/media decision and evidence.

## Browser verification actually performed

Chrome on the existing isolated port8001 fixture (350 published/95 drafts), not a claim about current production counts. Original port8000 database and media retained. Public GET detail visits can update view counts only in that isolated fixture. No worker, integration, account, source image or product business-field change. A temporary local router error flag was removed after the retry test; the old detail template in the ignored baseline view directory was used only to capture the before image.

- 1440x900: rail bottom880 at initial layout; wheel over rail changes rail617.6 while window remains0, price inputs reachable. Scrolling outside it moves products and expands rail to the sticky viewport height.
- Native desktop photo click and Kembali restore y1325.6/sidebar392 exactly; browser Back also restores position and enables controls. Focus returns to the originating product link.
- 390x844: Afnan + Unisex + available yields6 cards, all available. Brand opens/closes independently; filter Escape returns focus to its trigger.
- Search afnan, price_low and page2 survive detail/back. Native mobile departure and return both y591.2 with the exact same query and originating product focus.
- 320x844,375x844,390x844,768x900,1440x900: no horizontal overflow; measured names not clipped. Dialog width304.8 at320; categories/Peruntukan/status remain available when Brand is collapsed.
- Burgundy gallery: horizontal native pointer drag1→2, next2→3 and last-arrow disabled; Enter on first thumbnail returns1 with previous-arrow disabled. Final-built JS also passes native drag at320; CSS touch-action is pan-y pinch-zoom. Physical touchscreen/Safari gestures are **Not confirmed**.
- A local-only second-image404 displays failure and Coba lagi; removing the router error flag and retry loads the original second image, clears feedback and hides retry. Original photo bytes never changed.
- Single-image Qammaris Signature Cache at320: photo loaded, no redundant arrows/thumbnails and no overflow. Final page console error/warning query returned an empty list.
- Legacy unknown filter URL still renders an empty-state result and selected hidden compatibility option. Normal choices are Semua/Tersedia/Habis; unknown product/domain behavior remains covered by PHP tests.

## Before/after screenshots

| Surface | Before | After |
| --- | --- | --- |
| Catalog desktop1440 | [Before](before-1440.png) | [After](after-1440.png) |
| Catalog mobile390 | [Before](before-390.png) | [After](after-390.png) |
| Detail gallery390 | [Before](gallery-before-390.png) | [After](gallery-after-390.png) |

Additional: [rail price controls](sidebar-price-1440.png), [filter390](filter-390.png), [filter320](filter-320.png), [320](after-320.png), [375](after-375.png), [768](after-768.png), [gallery320](gallery-320.png), [expected image error](gallery-error-390.png).

## Automated verification

- Laravel targeted catalog hardening/discovery/detail/connected availability: **21 passed,191 assertions**.
- Node navigation-state and existing image-bound safety: **8 passed**.
- JS syntax for navigation/gallery: passed. Scoped Pint: passed. `git diff --check`: passed.
- Vite production build: passed. Existing DaisyUI radialprogress and large lazy about-lanyard warnings persist; no new package/type-check/lint framework was introduced. Full-suite coverage was not measured.

## Limits and recovery

Not deployed or merged to main; Owner visual acceptance/release pending. Browser-only position restoration requires sessionStorage; blocked storage or no JavaScript falls back to normal catalog URLs. Device-native touch/pinch, Safari and production behavior after release remain unverified. Existing backend unknown/status/pricing/quantity semantics unchanged.

Rollback: revert this frontend patch and rebuild through the normal release workflow; no database restore, image migration, credentials or API rollback needed. Next recommended scope is P8-08 admin new-product drafts/inbox and supplemental Shopee/manual media; it has not started. Homepage merchandising/illustrations remain deferred.
