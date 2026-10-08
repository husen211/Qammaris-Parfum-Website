# Journal automation v1 — draft only

BLOG-05 review implementation, 2026-10-08. Not deployed/activated. Base `/api/automation/v1`. This contract is separate from Qammaris App feed, the human admin session, and customer orders. The API is disabled by default (`BLOG_AUTOMATION_ENABLED=false`).

## Authentication and limits

Send `Authorization: Bearer <machine-token>` and `Accept: application/json`. Sanctum SHA-256 token hashes belong to a separate `BlogAutomationActor`, never `User`. Tokens must have an expiry; command-issued tokens expire after 90 days, and Sanctum also bounds token age to 90 days. No cookies/SPA/session authentication. Disable actor or revoke token to close access without deleting attribution. Do not reuse feed/admin credentials. HTTPS is required outside localhost.

Abilities: `blog:read`, `blog:write`, `blog:media`, `catalog:read`. Command accepts only these abilities, not wildcard/publication rights. 60 requests/minute per actor across tokens, 10 media requests/minute per actor; an additional 120 requests/minute per IP runs before authentication, including failures. Failed requests/replays consume limits. 429 includes `Retry-After`. Responses are private/no-store.

## Operations

| Method/path | Ability | Input/behavior |
|---|---|---|
| GET `/blog-posts` | blog:read | Owned, unarchived, unpublished drafts only; page/per_page 20 default, 1–50 allowed |
| GET `/blog-posts/{id}` | blog:read | Numeric internal ID; owned draft with revision, editorial fields and active media |
| POST `/blog-posts` | blog:write | Editorial fields below; required Idempotency-Key; incomplete draft allowed; author defaults Qammaris Editorial |
| PATCH `/blog-posts/{id}` | blog:write | Required positive integer revision; omitted fields retain current values |
| POST `/blog-posts/{id}/media` | blog:media | Multipart file+metadata; required Idempotency-Key and revision; upload only |
| GET `/blog-taxonomy` | blog:read | Existing active categories/tags; no taxonomy writes |
| GET `/products` | catalog:read | Public catalog metadata only; optional search≤100 chars, page/per_page as above |

Agent cannot publish/schedule/archive/delete, assign actor, create taxonomy, edit an unowned/published/scheduled/archived article or modify any catalog value. Owner may explicitly assign/reassign/revoke a draft via its editor’s **Kelola akses agent untuk draft** link. Assignment has its own form/revision/audit, does not save editor contents, and does not change editorial time. Published/scheduled/archive blocks apply even when an old actor ID remains for attribution. Unknown/forbidden input fields return 422, not silent discard.

## Editorial payload

Allowed top-level keys: `title`, `subtitle`, `excerpt`, `content`, `category`, `author`, `meta_description`, `seo_title`, `canonical_url`, `seo_indexable`, `seo_followable`, `og_title`, `og_description`, `og_image_url`, `featured_image_alt`, `featured_media_id`, `tag_ids`, `related_product_ids`, `related_article_ids`, `faqs`, `references`. PATCH adds `revision`. Never send is_published/status/published_at/is_featured/slug/actor/view_count/archive fields. Ownership comes from the token.

Existing shared editorial rules apply: title/SEO title 255 chars; author 100; excerpt/OG description 1000; subtitle 2000; meta description 160; HTML500000; tags≤30; ordered product IDs≤12 / article IDs≤3; FAQs≤12 (question 255,answer 2000); references≤20 (title 255,HTTPS URL≤2048). Nested FAQ/reference keys are strictly allowlisted. Canonical/OG URL only HTTPS without credentials/fragments; OG override is metadata, never fetched. Category is its exact existing name; use taxonomy lookup. Explicit `[]` clears lists; omitted lists preserve them. Draft completeness does not publish anything.

HTML is sanitized server-side. Use validated `data-qammaris-product`, `data-qammaris-media`, gallery/callout/CTA/article/YouTube markers from the [media guide](../runbooks/JOURNAL_MEDIA.md); do not send renderer-generated cards or arbitrary iframe/script. New/changed agent HTML rejects raw `<img>`; images use owned file-upload media IDs. An assigned legacy draft retains omitted old content, including legacy images, until deliberately edited. Cross-article/archived media markers and featured media IDs are rejected. Submitted product price/status/markup is discarded; the renderer reads the current public catalog. No image-URL downloading endpoint exists.

## Media payload

`image` required JPEG/PNG/WebP file≤5 MB; MIME/extension/dimensions checked. `revision` required. `alt` required≤255, `license` required≤255. Optional `caption`≤2000, `credit`≤255, `source_url` HTTPS≤2048. Required `crop`: original/16: 9/4: 3/1: 1; `focal_x`,`focal_y`: integer 0–100. Shared processor limits 6000 px/side, 12 MP and estimated memory. GD/WebP fallback, variants/original retention and safe compensation follow BLOG-04. No body download from source_url; it is provenance metadata only.

201 returns media ID, original URL, responsive srcset/metadata and the new article revision. Upload does not automatically replace the hero; PATCH `featured_media_id` at the returned revision to select it. Hero alt follows owned media. Read current revision after every mutation or conflict.

## Success, errors and replay

Success `{data,meta}`. Create/upload 201 and replay 200; GET/PATCH 200. Lists expose page/per_page/total/last_page and compact ID/title/slug/excerpt/category/author/revision/status summaries. GET/create/PATCH draft detail includes allowed editorial fields, tag IDs and owned active media; no tokens/internal disk keys/actor credentials. Catalog lookup returns ID/name/brand/URL/image URL/volume_ml/price/availability, from current public offer only. `price` is the decimal string from existing storage (not a floating-point calculation); customer formatting remains the existing whole-rupiah display. No inventory reservation or numeric stock promise.

Error shape:
```json
{"error":{"code":"validation_failed","message":"Check the indicated fields.","fields":{"is_published":["This operation does not allow this field."]}},"meta":{}}
```

401 unauthenticated/expired/revoked; 403 scope/actor/ownership/state; 404 missing ID; 405 unsupported operation; 409 revision_conflict or idempotency_conflict; 422 field errors; 429 rate_limited; 503 automation_disabled; 500 save_failed with no SQL/request/token disclosure. JSON errors also apply without a JSON Accept header. Changed/different-owner article responses never include its content.

Create/upload Idempotency-Key must be 8–128 letters/digits/`_.:-`, unique per operation. DB uniqueness covers actor+scope+key hash; upload scope includes article ID. Payload hashes canonicalize object key order, retain list order, and include file checksum/MIME, not filename. Same key with altered payload conflicts; identical successful retry returns the original resource ID, **its current owned draft state**, `meta.replayed=true` and `meta.original_revision`. This is not a frozen historical body response. Ownership/state is checked again on replay.

Completion pointer/revision commits inside the shared mutation transaction together with audit. No raw request/response body/token/key is stored in the replay table. Concurrent pending duplicate returns 409 with Retry-After 5 seconds; retry the **same** payload/key after waiting. Handled failures roll back mutation/files and release the pending reservation. Hard crash can leave pending; never invent a new key to bypass it. Operator investigates per runbook. Successful replay records have no automatic deletion, preserving idempotency; retention/pruning requires a separate decision.

PATCH uses revision instead of Idempotency-Key. On 409 GET the latest own draft, reconcile edits deliberately, then PATCH its new revision; never blindly retry with a fabricated revision. Bounded DB deadlock retries do not bypass revision or ownership guards.

## Example flow

Use an authorized environment’s BASE_URL and a token from the agent secret store; no production token is active for this branch.

1. GET `/blog-taxonomy`, GET `/products?search=afnan`.
2. POST `/blog-posts` with a stable key and `{"title":"Panduan aroma segar","category":"Panduan","excerpt":"Panduan memilih parfum sehari-hari.","content":"<h2>Memilih aroma</h2><p>Isi editorial...</p>"}`.
3. Multipart upload media using the returned ID/revision and a second key. Retain the same file/metadata/revision/key for retries.
4. PATCH with **upload’s new revision**, featured_media_id, owned media marker and product IDs. Product marker sample `<div data-qammaris-product="123"></div>`; body must not freeze catalog prices/stocks.
5. Hand off ID/slug/current revision and editorial review notes to Owner. Stop at draft; human previews/publishes in admin.

Agent authoring: [JOURNAL_AGENT_GUIDE](../runbooks/JOURNAL_AGENT_GUIDE.md). Operator activation/recovery: [JOURNAL_AUTOMATION](../runbooks/JOURNAL_AUTOMATION.md).
