# Qammaris — backlog current

Updated 2026-10-08, approved Journal production release; audit scope retained. This board is the entry point; detailed prior scope/acceptance/results remain in the [historical backlog](../history/2026-10-07-context/BACKLOG.md), [audit](../audits/2026-10-07-maintainability.md), ADRs and linked verification. Historical “next/unstarted/pending” paragraphs are relative to their date. Do not implement them again or treat them as new authorization. Current rules: [BUSINESS_RULES](../product/BUSINESS_RULES.md); executable flow: [ARCHITECTURE](../architecture/ARCHITECTURE.md).

**Release update:** Owner authorized review/consolidation, staging/MySQL migration and production release, plus a temporary staging actor inside a fully rolled-back test transaction. AUD-01–07 are complete within their approved scopes and included in merged PR24 / production `9325df4`. [Release proof](../verification/audit-release/README.md) supersedes implementation-phase pending-release/MySQL-unconfirmed statements retained below and in dated verification. P9 acceptance limits remain open; this is not 100% closure of the original program.

## Status and working limit

BACKLOG = proposed/not approved for execution; READY = accepted bounded scope ready; IN_PROGRESS = active work; IN_REVIEW = complete within stated scope, review/limits still open; BLOCKED = named external prerequisite; DONE = accepted criteria and stated verification completed. One approved implementation item at a time. A released feature may have separate IN_REVIEW device/runtime acceptance; do not infer either code unreleased or all tests passed from the label alone.

## PREF-01 — Preference audit and human quality reference — IN_REVIEW

Owner approved implementation of [V1 plan](QAMMARIS_FRAGRANCE_PREFERENCE.md), one item at a time. Read-only public378-product capture, explicit audit dictionary/normalizer, fingerprints, 750 written note forms, 25 placeholder products, 29 residual terms, 19 unverified identical-note groups and20 legacy baselines packaged. [Audit/checks/limits](../verification/pref-01/README.md); [30-case Owner worksheet](fragrance-preference/REVIEW.md). Owner subsequently delegated worksheet filling to Codex because of limited time. All20 normal profiles now have40 primary aroma proposals, one separate +3,6% alternative, evidence/rejection notes and provisional empty judgments; all10 edge contracts reviewed against the plan. [Agent reference](../verification/pref-01/agent-reference.json) is attributed to Codex, **not human gold labels or passed engine tests**. Four potential source conflicts separately recorded without writeback. Owner/staff independent acceptance, semantic decisions and reference freeze remain pending; no accuracy target asserted. No public quiz/UI/DB/media/package/credential change, no V1 accuracy claim. Owner explicitly continued PREF-02 development on2026-10-10; PREF-01 remains IN_REVIEW with independent quality acceptance pending. Previous About review is a separate visual acceptance.

| ID | Status | Next prerequisite |
|---|---|---|
| PREF-02 | IN_REVIEW | Backend code/provisional evaluation prepared under explicit continuation; independent review/calibration/quality acceptance pending; [evidence](../verification/pref-02/README.md) |
| PREF-03 | IN_REVIEW, active | Beta v1.2 correction live via PR51; Owner-requested 21st Multistep UI prepared for review, not deployed ([UI proof](../verification/pref-03/multistep-ui/README.md)); human quality review still pending; [revision release](../verification/pref-03/question-revision/RELEASE.md), [implementation proof](../verification/pref-03/question-revision/README.md), [initial beta release](../verification/pref-03/RELEASE.md) |
| PREF-04 | READY, not started | Final human-quality acceptance remains separate from the authorized PREF-03 beta technical release |

Owner2026-10-10 separately authorizes a labelled usable beta for testing before human-quality acceptance. [ADR039](../architecture/decisions/ADR-039-preference-website-beta.md) supersedes the prior beta release gate only; never mark independent quality targets passed or rely on About permission. PREF-01 cannot become DONE while human reference is pending. PREF-02 code has separate additive profile/revision migration, preview/apply/review operations, default-disabled catalog adapter, traceable provisional ranking and offline training/hold-out comparison.PREF-02.3 distinguishes explicit projection/sweetness/short-longevity compromises from unknowns and excludes nonpositive totals;53 focused tests/265 assertions passed. [Latest15-case quality comparison and limits](../verification/pref-02/quality-v3/README.md); earlier reader/backend proofs remain dated baselines. PREF-03 website adapter is now implemented on its branch with64 focused tests/406 assertions and local responsive/full-flow browser proof. Beta additive migrations, main CI/deployment and live result/feedback/photo/privacy verification passed2026-10-10; [release proof](../verification/pref-03/RELEASE.md). No catalog mutation; one active item is PREF-03. PREF-04 final acceptance remains unstarted.

## ABOUT-04 — Source-faithful split hero — IN_REVIEW

Owner requested actual hero-section-2 animation and flow. Paid source demo5260/component3839 ported to Blade/native motion: split logo/title/divider/subtitle/text CTA/contact row and diagonal photo, 500ms/20px item reveals with exact nested 150ms stagger/200ms child delay, independent 1200ms circOut polygon reveal. One existing supplied store photo, actual source contact SVGs, native responsive/touch/reduced-motion/no-JS. Existing3 About tests/96 assertions, Blade/Pint/Vite and Chrome320/390touch/768/1440 passed; actual motion recording and sampled frames saved. [Sources/evidence/limitations](../verification/about-split-hero/README.md). No new package/data/media/order change. Ongoing correction follows existing Owner publication authorization and normal GitHub release gates; Owner visual review stays open. No other phase started.

## ABOUT-03 — Hero 10 with one supplied storefront photo — DONE

Released PR45 at7e5ac42; live390touch/1440 hero/image/CTA and runtime passed. Owner subsequently requested the replacement in ABOUT-04; previous rendering/evidence below retained as history.

Owner requested exact21stHero10 and one supplied storefront photo. Paid MCP source demo19079/component9670 ported to Blade/native500ms optional reveal, centered serif title/highlight, two CTAs and uncropped single image. Old cinematic motion/pin removed; other About sections retained. Original copied/verified, three responsive derivatives no upscale; old media/DB/orders/credentials/packages untouched. Existing3Abouttests/95assertions, Pint/Blade/Vite and realChrome320/390touch/768/1440 plus no-JS/reduced-motion passed. [Source/verification/screenshots/limits](../verification/about-hero10/README.md). Bounded correction released through Owner-authorized normal gates; earlier pending verification snapshot is superseded by the release note above. Next Owner visual review; no other phase started.

## ABOUT-02 — Cinematic About and unique store photography — DONE

Hero presentation superseded by ABOUT-03; retained below as dated release history.

Owner approved the actual jahed hero and at least six more21st components beyond the first four. Ten new source-backed Blade/vanilla adaptations implement the cinematic hero, experience grid, gallery filter/lightbox, scroll spy/progress, visit steps, testimonial columns, catalog CTA, buttons and portrait video dialog. Seven unique photo compositions each appear once; paper-test/skin-test posters removed from visible About, original files retained. Mobile is static/readable without GSAP request or scroll pin; desktop source motion is bounded480px. Shared FAQ400ms, facts/hours/reviews/SEO/URL and backend/order/blog boundaries retained. No new dependency/data/schema/credential change. About3tests/82assertions,Vite/Pint/Blade and realChrome320/390touch/768/1440 plus fallback/motion cleanup passed. [Sources/screenshots/limits](../verification/about-cinematic/README.md). Owner explicitly approved PR43 publication; deployed7612d09 after green CI and verified live390touch/1440/320. One actualPHPworker/freshheartbeats/zero pending migrations or newLaravelerrors. [Release proof](../verification/about-cinematic/RELEASE.md). PhysicalSafari/externalReel remain unconfirmed. Next Owner visual review. No next phase started.

## UI-21ST-02 — Smooth FAQ and plain About copy — DONE

Owner requested a source-faithful animated FAQ and less poetic About text. Re-read actual21st demo1517:400ms height/opacity, single collapsible answer. Shared WAAPI enhancer supports open/close/switch/rapid reversal, keyboard, reduced-motion and native no-JS fallback. About generalizes Jakarta to studying outside Palu, uses “Mengenal parfum saat merantau” /“Awal mula Qammaris”, removes “perintis” and simplifies related headings/story.2024/February2026, photos/reviews/hours/URLs retained. No DB/media/orders/packages/credentials. Existing11Laravel/229assertions,Vite/Pint/Blade and Chrome390touch/1440 with reduced-motion/no-JS passed. [Evidence/screenshots](../verification/faq-motion/README.md). Owner explicitly approved PR41 after green CI; deployed8e1455b and live390touch/1440 confirmed actual400ms motion, copy, switch/collapse/rapid reversal, no overflow/JS errors. Worker/heartbeats current, zero pending migrations/Laravel errors. [Release proof](../verification/faq-motion/RELEASE.md). Physical iPhoneSafari remains untested; next Owner visual review. Other program limits unchanged.

## UI-21ST-01 — Shared FAQ/icons and About gallery/timeline — DONE

Owner approved exact21stFAQ/icon sources, then Fluid Expanding Grid and shadcnspaceTimeline01. Implemented shared native Blade FAQ on About/Journal/preview; ten actual21stSVG icons replace text symbols in affected UI. Gallery retains nine full compositions, click expansion on desktop and one-tap full photo on phones. Alternating timeline uses Owner-confirmed2024 start /February2026 opening; intermediate steps remain undated. No orders, DB/migrations, new packages, credentials or original media changes. Targeted15tests/263assertions, Vite/Pint/Blade compile, Chrome390/1440 and320no-JS checks passed. [Sources/screenshots/limits](../verification/faq-icons/README.md). Owner explicitly approved PR39 release (“saya izinkan”); deployed3063d63 and live390touch/1440 checks passed. Main CI471Laravel/3695assertions,31Node and Vite passed; worker/heartbeats current, no pending migrations or application errors apart from one corrected read-only checker diagnostic. [Release proof](../verification/faq-icons/RELEASE.md). Physical iPhoneSafari remains untested. Next Owner live visual review; other programs/acceptance remain separate.

## SEC-DEP-01/02 — Bounded dependency patches — DONE

Owner requested finishing remaining work after reporting safe site testing. Separately scoped after ABOUT-01 implementation: patch shell-quote/source-map-js/CommonMark only; no orders, package-major upgrade, database/media/content/credential change. Exact parent pin requires a concurrently-scoped shell-quote override. npm/Composer audits now report zero findings; clean npm install,469Laravel/3662assertions(two conditional skips),31Node tests, Vite, strict Composer and Chrome390/1440 smoke passed. Historical baseline findings remain below; Owner-approved PR37 patch deployed onfb7ed28 with zero pending migrations and live runtime checks. [Release proof](../verification/about-release/README.md). [Evidence and limits](../verification/security-dependencies/README.md).

## ABOUT-01 — Real store story, experience and media — DONE

Owner approved2026-10-08: complete remaining reviewed work, use nine supplied store/construction/design/tester images, the two first-party LinkedIn posts, portrait Instagram Reel, and clevision21st rating-card via MCP. Public review requested; Owner explicitly authorized production release of PR34–37 (“gass rilis”) after the initial automatic review required specific approval. PR34–37 release and live verification completed onfb7ed28. [Release proof](../verification/about-release/README.md).

Bounded scope: `/store/about` editorial redesign, verified story of Husein's Jakarta exploration/return to Palu, named timeline stages without unverified dates, all-product tester/staff recommendation/discussion/wholehearted-service claims approved by Owner, responsive real gallery/lightbox, click-to-load Instagram with fallback, dated Maps4,9 rating and only verified attributed review excerpts, local SEO/metadata, correct Owner hours. No catalog/order/API/database mutation or new package. Implementation/localverification complete:469Laravel/3662assertions,31JS,Vite/Pint,diff; realChrome320/375/390/768/1440 and genuine touch passed. Owner later supplied eight Google Maps screenshots; six complete attributed reviews now render in a responsive grid. Fresh About3tests/131assertions, Pint, Vite and Chrome320/375/390/768/1440 checks passed; [source/evidence](../verification/about-experience/reviews.md). Reviewquote prerequisite resolved; productionrelease explicitly approved and verified onfb7ed28 at320/375/390/768/1440 plus genuine touch/gallery/FAQ/Reel/source/API/runtime checks.

User reports previous real-site testing was safe and asks review directly on production. Record that Owner observation without claiming unperformed device/event/checkout/load checks were independently passed. PR34hours and PR35API-documentation were merged on the approved release path; About and dependency patch then merged and passed final CI/deploy/public/runtime verification; original P9 and legacy editorial acceptance limits remain separate. Source/evidence: [ABOUT-01](../verification/about-experience/README.md).

## Bounded store copy correction

**STORE-HOURS — DONE:** Owner confirmed Sabtu–Kamis 09.00–21.00 WITA; Jumat tutup. Blade copy corrected and business rule recorded; Chrome390/1440 preview and Blade compilation pass. Owner explicitly approved PR34–37 release; PR34 merged and website hours verified live in the finalfb7ed28 release; Google profile change is outside scope. [Verification](../verification/store-hours/README.md). No data/API/order change or next-phase authorization.

## Active separate program — Qammaris Journal

Owner approved the [Journal plan](QAMMARIS_JOURNAL.md), BLOG-01–05 implementation, transaction-only staging fixtures, five additive staging/production migrations after staging passed, and a blog-only production release. Order work is excluded. [BLOG-06 release evidence](../verification/blog-06/README.md) supersedes earlier phase “not deployed/staging unconfirmed” statements; it does not close P9. Owner subsequently authorized production API activation and local credential setup2026-10-08; [activation evidence](../verification/journal-activation/README.md).

| ID | Status | Current scope / next prerequisite |
|---|---|---|
| BLOG-01 | DONE | Safe shared writes, revisions, retained images, archive/restore and audit installed via PR31; selected MySQL DDL/replay and staging transaction passed. [Implementation](../verification/blog-01/README.md) |
| BLOG-02 | DONE | CMS fields, Tiptap visual/HTML editor, taxonomy/tags, authenticated preview and scheduling installed. Legacy IDs/URLs/content/authors preserved. [Implementation](../verification/blog-02/README.md) |
| BLOG-03 | DONE | Public responsive Journal/search/TOC/share, metadata/schema and scheduled sitemap boundaries installed; live three article URLs/schema and five widths checked. [Implementation](../verification/blog-03/README.md) |
| BLOG-04 | DONE | Owned responsive/cropped media and full editorial components/relations installed; GD/WebP staging and local native-file-input browser upload passed. [Implementation](../verification/blog-04/README.md) |
| BLOG-05 | DONE | Scoped draft-only API/schema/admin assignment installed; production gate enabled by separate Owner approval2026-10-08; one local machine actor/token. Staging auth/replay/revision/ownership/upload guards passed. Local activation/read access verified; cloud-only secret provisioning is not configured. [Implementation](../verification/blog-05/README.md) |
| BLOG-06 | IN_REVIEW | Approved production release completed via PR31 /3c8d35f; CI/release/MySQL/runtime/public browser/touch passed. Owner editorial walkthrough, three legacy alt fields and one prose price/stock article review remain; physical Safari/production upload/external SEO and sustained load unconfirmed. [Release](../verification/blog-06/README.md), [runbook](../runbooks/JOURNAL_RELEASE.md) |

BLOG-01 acceptance implemented: failed file/DB/audit write keeps current article/image; committed image survives cache outage; stale editor/archive/restore fails; no-op/views preserve editorial time/revision; legacy ID/slug/author/content/image formats survive additive migration and replay; archive excluded from public listing/category/detail/sitemap and restored as draft. No historical body rewrite, purge, API, package install, public redesign or production mutation. 403 Laravel tests/2804 assertions and31 JS tests passed; browser widths320/375/390/768/1440 checked with screenshot/keyboard/two-tab conflict evidence. Full check/cleanup/limitations in evidence.

## Original catalog program board

Historical finding SEC-DEP-01 (patched and released, see DONE and release proof above): npm audit reports concurrently9.2.4/shell-quote1.9.0 (critical) and source-map-js1.2.1 (high). Versions are identical in cb0a1c3 and BLOG-02; not introduced by Tiptap. Assess/update in a separately scoped dependency task, then repeat audit before release.

Historical finding SEC-DEP-02 (patched and released, see DONE and release proof above): BLOG-04 locked Composer audit reports two existing league/commonmark advisories, medium GHSA-97jj-33gv-5xf9 and high GHSA-3q6v-r5mr-hxv8. BLOG-04 does not change either lockfile; Journal content uses the existing HTML sanitizer, not Markdown conversion. Assess affected application paths and package update separately; dependency audits are not clean.

| ID | Phase | Status retained | Current meaning / evidence |
|---|---|---|---|
| P0 | Discovery/decisions | DONE | Historical baseline and accepted product rules; not fresh production counts |
| P1 | Foundation/staging/Git | IN_PROGRESS | P1-01–03 complete; P1-04 cutover released, acceptance IN_REVIEW ([proof](../verification/p1-04/PRODUCTION_CUTOVER.md)) |
| P2 | Tests/catalog safety | DONE | Existing regression/ownership/validation safeguards retained |
| P3 | Product foundation | DONE | Stable IDs/slugs, one offer, publication/availability boundaries |
| P4 | Media | DONE | Disk abstraction/lifecycle + earlier R2 rehearsal; current persistent public disk, no new R2 move |
| P5 | Admin | DONE | Catalog/editor/taxonomy/media workflows available |
| P6 | Bulk/audit | DONE | CSV, snapshot/maintenance and transactional guarded apply |
| P7 | Public UX | DONE | Catalog/search/cart/recipient WhatsApp flow released; device gates tracked under P9 |
| P8 | App/integration/import | IN_PROGRESS | Feed/automatic drafts/prices/Shopee released; older acceptance consolidated for review with P9, no website write API claimed |
| P9 | Acceptance/observation | IN_PROGRESS | P9-01 IN_REVIEW; exact remaining limits below |
| AUD | Maintainability/context | DONE | AUD-01–07 reviewed and released through PR24; targeted MySQL migration/runtime rehearsal passed; P9 acceptance is separate |

Statuses are not a fabricated progress percentage. P1/P8/P9 are not marked complete merely because the current code is live. [MASTER_PLAN](../architecture/MASTER_PLAN.md) defines closure and future-program boundaries.

## Separate program — Pesanan Online (parallel to Journal)

Owner requested and approved ORD-01 on 2026-10-07, developed in parallel with the Journal program on its own branch/worktree. Program and later items: [ONLINE_ORDERS](ONLINE_ORDERS.md).

| ID | Status | Current scope / next prerequisite |
|---|---|---|
| ORD-01 | IN_REVIEW | Admin-created order + customer completion link, price snapshot, admin payment/shipping/funding, tracking timeline, staff group message + secret staff task link, shipping-fee advance/reimburse. Branch `modernization/ord-01-online-orders`; not deployed. [Evidence](../verification/ord-01/README.md), [ADR-048](../architecture/decisions/ADR-048-online-order-links-and-tracking.md), [runbook](../runbooks/ONLINE_ORDERS.md) |
| ORD-02 | IN_PROGRESS | Owner-requested redesign (2026-10-08): Super Admin/Staff Order roles + user management, Admin PWA, separate payment/preparation/courier/J&T/handover states, repeat customers, keep, private API v1 for Qammaris App. Stage 0 reviewed by Owner (D1–D13 decided). **ORD-02a RBAC + user management IN_REVIEW** (415 tests; [evidence](../verification/ord-02a/README.md), [ADR-049](../architecture/decisions/ADR-049-admin-roles-and-user-management.md)). **ORD-02b Admin PWA IN_REVIEW** (439 tests + 40 JS; real Chrome installability/offline/logout checks; [evidence](../verification/ord-02b/README.md), [ADR-050](../architecture/decisions/ADR-050-admin-pwa.md)). API contract **r4.1 = frozen API v1 baseline** (App confirmed K-A/K-B with its own signing code; OpenAPI hash locked by `OrderApiContractTest`; breaking changes need both agents). ORD-02e follows the final contract and is tested jointly with the App agent. ORD-02c/02d approved to start; **ORD-02c slice 1 done** (state dimensions, ORD-01 backfill/sync, derived queue/flags/completion). **Slice 2 done** (per-order legacy/V2 cutover, append-only payment/refund ledger, Super Admin refund decision/reconciliation; ORD-01 cancels after payment flagged `needs_reconciliation`; [ADR-051](../architecture/decisions/ADR-051-order-cutover-and-money-ledger.md)). **Slice 3 done** (issues, V2 operations with exact packing, atomic J&T handover, cancel with explicit refund; `ORDERS_V2_ENABLED` cutover). **Slice 4 done** (repeat customers + confirmed saved addresses). **Slice 5 done** (keep, Super Admin-approved price adjustments, customer change requests; 475 tests). ORD-02c domain IN_REVIEW. **ORD-02d Admin PWA pages for V2 IN_REVIEW** (create from IG/WA with repeat customer and saved address, V2 order page, Super Admin finance panel, conflict/validation/confirm/loading/empty states; 483 tests + 42 JS; Chrome 320–430/1440; [evidence](../verification/ord-02d/README.md)). **ORD-02e Private Order API v1 IN_REVIEW** (14 endpoints per r4.1; HMAC + rotation, 7-day idempotency, atomic claims, webhook outbox with resend, private J&T QR, synthetic fixtures; 509 tests on SQLite, migration rehearsal + real multi-process concurrency on isolated MariaDB 10.4; [evidence](../verification/ord-02e/README.md), [ADR-041](../architecture/decisions/ADR-041-order-api-v1-implementation.md), [runbook](../runbooks/ORDER_API_STAGING.md)). Follow-up 2026-10-09: MariaDB 11.8.9 (production version) migration/concurrency pass; staging preflight command + noindex outside production; local isolated smoke test with the App on r4.1 ([SMOKE_LOCAL](../verification/ord-02e/SMOKE_LOCAL.md)): round 3 23 PASS/0 FAIL, 135/135 webhooks; found and fixed PHP 8.4 multipart QR signature bug. Owner decided 2026-10-09 (waiver approval vs sync, error codes, isolated hosted staging); **contract r4.2 = API v1 baseline** (App sign-off on `ff3532c` after K-1–K-4; [ADR-042](../architecture/decisions/ADR-042-order-api-r4-2-draft.md)); local r4.2 smoke `smoke-r42-1` 25 PASS/0 FAIL/1 SKIP, revisions and waiver decision matched ([SMOKE_R42](../verification/ord-02e/SMOKE_R42.md)); Owner Manual UAT environment ready ([MANUAL_UAT_ORDERS](../runbooks/MANUAL_UAT_ORDERS.md)); small known text issue: Integrasi App page still says "baseline r4.1"; isolated staging plan [STAGING_ISOLATED_PLAN](../runbooks/STAGING_ISOLATED_PLAN.md) awaiting Owner approval of cost/DNS. Waiting on Owner: staging deploy + per-direction credentials + tunnel URL; contract r4.2 (`Issue.opened_by` nullable); rehearsal on a MariaDB 11.8-compatible database before staging sign-off. Order ADRs renumbered 034→037, 035→038 after main used 034–036 for the Journal. [Plan](ORD-02_PLAN.md), [audit](../audits/2026-10-08-ord-02-online-orders.md), [API v1](../integrations/QAMMARIS_ORDER_API_V1.md) |
| ORD-03 | IN_REVIEW | Operational UX Simplification after Owner Manual UAT (2026-10-10): one contextual action per order step, collapsed secondary steps, payment without visible source (ledger keeps it), one-tap packing, J&T request -> waiting -> picked up with optional QR/resi, Link Pesanan with native share, customer page without site navigation, Sharelok wording, 16px touch fields; flag `ORDERS_SIMPLE_UX` for staff ongkir before payment and the customer payment preference. API v1 unchanged. Branch `modernization/ord-03-operational-ux`; [evidence](../verification/ord-03/README.md), [ADR-043](../architecture/decisions/ADR-043-order-operational-ux.md). (The earlier ORD-03 "keep" item was absorbed into ORD-02.) |
| ORD-04 | IN_REVIEW | Website Cart Checkout Integration (Owner 2026-10-10; formerly backlog ORD-06).
- **Order creation.** Guest cart checkout saves a V2 online order (`source=website`, no creator, lifecycle `draft` = "Menunggu konfirmasi website").
- **Checkout form.** Three delivery choices (pickup / instant Palu 1–2 h / J&T), address only for delivery, optional kelurahan/kecamatan/postcode, paperbag, payment preference, ongkir "menunggu konfirmasi staf".
- **Safeguards.** Idempotent checkout key, catalog re-check in the transaction, rate limit.
- **Success page.** The order link opens WhatsApp once and offers a retry.
- **Staff.** Staff Order confirms draft → active; payment is blocked while draft.
- **Phase-2 columns.** Empty and nullable; no 1 kg default.
- **Integration.** API r4.2 unchanged; App confirmed draft handling (`c098e81`).
- **Flag.** `ORDERS_WEBSITE_CHECKOUT`.

Branch `modernization/ord-04-cart-checkout`; [evidence](../verification/ord-04/README.md), [ADR-044](../architecture/decisions/ADR-044-website-checkout-orders.md). Manual UAT switched to ORD-04 on 2026-10-10 (joint verification with the App passed, then both sides cleaned to 0 orders). **Revision after Owner UAT (2026-10-11, branch only, UAT not yet switched):** payment required before packing/courier/handover in the domain and API (r4.3 text-only, signed by both agents 2026-10-11), five-step stepper, payment instructions with verified store accounts, customer total/accounts + Pesanan Saya, honest availability copy; [ADR-045](../architecture/decisions/ADR-045-payment-gate-and-order-stepper.md). **UX polish (2026-10-11, branch only):** Pesanan Saya 10 days without login (secure cookie + server reference), paperbag Rp4.000 per order (r4.4 `totals.packaging_fee`, signed by both agents 2026-10-11), direct "Kirim ke WhatsApp" with a per-browser UAT opt-in and `[UAT]` prefix, status/copy changes, staff link "Buka di Qammaris App"; [ADR-046](../architecture/decisions/ADR-046-order-ux-polish-history-paperbag.md). UAT not switched (Owner data kept). The earlier "ORD-04 authenticated staff" item was absorbed into ORD-02. |
| ORD-06 | Alias | → ORD-04 (cart checkout creates the same online order) |
| ORD-07 | IN_REVIEW | Owner 2026-10-10: test environments (`local`, `development`, `testing`, `uat`, `staging`) never use the production store WhatsApp number.
- With `STORE_WHATSAPP_TEST_NUMBER` set, links go to that number. Without it, no `wa.me` link is built and the checkout success page shows a copyable message.
- Production is unchanged.
- Shipped on the ORD-04 branch; [evidence](../verification/ord-04/README.md#whatsapp-in-test-environments-ord-07-before-the-uat-switch). |
| UX-02 | IN_REVIEW | Owner 2026-10-12: catalog price filter with thousands separators and stacked layout; quiz budget without "Rp1 juta ke atas"; Pesanan Saya clipboard-list icon; default Terlaris order mixes prices daily with affordable picks first; Distributor Resmi lists every brand on sale in a resting carousel with the supplied logos; 10 homepage best sellers plus an end card to the catalog. Branch `modernization/ux-02-catalog-home`; [evidence](../verification/ux-02/README.md). Open: logos for the 22 brands without one. Analytics research reported to the Owner, not implemented. |
| ANALYTICS-01 | IN_REVIEW | Owner 2026-10-12 chose Cloudflare Web Analytics (cookieless) + Google Search Console instead of GA4. Code: production-only partial in the public layout, inactive until `CLOUDFLARE_WEB_ANALYTICS_TOKEN` / `GOOGLE_SITE_VERIFICATION` are set; order-link pages and the Admin are excluded. Owner setup: [ANALYTICS](../runbooks/ANALYTICS.md) |
| ADM-01 | IN_REVIEW | Owner 2026-10-17: price adjustments apply directly without Super Admin approval (audit kept, total >= 0, older pending ones still decided by Super Admin); admin shell fix for the black block / double scroll (sr-only element escaped `<main>`, dark canvas under OS dark mode); Sharelok button also right after a website checkout (draft) plus a test-mode note; "Muat ulang" button on order list/detail with a hint while waiting for the customer. Branch `modernization/adm-01-order-ux`. |
| ORD-05 | BACKLOG | Customer-data retention/anonymization policy; currently no automatic deletion |
| ORD-08 | IN_REVIEW | Owner 2026-10-17: all order work happens on the Website; ongkir is recorded once. "Kurir sudah dipesan" / "Request pickup J&T" require "Ongkir ke kurir" and "Uang siapa" (active Qammaris App employees linked by account ID / Kasir / Customer bayar driver; Super Admin: Uang saya (GoPay Owner) / Kasir / Customer, no packing), with "Ubah ongkir kurir" afterwards; V2 `driver_funding` choice removed. An employee → Qammaris App creates that employee's reimbursement already approved (proof optional) via contract r4.5 website-origin costs; App Pesanan Online is view-only (Owner 2026-10-17, "Website saja"; rollback `VITE_ONLINE_ORDERS_WORK_IN_APP=1`). Staff group link text "Buka pesanan". Website branch `modernization/ord-08-website-courier-cost`, App branch `feat/ord-08-website-courier-cost`; [ADR-052](../architecture/decisions/ADR-052-website-courier-cost-and-auto-reimbursement.md), [r4.5 draft](../integrations/R4_5_DRAFT_WEBSITE_COSTS.md), [evidence](../verification/ord-08/README.md). Release (Owner): both sides together, `QAMMARIS_APP_OWNER_IDS` set, `QAMMARIS_ORDER_APP_TASK_LINKS=false`. |
| PWA-01 | IN_REVIEW | Owner 2026-10-11: installable public website "Qammaris Perfumes" (manifest id/scope `/`, own icons, worker `/sw.js` caching only build assets/public icons/offline page, admin and every non-GET untouched, offline checkout refused, no notifications), kill switch `PUBLIC_PWA_ENABLED` default off. Branch `modernization/pwa-01-public-pwa`; [evidence](../verification/pwa-01/README.md), [ADR-047](../architecture/decisions/ADR-047-public-website-pwa.md), [runbook](../runbooks/PUBLIC_PWA.md). Real Android/iPhone install and Android link handling between the two installed apps not tested. |
| REL-ORD-PWA | IN_REVIEW | Owner 2026-10-11 approved one RC PR (preparation/review only; no merge or deploy). Branch `modernization/rc-ord-pwa-2026-10-11`; merge audit fixed two Super Admin regressions in main's Journal/fragrance code; access matrix tests; MariaDB 745/745; order ADRs renumbered 048–051; webhook jobs on the production integration queue. Open: Owner RC approval, production backup + rehearsal on a copy, production config, joint activation with App r4.4. [Plan](../runbooks/RELEASE_ORD_PWA_RC.md), [audit](../verification/rc-ord-pwa/README.md) |
| DOC-ADR-01 | DONE (RC) | Order ADRs that collided with main were renumbered in the release candidate: 037→048, 038→049, 039→050, 040→051. Old filenames (including the earlier 034/035) stay as aliases, and the index lists 032–051 once each. Main's numbers are unchanged. |
| TEST-MYSQL-02 | DONE (RC) | main's `BlogAutomationTest` (SQLite trigger) and `StoreAboutTest` (boot-cached `store_info` on a persistent DB) failed on MariaDB only. Both were test-only, with no functional impact, and are fixed in the RC (MariaDB 745/745). Still open: `ProductSeeder` fails on a duplicate SKU (`AFNAN-9PM-50ML`). |
| TEST-MYSQL-01 | DONE | Two `ShopeeContentImportTest` cases assumed SQLite (double-quoted `"slug"` in a logged query; batch ID 1 after a rolled-back transaction). Fixed test-only (driver-neutral match, the batch's own ID) on 2026-10-09; full suite on MariaDB 11.8.9: 522 passed. |

ORD-01 local verification: 14 new tests/142 assertions; full suite and browser flow at 320/375/390/1440 on an isolated synthetic SQLite DB; staging MySQL, genuine touch/Safari and real WhatsApp sending not confirmed.

## FIX-SUPREMACY — Correct Pink Shopee content assigned to Purple — DONE

Owner approval 2026-10-07: explicitly correct the observed production Pink/Purple mismatch. Exact scope: Shopee42131600634 previously assigned to Purple879, belongs to Pink686. One transaction reassigns that Shopee identity only, completes Pink with the verified100ml/Wanita/EDP copy and three checksum-verified independent photo copies, publishes Pink, returns Purple to draft and soft-archives its incorrect Pink photos. Original application UUIDs, source prices/status, product IDs and slugs retained; no source-system/database/schema/credential/deployment changes.

Production audit batch8 stores two before/after rows; private preview/receipt retain recovery evidence and original media files. Shared importer rebind guards remain unchanged. Local53tests/264assertions passed; production preview/rehearsal rollback and verify passed; public Pink200/Purple404 and all three Pink gallery images loaded. Original applied batch6 remains historical evidence, not rewritten. Details, limits and scoped recovery: [correction verification](../verification/supremacy-pink/README.md). This correction does not start AUD-03 or close P9.

## AUD-07 — Narrow admin product change history — DONE

Owner execution approval: “gas aud 07”. Separate scope/retention answer: “Setuju tabel audit, simpan tanpa hapus otomatis”. Scope AUD-F11: one additive table, explicit admin actor on product create/edit/archive/restore and gallery primary/move/archive, safe before/after metadata/hashes in the business transaction. No UI, credentials/permissions, API/framework, backfill, purge, live migration or deployment.

Acceptance implemented:
- Changed allowlisted groups only; no-op/validation/authorization/foreign child/readiness failure does not commit history. Audit failure rolls back mutation; editor compensation retains old media and removes only newly stored files.
- Authenticated account ID comes from the session, never request actor fields. Description/notes/SKU/image keys hash-only; request bodies, URLs/files, customer/user credential data excluded. Existing feed/import evidence remains separate; shared accounts cannot distinguish physical human from automation.
- Historical IDs retained without FK cascade/nulling; no automatic deletion or invented old actors. No universal history/restore/tamper-proof claim or new history page.
- Baseline43tests/217assertions; new14tests/110assertions; intermediate110targeted tests/695assertions; final382Laravel tests/2666assertions and31Node tests passed. Strict Composer metadata and changed-PHP Pint passed; no UI/browser test claimed.
- Selected migration/replay on an isolated historical SQLite copy445products: all21original non-migration table fingerprints unchanged, audit table empty, migration recorded once. Subsequently, staging/MySQL migration/editor/audit/no-op passed inside a fully rolled-back transaction; targeted production DDL/replay passed with nine existing table fingerprints unchanged. Concurrent load/long-term growth remain Not confirmed. Production deployment pending-migration guard stayed intact.

Files, safe diagnostic steps, schema/data/media impact and retained-table rollback: [AUD-07 verification](../verification/aud-07/README.md), [ADR-031](../architecture/decisions/ADR-031-admin-product-change-history.md), [runbook](../runbooks/ADMIN_PRODUCT_AUDIT.md). Reviewed/released through PR24: [proof](../verification/audit-release/README.md). AUD-07 is the last item of this bounded audit queue. Recommended next is separately scoped P9 acceptance, not a new refactor or automatic next phase.

## AUD-06 — Whole rupiah and current-catalog cart totals — DONE

Owner execution approval: “gas aud 06”. Final money decision: “gajadi pake bilangan bulat aja … gausa desimal”, superseding the earlier decimal reply. Scope: AUD-F08/F09 only. Whole new prices in editor/CSV/shared manual offer operation, truthful legacy price handling, exact cart/WhatsApp calculation and current-catalog mutation totals. No schema/package/data rewrite, source contract change, production mutation or deployment.

Acceptance implemented:
- New selling/comparison prices reject nonzero fractions; database decimal zeroes remain accepted. Whole amounts have no decimal suffix. Hypothetical legacy fractions remain unchanged, visible accurately and flagged in the editor; no implicit rounding/unpublishing.
- Add/update/remove totals resolve all remaining current offers, never session prices; unresolved totals are null, `/cart/data` retains 409. Numeric JSON compatibility and quote/availability/publication/recipient guards remain.
- Read-only local historical snapshots:180 and445 products, zero fractional base/comparison/offer prices. Production was not measured during implementation; release preflight subsequently checked411 offers with zero fractions. Local default MySQL was not used.
- Final Laravel368tests/2556assertions,31Node tests, changed-PHP Pint, strict Composer metadata, Vite build and diff checks passed. Browser390×844/1440×900 money flows and320px longer amounts checked; a detected320px cart overflow is fixed. Genuine Safari/touch and live WA handoff not claimed; complete matched desktop-before screenshots were not captured.

Changed modules, consumer trace, screenshots, temporary-fixture cleanup, limits and code-only recovery: [AUD-06 verification](../verification/aud-06/README.md), [ADR-030](../architecture/decisions/ADR-030-whole-rupiah-and-current-cart-totals.md). Reviewed/released through PR24: [proof](../verification/audit-release/README.md). Device/WA limits remain under P9.

## AUD-05 — Editor save/validation and explicit dependencies — DONE

Owner execution approval: “lanjutt” after AUD-04 recommended AUD-05. Scope: AUD-F04/F05/F06 backend cleanup only. Shared `SaveProductEditor` for create/update transaction/media/publication; shared stable request rules/messages/comparison check with explicit create/update exceptions; Shopee guard/collaborator injection. No Blade redesign, schema/package/business-rule change, production mutation or deployment.

Acceptance implemented:
- HTTP redirects/input/errors/context stay in controllers; source-price protection, parent-owned offer, stable IDs/SKU/slugs, manual availability lock and publication/readiness guards remain.
- Failed attachment/publication restores DB state and compensates only newly stored files. Shopee actor/payload/media guards, checkpoint/source/product locks and dispatch timing remain intact; contracts stay separate.
- Baseline68tests/425assertions; expanded94tests/616assertions; full Laravel354tests/2467assertions,31Node tests, targeted Pint, strict Composer metadata, Vite build and diff checks passed.
- No UI changes or browser check claimed. SQLite does not verify live MySQL contention/webhook timing; existing UI/device acceptance limits remain. Existing build warnings and Blade duplication are documented, not fixed by this item.

Evidence/changed files/limits/code-only rollback: [AUD-05 verification](../verification/aud-05/README.md), [ADR-029](../architecture/decisions/ADR-029-shared-product-editor-save.md). Reviewed/released through PR24: [proof](../verification/audit-release/README.md). Broader acceptance remains under P9.

## AUD-04 — Shared related-product cards — DONE

Owner execution approval: “lanjut aud 04”. Scope: public detail related-product cards only, resolving AUD-F07. Reuse `_catalog-card` with h3 headings, visible active-offer price and lazy media; delete the obsolete sole-consumer `_card`. Existing same-brand eligibility/order/limit, detail query context, source availability, gallery and routes are unchanged. No schema, catalog/media mutation, dependency or deployment.

Acceptance implemented:
- Square contained media, existing whitespace balancing and local error fallback now apply to related cards. One accessible native link covers image/name; keyboard focus and existing click feedback are reused without new hover/motion logic.
- Laravel30 targeted tests/304 assertions and31Node tests passed; targeted Pint, Vite build and diff checks passed. Red regression on the old card confirmed missing h3 and image-recovery hooks.
- Real Chrome local350-product catalog: before/after390×844 and1440×900; additional320/375/768 widths without horizontal overflow. Mouse image click, keyboard Tab/Enter, native history back and related-link query context verified. Final Kembali click/control cleanup were interrupted by browser detachment; genuine touch/Safari and failed-image runtime were not confirmed.

Evidence, screenshots, limits and code-only rollback: [AUD-04 verification](../verification/aud-04/README.md). Reviewed/released through PR24, with production390/1440px detail/link checks: [proof](../verification/audit-release/README.md). Genuine device acceptance remains under P9.

## AUD-03 — Shopee review summaries and media recovery — DONE

Owner execution approval: “Berikutnya AUD-03”. Scope: one shared read-only row summary for counts, filters and rendering; batch readiness evaluation; clear download retry versus protected media review. No import framework, dependency, migration, automatic publication, production mutation or deployment.

Acceptance implemented:
- `ShopeeContentReview` replaces duplicated controller/Blade calculations. `EvaluateProductPublicationReadiness::forReview` batches slug checks and evaluates each distinct target once; apply/publish still use fresh `handle` validation.
- Failed downloads offer retry/manual upload; protected target/media/capacity outcomes require editor review or a new export pair. New Shopee blocked outcomes carry additive reason codes; legacy outcomes remain readable without rewriting historical rows. Recheck of pending content does not reset applied-media baselines.
- Same375-row local fixture:408 →25 review queries,375 →1 slug checks;6 work/369 complete unchanged. This is a SQLite read/render measurement, not a production latency claim.
- Laravel345tests/2384assertions and31Node tests passed; targeted Pint, Vite build and diff checks passed. Real Chrome review at390×844 and1440×900 covered375 rows, recovery, pagination, size-choice search, ready/queued/empty/disabled states. Existing build warnings and genuine touch/live upload limits remain explicit.

Evidence/screenshots/cleanup/rollback: [AUD-03 verification](../verification/aud-03/README.md). Reviewed/released through PR24: [proof](../verification/audit-release/README.md). Applied-media holds retain their guards; release is not an overwrite or re-import instruction.

## AUD-02 — Consolidate current repository context — DONE

Owner execution approval: “oke gas aud-02”. Scope: documentation only across AGENTS, README, rules, architecture, plan and board; safe current/history navigation and unique ADR references. No application/data/schema/media/config/credential/deployment work or next refactor.

Dependencies: AUD-01 source-referenced findings and already accepted Owner rules, particularly automatic connected pricing/drafts (ADR-025), Shopee recovery (ADR-026), search (ADR-027), recipient checkout (ADR-028).

Acceptance:
- Fresh session can follow AGENTS → rules → architecture → active board → relevant ADR/runbook/source without reading every historic phase.
- Clearly identify UUID draft creation, automatic valid prices/source status, manual/Shopee persistent media, human publication and recipient WhatsApp ordering.
- Retain dated launch approvals and decision/evidence text; validate moved relative links and alias old checkout ADR filename after separating its duplicate number.
- Preserve P9 unknowns; no invented completion, business rule, code or infrastructure change.
- Define one document owner per context purpose and the future-feature update convention, rather than creating competing context files.

Outcome: current documents reconciled; four complete pre-consolidation snapshots retained with source hashes/provenance; original checkout ADR-026 becomes compatibility alias to canonical ADR-028; ADR-026 Shopee and ADR-027 search retain their IDs. Earlier scope decisions are explicitly marked partially superseded, not erased. Historical product counts/one-off launch permissions are separated from recurring rules. Documentation-only diff/link/history checks and limits: [AUD-02 verification](../verification/aud-02/README.md). Reviewed/included in merged PR24: [release proof](../verification/audit-release/README.md).

## AUD-01 — Repository maintainability audit — DONE

Investigation complete, no refactor. [Report](../audits/2026-10-07-maintainability.md) records 14 findings, no established Critical finding, actual code boundaries, data/runtime unknowns and bounded follow-ups. AUD-02 approval accepts the documentation follow-up, not all proposed code/business changes. Historical Laravel339/2306assertions and31Node baseline plus related-card browser390/1440 screenshots belong to AUD-01 evidence; they are not new tests run by AUD-02.

## P9-01 — Bounded production acceptance — IN_REVIEW

[P9-01 evidence](../verification/p9-01/README.md): last observed PR15/0dc6924; health/feed authentication, current public readiness/local file existence, cached-source price/status parity, scheduled reconciliation and one worker passed within the read-only scope. The Owner's real batch apply/stored photos were observed independently; agent did not apply it. These are dated observations, not immutable counts.

Remaining acceptance limits:
- Physical Safari/genuine touch single-tap and scroll not tested.
- Native file chooser/upload path blocked by existing extension permission; permission was not changed. Persisted Owner import/CDN outcomes are confirmed, native upload path is not.
- New source-event timing/replay/outage in current production not exercised; existing source tests/staging event evidence do not replace it.
- Populated production recipient checkout/WA handoff not submitted; empty cart/navigation passed. No fabricated customer/order.
- Two protected media-baseline holds preserve published photos; retry alone cannot reconcile stale baseline. AUD-03 improves local recovery guidance; production resolution is still unconfirmed and is not permission to overwrite.

Scope remains verification only unless separately approved. Owner advanced to the audit without closing these gates. P1-04 and older P8 staging/launch IN_REVIEW records remain in history; review remaining scope against these latest observations rather than re-running old launch scripts or resetting checkpoints.

## Released feature evidence index

| Area | Current status | Relevant source of proof |
|---|---|---|
| P1-04 production activation/GitHub release | Released, acceptance IN_REVIEW | [Cutover](../verification/p1-04/PRODUCTION_CUTOVER.md), [P9](../verification/p9-01/README.md) |
| P8-01–03 webhook/feed/status activation | Released, current event gate under P9 | [Integration runbook](../runbooks/QAMMARIS_APP_INTEGRATION.md), [P9](../verification/p9-01/README.md) |
| P8-04–07 historical launch drafts/copy/pairing | Executed bounded launch steps; old reviews retained | [Historical backlog](../history/2026-10-07-context/BACKLOG.md); ADR-022–024, not recurring write authorization |
| P8-08 automatic drafts/source pricing | Released PR4/5; earlier device waiver scoped | [Implementation](../verification/p8-08/README.md), [P9 price parity](../verification/p9-01/README.md) |
| P7-10 daily best sellers | Released; homepage price hidden later | [P7-10](../verification/p7-10/README.md), current rules |
| P7-11 dropdown filters | Released PR13 | [P7-11](../verification/p7-11/README.md), [combined release](../verification/releases/2026-10-07/README.md) |
| P7-12 cart/recipient checkout/direct cart motion | Released PR13; device/send limits retained | [Checkout](../verification/p7-12/README.md), [motion](../verification/p7-12/cart-motion/README.md), ADR-028 |
| P7-13 destination skeleton navigation | Released PR13 | [P7-13](../verification/p7-13/README.md), combined release |
| P8-09 recurring Shopee XLSX enrichment | Released PR13 | [P8-09](../verification/p8-09/admin-import/README.md), [runbook](../runbooks/SHOPEE_ADMIN_IMPORT.md) |
| P8-09a exact21 Owner supplement | Completed exact approved batch | [Owner21](../verification/p8-09/OWNER_21_PRODUCTION.md); not global import/publication permission |
| P7-15 relevant typo search | Released PR14 | [P7-15](../verification/p7-15/README.md), ADR-027 |
| P8-10 size/recovery/work-only Shopee review | Released PR15 | [P8-10](../verification/p8-10/README.md), ADR-026 |

Previous detailed items/acceptance remain in [history](../history/2026-10-07-context/BACKLOG.md); older counts, pending release remarks and one-off exceptions have not been discarded. Release proof confirms code availability; remaining device/live workflows are not silently accepted.

## Work remaining after AUD-07

The bounded AUD-01–07 queue is reviewed, released and complete within documented scopes. There is no approved AUD-08.

1. Review/consolidation, staging/MySQL rehearsal, targeted production migration and GitHub release are complete: [proof](../verification/audit-release/README.md). The pending-migration guard was retained; no product data/media rewrite occurred.
2. Close or explicitly scope-waive outstanding P9/device/upload/new-source-event/populated-checkout/protected-media and Owner acceptance limits documented above. Local audit tests do not satisfy them.

Payment gateway, machine-write website API, cloud migration and arbitrary normalization are not active backlog items or launch prerequisites. New requests require a concrete approved scope. Measured query/compatibility-route/name cleanup remains later work only after benchmark/consumer evidence (AUD-F10/F13/F14), not a new approved phase. Unrelated audit findings stay in the [register](../audits/2026-10-07-maintainability.md).
## ANALYTICS-02 — Optional GA4 visitor cities

Owner approved direct implementation on 2026-10-12. Implemented: production-only discovery-page allowlist, explicit visitor consent with withdrawal, sanitized page context, no advertising/automatic form tracking, and city-report runbook. Cart, checkout, quiz, private order/task links and Admin remain excluded. No schema, customer data, order flow or media changes. Six PHP analytics tests /81 assertions, 67 Node tests, mobile390/desktop1440 browser consent checks, PR58 CI and official release passed. Live safe/private pages, unchanged flags, two workers and fresh cron heartbeats passed. Initial tag loading produced no collection request; a focused follow-up uses the vendor Arguments command contract with a regression test. Collection acceptance remains pending until Realtime receives a consenting public visit. Android device testing is not available in this task. See [analytics runbook](../runbooks/ANALYTICS.md) and [verification](../verification/ga4-city/README.md).
### ANALYTICS-02 UI correction — consent preference in footer

Owner requested removal of the floating “Pilihan analytics” control after Allow/Skip. The first-visit consent and remembered choice remain; the preference control moves into the normal footer flow so it no longer overlays the homepage/catalog. The GA4 loader, tracking boundaries and environment settings are unchanged. Targeted analytics tests and mobile/desktop browser verification are the gate; no performance delta is claimed without a comparative measurement.
