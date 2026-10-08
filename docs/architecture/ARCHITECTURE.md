# Arsitektur aplikasi saat ini

Konsolidasi AUD-02 — 2026-10-07. Ini peta kode/boundary current, bukan catatan seluruh fase atau bukti runtime baru. Keputusan bisnis: [BUSINESS_RULES](../product/BUSINESS_RULES.md); status: [BACKLOG](../planning/BACKLOG.md); [riwayat sebelum konsolidasi](../history/2026-10-07-context/ARCHITECTURE.md). Baseline production terakhir diamati adalah PR15/`0dc6924` dalam [P9-01](../verification/p9-01/README.md); angka/status live dapat berubah.

## Stack dan batas tanggung jawab

Laravel 12 / PHP 8.2+, Blade, Eloquent, MySQL/MariaDB, Tailwind 4/DaisyUI/Vite. React islands terbatas (navbar/elemen 3D), bukan router SPA. Form requests menangani HTTP validation; focused product operations pada `app/Actions/Products` menangani mutasi yang dipakai lintas form/import/feed. Services menangani parsing, snapshots, storage, dan client source; Support menangani query state/search/copy. Tidak ada generic repository atau event bus yang perlu ditambahkan.

## Katalog dan detail publik

```text
GET /products -> web route -> ProductController + ProductCatalogState
              -> published/visible eligibility + offer/brand/media Eloquent
              -> SearchMatcher + normalized filters/explicit sorting
              -> pagination -> Blade catalog + _catalog-card
GET /products/{slug} -> visibility check -> current offer/media/availability
                     -> Blade detail + validated catalog return context
```

File entry: `routes/web.php`, `app/Http/Controllers/ProductController.php`, `app/Support/ProductCatalogState.php`, `app/Support/SearchMatcher.php`, `app/Support/CatalogAvailability.php`, `resources/views/products/`.

Search reads scoped eligible metadata, scores all terms with exact/partial before bounded alphabetic typo matching, restricts IDs and binds relevance ordering in SQL. Numbers/SKU/UUID remain strict. Explicit sort stays authoritative; no-search default is best sellers then stable latest. Client option search uses equivalent matcher/shared fixtures. Description matching and external search infrastructure are absent from this current discovery path. Linear metadata cost is suitable for the observed hundreds, not an unmeasured scale guarantee ([ADR-027](decisions/ADR-027-relevant-typo-tolerant-search.md)).

GET state is allowlisted and reused in controls, pagination and detail return, with canonical detail URL free of query. Native browser navigation remains. Normal eligible detail GET can increment view_count. AUD-04 reuses `_catalog-card` for the detail's existing four eligible same-brand related products, with h3 headings, visible active-offer prices and lazy images. Square contain/whitespace balancing, image fallback and native-link feedback are shared with catalog/home; the unused `_card` is removed. Query/order/context are unchanged. Implementation evidence: [verification](../verification/aud-04/README.md); [production release](../verification/audit-release/README.md).

`HomeController` selects at most six eligible best-seller IDs using a daily circular window in Asia/Makassar and eager-loads those records. No write/cron/global timezone change. The homepage uses catalog-card presentation without price; catalog/detail retain price.

## Admin product, taxonomy, and media

```text
Session login -> auth + admin authorization -> admin controller/FormRequest
              -> shared offer/readiness/publication/media operations
              -> Eloquent transaction + Laravel product disk -> Blade result/context
```

Entry files: `app/Http/Controllers/AuthController.php`, `app/Http/Controllers/Admin/`, `app/Http/Requests/Admin/`, `routes/web.php`. AUD-05 routes both editor writes through `SaveProductEditor`: explicit field/notes payload, transaction, offer/media/publication orchestration and new-file rollback cleanup. `ProductEditorRequest` shares stable rules/messages/comparison validation while store/update retain draft/publish, current taxonomy, owned offer and image/availability differences. HTTP redirect/context and friendly errors stay in the controller; Blade is unchanged. Existing `SyncSingleOffer`, `AttachProductImage`, `EvaluateProductPublicationReadiness`, `PublishProduct` and media lifecycle operations remain authorities. Source-connected price protection stays inside the shared offer mutation. Editor file IO remains inside the DB transaction as before; failure compensation only covers new stored paths. [ADR-029](decisions/ADR-029-shared-product-editor-save.md), [AUD-05 verification](../verification/aud-05/README.md); [approved production release](../verification/audit-release/README.md).

One product retains one active `ProductVariant`; offer is selling-price authority, base_price compatibility mirror. Draft-without-size preparation may retain source price without inventing an offer. IDs, slugs, active/archived media and provider identities survive ordinary archive/code rollback. Brand/category archive/constraints protect existing relations.

`ProductMediaStorage` resolves the configured Laravel disk (`config/media.php`, `config/filesystems.php`); public URLs come from storage, never domain-hardcoded cloud code. Last verified production uses persistent public storage. R2 adapter/copy-verifier remain available from earlier staging work; no current R2 migration requirement. New file IO must be checked/cleaned on failed attachment; soft-archived files are retained for recovery.

## App feed, webhook, and automatic drafts/prices

```text
Internal app -> POST /integrations/qammaris-app/webhook
             -> raw-body HMAC + timestamp/payload bounds + throttle
             -> database qammaris-app job -> HTTP 202
Job / 30-minute schedule -> SyncQammarisAppFeed -> read-only HTTPS cursor feed
                         -> validate page -> checkpoint/source/product locks
                         -> cached UUID snapshots + identity lookup
                         -> visible new UUID: PrepareQammarisAppDrafts
                         -> mapped product: availability + SyncQammarisAppPrice
                         -> audit + checkpoint commit together
```

Entry: `routes/integrations.php`, `app/Http/Controllers/Integrations/QammarisAppWebhookController.php`, `app/Services/QammarisAppClient.php`, `app/Actions/Products/SyncQammarisAppFeed.php`, `routes/console.php`. Integration route is outside session/CSRF web middleware; HMAC protects this single receiver. Machine API key/secret stay config/env and are read-only upstream. 202 acknowledges a persisted job, not completed sync.

Revision equal/older is no-op. Feed stores hidden/active/merged/source/ETA metadata; visibility is separate from publication. Connected status has no expiry/outage downgrade. Valid newer source price updates existing selling/base/comparison price through shared operations; missing/invalid/hidden source retains price for review. Existing names/slugs/media/publication are not overwritten. New drafts parse explicit size/concentration; duplicate candidates are held, not rebound or published.

Admin app inbox reads cached source metadata, not live upstream writes. Manual sync queues the same job; historical unlinked snapshots require actor-bound immutable preview/apply. Details: [ADR-025](decisions/ADR-025-recurring-app-catalog-admin-workflow.md), [integration runbook](../runbooks/QAMMARIS_APP_INTEGRATION.md). Staging-only launch CLI contracts remain restricted; production recurring flow does not remove those guards.

## Imports and audit

```text
Auth/admin + CSRF -> bounded upload/preview -> actor-owned batch + rows
                 -> explicit guarded apply -> shared product operations -> audit
Applied image candidates -> database product-import-images jobs
                         -> allowlisted downloader -> product disk
                         -> guarded append/attachment -> per-slot outcome
```

Canonical provider CSV and internal-ID bulk maintenance remain distinct contract versions with persisted source/hash/state/actor guards and idempotent transactions. Read-only snapshot/report exports are allowlisted and formula-safe. Protected resolution and maintenance do not mutate publication/availability/slug/media/identities through unintended fields. Details: [ADR index](decisions/README.md), ADR-011–019.

Recurring Shopee entry: `AdminShopeeContentController` + Shopee requests → `ShopeeContentXlsx`/`ShopeeContentPreviewer` → actor-owned immutable `shopee-content-v1` source/rows, newer `content-v2` proposals → `ApplyShopeeContent`/external identity/offer operations. Paired Basic/Media files join by product code. Exact existing Shopee identity precedes conservative unique matching; human choice resolves ambiguity. Size override needs per-row explicit confirmation and excludes mismatched source description.

AUD-05 extracts `ShopeeContentGuard` for the unchanged batch actor/contract and row payload assertions reused by writes, image queue and image target. Existing write collaborators and controller image queues are injected; the image target no longer resolves the content-write orchestrator. No import contract/lock/dispatch changes.

Content fingerprint excludes price/stock/timestamps it does not write, protects identity/size/content/publication/hidden/media baseline, and revalidates payload integrity. Explicit recheck changes proposals/audit only. Recoverable row conflicts are held; safe rows proceed; no-op makes no product/media/job writes. Photo queue/attach repeats source/media guards and appends up to three without replacing cover. Publication is a separate readiness-checked selection. Network IO remains outside business transactions.

AUD-03 uses `ShopeeContentReview` for one read-only row summary shared by controller totals/filtering and Blade rendering. Distinct targets use `EvaluateProductPublicationReadiness::forReview` with one grouped slug query; write operations still call fresh `handle` readiness. GET does not refresh proposal fingerprints or mutate products. New Shopee blocked-image outcomes carry a reason; review distinguishes retryable download/cover dependency from protected target/media/capacity holds, with conservative legacy fallback. An applied-image hold needs editor review or a new export pair, not pending-content recheck. No new import framework or schema. Local375-row proof408→25queries: [AUD-03](../verification/aud-03/README.md); [approved production release](../verification/audit-release/README.md). [ADR-026](decisions/ADR-026-recurring-shopee-content-import.md), [Shopee runbook](../runbooks/SHOPEE_ADMIN_IMPORT.md), [P8-10 proof](../verification/p8-10/README.md).

AUD-07 adds `RecordProductAdminChange` and one additive `product_admin_changes` table. Editor saves require the authenticated actor explicitly, lock/read current product state, and record one combined committed outcome. Gallery primary/move/archive and direct restore pass that actor to existing operations; `ArchiveProduct` coordinates its state/history transaction. Changed field groups only, description/notes/SKU/key hashes and active-gallery IDs/order/primary metadata; no request body, file/URL or customer/user credential fields. Audit failure rolls back the same transaction and uses the existing new-file cleanup; unchanged operations create no record. Imports/feed retain their original separate audits and non-human callers do not invent an admin actor. IDs intentionally remain historical without FK deletion coupling; no history route/model observer/generic framework, backfill or automatic purge. Authenticated account identity does not prove physical human identity or distinguish shared-session automation. [ADR-031](decisions/ADR-031-admin-product-change-history.md), [runbook](../runbooks/ADMIN_PRODUCT_AUDIT.md), [verification](../verification/aud-07/README.md); [approved production release](../verification/audit-release/README.md).

## Cart, recipient checkout, and UI navigation

```text
Detail add -> validated cart POST -> session IDs/quantity + confirmed feedback
Navbar cart link -> GET /cart -> current public offer/availability resolver
GET checkout -> server/session review fingerprint -> recipient Blade form
POST checkout -> CheckoutRequest -> current catalog + review guard
              -> InquiryWhatsApp::orderUrl -> WhatsApp composer redirect
```

`CartController`, `app/Http/Requests/Cart/`, and `app/Support/InquiryWhatsApp.php` retain routes and numeric cart JSON fields. Public labels use keranjang/pesanan; helper name is historical. Only available, published, positive-price offers proceed. Required name/phone/address and bounded optional postcode/note are not stored in order/customer tables or application logs; failed validation may use temporary session old input. No-store/no-referrer responses limit application/browser caching. Cart remains after redirect because actual WA sending is unobservable. No numeric reservation/payment/order ledger ([ADR-028](decisions/ADR-028-whatsapp-order-checkout.md)).

AUD-06: `Support/Rupiah` shares the whole-price input rule across editor/CSV/`SyncSingleOffer`, and converts stored decimal rupiah to integer hundredths for exact multiplication/sums. Canonical decimal strings bind the quote and feed Blade/WhatsApp formatting; numeric compatibility JSON casts occur only at output. Add/update/remove totals now resolve every remaining item from current catalog data; an unresolved cart returns `cart_total: null`, never a partial/stale total. `/cart/data` retains its 409 review response. The unused session-price `cart_total()` helper is removed after tracing consumers: product JS uses success/count, cart-page JS reloads, navbar history refresh uses count only. Whole prices display without decimals; hypothetical legacy fractions retain exact display/arithmetic and are flagged in the editor, with no data rewrite. Cart wrapping handles longer totals on small screens. [ADR-030](decisions/ADR-030-whole-rupiah-and-current-cart-totals.md), [verification](../verification/aud-06/README.md); [approved production release](../verification/audit-release/README.md).

`product-cart.js` runs confirmed-success Web Animations flight with reduced-motion fallback; `cart-page.js` serializes quantity/removal mutation feedback. Header points directly to /cart, not drawer. Shared layout destination skeletons observe native same-tab links/valid submits; pageshow resets pending state and timeout recovery never replays POST. No HTML-fetch/swap router; Blade navigation recreates document/navbar DOM. Touch rules/device limits remain explicit, not inferred from mouse viewports.

## Online orders — ORD-01 review branch, not deployed

```text
Admin session + auth/admin + CSRF -> AdminOnlineOrderController + OnlineOrder*Request
  -> CreateOnlineOrder (published active offer -> item price snapshot, hashed + encrypted tokens)
  -> OnlineOrderWorkflow (row lock, revision/from-stage guard, event in same transaction)
GET/POST /pesanan/{token}        -> OnlineOrderController (customer details until paid, status)
GET/POST /tugas-pesanan/{token}  -> OnlineOrderStaffController (shipping steps + fee advance only)
Views: OnlineOrderTimeline (per-audience allowlist) + x-order-timeline, OnlineOrderMessages -> copy/wa.me
```

Tables `online_orders`, `online_order_items` (snapshot, historical catalog IDs without cascade), `online_order_events` (append-only timeline/audit). Token lookup uses the SHA-256 hash; the `encrypted` cast copy lets admins re-copy links; regeneration invalidates. Bearer pages are throttled and send no-store/no-referrer/noindex, with a neutral 404 for unknown/expired/replaced links. `InquiryWhatsApp` gains `textUrl`/`shareUrl`/public `plainText`; the cart checkout path is unchanged and still stores nothing. No package, queue, scheduler, payment, or Majoo integration. [ADR-034](decisions/ADR-034-online-order-links-and-tracking.md), [runbook](../runbooks/ONLINE_ORDERS.md), [verification](../verification/ord-01/README.md).

## Admin access — ORD-02a review branch, not deployed

```text
Login (email|username, no remember-me) -> session auth_version + last activity
admin.* routes -> auth + AdminMiddleware (active, auth_version, 12h idle, must-change-password)
               -> can:{catalog|blog|orders|users}.manage (+ orders.finance) -> FormRequest gate -> action gate
AuthorizationServiceProvider: super_admin | staff_order | admin (legacy) | customer
ManageAdminUser: lock all Super Admins + target, last-Super-Admin guard, auth_version bump, user_admin_changes in transaction
qammaris:grant-super-admin: explicit, previewed, audited bootstrap (actor null)
```

A route-table test fails if any new `admin.*` route lacks its area ability. ORD-01 order operations use `orders.manage`, `orders.finance`, and the order-aware `orders.cancel`. [ADR-035](decisions/ADR-035-admin-roles-and-user-management.md), [runbook](../runbooks/ADMIN_ACCESS.md), [verification](../verification/ord-02a/README.md).

## Runtime, deployment, and verification boundaries

GitHub stores code/locks/docs, not .env/database/uploads. CI builds/tests; production workflow accepts successful same-repo main-push CI, enable flag and production environment; server activation marker/current revision/pending-migration guards apply. Feature-branch build cannot deploy. Even documentation merged to main can trigger this configured path; Owner release approval remains separate.

Last verified production uses per-commit releases/current link with protected shared env and persistent storage. Existing minute scheduler/watchdog maintain a single database worker for app/feed and image queues. Deployment does not reseed/replace runtime data or implicitly migrate; code rollback retains shared DB/media/checkpoints/audits. File Manager is not required for routine code release. [Deployment runbook](../runbooks/HOSTINGER_GITHUB_DEPLOYMENT.md), `.github/workflows/production-release.yml`, `tools/hostinger/`.

No controlled website write API, AI integration or payment gateway has been implemented. Device/native upload/new upstream event timing and populated production checkout remain Not confirmed in [P9-01](../verification/p9-01/README.md). Tests, historic release counts and unknowns are evidence at their recorded dates, not a claim of fresh production verification during this documentation task.
