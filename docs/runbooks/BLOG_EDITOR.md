# Qammaris Journal — editorial CMS (BLOG-02–04)

Installed with BLOG-01–05 through approved PR31. [Release evidence](../verification/blog-06/README.md) supersedes the old phase pending statements; future migrations/releases still need approval.

## Owner workflow

1. Admin → Blog Posts / Qammaris Journal → Buat artikel.
2. Konten: title, introduction, card summary, category, author and visual body. HTML mode is available; server cleans all HTML at save and preview.
3. Media: JPEG/PNG/WebP main image max5MB/6000px per side/12MP and descriptive alt. Replacement retains the former image. Draft can omit these. Save writing before opening the owned-media manager; review responsive/focal crops and rights metadata, then return to reload revision. [Media guide](JOURNAL_MEDIA.md).
4. SEO: optional title and description, otherwise article title/summary.
5. Relasi: choose tags and ordered related products/articles/FAQ/references. Add/move/remove labeled rows; explicit empty clears a list. Content → Tambahkan komponen artikel inserts owned image/gallery, callout, CTA, YouTube and inline related article. Product blocks resolve current eligible catalog cards. Open category/tag management only after preserving unsaved writing. Names/URLs stay fixed; activation is reversible.
6. Publikasi: draft or publish/schedule; timestamp uses the explicitly displayed application timezone. Empty timestamp on a new publication means now; on edit it retains the previous date. Featured selection is stored for BLOG-03 landing.
7. Preview opens a separate authenticated tab without saving. Desktop/Mobile changes the article frame. Close it to resume writing.
8. Save. Errors retain form text and open the relevant section; uploads must be chosen again after a rejected save. A revision conflict requires an explicit reload after keeping a copy of unsaved writing.

Existing published articles remain available; missing image alt is shown for review. New publishing/scheduling, including restored/unpublished drafts, requires complete fields. Archives and restores use BLOG-01 behavior.

## Local verification and later rollout

Apply additive000003/000004/000005 in order only after BLOG-01. Taxonomy/SEO/owned media/relations do not rewrite original article columns, assign categories by guess or convert old HTML. Test migration/runtime on staging MySQL before rollout. Keep persistent blog filesystem/config/storage link and verify actual web GD/WebP/memory before enabling responsive processing; unavailable capabilities show original fallback/warning.

Dedicated frontend entry is in Vite manifest and only blog editor pages load it. If JavaScript fails, HTML textarea remains available. Do not remove that fallback or depend on client validation for publication.

Recovery: retain new tables/columns, pivot rows, original/old images and BLOG-01 audit history. Use compatible rollback/forward fix understanding category_id, revision, archive and image disk. Do not migrate down populated tables or switch to pre-Journal code. Back up current production before an approved migration/release; the historical launch waiver is not applicable.

Media/crops and live product-price cards are implemented in BLOG-04; the BLOG-05 API code is installed with its production gate OFF. Production credential activation remains separate. Implementation evidence: [BLOG-02](../verification/blog-02/README.md), [BLOG-03](../verification/blog-03/README.md), [BLOG-04](../verification/blog-04/README.md). Current release/limits: [BLOG-06](../verification/blog-06/README.md).
