# Repository maintainability audit — 2026-10-07

Status: investigation complete, **AUD-01 IN_REVIEW**. This report proposes follow-up work; no application refactor or deployment is included. Owner explicitly advanced to this audit after P9-01. P9's remaining device/upload/event limits are not silently closed.

Follow-up: AUD-06 now implements the final whole-rupiah rule and current-catalog cart totals on its review branch; see [verification](../verification/aud-06/README.md) and [ADR-030](../architecture/decisions/ADR-030-whole-rupiah-and-current-cart-totals.md). Findings below describe the original audited baseline, not a production release claim.

AUD-07 follow-up: narrowly scoped admin product/media history is implemented on its review branch with Owner-approved no-auto-delete retention; [verification](../verification/aud-07/README.md) documents AUD-F11 coverage and exclusions. Original audit findings remain dated baseline evidence.

## 1. Executive assessment

Keep the current Laravel monolith. The repository already has useful boundaries: Form Requests, Eloquent scopes/relationships, focused product operations, guarded import batches, two database queues, and a Laravel filesystem adapter. A rewrite, generic repository layer, new search engine or new framework would add complexity without solving a demonstrated current requirement.

The most urgent maintainability issue is **contradictory current context**. A new agent can read the beginning of AGENTS, README or BUSINESS_RULES and implement an old inquiry/proposed-price/cloud-storage flow, despite later approved decisions and deployed code. Consolidating current rules and separating dated history should come before broad code cleanup.

The highest-value code follow-ups are narrowly bounded: remove business evaluation from the Shopee review Blade, retain one reusable catalog-card contract, and simplify duplicated editor plumbing while preserving source-price/media/publication guards. Long import files contain real validation/concurrency logic; their length alone does not justify dismantling them.

No Critical finding is established. Priorities below indicate practical maintenance impact, not vulnerability severity.

## 2. Baseline and limits

- Application code audited: deployed PR15 revision `0dc6924479ae108177c467a380b3978b1f89c70b`; current audit branch also carries P8/P9 documentation awaiting review in PR16. This audit does not redeploy it.
- `composer.json`: PHP `^8.2`, Laravel `^12.0`; lockfile resolves Laravel **v12.69.2**, Sluggable **3.8.1**, Flysystem S3 adapter **3.35.3**. These are repository versions, not claims about newest releases or vulnerability status.
- `package.json`: Node >=22.12.0 <25, npm10.9.8, Vite7, Tailwind4/DaisyUI5, vanilla catalog/admin JavaScript, limited React/Three islands. No package installation/update performed.
- Latest production data evidence is the dated [P9 report](../verification/p9-01/README.md): 448 products, 375 public, 73 drafts, 1,119 active local image records. These were not freshly exported/recounted in this audit.
- Source review covers routes/bootstrap/auth, Product and related schema, public catalog/cart, admin editor/requests, Shopee preview/apply/media guards, app sync/price operations, shared helpers, JS entry/navigation, representative regression tests, CI/release packaging and current document structure. Existing launch-only tooling was inventoried, not executed.
- Not a line-by-line proof of every historical script, dependency security audit, production SQL benchmark, disk-capacity review or infrastructure penetration test. Dependency vulnerabilities and production MySQL concurrency under load are **Not confirmed** here.
- No credentials, environment values, request bodies, customer data or raw source snapshots were printed or included.

## 3. How the application currently works

```text
Public GET /products + validated GET context
  -> ProductController -> published/hidden scopes + identity search + active-offer filtering/sort
  -> Eloquent/MySQL pagination -> Blade catalog card -> native detail link

Detail GET /products/{slug}
  -> route model binding -> visibility guard -> offer/media/related eager load
  -> Blade detail/gallery + related-card partial -> current catalog return context

Admin session -> auth + admin middleware -> Form Request
  -> editor controller orchestration -> SyncSingleOffer / AttachProductImage / PublishProduct
  -> DB transaction + filesystem compensation -> Blade / redirect

App HMAC webhook -> raw-body signature/input checks -> database job -> 202
  -> same SyncQammarisAppFeed used by 30-minute reconciliation
  -> checkpoint lock -> validate/store UUID snapshots -> draft creation or availability/price apply
  -> before/after app audit + atomic checkpoint -> public queries

Shopee XLSX pair -> bounded reader -> actor-owned immutable preview rows
  -> human choice/size confirmation/recheck -> ApplyShopeeContent
  -> current-source/product locks + content fingerprint + row audit
  -> photo queue -> bounded downloader -> website disk -> guarded append
  -> separately selected readiness-checked publication

Cart session IDs/quantities -> current published offer resolver
  -> available-only checkout + recipient Form Request + review fingerprint
  -> WhatsApp composer redirect; no payment/order reservation or persistent order database
```

Sources: [web routes](../../routes/web.php), [ProductController](../../app/Http/Controllers/ProductController.php), [Product](../../app/Models/Product.php), [AdminProductController](../../app/Http/Controllers/Admin/AdminProductController.php), [feed](../../app/Actions/Products/SyncQammarisAppFeed.php), [webhook](../../app/Http/Controllers/Integrations/QammarisAppWebhookController.php), [Shopee controller](../../app/Http/Controllers/Admin/AdminShopeeContentController.php), [CartController](../../app/Http/Controllers/CartController.php).

There is no website product-mutation REST API or unrestricted automation database interface established by these routes. The internal-app feed is an inbound consumer integration. Queries in controllers are ordinary Laravel usage; routes contain registrations rather than product business mutations. The `inspire` console closure is unrelated and harmless.

## 4. Product/data boundaries worth retaining

- Internal product/offer IDs and slugs are stable. `Product::getSlugOptions()` disables slug regeneration on update; external provider UUIDs do not replace website IDs.
- One product has one size/price: the additive [single-offer migration](../../database/migrations/2026_09_16_000002_make_product_drafts_and_single_offers_explicit.php) has a unique product_id constraint. ProductVariant is compatibility detail, not an invitation to introduce a new multi-variant UI.
- Offer price is public authority. `base_price` is a compatibility mirror/initial incomplete-draft value. [SyncSingleOffer](../../app/Actions/Products/SyncSingleOffer.php) enforces cached connected source prices; [SyncQammarisAppPrice](../../app/Actions/Products/SyncQammarisAppPrice.php) retains invalid/hidden-source data rather than guessing.
- Publication and source visibility/availability remain separate. Sold-out is discoverable; hidden tombstones do not destroy catalog identity. Unknown does not become available by default. Connected statuses have no expiry; legacy/manual freshness still exists.
- [Publication readiness](../../app/Actions/Products/EvaluateProductPublicationReadiness.php) checks required copy/taxonomy/offer/primary file. [ProductMediaStorage](../../app/Services/ProductMediaStorage.php) uses Laravel disks, validates canonical paths, checks storage failures and rejects remote delivery paths.
- [AttachProductImage](../../app/Actions/Products/AttachProductImage.php) and image lifecycle operations lock owning products/children and protect the last published primary photo. Media archives retain files. Database transactions do not make filesystem IO atomic; existing compensation and post-download attach checks are necessary.
- Import batches/rows carry source/proposal fingerprints, actor, outcomes and before/after snapshots. Content-v2 intentionally excludes independent price/status changes. Do not merge this with the broader maintenance fingerprint or remove safety checks as apparent duplication.

Existing foreign keys/cascades are not evidence that archive-only admin deletes production rows. Raw out-of-band deletion would have different consequences; no deletion/migration is proposed in this maintainability item. Removing compatibility columns, offer tables, audit fields, or changing IDs/slugs would be **High migration risk** and is not needed to address the findings below.

## 5. Technical debt register

All findings are source-proven unless a limit is stated. S/M/L are relative implementation scope, not promised delivery dates. No follow-up is executed by this report.

| ID | Finding and evidence | Concrete impact / risk | Priority | Recommended direction | Dependency | Scope |
|---|---|---|---|---|---|---|
| AUD-F01 | Current rules conflict with initial text: [AGENTS product rules](../../AGENTS.md), [BUSINESS_RULES availability/price and inquiry sections versus later P8-08/P7-12 decisions](../product/BUSINESS_RULES.md), [README deployment](../../README.md), [MASTER_PLAN R2/inquiry goals](../architecture/MASTER_PLAN.md). | Agent/operator may reintroduce price approval, inquiry-only checkout or cloud migration. High context risk, no proven new runtime fault. | High | Rewrite current summaries from accepted decisions; explicitly mark historical decisions. Keep status-only app availability distinct from numerical/reserved inventory. | Owner decisions already recorded; exact current-state review | S |
| AUD-F02 | [BACKLOG](../planning/BACKLOG.md) has about 2,700 lines with implementation histories before its board; both [checkout ADR-026](../architecture/decisions/ADR-026-whatsapp-order-checkout.md) and [Shopee ADR-026](../architecture/decisions/ADR-026-recurring-shopee-content-import.md) share a number. Several released ADR headings still say pending. | Slow/ambiguous onboarding and references. Medium document risk. | Medium | Short current index/read order; compact active board; link dated evidence rather than duplicate it. Give ADRs unique references with retained aliases/history; verify inbound links before any rename. | F01 | M |
| AUD-F03 | [Shopee index](../../app/Http/Controllers/Admin/AdminShopeeContentController.php) lines40–62 evaluates readiness while scanning all rows; [Blade](../../resources/views/admin/shopee-imports/index.blade.php) lines62/67/121 parses size, calls readiness and cleans source copy again. Readiness performs slug existence and storage checks. | View rendering owns business evaluation; duplicated work and possible inconsistent row summaries. Per-row IO proven; current production latency impact not measured. | Medium | Compute one explicit read-only row summary in the existing controller/service boundary, pass display data to Blade, reuse the same readiness operation. Keep fresh write-time checks. | Preserve P8-10 work/complete semantics | M |
| AUD-F04 | [AdminProductController](../../app/Http/Controllers/Admin/AdminProductController.php) store/update plus helper methods combine listing/context, product field assembly, manual availability, offer/media orchestration, transaction handling, cleanup and publication errors. | Several reasons to change one controller; duplicated upload/rollback paths are fragile to extend. Medium behavior risk. | Medium | First remove obsolete conversational comments; then isolate shared editor payload/notes and, if reused by both writes, one focused editor-save operation. Keep redirect/query concerns in controller. | F01; transaction/media regression baseline | M |
| AUD-F05 | [ProductStoreRequest](../../app/Http/Requests/Admin/ProductStoreRequest.php) and [ProductUpdateRequest](../../app/Http/Requests/Admin/ProductUpdateRequest.php) repeat field limits/messages/compare-at validation. Update has deliberate ownership/current-taxonomy/published exceptions. Create/edit Blade also duplicate core fields. | Fixes can drift; blindly merging rules would remove valid differences. Medium maintenance risk. | Medium | Share only stable rule/message/field fragments. Keep two requests and explicit create/update behavior; avoid a generic form builder. | F04 contract and validation tests | M |
| AUD-F06 | [ShopeeContentImageTarget](../../app/Services/ShopeeContentImageTarget.php) resolves the write operation `ApplyShopeeContent` only to call its payload assertion; the [operation](../../app/Actions/Products/ApplyShopeeContent.php) and controller resolve collaborators with `app()` inside methods. | Read/guard code depends on a larger write orchestrator; dependencies are less visible. No unsafe mapping proven. | Medium | Inject real existing collaborators. Consider one small shared Shopee proposal validator only because write and image checks are two actual callers. Keep distinct import contracts. | F03; tamper/actor/media guard tests | S–M |
| AUD-F07 | [Detail related include](../../resources/views/products/show.blade.php) line299 uses [old _card at the pre-AUD-04 revision](https://github.com/husen211/Qammaris-Parfum-Website/blob/a3cfc8dad49b7371f43a92ad8ec48effdee66cff/resources/views/products/_card.blade.php): portrait/cover/independent presentation; catalog/home use [shared _catalog-card](../../resources/views/products/_catalog-card.blade.php): square/contain/fallback controls. | Fixes to card presentation do not reach related products. Actual source and Chrome computed cover confirmed; no claim every asset is cropped. Medium UI regression risk. | Medium | Reuse the current card for related products with heading/context options; preserve links, availability, lazy loading and keyboard focus. No broader detail redesign. | F01; actual related/browser regression tests | S |
| AUD-F08 | [CartController](../../app/Http/Controllers/CartController.php) add/update/remove return global `cart_total()` from stored session prices; page/checkout correctly resolve current prices. [Helper](../../app/Helpers/helpers.php) totals raw session values. | Legacy JSON can diverge from authoritative checkout after a price sync. Visible customer mispricing through this response is **Not confirmed**; current cart-page JS reloads. | Nice to Have | Trace consumers, then derive totals from resolved items or stop exposing unused totals. Keep server quote protection. Do not trust session prices. | Cart response compatibility tests | S |
| AUD-F09 | Decimal(10,2)/numeric editor validation permits fractional prices, but [cart resolver](../../app/Http/Controllers/CartController.php) casts offer price to int while `format_rupiah` rounds to zero decimals. | Deterministic fractional counterexample:100.90 displays101, cart uses100. Current source price contract is integer; affected production values **Not confirmed**. Medium edge-case correctness risk. | Medium | Decide a single money unit/rounding rule before changing behavior. If whole rupiah only, validate it consistently; otherwise preserve decimals through totals. No schema change needed by default. | Owner money-unit decision, fractional regression test | S |
| AUD-F10 | `Route::resource('products', AdminProductController::class)` registers admin show, but PHP Reflection confirms no show method. [Routes](../../routes/web.php) also retain the intentionally disabled unscoped legacy image-delete route. | Unused/broken route surface; no active UI link to show found. Low exposure; actual request failure not exercised on production. | Nice to Have | Exclude unsupported show explicitly. Inventory legacy callers before removing/redirecting disabled image route. Leave scoped image operations authoritative. | Route regression tests | S |
| AUD-F11 | Feed/import writes persist audits; manual editor updates and [image lifecycle](../../app/Actions/Products/ArchiveProductImage.php) do not persist a comparable before/after actor record in their code. No general product observer/audit model found. | Harder to reconstruct human media/copy changes behind an import conflict. Medium operational gap, not proof of unlogged infrastructure activity. | Medium | Add narrowly scoped attribution when editing product content/media next requires it; reuse a suitable audited operation, or separately approve a minimal audit table. Exclude customer data/credentials. | F04; retention/business decision; additive migration if needed | M |
| AUD-F12 | [Shopee image-target guard](../../app/Services/ShopeeContentImageTarget.php) rejects changed baseline; [review Blade](../../resources/views/admin/shopee-imports/index.blade.php) offers retry alongside re-upload. [P9](../verification/p9-01/README.md) observed two real held covers. | A download retry can repeatedly hit the same content conflict. Medium recovery/UX confusion, existing photos protected. | Medium | Show target-conflict recovery separately from transport retry. Preserve baseline and human review; do not force overwrite or clear guards. | Error classification/guard tests | S |
| AUD-F13 | [Product search scope](../../app/Models/Product.php) loads eligible metadata before paginating ranked SQL results. [Shopee preview](../../app/Services/ShopeeContentPreviewer.php) calls fresh/fingerprint/images per product despite eager loads; review scans entire batch. | Cost grows with eligible catalog/batch; actual few-hundred-catalog delay not benchmarked. Low current scale risk. | Nice to Have | Measure queries/time at375/1000 rows, then remove repeated immutable reads in the read path only. Preserve fresh write guards; no search engine/Redis by default. | F03; reproducible SQL/query benchmark | M |
| AUD-F14 | [Product legacy price-range accessors](../../app/Models/Product.php) retain base-price fallbacks/multi-price naming; admin list calls price_range, public cards use activeOffer. Helper/class/data attributes still contain inquiry terminology. [AppServiceProvider](../../app/Providers/AppServiceProvider.php) uses silent schema-error fallback and cached store data. | Compatibility names can mislead; silent fallback obscures diagnosis. No public-price fallback or current provider outage proven. | Nice to Have | Trace real consumers before removal/rename; clarify legacy roles, retain route/session/DOM compatibility. Add bounded, safe diagnostics only if operational need demonstrated. | Current document contract; consumer inventory | S–M |

## 6. Controller/model/duplication diagnosis

**God controller:** AdminProductController is the clearest candidate for responsibility cleanup, not a justification for a site-wide service layer. Store and update are two real callers of shared editor logic. AdminShopeeContentController's GET summary is a second bounded hotspot; its mutation methods already delegate to operations. Public ProductController and HomeController are reasonably focused.

**Fat model:** Product contains relations, casts, scopes, compatibility pricing, publication transitions and effective availability. There is no demonstrated reason to replace it with a domain framework. Its price-range compatibility methods and search scan deserve targeted review; size alone is not a defect. Keep SQL and accessor availability semantics aligned.

**Duplicated queries/validation:** Shopee readiness, copy/size preparation, per-product fingerprint images and editor compare-at rules are concrete duplicates. Intentional repetitions that revalidate before a write, after network IO or inside a lock are safety checks and must remain. Similar-looking launch/canonical/maintenance/Shopee operations have different identity and mutation rules; a single generic importer would make those rules harder to see.

**Business logic in Blade:** Shopee readiness and parsing are actual business evaluation, with query/storage side effects. Availability color maps/card formatting are presentation and do not need a new layer automatically. No product-write logic in routes was found.

**Mass assignment/authorization:** Controllers use explicit product field arrays, requests, or action-validated forceFill, rather than unchecked `$request->all()`. Broad model fillable lists are not automatically exploitable. Admin routes require auth+role; import operations bind actor+contract+batch/child ownership. New endpoints must preserve those boundaries. Inline login validation is small and does not require a dedicated login service. No new authentication vulnerability is proven by this maintainability review.

**Logging/transactions:** Feed failure stores a safe generic code and rethrows a generic error; this protects payloads but limits root-cause diagnosis. Media storage reports failures; controller rollback compensates newly stored files. Do not replace sanitized operational errors with raw response/request/secret logging. Manual actor audit coverage and provider fallback observability should be addressed independently of transaction restructuring.

## 7. Pragmatic target and tradeoffs

| Current | Recommended bounded direction | Why worth the complexity / what it costs |
|---|---|---|
| Current rules plus dated contradictory appendices | Short current rules/read order plus linked decisions/evidence | Prevents wrong implementations. Costs careful reconciliation and link checking, not a new runtime system. |
| Controller and Blade separately evaluate Shopee rows | One read-only row-summary helper at existing admin boundary | Two real consumers need identical state. Adds one small helper/data array contract; avoids DTO factory/presenter hierarchy. Write checks remain fresh. |
| Product editor repeats payload/upload/transaction glue | First shared stable payload helpers; one focused save operation only if it simplifies both store/update | Two actual callers justify reuse. Adds a callable boundary and test responsibility; no generic CRUD repository. Keep IO compensation/locks explicit. |
| Two actively rendered card templates | Current shared Blade card reused by related section | Demonstrated drift. Costs option/context tests; no component framework migration. |
| Mutation operation also supplies import payload validation to image guard | Injected small shared contract validator if extraction stays focused | Two real callers need identical tamper checks. Costs one class; reject a generic policy engine. |
| Different prices/old names/session totals across compatibility paths | Document authority; trace consumers; change one response/rounding policy at a time | Avoids a high-risk simultaneous money/cart rewrite. Costs regression cases and an explicit money decision. |

All of these remain Laravel-native. Keep `app/Actions/Products`, existing services, Form Requests, models, Blade and small JS modules; no directory-wide rename is needed. Introducing repositories, commands/events for every write, DTOs for every array, global state machines or new packages is not supported by this evidence.

## 8. Documentation authority and session entry plan

The repository already has the files the Owner wants; do not create `agent.py.md` or a second business-rules source.

| Document | Intended role after a separately approved consolidation |
|---|---|
| AGENTS.md | Short agent constraints + read order + document ownership; current product summary must agree with accepted decisions. No implementation diary. |
| README.md | How to run/check this project and where current context lives; accurately state GitHub deployment and persistent website media. |
| docs/product/BUSINESS_RULES.md | Current enforceable domain decisions with explicit exceptions, separate from launch-wave histories/counts. |
| docs/architecture/ARCHITECTURE.md | Current executable flows, real boundaries and source references, rather than sequential release history. |
| docs/architecture/MASTER_PLAN.md | Program goals, dependencies and remaining closure gates; historical overrides clearly linked. |
| docs/planning/BACKLOG.md | Compact statuses/active item/acceptance/dependencies and links to evidence; preserve historical records in place or by verified moves. |
| docs/architecture/decisions/ADR-*.md | One unique stable identifier per significant decision; supersession links, not silent deletion of old reasoning. |
| docs/runbooks/ | Current operational steps, safe recovery and environment limits. Dates/counts are evidence, not universal values. |
| docs/verification/ and docs/audits/ | Dated tests/screenshots/production observations and investigations; never mistaken for current configuration. |

Proposed new-session read order: AGENTS -> current BUSINESS_RULES -> current architecture summary -> active backlog item -> only the relevant ADR/runbook/source/tests. MASTER_PLAN remains the program reference. No new “context memory” file is necessary unless this navigation still proves insufficient.

For each future feature: identify one backlog item; update business rules only for an approved decision; update the current architecture if a boundary changes; add an ADR only for a significant tradeoff; update a runbook only for operations; record actual validation/limits; keep detailed code history in commits/PRs. Do not append the full same story to every document.

## 9. Things we should NOT refactor

- Laravel/Blade/Eloquent/MySQL, full-document native links and GET catalog state. No SPA/router rewrite to achieve perceived speed.
- Existing UUID/provider mappings, optional SKU, IDs, slugs, source revisions/checkpoint and content-v2 protection.
- ProductVariant into Product, base_price removal, audit-table redesign or normalization for its own sake.
- HMAC raw-body/timestamp verification, enqueue-before202, shared worker lock, page transaction and reconciliation. Preserve signature/revision/no-op coverage.
- Product media disk abstraction, primary/last-image/archive checks, bounded downloader and append guards. No R2/cloud migration just because an older plan preferred it.
- Shared PHP/JS search fixtures: duplication across runtimes is deliberate. Hundreds of products do not establish a need for Elasticsearch/Meilisearch.
- Limited React/Three islands or public visual identity during a backend cleanup. Heavy about-page assets should be measured separately before removal.
- Launch-only scripts/historical migrations/evidence simply because production launch is complete. They retain provenance and safety gates; inventory/deprecate before separately approved cleanup.
- Checkout into a payment/order subsystem. Boolean availability is not numerical stock/reservation, and payment remains a separate business/schema project.
- Existing tests replaced by implementation-mirroring tests or snapshots only. Preserve behavioral guards and meaningful regression scenarios.

## 10. Proposed follow-up order

These are proposals, not active implementation. Each needs its own scoped backlog acceptance and regression plan.

| Item | Objective / changes | Dependency | Production/migration risk | Definition of done |
|---|---|---|---|---|
| AUD-02 | Consolidate current context across AGENTS/README/business rules/architecture/board; unique ADR references with retained history | AUD-01 review; accepted Owner decisions | Low data risk; no schema/code/data changes. Merging main still triggers deploy unless independently authorized. | Fresh agent can identify current app price/draft/order/media flow from short entry path; contradictory current rules removed or marked historical; links/evidence preserved. |
| AUD-03 | Shopee review summaries and distinct stale-media recovery; eliminate evaluation from Blade | AUD-02; F03/F12 | Medium behavior risk; no migration | Work/complete counts, size-confirm, actor scope, no-op, partial apply and retry/conflict states unchanged except clearer recovery; bounded query measurement and real375-row browser checks. |
| AUD-04 | Reuse catalog card for related products | AUD-02; F07 | Low data / medium UI risk; no schema | Same source-aware labels/links; whole contained media/fallback/long names; keyboard/reduced motion and agreed mobile/desktop checks; release touch limit explicitly handled. |
| AUD-05 | Editor duplication cleanup with explicit dependencies | AUD-02; F04/F05/F06 | Medium transaction/media/source-price risk; no migration assumed | Create/update/publish/draft, parent-owned offer, connected prices, rollback cleanup and context tests pass; no loss of IO/concurrency guards. |
| AUD-06 | Set money unit; resolve any legacy JSON/session total mismatch | AUD-02; Owner money decision; F08/F09 | Medium business behavior risk; data audit before enforcing new validation | Fractional/whole-price cases have agreed behavior; current-price checkout quote protection intact; all consumers traced. |
| AUD-07 | Narrow actor audit coverage/diagnostics, only if needed | AUD-05; retention/scopes decision; F11 | Medium; additive schema may require separate data/migration review | Human content/media changes attributable without secrets/customer payload logging; retention/recovery documented. |
| Later measured work | Query/read optimization and compatibility route/name cleanup | Relevant benchmark/consumer evidence; F10/F13/F14 | Low–medium; no speculative indexes or package changes | Reproducible improvement without changing identity/search/publication/guard semantics. |

Dependency order: **current context -> bounded review/card fixes -> editor mutation cleanup -> money/audit decisions -> measured optimization**. P9's remaining device/upload/new-source-event acceptance remains a separate open track; this audit does not pretend it is implementation debt already resolved.

## 11. Verification actually run

- Local `C:/xampp/php/php.exe vendor/bin/phpunit --colors=never`: **339 tests / 2,306 assertions passed**, PHP8.2.12/PHPUnit11.5.56,19.123seconds. Existing phpunit.xml uses SQLite in-memory, array sessions/cache, no development/production database connection. No production writes/tests performed.
- `node --test tests/js/*.test.mjs`: **31 passed / 0 failed**. These include shared search fixtures, navigation/hover guards, image bounds and cart feedback behavior.
- PHP Reflection/autoload check: AdminProductController has no `show` method; routes resource registration independently inspected. No production request to the unsupported endpoint made.
- Pure arithmetic check confirmed100.90 -> formatted101 versus int100. It is an edge-case counterexample, not a production monetary discrepancy report.
- Chrome production detail/related section at actual **390×844 and1440×900**: computed `object-fit:cover` on active related image confirmed; main catalog uses contain by source/P9 evidence. Desktop document client/scroll1425/1425. [Mobile](../verification/aud-01/related-390.jpg), [desktop](../verification/aud-01/related-1440.jpg). Visible black ring is intentional keyboard focus from native Tab during inspection, not proof of stuck touch-hover. No UI changed, so these are baseline screenshots; no “after” screen exists. PhysicalSafari/touch not tested.
- New documentation link/whitespace checks recorded with the final audit commit. No package audit, Vite rebuild or new test-writing was needed for this investigation-only change.

Known limits: source growth cost not benchmarked; real MySQL deadlock/load behavior not newly tested; fractional production prices not inspected; newest upstream contract not reread from the other project; no credentials/infrastructure touched. Runtime numbers use the explicitly dated P9 observation. Historical accepted decisions do not waive future production deployment gates.

## 12. Outcome and recommended first implementation

Only audit documentation, its backlog/master-plan checkpoint and two baseline screenshots change. Application/database/product/media/configuration remain untouched; an ordinary detail visit may increment the existing view counter. No application rollback is required. Revert the documentation commit to remove this report if necessary, retaining historical evidence rather than deleting data.

Recommended first implementation is **AUD-02 current documentation consolidation**, not broad refactoring. It solves the proven stale-context problem without business/data/schema risk and gives future agents a short reliable starting path. Code refactors start only after a concrete follow-up scope is reviewed. No next item has been started.
