# AUD-03 — Shopee review summaries and media recovery

Date: 2026-10-07. Owner approval: “Berikutnya AUD-03”. Status: local implementation verified, branch review pending; no production deployment or data operation. This evidence does not close P9 device/live acceptance.

## Outcome and scope

Previously controller and Blade independently classified work/complete/ready rows and re-evaluated publication readiness. `ShopeeContentReview` now produces one read-only summary for totals, filtering and rendering. `EvaluateProductPublicationReadiness::forReview` checks distinct targets once with a grouped duplicate-slug query using the same rules as `handle`. Write-time apply/publication continues using fresh `handle`; the read-only snapshot does not authorize a mutation or refresh a source fingerprint.

Failed downloads and the cover dependency offer retry/manual upload. Protected target/media/capacity holds ask for editor review or both new exports, preserving existing photos. New Shopee blocked outcomes add a reason to existing JSON only; old outcomes are not rewritten. Legacy blocked records default to review unless they carry the exact known cover-dependency message; terminal errors without usable outcomes also require review. Mixed failed/protected outcomes require review first. Filter `images_failed` retains its URL/meaning with the clearer label **Foto bermasalah**. Batch retry remains available for safe failed rows alongside holds; guards at enqueue and attach remain authoritative. Pending-content recheck does not reset an applied-image baseline.

Changed code:
- [Review summary](../../../app/Services/ShopeeContentReview.php)
- [Readiness](../../../app/Actions/Products/EvaluateProductPublicationReadiness.php)
- [Controller](../../../app/Http/Controllers/Admin/AdminShopeeContentController.php)
- [Blade review](../../../resources/views/admin/shopee-imports/index.blade.php)
- [Queue outcomes](../../../app/Actions/Products/QueueProductImportImages.php)
- [Worker outcomes](../../../app/Actions/Products/AcquireProductImportRowImages.php)
- [Regressions](../../../tests/Feature/ShopeeContentImportTest.php)

No dependencies, migrations, route shapes, environment files or publication rules changed. Actor ownership, content-v2, size confirmation, source prices/status, append-only media, partial apply and replay protections remain covered by existing tests.

## Measurement

One375-row synthetic batch and375 synthetic products in a separate SQLite database, same GET review/render scope, cold per-request relations and query logging:

| Measure | Before | After |
|---|---:|---:|
| Review queries |408|25|
| Slug check queries |375|1|
| Work rows |6|6|
| Complete rows |369|369|

Fixture cases:369 published complete products; failed cover with dependent additional photo; changed website media hold; complete draft;100ml source/75ml website candidate; queued draft; legacy pending proposal. Eager-loading duplicate objects may remain bounded per relationship, but readiness is evaluated once per distinct product. Disk presence checks remain proportional to distinct targets, not eliminated. This is not a production MySQL or latency benchmark. The regression asserts a bounded query threshold (40) and one grouped slug query, not an exact query count.

## Checks actually run

- `php artisan test --filter=ShopeeContentImportTest`:37 passed,242 assertions.
- Targeted Pint on the six changed PHP/test files: passed after formatting.
- `php artisan test`:345 passed,2384 assertions,123.37s.
- `node --test tests/js/*.test.mjs`:31 passed.
- `npm run build`: passed; existing DaisyUI `@property` optimizer warning and large 3D chunk warning remain. Those unrelated bundles were not restructured.
- `git diff --check`: passed.

New regressions cover six legacy/structured outcome combinations, conservative unknown terminal errors, unchanged product attributes after GET, query bounds at375 rows and fresh publication rejection when category becomes inactive after review. Existing tests exercise size confirmation, actor/child scope, partial apply, no-op, retries, occupied identity, stale content/media and preserved source pricing/status.

## Browser proof

Real Chrome, local isolated375-row fixture at390×844 and1440×900. Actual viewport resizing with mouse/keyboard, not touch/iPhone simulation. Verified recovery messages for failed cover versus changed-media hold, `Foto bermasalah` containing both rows, complete filter/pagination25 rows and next page, ready draft, candidate search narrowing to75ml, visible size confirmation, queued status, empty search result and disabled zero-apply. Keyboard activation of description disclosure retained visible focus. No captured console errors. Main scrollWidth equaled clientWidth:360 at mobile and1154 at desktop; no horizontal overflow on the inspected states.

| Before | After |
|---|---|
|[Mobile](before-mobile.png)|[Mobile](after-mobile.png)|
|[Desktop](before-desktop.png)|[Desktop](after-desktop.png)|

Screenshots focus the protected-media row and recovery guidance. The batch still has a separate retryable row, so its global retry button correctly remains visible. Local primary photos were tiny synthetic files to exercise file existence/metadata; screenshots are not photography or CDN verification. Import POST/worker failures and protected attachment behavior were verified by feature tests, not external production writes. Native file chooser/upload, real touch and live production outcomes remain unconfirmed.

## Data impact, cleanup and recovery

Production/database/media/credentials were not accessed or changed. Existing local catalog was not replaced. Temporary loopback server8013, synthetic admin/products/import batch, isolated database/media/session/runtime files were removed after browser verification. Any fallback local sessions bearing the exact temporary origin were removed; no broad session cleanup. Viewport restored and test tab closed. Pre-existing unrelated untracked files retained.

No rollback migration needed. Code revert retains data, media and audit; the optional JSON reason is backwards-readable by older code. A release rollback should stop the image worker through the established runbook and preserve successful outcomes. Prefer forward fix/retry over deleting batches or images. Deployment and resolution of existing production holds require separate authorization.

Documentation updated: [backlog](../../planning/BACKLOG.md), [architecture](../../architecture/ARCHITECTURE.md), [ADR-026](../../architecture/decisions/ADR-026-recurring-shopee-content-import.md), [Shopee runbook](../../runbooks/SHOPEE_ADMIN_IMPORT.md). No business decision changed, so BUSINESS_RULES and MASTER_PLAN remain untouched. Recommended next item: AUD-04 related catalog cards, after Owner direction; not started here.
