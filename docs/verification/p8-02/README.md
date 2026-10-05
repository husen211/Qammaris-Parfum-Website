# P8-02 — Connected public availability verification

Verified locally on 2026-10-05. Surface: existing public catalog cards, detail, inquiry page/drawer and WhatsApp message. No redesign, new dependency, URL change or admin changes.

## Environment and data boundary

MySQL at the configured local endpoint did not accept a connection. No repair or original database mutation was attempted. Preview runs at `http://127.0.0.1:8000/products` using a separate ignored SQLite database, `storage/app/private/p8-02-preview/20261005/catalog.sqlite`. It contains public catalog rows from the verified **2026-09-14 local baseline backup**, not current production data. No users/authentication records were imported; no live app request was made, API credentials remain blank. Existing local media is read without copying or rewriting files.

Four temporary products represented app `available`, `sold_out`, OTW, and `unknown`, with intentionally old checked timestamps and synthetic prices/names. These are UI fixtures, not real stock. They and their offers were removed after testing. Final counts: 180 products, 64 offers, 19 image records, zero users, zero source snapshots/audits. Browser inquiry session was emptied and verified. The remaining preview cannot demonstrate live connected inventory until activation/mapping is verified separately.

## Screenshots and UX rationale

| Evidence | Viewport | Purpose |
| --- | --- | --- |
| [Before desktop catalog](before-desktop-catalog.jpg) / [After desktop catalog](after-desktop-catalog.jpg) | 1440×900 | Same layout/tokens; connected labels match app states, legacy cards retain confirmation. |
| [Before mobile detail](before-mobile-detail.jpg) / [After mobile detail](after-mobile-detail.jpg) | 390×844 | Removes checked-time and redundant stock verification from connected detail; keeps price/size/action and inquiry reservation boundary. |
| [Desktop detail](after-desktop-detail.jpg) | 1440×900 | Connected detail retains existing composition and actions. |
| [Mobile inquiry](after-mobile-inquiry.jpg) | 390×844 | Two units represent interest, fresh label is Tersedia, notice does not require another stock check. |
| [Mobile inquiry error](after-mobile-inquiry-error.jpg) | 390×844 | Hidden item gives review/retry recovery and neutral notice, not stale source confirmation. |
| [Mobile restock](after-mobile-restock.jpg) | 390×844 | OTW remains Habis · Restok segera, only restock inquiry action. |
| [Mobile filter](after-mobile-filter.jpg) | 390×844 | Habis filter includes sold-out and OTW, excluding available. |
| [Mobile empty results](after-mobile-empty.jpg) | 390×844 | Existing search/filter recovery remains available. |

Screenshots containing `Uji Lokal ...` show temporary synthetic verification records, which no longer exist in the preview.

## Checks actually performed

- Relevant baseline before changes: 16 tests / 155 assertions passed.
- Focused presentation/catalog/detail/inquiry checks: 21 tests / 204 assertions passed before adding the final cart-header regression assertion.
- Full suite after the cart-header assertion: **222 tests / 1487 assertions passed**.
- Laravel Pint on all modified/new PHP files passed; `git diff --check` passed.
- `npm run build` passed. Existing DaisyUI `@property` and large `about-lanyard` chunk warnings remain outside scope.
- Browser at 390×844 and 1440×900: source labels, no expiry messaging despite old timestamps, neutral unknown, OTW restock-only, add two units, drawer success/loading/error/retry/empty, Escape focus return, fresh inquiry page, desktop available filter, mobile sold-out filter, empty query results, and legacy detail confirmation.
- DOM overflow checks false on mobile detail, mobile filter, desktop catalog/detail. Browser console error/warning list empty. WhatsApp URLs/message generation checked without sending messages; checkout redirect behavior covered by feature tests.
- Source label cannot be spoofed by saved session data; tests cover mixed legacy/app inquiry and an old ETA remaining sold out.

## Files and rollback boundary

P8-02 application files: `app/Support/CatalogAvailability.php`, `app/Support/InquiryWhatsApp.php`, `app/Http/Controllers/CartController.php`, `resources/js/ui/cart-drawer.js`, `resources/views/cart/index.blade.php`, `resources/views/components/cart-drawer.blade.php`, `resources/views/products/{_card,index,show}.blade.php`; regression coverage in `tests/Feature/ConnectedAvailabilityPresentationTest.php`.

P8-02 adds no migration or product write operation. The earlier P8-01 migration was used only to construct this isolated preview, not applied to the original MySQL database. Prices, IDs, slugs, offers, media and existing credentials remain unchanged outside the disposable preview.

Before activation, P8-02 rollback is a focused code revert plus asset rebuild, retaining P8-01 backend state. After activation prefer forward fix; legacy UI reintroduces misleading confirmation/time copy for connected products. Never drop integration tables or reset source checkpoint as a UI rollback.

Documentation updated: business rules, current architecture, ADR-021 scope, integration runbook, backlog, this evidence and local preview runbook. Remaining gate: reviewer acceptance and separately approved staging activation with real credentials/network, MySQL, persistent worker and scheduler. No deployment or public endpoint activation was performed.
