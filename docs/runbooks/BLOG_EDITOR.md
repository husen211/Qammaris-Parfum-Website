# Qammaris Journal — editorial CMS (BLOG-02 review)

Not deployed. BLOG-01 is a prerequisite. Owner must separately approve staging migrations and production release.

## Owner workflow

1. Admin → Blog Posts / Qammaris Journal → Buat artikel.
2. Konten: title, introduction, card summary, category, author and visual body. HTML mode is available; server cleans all HTML at save and preview.
3. Media: JPEG/PNG/WebP main image max 5 MB / 6000 px per side and descriptive alt. Replacement retains the former image. Draft can omit these.
4. SEO: optional title and description, otherwise article title/summary.
5. Relasi: choose managed tags and optionally insert a visible catalog product link. Open category/tag management in another tab; reload editor only after saving unsaved writing. Names/URLs stay fixed; activation is reversible.
6. Publikasi: draft or publish/schedule; timestamp uses the explicitly displayed application timezone. Empty timestamp on a new publication means now; on edit it retains the previous date. Featured selection is stored for BLOG-03 landing.
7. Preview opens a separate authenticated tab without saving. Desktop/Mobile changes the article frame. Close it to resume writing.
8. Save. Errors retain form text and open the relevant section; uploads must be chosen again after a rejected save. A revision conflict requires an explicit reload after keeping a copy of unsaved writing.

Existing published articles remain available; missing image alt is shown for review. New publishing/scheduling, including restored/unpublished drafts, requires complete fields. Archives and restores use BLOG-01 behavior.

## Local verification and later rollout

Apply additive migration 2026_10_07_000003 only after BLOG-01 migration. It adds taxonomy definitions/relations/metadata without changing original article columns, assigning categories by guess or converting old HTML. Test migration/runtime on staging MySQL before rollout. Keep persistent blog filesystem/config/storage link from BLOG-01. No resize/GD requirement yet.

Dedicated frontend entry is in Vite manifest and only blog editor pages load it. If JavaScript fails, HTML textarea remains available. Do not remove that fallback or depend on client validation for publication.

Recovery: retain new tables/columns, pivot rows, original/old images and BLOG-01 audit history. Use compatible rollback/forward fix understanding category_id, revision, archive and image disk. Do not migrate down populated tables or switch to pre-Journal code. Back up current production before an approved migration/release; the historical launch waiver is not applicable.

Full media library/crops, live product-price cards, API tokens and production activation are not part of this item. Evidence and exact limits: [BLOG-02](../verification/blog-02/README.md).
