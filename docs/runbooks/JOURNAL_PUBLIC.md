# Public Journal and SEO — BLOG-03 review

Not deployed. Depends on BLOG-01/02. [Verification](../verification/blog-03/README.md), [ADR-034](../architecture/decisions/ADR-034-public-journal-search-seo.md).

## Owner workflow

Use the existing grouped editor. Mark an eligible article “Artikel unggulan” for the unfiltered Journal first page. With multiple selections, the newest publication wins. Search/category pages show relevant results rather than a featured promotion.

SEO title falls back to title; description to excerpt; OG title/description to those values; OG image to the main image. Leave overrides empty normally. Set a canonical HTTPS URL only when intentionally identifying another original; that article remains readable but is excluded from the sitemap. Index/follow are independent of publication.

An OG image override references an already-available Owner-owned image; it does not upload/download a file or replace the hero. Prefer the persisted main image. Content changes use existing revision conflict handling. SEO-only changes preserve editorial time.

Use H2 for main sections/H3 for subsections. Three nonempty H2 sections enable the generated TOC; repeated headings receive unique anchors. Tables scroll within the article. Product markers resolve eligible live catalog names/links, not submitted prices. Full card/media/FAQ components are future BLOG-04. Existing prose prices/stocks require human editorial correction in BLOG-06; no automatic rewrite.

Share actions use the clean article URL. Copy failure exposes a selectable URL and retry. Native share appears only when supported. External share links open providers; no automatic message is sent.

## Release checklist and recovery

Before a separately approved release, rehearse additive migrations000002–000004 on staging MySQL, verify retained existing articles/URLs/media, genuine iPhone Safari one-tap/scroll, upload and schedule boundaries. Clear sitemap.xml cache during compatible activation; edits already invalidate it, next scheduled boundary expires it automatically.

Check article HTML head/canonical/robots/OG and JSON-LD, then external Google validators and Search Console as a separate operational action. Do not claim successful indexing merely from valid JSON. Inspect existing prose containing prices/stock; keep articles live while Owner reviews.

Rollback retains added tables/columns/history/media. Use code that understands archive/taxonomy/SEO flags. Do not migrate down populated schema or purge images as part of application rollback. Pending dependency advisory SEC-DEP-01 remains a separate scoped task.
