# ADR-034 — Public Journal search, rendering and SEO

Date: 2026-10-07. Status: implemented in BLOG-03 review branch, not deployed.

## Context and decision

Owner selected public Journal after BLOG-02. Keep Blade/native navigation and stable /blog URLs. Public eligibility remains the existing publication/date/archive predicate. No historical article rewrite or catalog mutation.

JournalSearch reuses bounded SearchMatcher for title, excerpt, active tags and eligible marker-product/brand names. Sanitized body matching is a literal normalized phrase with word boundaries, secondary to metadata; never fuzzy. All query terms must match metadata under existing matcher rules. A single product lookup excludes draft/hidden/archived products. No search service or generic repository. Cost is linear in visible articles: measure again before larger datasets; local small-fixture measurements are not production performance proof.

RenderBlogContent produces safe HTML, unique H2/H3 anchors, contained scrollable tables and eligible current product links. The TOC appears only with three nonempty H2 headings. Source content stays unchanged. Public and authenticated preview share rendering. Full product cards and ordered relations remain BLOG-04.

Use dedicated small Journal Vite assets; do not load Tiptap publicly. One-column mobile reading, about720px desktop content, optional TOC sidebar, native links/details/share and visible keyboard focus. Hover is mouse-only, without geometry changes or stagger. Existing destination skeleton remains native Blade navigation. Featured article is highlighted only on the unfiltered first page; suppress its duplicate card there, retaining pagination membership (an older featured article may appear again on a later page).

Add nullable canonical/OG overrides and index/follow flags, default true. Shared server validation permits HTTPS URLs without credentials/fragments. OG image override is metadata for an Owner-owned already-available file; no server fetch, downloader or new media storage path. Main image remains fallback. Separate SEO fields use existing revision/audit/cache operations; URLs/text recorded as hashes. SEO-only edits do not change the established editorial timestamp.

BlogPosting/BreadcrumbList reflect visible title, author, dates, summary, image and navigation. Safely encode JSON-LD; no invented author profile or product-price snapshot. Listing canonical retains category route and page number; search pages are noindex/follow. Article canonical defaults to query-free self URL. Indexability does not change public visibility.

Sitemap excludes nonindexable/external-canonical articles. Article lastmod uses editorial time, then actual publication date for legacy null editorial time, never views/updated_at. Reuse sitemap.xml invalidation; cache stores XML and absolute expiry, capped by next scheduled publication or one hour. Response max-age decreases with remaining expiry. Old string cache regenerates safely.

## Alternatives and consequences

No body-wide typo search: it recreates unrelated-result failures. No search infrastructure for the current small journal without demonstrated volume. No JSON body migration, price/stale-text rewrite or automatic unpublish. No image resize/media library/API work in this phase.

SEO validation is local JSON/visibility testing. [Google Article guidance](https://developers.google.com/search/docs/appearance/structured-data/article), [canonical guidance](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls) and [sitemap guidance](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap) inform this implementation; markup does not guarantee indexing or rich results. Staging MySQL, genuine touch, Google validators/Search Console and Owner acceptance remain release gates.

Keep additive metadata columns/media on application rollback. After nonindex/canonical settings are used, rolling back to code that ignores them is unsafe; prefer compatible forward fix. No populated migration down.
