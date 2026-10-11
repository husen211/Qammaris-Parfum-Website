# BLOG-01 — preparation and safe release boundaries

This runbook does not authorize future operations. BLOG-01 is installed through the approved2026-10-08 [Journal release](../verification/blog-06/README.md). [ADR-032](../architecture/decisions/ADR-032-blog-write-foundation.md), [evidence](../verification/blog-01/README.md), [program](../planning/QAMMARIS_JOURNAL.md).

## Behavior and configuration

- Admin create/edit uses SaveBlogPost, archive/restore uses ChangeBlogPostArchive; explicit actor and audit in the business transaction. Existing DELETE archives, PATCH restore returns draft. Public URLs/slugs preserved.
- Edit/archive/restore submits current revision. Stale JSON receives409; browser retains typed text and offers reload. Copy unsaved work before reload; choose upload again after failed save (browsers cannot retain a file input).
- BLOG_MEDIA_DISK selects a Laravel disk, default public. New files live under blog/; persist shared storage and expose that disk's normal URL. No package/cloud requirement. Existing image paths remain unchanged and disk remains null until a successful new upload.
- Upload JPEG/PNG/WebP <=5MB and <=6000px per side. Original retained; BLOG-04 additionally creates responsive/cropped variants. Ensure web/PHP upload limits allow the validated size on staging before release. Extension/MIME alone do not authorize other file types.
- After-commit sitemap invalidation failure does not undo committed saves. Existing cache TTL remains; fresh scheduled-boundary SEO behavior is BLOG-03.

## Rehearsal before approved rollout

1. Obtain scoped staging/migration authorization; confirm target database, persistent storage and release revision without printing secrets. Current production data must not be used as disposable fixtures.
2. Capture a verified recovery point immediately before any approved production DDL; prior fresh-launch waiver is not recurring permission. Rehearse selected additive migration on staging MySQL, verify original article columns/IDs/slugs/authors/media unchanged and history initially empty. SQLite is not MySQL DDL/concurrency proof.
3. Run only `2026_10_07_000002_add_blog_write_safety.php` under the project's targeted-migration procedure; inspect migration status and rerun for replay evidence. Do not reseed, backfill editorial dates or run migrate:fresh.
4. Before switching code, schema must exist: BLOG-01 public queries reference archived_at. Keep current code while DDL adds the unused columns/table; verify defaults before switching. MySQL DDL may not roll back atomically; after partial failure inspect columns/table/migration ledger and forward-repair deliberately, never blind retry/down.
5. Use approved temporary staging fixtures only to exercise save/no-op/revision conflict/archive/restore/media failure/audit, with explicit cleanup or rolled-back data. No admin-account creation on staging implied here.
6. Publish only after Owner approval, normal GitHub CI/release/migration guards; no secrets or uploads in Git. Preserve shared storage/env. Confirm legacy public articles and new image URLs, archived404, restored draft404, stable URLs and history attribution.

## Recovery and diagnostics

Failed DB/audit mutation rolls back article/history and attempts deletion of only the new upload. Old reference/file remains. Failed cleanup may leave an orphan; inspect authorized storage inventory and resolve in a separate scoped retention task. Never delete an old image based solely on a historical hash.

Logs use blog.write_failed, blog.upload_cleanup_failed, blog.sitemap_invalidation_failed with exception class only. Do not log query bindings, entire requests, article HTML, file URLs or credentials. History includes article/admin IDs, action/revision/time and safe allowlisted metadata/hashes, not recovery copies of body or original image path.

Archive keeps content/media/date; restore becomes draft. Physical purge is not implemented. No automatic history retention deletion. Read-only history inspection requires existing authorized DB/tool access; no new public history endpoint or agent database access.

Application recovery must keep archive/public-visibility filtering, new disk resolver, revision controls and transactionally coupled audit. A pre-BLOG-01 rollback can re-expose archived published articles and break new media URLs; prepare a compatible rollback or forward fix. Keep additive columns/table/history/files. Do not run migration down against populated history. If a problem occurs before code activation, leave the additive schema unused while repairing the build.

## Historical phase limits

The original BLOG-01 report did not confirm staging/production MySQL/runtime/device/release. Those bounded checks were subsequently performed in [BLOG-06](../verification/blog-06/README.md); physical Safari, production native upload, sustained MySQL load and Owner walkthrough remain unconfirmed. Production machine API stays OFF; no automatic next phase.
