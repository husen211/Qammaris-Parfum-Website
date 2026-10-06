# P7-10 — Default best sellers and daily homepage selection

Owner request: existing marked best sellers first in the catalog, plus a changing daily homepage selection. Implementation is isolated on `codex/bestseller-discovery` from production/main `7b86b00`, without unreleased PR6/PR7 code. No production deployment or data operation.

## Outcome and rationale

- Default `Terlaris dahulu` puts marked products ahead of ordinary products throughout pagination; publication date and ID preserve predictable ordering within groups.
- Explicit Terbaru, price and popular retain their original meaning. Query normalization/forms/JavaScript now preserve `sort=latest`, including search/filter and detail return.
- Homepage shows up to six existing visible marked products from a deterministic circular ID list, advancing one position per Palu day at midnight WITA. Adjacent days share five entries when more than six exist. No random reshuffle on reload, cron, mutation, migration or new dependency.
- Six or fewer eligible entries rotate order only. Draft/archived/inactive/hidden remain excluded; sold-out remains discoverable. Empty selection offers the catalog without inventing best sellers.
- Homepage reuses catalog photography, fallback, full names, price/size/availability and native detail links. Existing typography/palette/section layout remain. Horizontal rail has keyboard-accessible arrows, disabled endpoints, reduced-motion handling and mobile gutters/snap padding.

## Verification actually run

- Focused PHPUnit: **16 tests / 138 assertions passed**, covering new default priority across page boundaries, filtering, tie-breakers/null dates, explicit sorts, normalized return context, daily stability/coverage, WITA midnight vs UTC, excluded cohorts, sold-out/missing-media/empty cases and unchanged product rows after homepage requests.
- Full PHPUnit on the isolated main-based branch: **281 tests / 1901 assertions passed**. This branch intentionally does not include PR7's additional Shopee tests.
- Existing Node regressions: **12 passed**. JavaScript syntax checks passed for both changed helpers.
- Scoped Pint and `git diff --check` passed. Vite production build passed; existing DaisyUI `@property` and large about-lanyard chunk warnings remain.
- Chrome viewport checks: **320x740, 375x844, 390x844, 768x900, 1440x900**. Catalog: zero horizontal overflow or clipped names; first page has 24 marked products in the disposable 30-flag fixture. Homepage: six cards, first image loaded, contain fit, zero document overflow, 24px mobile gutters.
- Native page2 navigation: remaining six marked cards precede ordinary cards; native detail and Kembali retain page2/default context.
- Mobile native Terbaru selection -> search `afnan` -> Unisex filter retained explicit latest URL and controls. Desktop price-low showed ascending displayed offer prices; selecting default removed sort query.
- Native homepage photo/card click opened the correct Burgundy detail. Desktop arrow click/keyboard Enter advanced the rail; at start left disabled, at the measured end608 right disabled. Same-day repeat selection remained stable.

Viewport resizing/mouse/keyboard are not genuine touch emulation. Physical iPhone/Safari single-tap/swipe and an actual released midnight rollover are **Not confirmed**. PHP clock-controlled tests prove the date logic. Reduced-motion branch is implemented and syntax/build checked, not exercised via an OS preference switch. Shared failed-image recovery remains existing catalog behavior; no new network-failure browser simulation was performed here.

## Evidence and fixture isolation

Before captures inspect actual production read-only (371 public products). After captures use a disposable copy of the existing historical local launch UI reference (350 published /95 drafts /zero users), with **30 synthetic best-seller flags only in that copy**. Screenshots are layout/query evidence, not current production merchandising or claims that extra products were published. Original fixture/database/photos, production, credentials and API are unchanged. The task's own server, copied database/router/scripts/sessions are removed after verification.

Browser screenshots are JPEG. Explicit viewport clips were used for final captures because the browser's default screenshot intermittently scaled content into a larger white canvas. `browser-checks.json` records observed DOM layout measurements; lazy offscreen images are not claimed loaded simply because a viewport resized.

| Surface | Before | After |
|---|---|---|
| Catalog desktop | [Before](before-catalog-1440.jpg) | [After](after-catalog-1440.jpg) |
| Catalog mobile | [Before](before-catalog-390.jpg) | [After](after-catalog-390.jpg) |
| Homepage desktop | [Before](before-home-1440.jpg) | [After](after-home-1440.jpg) |
| Homepage mobile | [Before](before-home-390.jpg) | [After](after-home-390.jpg) |

Additional after screenshots cover320/375/768. Before viewport images exclude the desktop scrollbar (1425/375px capture widths), while final clips use explicit requested dimensions.

## Files, impact and recovery

Changed: HomeController, ProductController, ProductCatalogState; catalog/home Blade views and shared card heading; catalog-discovery/home-best-sellers JavaScript plus app entry; two feature test files; BUSINESS_RULES, ARCHITECTURE, ADR020, BACKLOG and this evidence. No database/schema/data/media/API/price/stock/URL-path/environment/account/dependency/deployment changes. Homepage selection uses bounded selected-record eager loading rather than loading every candidate's relations.

Rollback: revert this code commit and rebuild assets together, restoring latest-default normalization and prior homepage selection/template. No database or media rollback is required. Do not deploy only the PHP half: server state, Blade options and JavaScript submission defaults must agree.

Recommended next action: Owner review of this concrete PR, followed by separately authorized release readiness/touch verification. P7-09/PR6 click-feedback and P8-09/PR7 admin import remain separate unreleased work; do not merge their behavior or production authorization implicitly.
