# AUD-06 — Whole rupiah and authoritative cart totals

2026-10-07. Status: IN_REVIEW; local implementation and checks complete, release not performed. Scope: AUD-F08/F09. Final Owner decision is whole rupiah; the earlier decimal choice is superseded. [ADR-030](../../architecture/decisions/ADR-030-whole-rupiah-and-current-cart-totals.md) records the rule and legacy preservation.

## Outcome and changed modules

- `app/Support/Rupiah.php`: small shared whole-price rule, integer-hundredths conversion, exact sums and formatting. Whole values omit `,00`. Existing fractions remain exact rather than silently changing data.
- `ProductEditorRequest`, `ProductImportPreviewer`, `ProductMaintenancePreviewer`, `SyncSingleOffer`: reject new nonzero fractional selling/comparison prices as applicable; accept stored decimal zeroes; retain source-price authority. The shared manual offer guard prevents an old fractional proposal bypassing changed preview rules.
- `CartController`, `InquiryWhatsApp`: current-catalog resolved money, exact multiplication/subtotals and quote binding; current totals for add/update/remove; null for unresolved remaining carts. Numeric JSON price/total keys retained at the output boundary. No client-submitted amounts become authoritative.
- `helpers.php`, `Product`, `ProductVariant`, admin app/Shopee/canonical preview and catalog-filter views: shared formatting. Unused session-price `cart_total()` removed. Amount formatting no longer rounds whole values or truncates legacy fractions.
- `AdminProductController`/edit: read-only warning for a hypothetical stored fractional selling/comparison price; no automatic correction. Money input step/min use1. Optional comparison input min1 now agrees with existing positive validation. Create/update paths remain explicit and validated.
- `cart/index.blade.php`: min-width and summary wrapping fix for a long amount at320px. No unrelated redesign, media, navigation or source-status change.
- Tests cover whole input/storage, rejection before mutation, CSV rejection, manual-operation guard, legacy warning/no rewrite, stale session totals, no partial cart, cent-level quote changes and decimal arithmetic boundaries.

## Evidence and consumer trace

Baseline cart/inquiry tests:14passed/147assertions. Synthetic browser price100.90 showed unit101 but two-item total200 before the patch. Red regressions additionally showed that100.90→100.91 did not invalidate the old checkout quote, and mutation totals used session prices. These failures were reproduced before implementation.

Read-only SQLite inspection: p8-02 historical snapshot180products/64offers and p7-07 snapshot445products/390offers; fractional base/comparison/offer counts all0. Empty `database/database.sqlite` has no catalog tables. Local default MySQL was unavailable (connection error2002); neither current production counts nor fractional production records are confirmed. Historical snapshots are not live catalog evidence.

| Consumer | Result |
|---|---|
| Product/cart Blade and WhatsApp | Shared formatter; current resolved offer amounts, exact totals |
| `product-cart.js` | Uses success/count only; ignores numeric total |
| `cart-page.js` | Successful mutation reloads the current page; ignores numeric total |
| React navbar `CardNav.jsx` | `/cart/data` on persisted history reads count only |
| `/cart/data` compatibility | Numeric prices/line totals retained, formatted amounts exact, invalid cart409 |
| Canonical/maintenance imports | Whole-price input; shared offer write protection; guarded transactional apply retained |
| Qammaris App client/sync | Existing positive integer contract/automatic price and retained-price guards unchanged |
| Mapping review difference export | Existing two-decimal diagnostic representation, not a selling-price write or customer total; unchanged |

Remaining `number_format` usage is file-size formatting and the mapping-review diagnostic, not cart/checkout price arithmetic. No repository caller remains for the removed global `cart_total()` helper. External callers beyond the repository are Not confirmed.

## Checks actually run

- Final `php artisan test --compact`: **368passed,2556assertions**.
- Focused cart/editor/import/source checks passed before the final suite; final whole-rule focused pass58tests/426assertions.
- `node --test tests/js/*.test.mjs`: **31passed**.
- Changed PHP Pint: **passed**. Strict Composer metadata: **valid**. `git diff --check`: **passed**.
- Final `npm run build`: **passed**,669modules. Existing DaisyUI `@property` optimizer and large3Dchunk warnings remain; no new packages/lockfile changes.

## Real browser and UX rationale

Chrome browser, local disposable SQLite copy on port8012. Requested390×844 and1440×900 viewports; CSS content widths account for scrollbars.320px additional bound-price checks. Viewport resizing is mouse-driven, not genuine touch/Safari.

- Catalog/detail, two-item cart and checkout show matching amounts; final whole case100000×2 displays `Rp 200.000` without decimals. Detail comparison price follows the same rule.
- Add, quantity decrease2→1, remove and empty state exercised. Pending feedback observed, successful state observed afterwards. No WA message submitted; redirect payload/quote/privacy verified by automated tests.
- Final admin controls accept100000.00 withstep1 and reject100000.50 via native step mismatch. Server rejection is tested independently. Legacy correction notice observed without saving the price.
- A320px test with legacy maximum stored amount99999999.99 overflowed the page (content342px/client305px); after min-width/wrapping fix cart, checkout, catalog and detail content fit305px. Screenshots show the preserved amount, not permission to create a new fractional price.
- Current-page console error check returned no entries. Fixture media files were deliberately not copied; local placeholders/missing-file readiness notice do not prove production media failure. This task changes no media.

| Evidence | Screenshots |
|---|---|
| Before/after legacy100.90 regression, mobile | [before catalog](before-catalog-390.png), [after catalog](after-catalog-390.png), [before cart](before-cart-390.png), [after cart](after-cart-390.png) |
| Desktop legacy result | [cart](after-cart-1440.png), [checkout](after-checkout-1440.png) |
| Final whole prices, mobile | [catalog](after-whole-catalog-390.png), [checkout](after-whole-checkout-390.png); detail/cart verified by browser DOM, their resize captures excluded |
| Final whole prices, desktop | [catalog](after-whole-catalog-1440.png), [cart](after-whole-cart-1440.png), [checkout](after-whole-checkout-1440.png) |
| Admin final rule | [desktop](after-admin-1440.png); mobile validation and legacy notice observed in browser, malformed resize captures excluded |
| Long amount320px | [before overflow](before-large-total-320.png), [after wrap](after-large-total-320.png), [detail](after-large-detail-320.png) |
| Empty cart | [mobile](after-empty-cart-390.png) |

The attempted desktop-before screenshot captured stale mobile layout during resize and was excluded. Four further mobile captures showed miniature stale resize content; these were also excluded, rather than presented as visual evidence. Valid mobile catalog/checkout and desktop cart/checkout captures are retained. Matched desktop-before/after evidence is therefore incomplete; after-desktop behavior was verified. No broad UI acceptance or genuine device gate is closed by this report.

## Data impact, cleanup and release/recovery

No migrations, data rewrite, rounding, publication change, production/staging write, deployment, dependency installation or uploaded-media mutation. Only a disposable local copy held a synthetic product/offer/admin and its database-backed browser session. Source snapshots and human accounts were untouched. Temporary server/tab/viewport/copy cleanup is recorded after completion below.

Cleanup verified: agent-created tab closed, viewport reset, PHP server stopped, and the exact workspace-scoped `storage/app/private/aud-06-browser` copy removed (including synthetic account/product/offer/session). Original snapshots remain; unrelated pre-existing `tools/__pycache__/` left untouched.

Future approved release needs the usual CI/deployment process, no schema migration. Existing quotes can request a fresh checkout review once; keep sessions and cart records. Fractional un-applied manual CSV proposals must be corrected/re-previewed; protected apply rolls back on rejection and does not rewrite applied history. Code-only revert requires no data restore but would restore the known truncation/quote issue; prefer a narrow forward fix. Neither release nor legacy-price correction is authorized by this implementation report.

Docs updated: BUSINESS_RULES, ARCHITECTURE, BACKLOG, CSV contracts, ADR index/ADR-030 and this evidence. Recommended next: **AUD-07** human actor audit/diagnostics, after Owner direction; not started. Existing P9/live/device limits stay open.
