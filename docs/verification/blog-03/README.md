# BLOG-03 — Public Journal/search/SEO verification

Date: 2026-10-07. Status: IN_REVIEW, not deployed. Branch modernization/blog-03-journal depends on BLOG-02 (68fae08), which depends on BLOG-01. Owner selected this item with “gas lanjut”. Order work remains separately assigned to Claude Code; no order implementation changed.

## Outcome and scope

Calm Journal landing with search/category/optional selected featured article, newest grid and query-preserving native pagination. Article hierarchy shows title/dek/author/date/read time above contained hero, about720px desktop body and optional TOC/product-link sidebar. Mobile uses one column and TOC accordion. Tables scroll internally. Max3 related articles, next article, store CTA and WA/copy/Facebook/X/native share. Public views/English sharing labels/decorative hero overlay removed.

Search uses bounded typo metadata matching; body is exact normalized phrase secondary. Hidden/draft/archive/scheduled articles are excluded before matching. Active tags and current eligible product/brand markers are searchable without catalog writes. Product card pricing and richer components remain BLOG-04.

Server-validated canonical/index/follow/OG editor fields and shared revision/audit writes. Article image is OG fallback; overrides reference already-available HTTPS files without downloader. Safe JSON-LD BlogPosting/BreadcrumbList, clean article canonical/share URL, category/page listing canonical, search noindex. Sitemap excludes external-canonical/nonindexable articles; editorial/publication lastmod and TTL/HTTP max-age stop at scheduled publication boundary.

## Checks actually run

- JournalPublicTest: 8 tests /98 assertions passed. Covers metadata priority/typo/body boundaries, active tags/product visibility/no catalog writes, private visibility, feature/filter/pagination, TOC/unique IDs/table containment/no source rewrite, escaped head/JSON-LD/HTTPS and malformed array validation/audit hashes, sitemap scheduled cache boundaries, no-write preview.
- Baseline40 blog tests /254 assertions passed before additions.
- Full Laravel suite: final423 tests /2993 assertions passed,18.57s. An initial run failed HeadMetadataSafetyTest's old two-schema expectation; updated to assert exact Organization/WebSite/BlogPosting/BreadcrumbList types and validate every JSON block. Repeated relevant test and full suite passed. A preceding full run without the local Vite manifest, matching PHP CI conditions, passed423 tests /2989 assertions; restored manifest, then added four URL-array validation assertions and reran the final full suite.
- Node tests:31 passed,0 failed.
- Vite build:723 modules passed. Journal JS1.43kB (.69 gzip), CSS8.52kB (2.26 gzip); no public editor bundle. Existing DaisyUI @property optimizer warning and large about-page3D chunk remain.
- Pint dirty passed; git diff --check passed; composer validate --strict passed.
- Additive migration000004 ran on disposable local SQLite. No staging/production migration executed.
- npm audit reports the same3 pre-existing advisories: concurrently/shell-quote critical, source-map-js high (SEC-DEP-01). No dependency/lock changes in BLOG-03. Audit is not clean.
- Local synthetic15-article search benchmark:10 passes, median3.23ms, maximum11.30ms (warm local SQLite). Not production-size performance evidence.

## Real browser observations

Chrome extension, mouse/viewport tests only. Public landing and long article checked at320/375/390/768/1440px. scrollWidth did not exceed viewport: browser scrollbar uses15px, measured widths305/360/375/753/1425. Desktop body720px. Small tables scroll540px content within273/328/343px wrappers; no page overflow. Mobile TOC collapsed initially, desktop open. Native anchor click settles at~120px below fixed navbar; headings repeated with unique anchors.

Typed pnduan via mobile form: metadata typo matched expected title/summary articles; category Review reduced results to4, nonsense term showed empty state/clear action. Clicked next/previous pagination; page2 canonical retained page number. Opened article from image/text action and short next article with keyboard; short article had no TOC. Copy button showed “Tautan berhasil disalin.” Keyboard Tab reached category/SEO flag with visible focus. Existing destination skeleton appeared during native form/navigation and disappeared at destination. Genuine touch is not verified.

Synthetic missing hero changed to local nonexistent file: fallback loaded product-placeholder.svg (naturalWidth600), zero remaining failed images; fixed frame retained. No public page console errors before intentional404. Expected missing-image404 during this test is not a production-media check. Actual metadata DOM has four valid schema blocks and article image/clean canonical; Tiptap absent from public scripts.

Admin SEO fields inspected at1440/390px on a disposable local test account, labels/help text/readable checkbox focus; no mobile overflow. No admin article write was needed for layout check. Invalid input/shared-save behavior covered by server tests. Native share dialog/external provider send and clipboard-denied fallback not exercised in browser. No outbound WhatsApp/social messages sent.

## Screenshots and rationale

The featured article gives one clear entry; metadata/search stay readable without cards/overlays competing with content. Narrow reading column, conditional TOC and contained table reduce reading/navigation effort. Screenshots use explicitly synthetic articles; no customer data.

| Surface | Before | After |
|---|---|---|
| Landing desktop | [Before](screenshots/before-landing-desktop.jpg) | [After](screenshots/after-landing-desktop.jpg) |
| Landing mobile | [Before](screenshots/before-landing-mobile.jpg) | [390](screenshots/after-landing-390.jpg), [320](screenshots/after-landing-320.jpg), [375](screenshots/after-landing-375.jpg), [768](screenshots/after-landing-768.jpg) |
| Long article | [Desktop](screenshots/before-article-desktop.jpg), [mobile](screenshots/before-article-mobile.jpg) | [1440](screenshots/after-article-1440.jpg), [390](screenshots/after-article-390.jpg), [320](screenshots/after-article-320.jpg), [375](screenshots/after-article-375.jpg), [768](screenshots/after-article-768.jpg) |
| States | — | [Empty](screenshots/after-empty-mobile.jpg), [TOC/table](screenshots/after-toc-table-mobile.jpg), [copy success](screenshots/after-share-mobile.jpg), [short article](screenshots/after-short-mobile.jpg), [failed-image fallback](screenshots/after-fallback-mobile.jpg) |
| SEO admin | Existing grouped editor retained | [Desktop](screenshots/after-admin-seo-desktop.jpg), [390](screenshots/after-admin-seo-mobile.jpg) |

## Files/data/documentation and release limits

Core: BlogController, SitemapController, JournalSearch/JournalMetadata/RenderBlogContent, BlogEditorialRules, BlogPost, shared SaveBlogPost/RecordBlogPostChange and admin preview. UI: blog views/partials, dedicated journal assets/Vite entry, admin SEO section, layout metadata fallbacks, Journal labels in nav/footer/home. Tests: JournalPublicTest and expanded HeadMetadataSafetyTest.

Migration000004 adds six metadata columns only; existing rows default index/follow true, nullable overrides. No body/media/price/stock/author/ID/slug backfill or production write. Public read retains existing internal view-count increment. Synthetic environment/database/account/articles/session/scripts are disposed after checks; dependency/build tooling stays local/ignored.

Updated backlog, rules, master plan, architecture, Journal roadmap, ADR-034/index and JOURNAL_PUBLIC runbook. Review/CI links are recorded with delivery. Not a production release or phase acceptance.

Remaining: staging MySQL/runtime/migration rehearsal, native upload and genuine touch acceptance; external Google validator/Search Console/indexing not confirmed. No Lighthouse/Core Web Vitals measurement or large production journal dataset. Preview regression is server-tested; sandboxed interactive preview restrictions remain BLOG-02 limits. No API credentials created.

Recovery: retain additive schema/history/media; no populated migration down. After new index/canonical values are used, old code ignoring them may reintroduce indexing; use compatible rollback/forward fix. Old article text prices/stock require human BLOG-06 review.

Recommended next item: BLOG-04 media/crops and complete components/ordered relations, after Owner selects it. Not started.
