# Arsitektur aplikasi saat ini

Konsolidasi AUD-02 — 2026-10-07. Ini peta kode/boundary current, bukan catatan seluruh fase atau bukti runtime baru. Keputusan bisnis: [BUSINESS_RULES](../product/BUSINESS_RULES.md); status: [BACKLOG](../planning/BACKLOG.md); [riwayat sebelum konsolidasi](../history/2026-10-07-context/ARCHITECTURE.md). Journal production release PR31/`3c8d35f` was verified2026-10-08 ([BLOG-06](../verification/blog-06/README.md)); [P9-01](../verification/p9-01/README.md) remains older bounded catalog acceptance, not fresh global closure.

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

## Blog foundation — BLOG-01 installed

```text
Admin session + auth/admin + CSRF -> BlogPostStore/Update/RevisionRequest
  -> SaveBlogPost -> BlogHtmlSanitizer + verified BlogMediaStorage (file IO first)
                 -> transaction + fresh row lock + revision check
                 -> BlogPost + RecordBlogPostChange -> after-commit sitemap invalidation
  -> ChangeBlogPostArchive -> revision + archive/restore-as-draft + same-transaction history
Public /blog/category/detail/sitemap -> BlogPost published/isPubliclyVisible excludes archives
```

Entry: `AdminBlogPostController`, `app/Actions/Blog/`, `app/Services/BlogMediaStorage.php`, `BlogPost` and additive `2026_10_07_000002_add_blog_write_safety.php`. Requests/HTTP feedback remain in controller/request; explicit admin actor, allowlisted writes, stable slug and existing sanitizer retained. New image disk/path explicit, old legacy resolver unchanged when disk null. Old image is not deleted; failure cleans only this write's new upload. Cache error after commit cannot remove attached image. Views/no-op do not change editorial timestamp/revision. Historical editorial time is null until a real editorial change.

Archive leaves publish flag/date intact but public queries exclude it; restore becomes draft. `blog_post_changes` records action/admin/article/revision/time and changed safe metadata/hashes, no cascade/backfill/full request/body/file URLs. Human/machine attribution is implemented by BLOG-05; production machine identities remain unprovisioned. UI adds archive filter, restore, revision conflict/reload and a contained keyboard-scrollable table; no public redesign. [ADR-032](decisions/ADR-032-blog-write-foundation.md), [runbook](../runbooks/BLOG_FOUNDATION.md), [verification](../verification/blog-01/README.md), [approved later scope](../planning/QAMMARIS_JOURNAL.md).

## BLOG-02 — editorial CMS

Installed in production with BLOG-01 through the approved Journal release. Additive migration000003 adds nullable category_id, subtitle/alt/featured/SEO title and managed blog taxonomy/tag pivot without rewriting article rows or the historical category enum. Eager-loaded category relation is authoritative when assigned; null falls back to legacy name. Public category/filter queries support both paths and preserve the four old URLs. Taxonomy management adds immutable names/slugs and reversible active status; no deletion/backfill.

BlogPostStore/Update/PreviewRequest share BlogEditorialRules. SaveBlogPost locks current article/revision, category and selected tags, sanitizes content, validates new publication readiness, syncs tags and metadata with the same blog audit transaction; omitted tags preserve, explicit empty clears. Existing published metadata omissions remain review tasks. No-op/editor load/view does not advance editorial time. Dedicated blog-editor Vite entry imports Tiptap3.31.4 only for create/edit; one shared Blade form and field component provide persistent native sections, visual/HTML modes and HTML fallback.

POST/PUT preview routes remain behind admin/session/CSRF. They render transient data through the same blog.show/RenderBlogContent as public reads, without saving uploads/articles or incrementing views/history. noindex/no-store/no-referrer plus sandboxed desktop/mobile frames. Product markers retain only validated ID; child HTML is discarded and public current name/URL resolved at read time. Rich price/status cards, ordered relations and responsive media are implemented by BLOG-04; no catalog writes. [ADR-033](decisions/ADR-033-blog-editorial-cms.md), [editor guide](../runbooks/BLOG_EDITOR.md), [evidence](../verification/blog-02/README.md).

## BLOG-03 — public Journal and SEO

Installed in production with BLOG-02 through PR31. BlogController shares public/category/search listing and current visibility guard. JournalSearch applies bounded metadata matching to titles/excerpts/active tags/eligible marker-product and brand names; sanitized body is literal normalized phrase only. Candidate scan is linear, product resolution batched; no search infrastructure or new dependency.

RenderBlogContent returns safe HTML, unique H2/H3 anchors, optional TOC after three H2, table overflow wrappers and eligible current product links. Public/preview renderer stays shared; no persisted body rewrite. Dedicated journal.js/CSS load only on public Journal views. Native navigation/search/pagination keep shared destination skeleton; copy has selectable-URL retry and images have one-shot placeholder fallback.

Additive migration000004 adds canonical/OG overrides and index/follow defaults. BlogEditorialRules validates HTTPS URLs without credentials/fragments, SaveBlogPost writes existing revision/audit transaction; sensitive SEO text/URLs are hashes in history. JournalMetadata renders safe BlogPosting/BreadcrumbList based on visible facts. Layout defaults remain for other pages. Sitemap caches XML plus absolute expiry, caps TTL/HTTP max-age at next publication boundary, uses editorial/publication lastmod, excludes nonindexable/external-canonical articles and keeps existing cache invalidation key.

The BLOG-03 scope itself does not implement resize/full product cards/API; BLOG-04/05 supply those features. Existing content/media/IDs/slugs/authors retained; current catalog remains read-only. [ADR-034](decisions/ADR-034-public-journal-search-seo.md), [operator guide](../runbooks/JOURNAL_PUBLIC.md), [verification](../verification/blog-03/README.md).

## BLOG-04 — owned media and components

Installed in production with BLOG-03 through PR31. Additive000005 creates article-owned BlogMedia metadata/variant rows and nullable featured pointer/ordered JSON lists. BlogImageProcessor verifies originals, enforces5MB/6000px/12MP/estimated memory limits, generates immutable bounded WebP original+chosen crop generations and compensates only new derivatives. GD/WebP unavailable uses original with warning; no automatic backfill or purge. SaveBlogMedia scopes parent IDs, revision/row locks and existing transactional audit; featured alt stays synchronized, feature archive is blocked until replacement. Media form saves separately from writing and requires editor reload after revision changes.

BlogComponentRules validates owned active media/gallery and bounded existing catalog/article IDs, structured FAQ/references and no self-reference. Sanitizer retains canonical ID/plain-text markers; RenderBlogContent reconstructs trusted media/gallery/callout/CTA/fixed YouTube/related article HTML and current public catalog cards. Ordered hidden/draft/archive targets disappear on read. JSON IDs normalize to integers, explicit clear differs from omitted fields, and no catalog write occurs. Tiptap atomic nodes and native repeaters preserve visual/HTML content; public native gallery/FAQ remain usable without scripts. Existing preview sandbox limits persist. [ADR-035](decisions/ADR-035-journal-media-and-components.md), [runbook](../runbooks/JOURNAL_MEDIA.md), [evidence](../verification/blog-04/README.md).

## BLOG-05 — draft automation

BLOG-05 code/schema is installed with BLOG-04 through PR31; the production API gate was enabled after separate Owner approval2026-10-08 ([activation](../verification/journal-activation/README.md)). Additive `2026_10_08_000001` creates separate machine actors, Sanctum hashed token records and unique hashed idempotency reservations; nullable blog ownership does not rewrite existing articles. `routes/api.php` adds only versioned draft/media/taxonomy/public-product lookup routes. Feature gate false by default; Sanctum Bearer-only guard never consumes admin cookies. Ability/ownership/state and shared per-actor limits apply. Safe JSON errors/private responses remain scoped to this API prefix; Qammaris App integration routes are unchanged.

SaveBlogPost/SaveBlogMedia share explicit human/machine authorization after row locks and bounded DB deadlock retries; PATCH preserves omitted data. IdempotentBlogWrite reserves a unique key before IO and completes resource pointer/revision inside the shared write transaction via internal callback. Audit/attachment/completion roll back together; newly prepared media compensates on failure. Replay checks current ownership/state. Pending crash needs operator investigation, not automated expiry. RecordBlogPostChange attributes actor_type=machine without a new generic audit layer. AssignBlogDraft provides admin-only draft assignment/revocation with revision/audit, preserving editorial time. BlogAutomation CLI issues only scoped expiring machine tokens after explicit activation; one local machine actor/token was subsequently issued under the explicit activation approval.

[ADR-036](decisions/ADR-036-journal-draft-automation.md), [contract](../api/JOURNAL_AUTOMATION.md), [operator/agent guides](../runbooks/JOURNAL_AUTOMATION.md), [evidence](../verification/blog-05/README.md). Staging MySQL/GD and production migration/release passed in [BLOG-06](../verification/blog-06/README.md); Owner editorial walkthrough remains separate; local credential activation/read verification completed in the later approved operation.

## Runtime, deployment, and verification boundaries (current)

GitHub stores code/locks/docs, not .env/database/uploads. CI builds/tests; production workflow accepts successful same-repo main-push CI, enable flag and production environment; server activation marker/current revision/pending-migration guards apply. Feature-branch build cannot deploy. Even documentation merged to main can trigger this configured path; Owner release approval remains separate.

Last verified production uses per-commit releases/current link with protected shared env and persistent storage. Existing minute scheduler/watchdog maintain a single database worker for app/feed and image queues. Deployment does not reseed/replace runtime data or implicitly migrate; code rollback retains shared DB/media/checkpoints/audits. File Manager is not required for routine code release. [Deployment runbook](../runbooks/HOSTINGER_GITHUB_DEPLOYMENT.md), `.github/workflows/production-release.yml`, `tools/hostinger/`.

The draft-only Journal machine API code/schema is installed in production and disabled by default; production was subsequently activated for one local agent; it is not a catalog/payment/order write API. Device/native upload/new upstream event timing and populated production checkout remain Not confirmed in [P9-01](../verification/p9-01/README.md). Tests, historic release counts and unknowns are evidence at their recorded dates, not fresh production verification.

## Store About — ABOUT-01

`GET /store/about` → `StoreController::about` → existing cached`StoreInfo` read, curated`config/store_about.php`/`store_about_media.php` → Blade About, semantic rating/review partials, page-onlyVite`about.css`/`about.js`. No schema/write/API/CMS abstraction. Story/review changes are reviewed code content; six complete attributed Google Maps reviews are curated from Owner-provided screenshots and escaped through the existing review partial; source/date limits are documented. Twenty-seven WebP480/768/1200-budget derivatives preserve original composition and do not upscale; native master width is the srcset descriptor.

Lightbox uses native`dialog`, click-only links, arrow controls/keyboard, Escape, focus restoration and image-failure text; no-JS image links still open. Native`details` FAQ and anchor navigation remain usable without JS. Instagram iframe is constructed only after explicit click, exact static source allowlisted, with status/timeouts and permanent direct fallback. About no longer loads its3D island; homepage3D is untouched.

Metadata reuses existing layout sections; encodedJSON-LD graph contains AboutPage/Store/BreadcrumbList with visible address/hours and no aggregateRating/review markup. Existing URLs, navbar, skeleton routing, catalog/media storage and order work retained. [Verification](../verification/about-experience/README.md).

ABOUT-01/store-hours/scoped security patches were released through approved PR34–37 and verified live onfb7ed28; [bounded release proof](../verification/about-release/README.md). Persistent env/storage/API identity retained; no migrations or order changes.

## UI-21ST-01 — shared public FAQ and static SVGs (released)

`resources/views/components/faq.blade.php` is the single About/Journal/preview FAQ renderer. Native exclusive `details name` provides disclosure/keyboard/no-JS fallback; shared`faq.css` in appCSS supplies chat-bubble styles. `x-icon` renders a fixed switch of ten trusted21stLucideSVGs, escaped attributes and decorative accessibility; labels remain on containing controls. About/Journal/editor/photo UI use it; stored HTML is not rewritten. Trusted inline Journal article/video components now use Blade partials to render the same icons after sanitization.

About reuses existing responsive images, native dialog/Reel code and page-only JS. Fluid grid supports explicit per-item expansion at larger widths, retaining all nine photos; phone links still open the full photo with one tap. Every gallery/timeline/hero/visit photo uses contain/native ratio. Alternating timeline preserves current story and adds Owner-confirmed2024/February2026 dates. No React/new dependency or persistence layer. [Source and bounded verification](../verification/faq-icons/README.md).

Owner-approved PR39 deployed3063d63, verified live390touch/1440 with persistent DB/media/env retained; [release proof](../verification/faq-icons/RELEASE.md). Physical-device and other program acceptance remain separate.

## UI-21ST-02 — shared FAQ motion correction (released)

`x-faq` wraps answers in a flow-root/overflow-contained panel; common `ui/faq.js` progressively enhances native details with400ms height/opacity matching the actual21st source. Enhanced group exclusivity allows outgoing close motion; unenhanced markup retains native name. Rapid retargeting preserves visible progress and settles to natural height, with inert/aria state, reduced-motion/resize/pagehide handling. Shared public/admin app bundle covers About, Journal and preview. About copy is direct and uses “outside Palu”/“Mengenal parfum saat merantau”; timeline dates/media/SEO boundaries unchanged. [Verification](../verification/faq-motion/README.md). No data layer or new dependency.

Owner-approved PR41 deployed8e1455b and actual motion/copy verified live390touch/1440; [release proof](../verification/faq-motion/RELEASE.md). Persistent data/media/env retained; physical Safari and other program acceptance remain separate.

## ABOUT-02 — cinematic About source ports (implementation, release pending)

About remains server-rendered Blade with curated config and page-only CSS/JS. Ten retrieved21st sources ported to existing native/GSAP boundaries; [mapping/receipts](../verification/about-cinematic/sources.md). `about-navigation.js` shares one rAF for progress/scroll-spy and retains native fragment/history. `about-motion.js` is dynamically requested only for desktop≥1100px/850px-height/non-reduced motion; GSAP matchMedia bounds one480px pin and reverts on responsive/preference/pagehide changes, returning on persisted pageshow. Mobile/small-height/no-JS remain readable. `about.js` retains fluid expansion, adds category-aware native-photo navigation and portrait opt-in native Reel dialog with immediate frame cleanup/reopen safety. Existing shared FAQ400ms is unchanged. Seven unique visible photos; gallery is four construction/design assets, posters retained on disk. No data/persistence/dependency/URL change; production approval pending. [Bounded verification](../verification/about-cinematic/README.md).
