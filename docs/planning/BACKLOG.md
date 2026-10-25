# Qammaris — backlog current

Updated 2026-10-18: statuses reconciled against `main` and production (see [Status reconciliation](#status-reconciliation-2026-10-18)). This board is the entry point; detailed prior scope/acceptance/results remain in the [historical backlog](../history/2026-10-07-context/BACKLOG.md), [audit](../audits/2026-10-07-maintainability.md), ADRs and linked verification. Historical “next/unstarted/pending” paragraphs are relative to their date. Do not implement them again or treat them as new authorization. Current rules: [BUSINESS_RULES](../product/BUSINESS_RULES.md); executable flow: [ARCHITECTURE](../architecture/ARCHITECTURE.md).

**Release update:** Owner authorized review/consolidation, staging/MySQL migration and production release, plus a temporary staging actor inside a fully rolled-back test transaction. AUD-01–07 are complete within their approved scopes and included in merged PR24 / production `9325df4`. [Release proof](../verification/audit-release/README.md) supersedes implementation-phase pending-release/MySQL-unconfirmed statements retained below and in dated verification. P9 acceptance limits remain open; this is not 100% closure of the original program.

## Status and working limit

BACKLOG = proposed/not approved for execution; READY = accepted bounded scope ready; IN_PROGRESS = active work; IN_REVIEW = complete within stated scope, review/limits still open; BLOCKED = named external prerequisite; DONE = accepted criteria and stated verification completed. One approved implementation item at a time. A released feature may have separate IN_REVIEW device/runtime acceptance; do not infer either code unreleased or all tests passed from the label alone.

## Status reconciliation 2026-10-18

Every item below that was still IN_REVIEW, RELEASED or "DONE (RC)" was checked against `main` and production on 2026-10-18 (issue #69). Older "branch only / not deployed / UAT not switched" remarks in linked evidence are dated and superseded by this check.

- **Code on production:** each item's PR is merged into `main`, and every merge from PR13 to PR64 has a successful "Production release" run (CI deploys `main` automatically). The RC PR55 (`c12b1be`, 2026-10-12) carries ORD-01–04, ORD-07 and PWA-01.
- **Live switches, observed from outside (public GET requests only, no server or database access):**
  - website checkout saves orders: `/cart/checkout` renders the saving form (`checkout_key`, pickup/delivery, "Menunggu konfirmasi staf"), which needs both `ORDERS_WEBSITE_CHECKOUT` and `ORDERS_V2_ENABLED`; the throwaway session cart was cleared, no order was submitted;
  - Order API v1 for Qammaris App: an unsigned request returns 401 `unknown_client`, not 503 `unavailable`, so the API is enabled with credentials;
  - public PWA: `/manifest.webmanifest` and `/sw.js` return 200; Admin PWA manifest returns 200;
  - Cloudflare Web Analytics beacon and the consent-gated GA4 tag are in the public HTML; no `google-site-verification` meta tag is present;
  - multistep fragrance quiz UI is live on `/fragrance-quiz`.
- **Not observable from outside:** `ORDERS_SIMPLE_UX` (staff/customer order-link screens only), real-device push delivery, GA4 Realtime collection, Search Console verification by another method.
- **Finding:** the "Production release" run for docs-only `c9d0623` (#72) failed on an SSH connection timeout to the host; production stayed on `d6150e3`, also docs-only, so no feature was affected. The next merge to `main` deploys again.
- **Leftover branches (card #70):** the five remote branches not on `main` were resolved. `codex/pref-03-ui-release-record` (PR54) and `codex/bestseller-discovery` held release evidence only; it now lives on `main` (links above). `codex/pref-03-multistep-ui` and `codex/pref-03-revision-release-record` were already on `main` through PR53. `claude/confident-cori-cfu3sn` only vendored the third-party `find-skills` agent skill, which is not needed in the repository. Every branch tip is kept as tag `archive/<branch name>` before the branch was deleted; restore with `git push origin archive/<name>:refs/heads/<name>`.
- **Rule used:** an item is DONE when its scope is released and in use; device checks and Owner reviews that were never run are kept as open limits below, not reported as passed.

Open limits after this reconciliation (none blocks current use):

| Item | Open limit |
|---|---|
| PWA-01, ORD-02b | Real Android/iPhone install and Android link handling between the public and admin installed apps not tested |
| ORD-09 | Push delivery on a real device not yet confirmed by the Owner |
| ORD-03 | `ORDERS_SIMPLE_UX` value not observed from outside (check with `qammaris:release-check` / server `.env`) |
| ANALYTICS-01 | Search Console verification meta tag absent; confirm in Search Console if verified another way |
| ANALYTICS-02 | GA4 Realtime collection from a consenting visit not confirmed |
| UX-02 | Logos for the 22 brands without one |
| BLOG-06 | Owner editorial walkthrough, three legacy alt fields, one prose price/stock article; physical Safari / production upload / external SEO / sustained load unconfirmed |
| ABOUT-04, UI-21ST-01/02, ABOUT-02 | Owner visual review; physical iPhone Safari |
| SEC-DEP-2026-10 | App repository: 6 high `npm audit` findings in build-only tooling, no fix available (App's own item) |
| TEST-MYSQL-02 | `ProductSeeder` fails on duplicate SKU `AFNAN-9PM-50ML` (local seeding only) |
| P9-01 | Limits listed in its own section |

## OPS-DB-BURST-01 — Pages fail during traffic bursts — DONE

Found 2026-10-25 in the maintenance check (card #99): a 4-way parallel sitemap crawl returned HTTP 500 on 65 of 430 pages with `SQLSTATE[HY000] [2002] Operation not permitted`; the same error hit ~500 product requests on 2026-10-22 (07:47 and 12:48 UTC, sequential product slugs, i.e. a crawler). Measured on production: Hostinger refuses new MySQL connections above ~20 per second (socket and TCP alike); `max_user_connections` 75, `wait_timeout` 20 s, so persistent connections were rejected as an option. Fix: `App\Support\RetryingMySqlConnector` (bound as `db.connector.mysql`) retries only this refusal after 100/200/400/800 ms pauses with up to 50% jitter (worst case ~2.3 s extra), all other connection errors fail as before. Production MySQL probe with the connector (no app change): 4 parallel clients at ~40 connects/s failed 35% without and 4% with retries; an extreme 120-connect burst in ~0.1 s 83% → 8%. Sustained traffic far above ~20 PHP requests/s still fails; full-page caching would be the next step if that becomes real traffic. Released PR100 (production `f242599`, 2026-10-25): the same sitemap crawl returned 430/430 HTTP 200 at 4-way (avg 0.19 s, before 65 errors) and 8-way parallelism (avg 0.37 s, max 1.5 s), with no new `laravel.log` lines.

Other findings of the 2026-10-25 check (not fixed here):

| ID | Status | Finding |
|---|---|---|
| OPS-PUSH-STALE-01 | DONE | Four `SendAdminPush` jobs from 2026-10-18 06:17 sat on the `default` queue that the production worker never reads; created before fix `591bbda` moved admin push to the worker queue. Owner approved removal 2026-10-25; exactly those four rows (queue `default`, job `SendAdminPush`, created before the fix) were deleted on production; the jobs table is empty and `failed_jobs` stays 0 (card #102). |
| SEC-DEP-03 | DONE | `npm audit`: high advisories in dev-only `vite` 7.3.6 (launch-editor, dev server on Windows) and `follow-redirects` 1.16.0 (axios Node adapter, not in the browser bundle). `npm audit --omit=dev` and `composer audit` were clean. Lockfile-only patch to vite 7.3.7 / follow-redirects 1.16.1 (card #102); `npm audit` clean, build and JS tests pass. |
| TEST-FLAKE-02 | DONE (no code change) | `BlogWriteSafetyTest` (replacement/archive/restore) failed once in a full local run. Not reproducible alone or in 6 random-order Blog runs; reproduced by running two test processes at once in the same checkout: `Storage::fake('public')` shares `storage/framework/testing/disks/public`, so one run wipes the other's files. The failed run overlapped a background run. Run one suite at a time locally (CI already does). |

## CAT-PRICE-01 — Struck-through price on catalog cards — DONE

Owner 2026-10-23 (card #96): the struck-through `compare_at_price` showed only on the product detail page. The shared catalog card (`products/_catalog-card`, used by the catalog, related products and Journal product embeds; the homepage best-seller rail hides prices) now shows it after the selling price, under the detail page's rule (only when higher than the active offer price), with a screen-reader label "Harga sebelumnya". The 2026-10-07 card-trust rule stays: size and selling price come only from the active offer; `compare_at_price` is never a fallback price (`PublicCatalogCardTrustTest` updated accordingly). Verified 390/1440 locally. Released PR97 (production `16666fb`, 2026-10-23); production catalog pages show the struck-through price on cards with a higher compare price.

## PERF-01 — Faster homepage on phones — DONE

Owner 2026-10-23 (card #92): hPanel phone score 82 (desktop 98); Lighthouse mobile baseline on production 79 (FCP 3.0 s, LCP 3.1 s, Speed Index 15.3 s, 24 MB). Cause: every hero video downloaded on phones although three are `hidden md:block` (mobile video alone 21.6 MB), large PNG illustrations/logo, no cache header for video/build assets. Done: Hostinger CDN enabled (pages/admin `DYNAMIC`, static files edge-cached); hero videos re-encoded without the unused audio track (mobile 21.6 → 3.0 MB, desktop 17.4 → 2.3 MB) under `public/images/hero/` with WebP posters and loaded per visible column after `load` (reduced motion/Data Saver keep the poster); illustrations/logo/favicon as display-sized WebP/JPEG; static cache headers in `public/.htaccess`; old hero videos removed from the repo (git history keeps them). Same local server, Lighthouse mobile: Speed Index 40.8 → 4.7 s, page weight 28.6 → 3.7 MB, score 62 → 70 (local `artisan serve` has no gzip, so FCP/LCP are not comparable to production). Released PR93 (production `3580101`, 2026-10-23): Lighthouse mobile on production 79 → 84 (Speed Index 15.3 → 3.9–4.9 s, 24 MB → 3.7 MB; FCP 2.8 s). Follow-up PR94 (production `3fe8543`, 2026-10-23): Inter self-hosted instead of the render-blocking Google Fonts stylesheet, mobile hero poster preloaded. hPanel speed test after both releases: phone 95 (was 82; LCP 2.6 s, FCP 1.7 s, Speed Index 3.2 s, TBT 0, CLS 0.001), desktop 95 (was 98 on 2026-10-12; FCP 0.6 s, LCP 0.8 s). Local headless Lighthouse against production now gets HTTP 403 from the CDN after repeated runs while normal browsers and curl get 200; use hPanel/PageSpeed for later measurements. Found, not fixed: the React navbar bundle (~188 KB, ~167 KB unused on the homepage) loads on every public page.

## PREF-01 — Preference audit and human quality reference — IN_REVIEW

Owner approved implementation of [V1 plan](QAMMARIS_FRAGRANCE_PREFERENCE.md), one item at a time. Read-only public378-product capture, explicit audit dictionary/normalizer, fingerprints, 750 written note forms, 25 placeholder products, 29 residual terms, 19 unverified identical-note groups and20 legacy baselines packaged. [Audit/checks/limits](../verification/pref-01/README.md); [30-case Owner worksheet](fragrance-preference/REVIEW.md). Owner subsequently delegated worksheet filling to Codex because of limited time. All20 normal profiles now have40 primary aroma proposals, one separate +3,6% alternative, evidence/rejection notes and provisional empty judgments; all10 edge contracts reviewed against the plan. [Agent reference](../verification/pref-01/agent-reference.json) is attributed to Codex, **not human gold labels or passed engine tests**. Four potential source conflicts separately recorded without writeback. Owner/staff independent acceptance, semantic decisions and reference freeze remain pending; no accuracy target asserted. No public quiz/UI/DB/media/package/credential change, no V1 accuracy claim. Owner explicitly continued PREF-02 development on2026-10-10; PREF-01 remains IN_REVIEW with independent quality acceptance pending. Previous About review is a separate visual acceptance.

| ID | Status | Next prerequisite |
|---|---|---|
| PREF-02 | IN_REVIEW | Backend code/provisional evaluation prepared under explicit continuation; independent review/calibration/quality acceptance pending; [evidence](../verification/pref-02/README.md) |
| PREF-03 | IN_REVIEW | Beta v1.2 correction live via PR51; Owner-requested 21st Multistep UI released via PR53 (`c8e6530`, 2026-10-10) and live ([UI release](../verification/pref-03/multistep-ui/RELEASE.md), [UI proof](../verification/pref-03/multistep-ui/README.md)); human quality review still pending; [revision release](../verification/pref-03/question-revision/RELEASE.md), [implementation proof](../verification/pref-03/question-revision/README.md), [initial beta release](../verification/pref-03/RELEASE.md) |
| PREF-04 | READY, not started | Final human-quality acceptance remains separate from the authorized PREF-03 beta technical release |

Owner2026-10-10 separately authorizes a labelled usable beta for testing before human-quality acceptance. [ADR039](../architecture/decisions/ADR-039-preference-website-beta.md) supersedes the prior beta release gate only; never mark independent quality targets passed or rely on About permission. PREF-01 cannot become DONE while human reference is pending. PREF-02 code has separate additive profile/revision migration, preview/apply/review operations, default-disabled catalog adapter, traceable provisional ranking and offline training/hold-out comparison.PREF-02.3 distinguishes explicit projection/sweetness/short-longevity compromises from unknowns and excludes nonpositive totals;53 focused tests/265 assertions passed. [Latest15-case quality comparison and limits](../verification/pref-02/quality-v3/README.md); earlier reader/backend proofs remain dated baselines. PREF-03 website adapter is now implemented on its branch with64 focused tests/406 assertions and local responsive/full-flow browser proof. Beta additive migrations, main CI/deployment and live result/feedback/photo/privacy verification passed2026-10-10; [release proof](../verification/pref-03/RELEASE.md). No catalog mutation; one active item is PREF-03. PREF-04 final acceptance remains unstarted.

## ABOUT-04 — Source-faithful split hero — DONE

Released PR46 (`71a4c2e`, 2026-10-09). Owner visual review and physical Safari stay as open limits (reconciliation 2026-10-18).

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

## Separate program — Qammaris Journal (released)

Owner approved the [Journal plan](QAMMARIS_JOURNAL.md), BLOG-01–05 implementation, transaction-only staging fixtures, five additive staging/production migrations after staging passed, and a blog-only production release. Order work is excluded. [BLOG-06 release evidence](../verification/blog-06/README.md) supersedes earlier phase “not deployed/staging unconfirmed” statements; it does not close P9. Owner subsequently authorized production API activation and local credential setup2026-10-08; [activation evidence](../verification/journal-activation/README.md).

| ID | Status | Current scope / next prerequisite |
|---|---|---|
| BLOG-01 | DONE | Safe shared writes, revisions, retained images, archive/restore and audit installed via PR31; selected MySQL DDL/replay and staging transaction passed. [Implementation](../verification/blog-01/README.md) |
| BLOG-02 | DONE | CMS fields, Tiptap visual/HTML editor, taxonomy/tags, authenticated preview and scheduling installed. Legacy IDs/URLs/content/authors preserved. [Implementation](../verification/blog-02/README.md) |
| BLOG-03 | DONE | Public responsive Journal/search/TOC/share, metadata/schema and scheduled sitemap boundaries installed; live three article URLs/schema and five widths checked. [Implementation](../verification/blog-03/README.md) |
| BLOG-04 | DONE | Owned responsive/cropped media and full editorial components/relations installed; GD/WebP staging and local native-file-input browser upload passed. [Implementation](../verification/blog-04/README.md) |
| BLOG-05 | DONE | Scoped draft-only API/schema/admin assignment installed; production gate enabled by separate Owner approval2026-10-08; one local machine actor/token. Staging auth/replay/revision/ownership/upload guards passed. Local activation/read access verified; cloud-only secret provisioning is not configured. [Implementation](../verification/blog-05/README.md) |
| BLOG-06 | DONE | Approved production release completed via PR31 /3c8d35f; CI/release/MySQL/runtime/public browser/touch passed; Journal live since 2026-10-08. Editorial walkthrough, three legacy alt fields, one prose price/stock article and physical Safari/production upload/external SEO/sustained load are open limits (reconciliation 2026-10-18). [Release](../verification/blog-06/README.md), [runbook](../runbooks/JOURNAL_RELEASE.md) |

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

## Separate program — Pesanan Online (released)

Owner requested and approved ORD-01 on 2026-10-07. ORD-01–04, ORD-07 and PWA-01 were released together through the RC PR55 (`c12b1be`, 2026-10-12) and their switches are live (reconciliation 2026-10-18). Program and later items: [ONLINE_ORDERS](ONLINE_ORDERS.md). The detailed development history of each row is in its evidence, ADR and the Git history of this file.

| ID | Status | Current scope / evidence |
|---|---|---|
| ORD-01 | DONE | Admin-created order + customer completion link, price snapshot, tracking timeline, staff group message + staff task link, shipping-fee advance/reimburse. Released in PR55. [Evidence](../verification/ord-01/README.md), [ADR-048](../architecture/decisions/ADR-048-online-order-links-and-tracking.md), [runbook](../runbooks/ONLINE_ORDERS.md) |
| ORD-02 | DONE | Owner redesign 2026-10-08, released in PR55: ORD-02a Super Admin/Staff Order RBAC + user management ([evidence](../verification/ord-02a/README.md), [ADR-049](../architecture/decisions/ADR-049-admin-roles-and-user-management.md)); ORD-02b Admin PWA ([evidence](../verification/ord-02b/README.md), [ADR-050](../architecture/decisions/ADR-050-admin-pwa.md)); ORD-02c V2 order model: state dimensions, per-order cutover, append-only payment/refund ledger, issues, packing, J&T handover, repeat customers, saved addresses, keep, price adjustments ([ADR-051](../architecture/decisions/ADR-051-order-cutover-and-money-ledger.md)); ORD-02d Admin PWA pages for V2 ([evidence](../verification/ord-02d/README.md)); ORD-02e private Order API v1 with HMAC, idempotency, claims and webhook outbox ([evidence](../verification/ord-02e/README.md), [ADR-041](../architecture/decisions/ADR-041-order-api-v1-implementation.md)). Contract now r4.5 ([API v1](../integrations/QAMMARIS_ORDER_API_V1.md)); API enabled in production. [Plan](ORD-02_PLAN.md), [audit](../audits/2026-10-08-ord-02-online-orders.md). Order ADRs renumbered 034→037→048 and 035→038→049 (aliases kept). |
| ORD-03 | DONE | Operational UX after Owner Manual UAT (2026-10-10): one contextual action per step, collapsed secondary steps, one-tap packing, J&T request → waiting → picked up, Link Pesanan share, customer page without site navigation, 16px touch fields; flag `ORDERS_SIMPLE_UX`. Released in PR55. [Evidence](../verification/ord-03/README.md), [ADR-043](../architecture/decisions/ADR-043-order-operational-ux.md). (The earlier ORD-03 "keep" item was absorbed into ORD-02.) |
| ORD-04 | DONE | Website cart checkout saves a V2 online order (`source=website`, draft "Menunggu konfirmasi website"): pickup / instant Palu / J&T, payment preference, idempotent key, catalog re-check, rate limit; staff confirm draft → active; payment required before packing/courier/handover with a five-step stepper and verified store accounts ([ADR-045](../architecture/decisions/ADR-045-payment-gate-and-order-stepper.md)); Pesanan Saya for 10 days without login, paperbag Rp4.000 per order, direct "Kirim ke WhatsApp" ([ADR-046](../architecture/decisions/ADR-046-order-ux-polish-history-paperbag.md)). Released in PR55; checkout live. Emergency stop `ORDERS_WEBSITE_CHECKOUT=false`. [Evidence](../verification/ord-04/README.md), [ADR-044](../architecture/decisions/ADR-044-website-checkout-orders.md) |
| ORD-06 | Alias | → ORD-04 (cart checkout creates the same online order) |
| ORD-07 | DONE | Test environments (`local`, `development`, `testing`, `uat`, `staging`) never use the production store WhatsApp number: `STORE_WHATSAPP_TEST_NUMBER` or a copyable message; production unchanged. Released in PR55. [Evidence](../verification/ord-04/README.md#whatsapp-in-test-environments-ord-07-before-the-uat-switch) |
| UX-02 | DONE | Catalog price filter with thousands separators, quiz budget, Pesanan Saya icon, daily Terlaris mix, Distributor Resmi brand carousel, 10 homepage best sellers + catalog end card. Released PR56 (`0add5ac`, 2026-10-12). [Evidence](../verification/ux-02/README.md). Open limit: logos for 22 brands. |
| ANALYTICS-01 | DONE | Cloudflare Web Analytics (cookieless) + Google Search Console tag, production only, excluding order links and Admin. Released PR57 (`c43fcb3`, 2026-10-12); beacon live. Open limit: Search Console meta tag absent. [ANALYTICS](../runbooks/ANALYTICS.md) |
| ADM-01 | DONE | Direct price adjustments without Super Admin approval (audit kept, total ≥ 0), admin shell black block / double scroll fix, Sharelok after website checkout, "Muat ulang" button. Released PR61 (`535d5f3`, 2026-10-17). |
| ORD-10 | DONE | Owner 2026-10-21 (card #75): the customer picks "Metode pembayaran ongkir" (Sekalian transfer / Bayar langsung ke driver) on the order link and website checkout for local delivery; it fills the existing `shipping_payer`, so staff only type the ongkir amount. J&T is always part of the payment; pickup is not asked; free ongkir stays a store decision; locked once any money is received. No migration, Order API contract unchanged. Paid-to-driver ongkir counts as a final total, so payment instructions no longer wait for an amount; staff texts tidied after a full local walk-through. Released via PR76 (`d528af9`, 2026-10-21); production checkout shows the question. Open limit: physical phone/Safari not tested. [Evidence](../verification/ord-10/README.md). |
| ORD-11 | DONE | Owner 2026-10-21 (card #77): the WhatsApp message from a website checkout ends with "Link pesanan untuk admin", the same task link as the staff group (random public ID, no contact data, admin login required, follows `QAMMARIS_ORDER_APP_TASK_LINKS`). One tap opens the order; a guest lands on the admin login and returns to the order after signing in. Same branch and PR as ORD-10. Released with ORD-10 via PR76 (`d528af9`, 2026-10-21); [evidence](../verification/ord-10/README.md#ord-11--order-link-in-the-checkout-whatsapp-message). |
| ADM-02 | DONE | Owner 2026-10-21 (card #79): Pesanan Online list uses three tabs Aktif · Selesai · Dibatalkan that apply on tap (no "Tampilkan"); search stays, runs on Enter and looks in every status; special filters only from the attention banners. Owner feedback 2026-10-21: back link returns to the last tab, sliding pill with list swap, no square press frame in the admin, WhatsApp icon on every WhatsApp button (card #82). Branch `modernization/adm-02-order-filters`; [evidence](../verification/adm-02/README.md). Released via PR80 (production `1c63b3d`, 2026-10-21). |
| ADM-03 | DONE | Owner 2026-10-21 (card #83): Tandai Lunas opens a short dialog (amount, method, "Bukti pembayaran sudah dicek", "Sudah dicatat di kasir (Majoo)"); confirm stays disabled until both are ticked and the server refuses a payment without the Majoo check. Branch `modernization/adm-03-majoo-before-paid`; [evidence](../verification/adm-03/README.md). Released via PR84 (production `1c63b3d`, 2026-10-21). |
| ADM-04 | DONE | Owner 2026-10-21 (card #85): order page flow clearer without a redesign: the step to work on is a gold-tinted card with "Langkah sekarang", visible turning chevrons, "Ada masalah?", a "Lainnya" divider before the exception cards, "Poin member" adjustment without a reason, and a clear "Terapkan penyesuaian" button. Branch `modernization/adm-04-order-page-flow`; [evidence](../verification/adm-04/README.md). Released via PR86 (production `1c63b3d`, 2026-10-21). |
| OPS-DEPLOY-01 | DONE | Found 2026-10-23 releasing ORD-05: a deploy refused for pending migrations leaves `releases/qammaris-<sha>` (and the workflow's `releases/.deploy-<sha>`), so a re-run of the same revision stops at `test ! -e` without a message; both had to be removed by hand. Also the build package has no `bootstrap/cache`, so running artisan from it needs `mkdir -p bootstrap/cache` (otherwise a masked `Class "request" does not exist`, from `request()` in the console exception reporter in `bootstrap/app.php`). Fix: clean up the candidate on refusal and make the reporter console-safe. Fixed 2026-10-23 (card #89): the deploy script removes its candidate when it stops before the switch or rolls back (never the release `current` points to) and names a leftover folder instead of failing silently; the deploy script also removes the workflow's `.deploy-<sha>` temporary (the workflow file is unchanged: the laptop token has no workflow scope); the console exception reporter checks for a request first; the package ships `bootstrap/cache/.gitignore`. Simulated in WSL (refused → re-run succeeds, rollback cleans up, leftover message) and covered by `ConsoleExceptionReportTest`. Released via PR90 (production `d042173`, 2026-10-23); deploy left no `.deploy-*` folder. |
| ORD-05 | DONE | Owner 2026-10-21 (card #67): customers saved automatically (same WhatsApp number = same customer; admin-created complete orders, customer link submit, website order on staff confirmation); Pelanggan menu for Super Admin + Staff Order (list, search, history, addresses, edit name/note); delivery address kept at handover; manual Super Admin anonymization (blocked while an order runs; App receives the cleared revision). Production backfill `qammaris:customers:backfill` is preview-first and needs separate Owner approval + backup ([runbook](../runbooks/CUSTOMER_BACKFILL.md)). Release steps (backup, migration from the build package, merge): [RELEASE_ORD_05](../runbooks/RELEASE_ORD_05.md). One additive migration. Branch `modernization/ord-05-customers`; [evidence](../verification/ord-05/README.md). Released 2026-10-23: migration run from the build package, then production `32700b9`; backfill preview 8 orders → 5 new customers / 7 links / 1 skipped, apply waits for the Owner. [Release record](../runbooks/RELEASE_ORD_05.md#release-record-2026-10-23). |
| ORD-08 | DONE | All order work on the Website; ongkir recorded once with "Ongkir ke kurir" + "Uang siapa" (active App employees / Kasir / Customer; Super Admin: Uang saya (GoPay Owner) / Kasir / Customer); an employee advance becomes an approved App reimbursement via r4.5 website-origin costs; App Pesanan Online view-only. Released 2026-10-18 (Website `a5adcd2`, App `7bcded1`), follow-up fix PR64. [ADR-052](../architecture/decisions/ADR-052-website-courier-cost-and-auto-reimbursement.md), [r4.5](../integrations/R4_5_DRAFT_WEBSITE_COSTS.md), [evidence](../verification/ord-08/README.md), [pre-production audit](../verification/release-audit-2026-10-17/README.md), [release record](../runbooks/RELEASE_ORD_08_09.md#7-release-record-2026-10-18) |
| ORD-09 | DONE | Web Push to Super Admin + Staff Order for each website order (code, items, total; no contact data). Released 2026-10-18 with ORD-08; queue fix `d7d1a22` (#63). Open limit: real-device delivery confirmed by the Owner. [ADR-053](../architecture/decisions/ADR-053-admin-web-push.md), [runbook](../runbooks/ADMIN_PUSH.md) |
| OPS-INODE-2026-10 | DONE | Production deploys left old releases (403,410/400,000 inodes on 2026-10-17). `deploy-production-release.sh` now prunes after a healthy switch; post-deploy prune verified on production 2026-10-18, 72k inodes (≈18%). Staging deletion still an Owner decision. [Runbook](../runbooks/HOSTINGER_INODE_CLEANUP.md) |
| SEC-DEP-2026-10 | DONE | `symfony/http-foundation` CVE-2026-108082 patched 7.4.18 → 7.4.21 (lock-only), released 2026-10-18; `composer audit` clean. The App build-tooling `npm audit` finding belongs to the App repository. |
| PWA-01 | DONE | Installable public website "Qammaris Perfumes" (scope `/`, worker `/sw.js` caching only build assets/public icons/offline page; admin and non-GET untouched). Released in PR55; `PUBLIC_PWA_ENABLED` on. Open limit: real-device install/link handling. [Evidence](../verification/pwa-01/README.md), [ADR-047](../architecture/decisions/ADR-047-public-website-pwa.md), [runbook](../runbooks/PUBLIC_PWA.md) |
| REL-ORD-PWA | DONE | One RC PR55 for ORD-01–04 + PWA-01, merged and deployed 2026-10-12 (`c12b1be`); access matrix and MariaDB 745/745 in the RC audit. [Plan](../runbooks/RELEASE_ORD_PWA_RC.md), [audit](../verification/rc-ord-pwa/README.md) |
| DOC-ADR-01 | DONE | Order ADRs renumbered 037→048, 038→049, 039→050, 040→051 (old filenames kept as aliases); now on `main` via PR55. |
| TEST-MYSQL-02 | DONE | `BlogAutomationTest` and `StoreAboutTest` MariaDB-only failures fixed test-only; on `main` via PR55. Open limit: `ProductSeeder` duplicate SKU `AFNAN-9PM-50ML`. |
| TEST-MYSQL-01 | DONE | Two `ShopeeContentImportTest` cases assumed SQLite; fixed test-only on 2026-10-09; full suite on MariaDB 11.8.9: 522 passed. |
| TEST-FLAKE-01 | DONE | Found 2026-10-18 on docs-only PR74 CI: `OrderApiReadTest::authentication failures use the contract codes in order` signs a request at exactly `now() - 300` s; when the clock ticks one second before the middleware check the difference is 301 and the request is refused (401 instead of 200). Timing-only test flake; freeze time in the test. No production impact. Fixed 2026-10-25 (card #102): the test freezes time first. |

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
| P7-10 daily best sellers | Released PR8; homepage price hidden later | [P7-10](../verification/p7-10/README.md), [production release](../verification/p7-10/production/README.md), current rules |
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
## ANALYTICS-02 — Optional GA4 visitor cities — DONE

Released PR58–60 (2026-10-12); consent-gated tag live. GA4 Realtime collection stays an open limit (reconciliation 2026-10-18).

Owner approved direct implementation on 2026-10-12. Implemented: production-only discovery-page allowlist, explicit visitor consent with withdrawal, sanitized page context, no advertising/automatic form tracking, and city-report runbook. Cart, checkout, quiz, private order/task links and Admin remain excluded. No schema, customer data, order flow or media changes. Six PHP analytics tests /81 assertions, 67 Node tests, mobile390/desktop1440 browser consent checks, PR58 CI and official release passed. Live safe/private pages, unchanged flags, two workers and fresh cron heartbeats passed. Initial tag loading produced no collection request; a focused follow-up uses the vendor Arguments command contract with a regression test. Collection acceptance remains pending until Realtime receives a consenting public visit. Android device testing is not available in this task. See [analytics runbook](../runbooks/ANALYTICS.md) and [verification](../verification/ga4-city/README.md).
### ANALYTICS-02 UI correction — consent preference in footer

Owner requested removal of the floating “Pilihan analytics” control after Allow/Skip. The first-visit consent and remembered choice remain; the preference control moves into the normal footer flow so it no longer overlays the homepage/catalog. The GA4 loader, tracking boundaries and environment settings are unchanged. Targeted analytics tests and mobile/desktop browser verification are the gate; no performance delta is claimed without a comparative measurement.
