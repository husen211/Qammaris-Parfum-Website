# ADR-033 — Blog editorial CMS and transient preview

Status: implemented on BLOG-02 review branch; not deployed.

## Decision and scope

Owner selected BLOG-02 after the approved Journal roadmap and asked this agent to focus on blog while Claude Code handles orders. Worktree/branch isolation prevents overwriting concurrent order work. BLOG-01 remains the dependent review branch; no release approval is inferred.

Use Tiptap 3.31.4 vanilla JavaScript, StarterKit, TableKit and Image in a dedicated Vite entry loaded only by blog create/edit. Stored sanitized HTML stays authoritative. No SPA or JSON block conversion. Visual loading and Visual/HTML switches do not serialize/rewrite existing content; real visual edits do. Unsupported legacy markup can be reviewed in HTML mode. Server sanitizer remains authoritative, including bounded table spans.

Use native details sections (Konten/Media/SEO/Relasi/Publikasi) with persistent DOM and an HTML fallback. Drafts may be incomplete; missing title receives a stable draft title/slug. New publication/scheduling checks title, active category, summary, readable text, author, image and alt after sanitization/storage verification. Existing published or scheduled articles are not automatically unpublished for missing new image metadata. Existing published edits retain author/slug; restoring/unpublishing and later publishing requires current readiness.

Add blog_categories/blog_tags/blog_post_tag and nullable category_id plus subtitle, image alt, featured selection and SEO title. Seed exactly the four approved category definitions, not article reassignment. Keep the historical enum column untouched. A category_id selects managed taxonomy; null retains the exact legacy category. New custom categories keep a compatibility enum value, but display/filter/audit use the managed relation. Names/slugs are immutable in this initial management UI; reversible activation protects URLs and old content. No automatic legacy backfill. Tags sync under the article lock/revision and same audit transaction; omitted tags remain, explicitly empty tags clear. Audit stores IDs/flags and hashes for new text fields, never complete body.

Preview uses the same blog.show view and RenderBlogContent as the public route, with admin/session/CSRF validation, no file/DB/view/history writes, noindex/no-store/no-referrer. A bounded validated upload is displayed transiently as a data URI, not stored. Outer preview offers desktop/mobile frame widths. Sandbox blocks article scripts/forms and preview sharing is suppressed. Interactive site navigation is not a preview acceptance target.

Initial product markers preserve only validated internal product IDs; supplied child HTML/price/status are discarded. Render resolves current visible catalog names/links; hidden/draft/archived products disappear. Full live product cards with price/availability, gallery/crop/media library and ordered relations remain BLOG-04. This initial component never changes catalog data.

## Tradeoffs and remaining work

- Nullable taxonomy relation avoids rewriting deployed enum data. Compatible application rollback must understand category_id; old code could incorrectly display custom categories as the enum fallback. Keep schema/media and use a compatible forward fix.
- Editor bundle about 444 kB / 141 kB gzip is admin-editor-only; public bundles do not import Tiptap.
- Browser/native upload and genuine iPhone touch are separate acceptance gates. SQLite proof is not MySQL DDL/concurrency proof.
- Full SEO/OG/canonical/schema/sitemap/UI redesign belongs BLOG-03; responsive media/complete blocks BLOG-04; machine identity/API BLOG-05; staged acceptance/release BLOG-06.

References: [approved roadmap](../../planning/QAMMARIS_JOURNAL.md), [foundation](ADR-032-blog-write-foundation.md), [verification](../../verification/blog-02/README.md), official Tiptap [vanilla setup](https://tiptap.dev/docs/editor/getting-started/install/vanilla-javascript), [StarterKit](https://tiptap.dev/docs/editor/extensions/functionality/starterkit), [TableKit/Table](https://tiptap.dev/docs/editor/extensions/nodes/table), [Image](https://tiptap.dev/docs/editor/extensions/nodes/image).
