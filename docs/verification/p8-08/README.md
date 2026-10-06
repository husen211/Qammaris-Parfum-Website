# P8-08 — Admin app inbox, recurring drafts and prices

Status: IN_REVIEW; local implementation verified, no production deployment.
Date: 2026-10-06. Branch: `codex/admin-app-inbox`, based on `codex/catalog-navigation` / 30ff559 (PR4 dependency).

## Outcome and UX rationale

The old product page offered manual creation and an unexplained curated CSV importer; synchronized app records were not a recurring draft workflow. The new **Produk dari aplikasi** inbox shows cached source records, publication/readiness, price issues, held identities and hidden tombstones. New source UUIDs become drafts via the existing worker. Valid source selling prices follow automatically for existing connected products, as explicitly approved by Owner. Source-driven fields are clearly separated from website editorial work.

Operators complete photos, description, explicit size/classification and publication in the existing editor. Connected price is read-only and enforced by the shared offer operation, not just HTML. Connected availability is shown without the legacy 36-hour explanation/manual controls; local quantity is not presented as app inventory. The inbox groups work with simple links/search rather than adding a decorative dashboard. Buttons/new links have 44px targets, visible keyboard focus and inherited mouse-only hover guards. Editor cancel/save retains the inbox's allowlisted page/search/status context.

Historical snapshots have an explicit immutable preview/confirm/apply. Candidate matches stay held; no identity rebinding or automatic publish. Manual sync queues the existing job and reports safe retry feedback if dispatch fails. Canonical CSV is clearly labelled supplemental; a raw Shopee XLSX uploader is not implemented here.

## Changed modules

- `SyncQammarisAppFeed`, `PrepareQammarisAppDrafts`: reuse draft creation in the checkpoint/source transaction; human-bound historical preview/apply.
- `ApplyQammarisAppAvailability`, new `SyncQammarisAppPrice`, `SyncSingleOffer`: source price authority, last-valid retention, comparison-price cleanup, before/after audit and shared-caller protection.
- New `AdminQammarisAppProductController`, admin routes, inbox Blade: cached GET, auth/admin/CSRF writes, queued sync, filtered pagination and preview confirmation.
- `AdminProductController`: bounded inbox return context. Existing media controller passes it through to the editor; no arbitrary redirects.
- Admin layout/products index/editor/media/CSV-create views: new entry points and source/storage ownership copy.
- `QammarisAppCatalogWorkflowTest`, updated `QammarisAppIntegrationTest`: new approved behavior and regressions.
- BUSINESS_RULES, ARCHITECTURE, BACKLOG and ADR-025 record the approved change and release boundary.

## Tests actually run

Final complete `php artisan test --compact`: **275 passed / 1830 assertions**, 10.48s. Narrow workflow/catalog tests: 23 passed / 184 assertions. Coverage includes new/partial/hidden/oversized source records, old revisions, protected fields/offer IDs, invalid prices, rollback of taxonomy/product/identity/source/audit/checkpoint, shared price enforcement, admin authorization/read-only GET, actor-bound accepted confirmation, stale previews, idempotent apply, queued sync and safe dispatch failure, escaped stored source text, malformed query arrays, editor ownership and manual photo/content HTTP save without price override/publication.

`node --test tests/js/*.test.mjs`: **12 passed**. Scoped Pint `--test` including both controllers/actions/routes/tests: passed. `git diff --check`: passed. `npm run build`: passed (Vite7.3.6); existing DaisyUI `@property` optimization warning and large about-lanyard chunk remain unrelated.

During verification an old media-copy assertion failed after wording changed; the retained-file sentence was preserved and all tests passed. An initial new fake-image test required unavailable GD; it now uses a real small PNG fixture without installing GD/packages.

## Real browser evidence and isolation

Chrome, desktop1440x900 and mobile320x844/390x844 (Chrome emits some390 captures at843px). No horizontal overflow measured on inbox/editor at checked widths. Screenshots are raw browser captures; no image editing. Two demonstration/preview captures have a722px browser capture height and are supplementary, not the900px baseline.

The local port8002 fixture copied the prior445-product UI reference, then added synthetic sources/UUIDs and disposable records only. **450 source records shown in screenshots are mocked, not current production counts or a live API test.** Of these, synthetic new-feed processing created one draft; historical preview held one legacy candidate and created two additional drafts on explicit confirmation. Hidden source stayed a tombstone. Fixture counters change during this sequence (96 ->98 connected drafts), explaining different captures. No production/env/server/credential/legacy-local DB/media mutation. The synthetic admin, records, sessions, router and isolated SQLite fixture were removed and the owned server stopped afterward. No photo was uploaded into prior catalog media through the browser.

Browser checks actually observed:

- Entry from website product list to source drafts, clear offline/configuration-disabled sync state,25-row pagination against450 mocked sources.
- Draft search `LOCAL DEMO`, filter links retaining search, reset and zero-results state.
- Preview2 shows two candidates and one held legacy match; accepted checkbox/apply shows success and two unpublished drafts. Server tests separately prove stale/actor/replay guards.
- Draft page2 opens Monaco editor; cancel returns to `/admin/app-products?page=2&status=draft`.
- Hidden filter shows exactly the synthetic tombstone. No auto-create/edit action offered for unmapped hidden data.
- Connected editor shows readonly180000 and source availability, without legacy36-hour/manual stock controls. Draft readiness/media empty state observed.
- Mobile menu opens and source link measures44px; mouse clicks work. No touch/Safari claim.

Screenshot links:

| Evidence | File |
| --- | --- |
| Old products desktop/mobile | [1440](before-products-1440.png), [390](before-products-390.png) |
| Updated products mobile | [390](after-products-390.png) |
| New inbox desktop/mobile | [1440](after-inbox-1440.png), [390](after-inbox-390.png), [320](after-inbox-320.png) |
| Demonstration drafts / preview | [Drafts1440](after-inbox-demo-1440.png), [Preview1440](preview-1440.png) |
| Connected editor mobile | [390](editor-390.png), [320](editor-320.png) |
| Empty search / mobile menu | [Empty390](empty-390.png), [Menu320](mobile-menu-320.png) |

## Limits and remaining release checks

Browser file chooser rejected upload because the Chrome extension lacks file-URL permission; no permission was changed. Connected manual image upload/storage is proved through an HTTP feature test, **not browser upload success**. Browser control later detached before final viewport-reset confirmation; no production page was changed. Actual iPhone Safari/genuine touch-emulation single-tap and scroll verification remains the inherited P7-08 release gate. Current Blade document navigation still remounts the navbar.

No raw Shopee XLSX UI, photo/description curation, automated publish or new manual identity-rebinding UI. Held matches need explicit operator mapping through the existing operation. No claim of production automatic draft/price activation: this branch is unreleased. Newer source revisions update prices; historical mismatches are not replayed automatically. Cached unlinked snapshots use explicit preview. Historical production price drift is Not confirmed.

## Data impact, rollout and recovery

No migrations, dependency changes, schema/data/media/env operations on staging/production. When deployed, newer feed revisions may create drafts/taxonomy/UUID/offer rows or update selling/base/comparison prices and existing audit rows; stock contract/checkpoint discipline remains. Existing names/slugs/IDs/media/publication stay protected. Inspect held/price-review groups after approved release; do not reset cursor for a broad replay.

Review PR4 dependency and touch gate before approving release of this patch. On approved release, restart the normal queue worker through the existing deployment workflow, then verify a controlled new product creates one draft, source price changes apply, media saves and owner-driven publication remains explicit. No credentials or separate scheduler are introduced.

Rollback is a code revert through the existing release flow. Retain any newly created drafts/identities/media/audit/checkpoints; do not delete records or rewind source cursor. Reverting stops automatic draft/price behavior; a forward fix can resume from preserved state. No production rollback was performed or needed.

Recommended next scoped backlog item, not started: P8-09 raw Shopee media/basic XLSX supplemental preview and guarded enrichment.
