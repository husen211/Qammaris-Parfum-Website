# Current application architecture

## Best-seller discovery (P7-10, 2026-10-06)

`ProductCatalogState::DEFAULT_SORT` is `best_sellers`, omitted only when it is the default. `ProductController::applySort` prepends the existing boolean flag to the stable latest ordering; explicit latest/price/popular retain their own order. Blade controls, filter forms, JavaScript submissions, pagination and detail-return context share that normalized choice. Legacy `sort=latest` URLs remain valid and explicit.

`HomeController` reads eligible public best-seller IDs in stable order, takes a date-dependent circular window of at most six, then eager-loads only the selected product/brand/image/offer records. The offset advances daily in Asia/Makassar, without writes, cache invalidation jobs, new tables or global timezone changes. The existing homepage section reuses `products._catalog-card` with h3 headings, local media fallback, contain/white-margin balancing, price/size/source-aware availability and one native link per card. A scoped vanilla-JavaScript rail helper updates disabled arrow edges and honors reduced motion; horizontal native scrolling remains available without JavaScript. Other homepage sections and admin flags remain unchanged.

Implementation/review evidence: `docs/verification/p7-10/README.md`. No production activation is implied.

## Qammaris app consumer (P8-01, 2026-10-05)

```text
Internal app -> signed POST /integrations/qammaris-app/webhook
            -> raw-body HMAC + bounded validation + throttle
            -> database queue qammaris-app -> 202

Webhook job / 30-minute scheduled reconciliation
            -> SyncQammarisAppFeed -> read-only HTTPS changes feed
            -> validate page -> lock/check local checkpoint
            -> cache latest allowlisted UUID snapshots
            -> existing qammaris_app identity mapping
            -> ApplyQammarisAppAvailability
            -> product availability/hidden/ETA + before/after audit
            -> commit page checkpoint atomically

Public catalog/inquiry -> Eloquent published scope / isPubliclyVisible
                      -> exclude upstream hidden tombstones
                      -> connected availability without expiry
                      -> CatalogAvailability source-aware labels
                      -> Blade cards/detail + fresh inquiry resolution
```

Unmapped source records stay in `qammaris_app_products`; no matching, draft creation or publication is inferred. Explicit CLI mapping previews IDs and reuses existing identity invariants, then replays cached state. Feed price/name/taxonomy are review snapshots, not catalog writes. Product IDs, URLs, offers and media remain website-owned. Human availability edits are rejected for connected products; provider CSV cannot overwrite the source metadata.

The webhook is stateless, outside session/CSRF web routes, and uses HMAC instead. Credentials stay in config/env. Receiver acknowledgement means a database job exists, not that synchronization already completed. Worker/scheduler/secrets and deployed URL are not activated or verified by this code change.

P8-02 centralizes availability labels in `CatalogAvailability`, shared by Blade cards/detail, `CartController` and `InquiryWhatsApp`. Inquiry rows are resolved from current product metadata on every request; session labels are not trusted. App-only inquiries use reservation-neutral notices; legacy/mixed lists retain their confirmation notice. The drawer loads this notice from the existing JSON endpoint and resets to neutral during loading/error/empty states. Connected details hide checked timestamps; legacy freshness is unchanged. No UI redesign or new database fields are required by P8-02.

ADR-021 defines the integration. `CURRENT_STATE.md` remains the historical discovery baseline. Public copy/restock UI is implemented locally in P8-02; activation remains unverified. The restricted website mutation API and Shopee media import remain separate future items.


## Launch draft preparation (P8-04)

`qammaris-app:prepare-drafts` -> persisted `qammaris-drafts-v1` preview -> explicit exact batch apply -> MapExternalProductIdentity + SyncSingleOffer + ApplyQammarisAppAvailability -> new draft/nonactive products + existing import audit rows. Existing mapped products are skipped. Source/candidate/tamper/catalog guards run before transactional creation. The stock worker remains unchanged.

Owner Shopee media XLSX -> read-only extraction to private allowlisted JSON -> conservative unique name/size match (SKU never cross-provider key) -> separate explicit QueueProductImportImages -> existing product-import-images database jobs -> ImportedProductImageDownloader + ProductMediaStorage + AttachProductImage. The Qammaris contract requires successful cover before additional slots. Ambiguous/unmatched photos and incomplete app records stay in review; no publication/hotlink.

Current CLI deliberately rejects production. Old local catalog mapping review is read-only and preserves the source SQLite hash. ADR-022 governs the approved exception to the original stock-only scope.

## Staging launch copy (P8-06)

Owner Shopee basic export -> private deterministic curation by existing provider ID/name -> description and evidence-backed audience proposals -> guarded maintenance preview -> `qammaris-app:launch-copy` / `launch-copy-v1` batch -> existing transactional maintenance apply -> description/gender only on photographed structurally complete visible connected drafts. CLI actor is explicitly labelled with null human IDs; no admin account or write API is created. Stock worker, source checkpoint, offers, media, identities, URLs and publication remain independent and unchanged. Production is refused.

Private offline review shows verified copies of existing website covers plus factual copy and correction lists. It does not publish or modify website UI. Owner approval and readiness checks precede separate staging publication. ADR-023 explains scope and audit tradeoffs.

The same standalone review is served only behind existing staging HTTP Basic Auth at `/owner-review/p8-06-wave-one/`; anonymous access stays 401. It is a snapshot/artifact, not an application admin surface, live catalog or new authorization boundary. Catalog product publication is unchanged.

## Owner-approved Shopee pairing (P8-07)

Owner app-team mapping CSV + media/basic exports -> private exact-ID/provenance curation -> `qammaris-app:shopee-pairs` / `qammaris-pairs-v1` immutable preview -> exact current unique feed SKU -> existing `qammaris_app` UUID -> connected visible draft -> existing MapExternalProductIdentity plus blank-description fill -> separate existing image queue/downloader/storage/attachment. No name fallback or new product creation. Existing media/copy/website fields and publication remain protected. This supplied evidence supersedes the initial conservative name matcher for this dataset only. ADR-024 records the bounded operation and stale/identity guards.

The 56-row standalone Owner review at `/owner-review/p8-07-pairing/` shows downloaded/verified cover copies, all 102 supplied candidate references, descriptions and exact SKU/UUID/current website fingerprints. Choice export has no catalog mutation or publication side effect. Occupied candidates can be proposed as corrections with an explicit conflict flag, never silently rebound. Duplicate selected UUIDs prevent export. Owner choice apply uses `previewOwnerChoices` with the submitted export, original private review and applied source batch. It verifies original candidate/copy/photo/provenance evidence and the current exact UUID/website fingerprint, then reuses pairing apply/media operations. Stale or occupied choices remain held; no provider rebinding. Each row retains explicit selection evidence and canonical choice/review hashes. Raw exports/captures remain private; existing Basic Auth protects directory and every asset.

P8-06 continuation after approved pairing: `prepare_launch_audience_followup.py` joins the current private staging capture to exact Shopee identities and original basic-export copy. It proposes blank gender only for structurally complete photographed drafts, preserving all other fields. Existing launch-copy preview/recorder/apply guards remain unchanged; no new machine mutation contract or PHP deployment. The review builder accepts an explicitly bounded current cohort and includes a read-only guarded publication-candidate list. Actual publication remains a separate Owner-approved operation through existing publication readiness/operations.

Owner-approved staging publication of the exact 122-product list uses the one-off operator script `tools/staging_publish_launch_wave.php`. It refuses production and other databases/lists, persists immutable `launch-publish-v1` preview/audit in existing import tables, locks checkpoint/source/products, rechecks row/media/identity/source/readiness guards and calls existing `PublishProduct` within one outer transaction. Rehearsal rolls back both product and audit writes; applied replay is a no-op. Nullable actor IDs and explicit Owner-approved source label identify the trusted SSH operation without fabricating a human admin. No new HTTP endpoint, account, schema, queue, product mutation layer or general bulk publication feature is introduced.

Five named Owner corrections/publications use a separate fixed staging operator `tools/staging_publish_owner_corrections.php`: immutable approved preview -> current source/identity/row/readiness guards -> exact category/gender writes -> existing `SyncSingleOffer` only for two missing offers at unchanged prices -> existing `PublishProduct` -> `owner-correct-v1` batch7 before/after audit. Same outer transaction/ordered locks/rehearsal/replay discipline; no new application write interface, actor account, schema or relaxed maintenance contract.

Approved49 enrichment candidates follow the same bounded operator route via `tools/staging_publish_enrichment_wave.php` and owner-enrich-v1 audit batch8: immutable exact preview -> source/row/media/price guards -> missing category/audience fields -> eight existing-operation offer creations -> existing publication gate -> atomic audited apply/no-op replay. General maintenance authorization and prior operators remain unchanged; no new taxonomy/schema/account/HTTP boundary.

Owner-accepted remaining121 enrichment proposals use fixed staging operator tools/staging_publish_accepted_review.php and existing-table owner-accept-v1 audit batch9: exact immutable Owner manifest -> staging/source/row/media/unchanged-price guards under ordered locks -> permitted category/gender correction (52 unclear toUnisex) -> create only bodyspray/Perfume Oil if missing -> existing SyncSingleOffer for65 missing offers -> existing PublishProduct -> atomic before/after audit/no-op replay. Existing taxonomy/offers and protected catalog fields remain unchanged. No new schema/account/HTTP API or application deployment. Source uncertainty stays explicit; this acceptance does not broaden previous tools or future imports.

Final 53 Owner-listed genders use `tools/staging_publish_final_audience.php`: immutable exact-list preview -> actual staging/source/provider/row and gender-only readiness guards under ordered locks -> exact Eloquent gender write -> existing `PublishProduct` -> `owner-gender-v1` batch 10 before/after audit -> atomic apply/no-op replay. No category, offer, price, copy, media, stock, identity or URL mutation; no application deployment, schema/account or generic API change. Current staging has 350 launching products plus fixture, and 95 drafts without photos/descriptions.

### One-time fresh launching boundary (P1-04)

BootstrapLaunchCatalog persists a guarded CLI preview and transactional apply for the Owner-approved fresh catalog. It reuses Product, SyncSingleOffer, MapExternalProductIdentity, AttachProductImage and PublishProduct rather than bypassing their rules. Source IDs are reference-only, and copied photos must pass exact manifest verification. Existing catalog replacement and fixture/account/runtime transfer are refused. This operation is not a public API or recurring sync path. Implementation has six passing focused tests; actual production bootstrap remains pending. See PRODUCTION_CUTOVER_PREFLIGHT.md.


## Production activation completed — 2026-10-06

Owner's continuation approves the concrete reviewed cutover. New qammarisparfum.id target now serves350public+95draft/1016photos. PR2 merged main87959623d03c33b99c400e242cb35856b7f1e7a9; mainCI37431709979 and automatic productionrelease37431774330 build/deploy succeeded; server revision matches. Production marker/enable flag active, main-only environment unchanged. Only new target static traversal/read enabled; env/config0600 and private runtime remain private. Old public folder retained as public_html_legacy_20261006, old app/database/staging unchanged, no backup/deletion. Only Node backend WEBSITE_WEBHOOK_URL switched to production; hPanel one-env-change restart completed on same921569b4 source. Actual HTTPS200catalog/detail/search/login/home; draft/private/fixture404 and env403; real Owner admin login/product list445 passed. Feed401/200,452snapshots/checkpoint457/has_morefalse; actual signed production receiver202, duplicate/valid older wakeups drained with oneworker/queue0/failed0/errornull. Chrome1440x900/390x844 photos loaded/nooverflow, native search2BSPKutaresults. No new app/schema/package/UI implementation. Source production stock-event latency and separately timed post-cutover30-minute tick remain Notconfirmed; staging10/13second proof is historical. P1-04 IN_REVIEW for Owner acceptance; no next item started. Evidence/limitations/recovery: docs/verification/p1-04/PRODUCTION_CUTOVER.md. Earlier pending activation statements are historical and superseded.


## Public navigation and touch-safety — P7-08 in review

Existing full-document Laravel/Blade GET navigation remains. The React CardNav island opens synchronously without a GSAP timeline/stagger, retains native anchor clicks and closes on Escape with focus restoration. Shared JS marks same-origin clicks pending and clears that state on pageshow without intercepting navigation. Reveal observers skip interactive links/lists/containers. Tailwind's mouse-only custom hover variant plus an existing-PostCSS Vite transform guards library positive hover output; focus branches and negated base-visibility selectors remain intact. No SPA/PJAX, persistent cross-document navbar, new package or backend/data boundary is introduced. Actual touch/Safari release proof remains pending; see docs/verification/p7-08/README.md.

## Recurring app catalog and admin inbox — P8-08 in review

Webhook/reconciliation -> existing database job -> validated feed/checkpoint transaction -> source snapshot -> existing UUID applies availability + `SyncQammarisAppPrice`; a new visible UUID reuses `PrepareQammarisAppDrafts::createFromSnapshot` -> conservative candidate hold or unpublished draft + identity + explicit offer -> existing app audit -> checkpoint. Older/equal source revisions do not mutate the catalog. This approved behavior supersedes the historical stock-only worker descriptions above once deployed.

Auth/admin GET `/admin/app-products` -> cached snapshots + mapped products + readiness reasons -> paginated Blade inbox. POST sync queues the existing job; historical unlinked sources use actor-bound preview/confirm/apply through the same draft operation. Draft completion uses the existing admin editor/media/publication operations. Connected price inputs are read-only and `SyncSingleOffer` protects the price for every shared caller. No new API/schema/storage provider. ADR-025 documents exact policy and limits; implementation is local/in review, not production activation.

## WhatsApp order checkout — P7-12 (in review)

Existing session cart -> CartController current published/active offer resolver -> available-only checkout GET -> server review fingerprint in session + Blade recipient form -> CheckoutRequest normalized bounded recipient fields -> current catalog reread/fingerprint guard -> InquiryWhatsApp::orderUrl -> no-store/no-referrer redirect to WhatsApp composer. Internal helper name retained to avoid unrelated caller churn; public copy uses keranjang/pesanan. No order/customer table, payment SDK, quantity reservation or integration change. Failed input uses Laravel temporary old-input recovery; full request bodies are not logged. Cart remains after composer redirect because delivery is not observable.

Owner follow-up 2026-10-07 removes the rendered drawer and its JavaScript. Navbar links directly to `/cart`. Blade detail -> `product-cart.js` -> existing POST `/cart/add` -> confirmed count event to React navbar + honest CTA state + optional Web Animations flight. `cart-page.js` serializes list quantity/removal requests, restores quantity on failure and reloads current totals on success. Existing GET `/cart/data` remains compatible and refreshes the header count on restored browser-history pages; a stale response cannot supersede a newer addition event. No backend/schema/provider change for this UI follow-up.
