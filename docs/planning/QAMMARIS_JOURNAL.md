# Qammaris Journal — approved program

Owner approved implementation of this plan on 2026-10-07. This program is separate from the still-open P9 acceptance. Work on one item at a time; approval of the roadmap does not authorize automatic phase advancement, production deployment, credentials or broad content rewrites. Current status lives in [BACKLOG](BACKLOG.md), current rules in [BUSINESS_RULES](../product/BUSINESS_RULES.md).

## Goal and fixed decisions

Keep Laravel, Blade, Eloquent, MySQL, current typography and cream/charcoal/gold identity. Display name becomes Qammaris Journal in BLOG-03; preserve `/blog/...`, IDs, slugs, existing content, authors and media. Existing HTML remains the content format, sanitized on the server. No JSON-block conversion, generic repository, SPA or author-profile system.

Use Tiptap with vanilla JavaScript via a Vite entry loaded only by the admin editor. Provide visual and HTML modes. Admin may edit live articles directly. Agent may create/update its own drafts only; no publish capability. Drafts may be incomplete after BLOG-02 implements the separate draft/publication validation rules. New author default is Qammaris Editorial; never bulk-replace legacy authors.

## Phases and acceptance

| ID | Scope | Dependency | Acceptance |
|---|---|---|---|
| BLOG-01 | Shared save/archive operations, revision conflict, verified image replacement, explicit disk, editorial timestamp, transactional audit | Existing sanitizer/admin authentication/filesystem | Failures preserve article/current image; ID/slug/legacy fields retained; stale edits cannot overwrite; archive is recoverable as draft |
| BLOG-02 | CMS fields, managed categories/tags, visual/HTML editor, grouped form, authenticated preview, scheduling | BLOG-01 | Owner creates/previews/schedules/edits without mandatory HTML; field errors and editor round-trip verified; draft may be incomplete; new publication requires complete content |
| BLOG-03 | Journal landing/search/detail/TOC/share, metadata/schema/sitemap | BLOG-02 | Mobile/desktop reading works, drafts never leak, real metadata/visibility/cache boundaries tested |
| BLOG-04 | Responsive media/crops/focal point, gallery/callout/CTA/FAQ/references/video/product blocks and ordered relations | BLOG-02/03 renderer + media safety | All blocks preview safely; product price/status resolve current catalog; original files remain |
| BLOG-05 | Sanctum machine actors, draft/media/lookup API, idempotency/rate limits/audit/contracts | Shared writes/media and validated block renderer | Actor owns its drafts; replay safe; forbidden fields/publish/other actors' drafts rejected; tokens expirable/revocable |
| BLOG-06 | Existing-article review, migration rehearsal, Owner acceptance, operational runbook, approved release | BLOG-01–05 | Staging approved; release separately authorized; migration/media/runtime and rollback limits evidenced |

Dependency order: foundation → CMS → public Journal/SEO → complete media/blocks → agent API → acceptance/release. Do not install Tiptap/Sanctum or implement later stages during BLOG-01. Foundation is on a review branch, not deployed.

Execution update: Owner selected BLOG-02 in the next turn and assigned order work separately to Claude Code. CMS/editor/taxonomy/transient preview/readiness are implemented in an isolated dependent review branch, including Tiptap installation only in that phase. [BLOG-02 evidence](../verification/blog-02/README.md). BLOG-03–06 remain unstarted; no production release or API credentials authorized by this implementation.

## CMS and editorial model to implement later

Group editor controls into Konten, Media, SEO, Relasi, Publikasi. Labels/error associations explicit, state survives tab changes. Initial visual editor supports paragraphs, H2/H3, links, lists, quotes, tables, images, separator and validated product markers. Later blocks add galleries, callouts, CTA, FAQ, references, inline related articles and YouTube. No free-form iframe/script.

Preview uses the public renderer with desktop/mobile widths, admin auth, noindex and no view count. Markers are allowlisted/validated server-side; HTML-supplied product price/status/markup never trusted.

Add incrementally, without rewriting historical migrations:

- Article: subtitle/dek, featured selection, editorial time, revision and archive. BLOG-01 implements only the last three safety fields; featured selection/dek later.
- SEO: title, canonical override, index/follow, OG title/description/image, optional editorial keywords.
- Media: disk/path, alt, caption, credit, source URL, license/permission, dimensions, checksum and generated variants.
- Taxonomy: managed categories/tags and article-tag relation. Preserve Tips/Review/Panduan/Berita and their URLs; new Owner categories possible, no guessed reassignment.
- Relations: ordered catalog product IDs and article IDs; references and FAQ structured lists.

Excerpt is card summary; subtitle is article introduction, not duplicated by requirement. Reading time uses article text, 200 words/minute, minimum one minute. Editorial time changes with editorial work, never views/no-op. Existing content with handwritten prices/stocks is flagged for human correction, never automatically rewritten.

New publish/schedule requires title, active category, summary, meaningful body, author, featured image and alt. Legacy published articles remain visible; missing new metadata becomes a review task, never automatic unpublish. UI Draft/Dijadwalkan/Tayang/Arsip derives from existing publish boolean/date + archive marker; no competing status column.

## Media and live catalog relations

Use Laravel disks, default persistent public storage under `blog/`; no required cloud move or hotlink. JPEG/PNG/WebP, max5MB, MIME/extension/dimension checks and bounded processing. Original retained. Later variants 480/768/1200/1600 without upscale; optional16:9/4:3/1:1 crops reviewed with focal point. Master target1600×900, small-source quality warning. Check actual GD/WebP support before enabling processing. Record external-source permissions/license.

Replacement: store → verify → switch reference transactionally → retain old file. Archive retains article/files. Cleanup is separately approved retention work. BLOG-01 does not provide image-version/content-version restoration UI.

Product blocks resolve current catalog ID/image/size/price/availability/URL at render. Draft/hidden/archived products excluded; Habis stays eligible under catalog rules. This work must never alter catalog/source prices/status. Missing relations degrade safely.

## Public presentation and search

Landing: optional selected featured article, search/categories, newest grid, pagination; extra category sections only with content. Search title/excerpt/tags/related product-brand first; body exact matching secondary, conservative metadata-only typo tolerance. Do not reuse permissive body fuzzy matching.

Article order: breadcrumb → category → title → dek → author/date/read time → hero → body. Desktop body about720px, TOC/product sidebar; mobile one column with TOC accordion. TOC from H2/H3 with unique IDs, shown with at least three H2. Tables scroll within content, no page overflow. End: conditional references/FAQ, store CTA, max3 related articles, next article; no infinite scroll. Share WA/copy/Facebook/X and native share when supported.

Retain fonts/tokens; remove public view counter, mixed English labels, decorative hero overlay and touch-sticky hover. Follow current click/hover/reduced-motion rules.

SEO fallback: SEO title → title; description → excerpt; OG metadata → article metadata; OG image → featured image. Default canonical is clean article URL without tracking; explicit override only valid HTTPS. BlogPosting/BreadcrumbList reflect visible facts; no search-result guarantee. Sitemap includes only public indexable self-canonical articles; editorial lastmod, invalidation after edits and scheduled boundary. Indexing/Search Console outcome: Not confirmed.

## Agent API contract reserved for BLOG-05

Use Laravel Sanctum hashed tokens/abilities, separate machine identity from admin/employees/Qammaris App feed credentials. Default expiry90days, revocable,60requests/minute/actor, upload10/minute. Production activation separate after staging acceptance.

| Endpoint | Purpose |
|---|---|
| GET/POST `/api/automation/v1/blog-posts` | List own drafts/create draft |
| GET/PATCH `/api/automation/v1/blog-posts/{id}` | Read/update own draft |
| POST `/api/automation/v1/blog-posts/{id}/media` | Upload a file belonging to own draft |
| GET `/api/automation/v1/blog-taxonomy` | Active categories/tags |
| GET `/api/automation/v1/products` | Public catalog metadata lookup only |

Owner may explicitly assign an existing draft via admin. Agent cannot edit published/scheduled posts, publish/archive/delete, create taxonomy, or write catalog/prices/status. Reject publication/status/date/actor fields, not silently accept them. Use field allowlist; ownership before writes.

Create/upload Idempotency-Key prevents duplicate article/file on identical retry. Update requires revision,409 conflict. Responses `{data, meta}`; coded/field errors,401/403/409/422/429 appropriately. Upload files only, no arbitrary remote-image fetch. Admin/API share tested write/media/validation operations; focused operations justified by two concrete writers, no generic repository.

Blog audit attributes human/machine, article/action/time/revision and allowlisted changes; content hash only, no token/request dumps. BLOG-01 has admin-only attribution, not a machine API or universal observer.

## Verification and delivery gates

Regression: stable legacy IDs/slugs/media/author, draft/scheduling, editorial time, no-op/conflict, sanitizer/JSON-LD, editor visual/HTML round-trip. Media: invalid MIME/dimensions/extension, storage/resize/DB failure, foreign references, retained originals. Products: live prices/status/Habis/OTW, missing/hidden/draft relations, no catalog writes. API: auth/expiry/revocation/ownership/forbidden publish/replay/concurrency/rate limit.

Browser target320/375/390/768/1440px: short/long content, tables/gallery/search/preview, keyboard, loading/error/retry; before/after screenshots. Genuine iPhone Safari/touch required for one-tap/scroll evidence; resized mouse viewport is not touch proof. Relevant tests first, then Laravel/JS/build/dependency checks; validate schema and measure performance with evidence.

Additive schema, preview/exact backfill only. Preserve IDs/slugs/contents/authors/images. Staging MySQL/GD/WebP/runtime and migration rehearsal before approved GitHub release. Retain new tables/media on application rollback; any prior code ignoring archive/disk/revision is unsafe after new writes. No production migration/deployment by this plan execution turn.

Update board, rules, architecture, ADRs and runbooks per stage; future agent authoring/API/editor/media guidance belongs to its implementation phase. [BLOG-01 evidence](../verification/blog-01/README.md), [ADR-032](../architecture/decisions/ADR-032-blog-write-foundation.md), [foundation runbook](../runbooks/BLOG_FOUNDATION.md).
