# P8-09 — Recurring Shopee admin import

Date: 2026-10-06. Status: **IN_REVIEW, not deployed**. Branch: `codex/admin-shopee-import`; based on the unreleased P7-09 branch/PR6. Production approval for the preceding exact21 publication is not approval for this code release.

## Outcome and scope

Owner can upload original Informasi Dasar + Media XLSX, review product recognition/content/photo proposals, manually choose unresolved products, apply enrichment, retry photos, and explicitly publish selected ready products. Price/availability remain app-owned; source names/brands/slugs and old media are retained. Shopee products enrich existing UUID catalog products rather than create independent duplicates. Old CSV path remains reachable.

No migrations, package installation, production/staging application/data writes, environment/permission/credential changes or deployments in this implementation phase. Original Owner files are unchanged. The previous completed supplement's last verified371published/77draft catalog is historical evidence, not a new live measurement this turn.

## Evidence

- New reader successfully read original **371 Basic +371 Media** records. Media SHA256 `2710531916279ce70019bfa97220b6c55b870f819d939a4dceee3b909c57c70d`; Basic SHA256 `dc2774e82193a1ae8cbc4ce9487f804343a68ba4513cccd4b5dc0af3141682bf` unchanged. Media has instruction rows immediately after the visible header, supported explicitly; other missing product IDs are rejected.
- Real Chrome on loopback isolated Laravel/SQLite with450synthetic app-linked products. No real account/catalog/database/media was used for mutations; photo downloads were HTTP-faked to a valid1pixel PNG, not real product photography.
- Preview450:449recognized/1manual; review filter returns1; search narrows target options; explicit choice updates proposals to450recognized without product apply. Search no-result state checked with keyboard Enter.
- Initial sync-default test applied450content rows but exceeded HTTP60second execution limit after369photos. This uncovered a real configuration hazard. New-contract jobs now use database connection regardless of default sync. Repeat450apply completed, queued81remainingphoto jobs; isolated database worker drained81->0 and completed450photos, **0automatic publications**.
- Browser selected two ready products, explicitly confirmed, received **2produk berhasil diterbitkan**. Remaining448selection controls tested select-all/clear without publication. No automatic global Unisex or guessed price.
- Real browser paginationpage2 shows25rows startingDemo026; manual editor displays source-readonly price250000; Cancel restores batch2/page2. Stored photo URLs use local `/storage/products/…`, load successfully and are displayed with cleaned description.
- Mobile390x844 and320x740, desktop1440x900: no horizontal overflow in checked populated/empty states. Long names wrap. Confirmation and busy feedback; ready-list scroll and44px controls; native form clicks/keyboard submission used. These are mouse/viewport checks, **not touch/iPhone Safari proof**.

## Tests and checks actually run

- Full Laravel suite: **304tests /1951assertions passed** before adding the final three regression cases.
- Final focused suite (Shopee, legacy image acquisition, staging pairing, editor return): **50tests /252assertions passed**, including **24new Shopee tests /108assertions**. Cases cover actual multipart upload, mismatched pair, instruction rows, formula/entity/emptyXML/duplicateID/SSRF, escaped imported text, payload tamper, stale targets, occupied identities, hidden sources, invalid source price, preserved content/media/URLs/status, missing offer using source price, failed-cover retry, database queue selection/dispatch failure, atomic failed publish, replay, human ownership/child scoping and editor return safety.
- Node regressions: **20passed**.
- Vite build passed; existing DaisyUI `@property` optimizer warning and large unrelated about-lanyard bundle warning remain. No package changes.
- Scoped Pint and `git diff --check` passed.

## Screenshots and UX rationale

Before is the existing production CSV import page, inspected read-only. After uses450synthetic records in isolated local execution; it is not a production data comparison. Clear step labels replace operator CSV work with native XLSX upload, visible scope/changes, searchable manual choice, retry, and separate bulk publication. Stored images are shown only after download; preview does not hotlink or make network calls.

- [Before desktop](before-desktop.png), [after desktop](after-desktop.png):1440x900.
- [Before mobile](before-mobile.png), [after mobile](after-mobile.png):390x844.
- [Publish selection](publish-selection-mobile.png):390x844;448ready test products, no selection auto-published.
- [Stored copy/photo](stored-content-mobile.png):390x844; blank-looking photo is deliberately a synthetic1pixel fixture, not a failed Shopee product photo.
- [Empty search](empty-mobile-320.png):320x740.

## Limitations and release gates

1. Native Chrome file chooser failed because extension **Allow access to file URLs** was disabled. No extension permission changed. Multipart server upload tests passed; browser chooser upload is **Not confirmed**. Previously supplied documented remedy: chrome://extensions -> ChatGPT browser extension -> Details -> Allow access to file URLs; Owner-controlled permission decision, not a feature-code change.
2. Actual touch/Safari is **Not confirmed**. Earlier PR4/5 waiver does not automatically waive this release's gate.
3. Hosting->Shopee CDN timed out during the prior exact21 operation. Current tests mock image HTTP and do not prove production reachability. Before release, verify bounded real download plus active database `product-import-images` worker; manual-upload fallback/retry is available. Do not claim remote automatic images ready100%.
4. XLSX first sheet/original headers are supported; export layout changes require forward fix. Two files must have equal ID/name sets. Media capacity remains3; replacement/full-slot management is the existing editor. No live Shopee API/watcher.
5. Worker hard crash can leave processing metadata requiring existing failed-job recovery. No new queue watchdog, cron, process-manager or source API settings changed in this phase.

## Files/modules and documentation

New: `ShopeeContentXlsx`, `ShopeeProductCopy`, `ShopeeContentPreviewer`, `ShopeeContentImageTarget`, `ApplyShopeeContent`, admin Shopee controller/requests, Blade import page, XLSX test helper and feature tests. Changed: shared queue/acquisition guards, safe editor return path, admin navigation/legacy CSV explanation, routes and CI PHP extension list.

Updated: BACKLOG, BUSINESS_RULES, ARCHITECTURE. Added ADR-026 and SHOPEE_ADMIN_IMPORT runbook. No changes to app feed, database schema, public UI or media provider. Pre-existing exact21 audit/report/operator changes are prior work, separately identified in Git history where committed.

## Recovery and next item

Code rollback retains successful content/identity/media/audit; stop image worker during controlled rollback, forward-fix and retry only failed photos. Never delete products/media, reset feed checkpoint or restore old catalog as a feature-code rollback.

Recommended next item: bounded release readiness for this reviewed implementation — resolve PR6 dependency, verify PHP extensions/image worker/CDN/download and actual upload/touch gates, then seek concrete deployment approval. No next phase or production release started.

Cleanup verified: owned PHP loopback server stopped; isolated synthetic database/user/products/media/sessions/router removed after checking the exact absolute fixture path. Browser viewport override reset and final owned preview tab closed. Existing local user preview/server and production/staging data were not removed.
