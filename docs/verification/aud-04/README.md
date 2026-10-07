# AUD-04 — Shared related-product cards

Date: 2026-10-07. Owner approval: “lanjut aud 04”. Status: IN_REVIEW; local code and stated checks complete, device/release acceptance remains open. No production deployment or next-item execution.

## Outcome and scope

The detail page was the only rendered consumer of the old `_card`: portrait `cover`, independent typography/status styling, no catalog-image recovery hooks. Actual Chrome baseline showed square source photos (900,637,640,1024px) in276×369px portrait frames. The section now includes [the existing catalog card](../../../resources/views/products/_catalog-card.blade.php), explicitly passing h3, visible price and lazy loading. [Detail include](../../../resources/views/products/show.blade.php) and [regressions](../../../tests/Feature/PublicProductDetailTrustTest.php) are the only changed application/test files, plus removal of the obsolete view. No new component abstraction, JS/CSS behavior or package.

Existing square containment and conservative white-margin balancing retain the bottle/packaging; long names wrap instead of the old two-line clamp. Source-aware labels, active-offer price/size, best-seller badge, stable native image/name link and keyboard focus come from one implementation. The existing two-column mobile/four-column desktop section, same-brand query/order/limit, published/hidden scope and normalized query context remain. Home still hides price; catalog/detail show it. Main detail gallery is untouched.

## Checks actually run

- Before UI replacement: detail regressions2 failed/8 passed (97 assertions): missing h3 and missing image-recovery hook. These failures establish the old-card gap.
- `php artisan test --compact --filter='PublicProductDetailTrustTest|PublicCatalogCardTrustTest|PublicCatalogStateTest|HomeBestSellersTest'`:18 passed/205 assertions. No class named PublicCatalogStateTest exists; state/discovery checks were run separately below.
- `php artisan test --compact --filter='PublicCatalogDiscoveryTest|PublicCatalogHardeningTest'`:12 passed/99 assertions. Total30 targeted Laravel tests/304 assertions; no full-suite rerun claimed.
- `node --test tests/js/*.test.mjs`:31 passed, covering existing image bounds, keyboard/pointer return, click feedback, hover guard and navigation skeleton behavior.
- Targeted Pint on the changed PHP test: passed. `git diff --check`: passed.
- `npm run build`: passed. Existing DaisyUI `@property` optimizer and large3D chunk warnings remain unrelated to this change.

New related-section regressions retain context/offer, one native product link/no nested button, h3, lazy square image dimensions, initial placeholder and recovery hooks, escaped long/imported names, best-seller badge, app available without expiry, sold-out+OTW, app unknown and legacy stale copy. Draft/hidden/other-brand products remain excluded; the empty section is absent. Test media are synthetic fake-disk files, not an HTTP failed-image execution.

## Browser evidence

Real Chrome on the existing local site127.0.0.1:8001, whose catalog displayed350 products. Representative detail: Rahasia Ceremony, with Singel Glow/Love Petals/Goddess Water/Dreamy Matcha related cards. No fixture replacement or production access. Viewport resizing uses mouse/keyboard; it is not touch emulation.

| Viewport | Observed result |
|---|---|
|390×844|Two columns;166×166 square frames; contain/lazy; document clientWidth/scrollWidth375/375|
|1440×900|Four columns;278×278 square frames; contain/lazy; three assets balanced and one safely original; document1425/1425|
|320×844|Two columns;130×130 frames; document305/305|
|375×844|Two columns;158×158 frames; document360/360|
|768×844|Four columns;162×162 frames; document753/753|

Additional measurements: [responsive-checks.json](responsive-checks.json). No horizontal overflow on these rendered states. Availability/price remained aligned; imported product photography and packaging stayed visible. Mouse click on Love Petals image opened its matching heading/slug with one click. Tab reached the related link with visible solid focus and Enter opened the same detail. Native history back returned to Ceremony without pending feedback (observed count0). A later related-link click retained `search=Rahasia&sort=latest&page=2` and its Kembali href retained those same values.

| Before | After |
|---|---|
|[390 mobile](before-390.png)|[390 mobile](after-390.png)|
|[1440 desktop](before-1440.png)|[1440 desktop](after-1440.png)|

The black rectangle in baseline is keyboard focus from Tab, not evidence of a touch hover bug. After screenshots show the unchanged public identity with shorter square frames and the complete photography. A reload during the Vite build temporarily encountered unavailable local assets; final screenshots were replaced after the build completed and styles were confirmed.

Limits: browser control intermittently timed out, then reported its debugger detached during the final Kembali check. That final click was not confirmed; href and server context checks passed, and earlier native back passed. Console-log collection and final viewport-reset/tab-close confirmation were unavailable. No claim of console-clean runtime, restored viewport, physical Safari, touch scroll/single tap, failed-image HTTP fallback execution or live production verification. Missing media/error hooks/empty/source variants were verified by feature tests; image geometry/containment and existing balancing by browser plus Node tests. These limits keep the item IN_REVIEW and require device evidence before a separately authorized release.

## Data impact and recovery

No database/schema/import/API/source price/status/publication/media changes, credentials, new account, fixture record or server. Existing local detail GETs naturally increment view_count under the unchanged controller; no counter rewrite. PHPUnit used its configured isolated SQLite/fake storage. Local build regenerates ignored build artifacts; no production files changed. No temporary catalog/server cleanup needed; pre-existing unrelated `tools/__pycache__/` remains untouched.

Rollback is a code revert restoring the old view/include, followed by the normal approved asset build/release. No data migration, media restore, checkpoint rewind or database rollback is needed. Release remains separately authorized; this feature branch is stacked on AUD-03 for a bounded diff.

Documentation updated: [BACKLOG](../../planning/BACKLOG.md), [ARCHITECTURE](../../architecture/ARCHITECTURE.md), one stale “not started” master-plan flow label, and the audit's old-view source link pinned to Git history. Business rules and ADRs unchanged. Recommended next: AUD-05 editor/request duplication, only with Owner direction; not started.
