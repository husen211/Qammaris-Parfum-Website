# ADR-032 — Blog write safety before Journal CMS/API

Date: 2026-10-07. Accepted for BLOG-01 implementation on a review branch; production release not authorized. [Approved program](../../planning/QAMMARIS_JOURNAL.md).

## Problem and evidence

`AdminBlogPostController` previously owned validation-to-model mutations and file replacement. It removed the old `public` image before a replacement write completed and hard-deleted the article/file. There was no revision check or blog audit. Existing slug stability and HTML sanitizer already solve URL/output concerns and remain.

The accepted plan adds a second writer (draft-only API) later. A focused shared save operation is justified now by the existing failure hazard and the planned real second writer, not by a service/repository convention.

## Decision

`SaveBlogPost` receives explicit validated fields and a persisted admin actor. It sanitizes HTML, stores/verifies a new upload before a short DB transaction, locks fresh article state, checks revision, preserves stable ID/slug, changes selected fields and records audit in that transaction. On failure only its new upload is compensated; old media is never deleted. No-op saves and views do not advance revision/editorial time. Cache invalidation failure after commit is logged generically and must not delete committed media.

`BlogMediaStorage` uses `media.blog_disk` (default public) and records disk/path for new uploads. JPEG/PNG/WebP, max5MB, actual image/MIME/extension and max6000×6000 dimensions; verify existence and byte count. No resize/cloud/remote fetch added. Legacy image forms still resolve unchanged while disk is null.

`ChangeBlogPostArchive` revision-checks archive/restore under lock. Archive retains publish flag/date/contents/files but public scopes exclude it. Restore clears archive and returns draft; republication requires explicit edit. Existing DELETE route now archives, new PATCH restore route remains authenticated admin+CSRF. No physical purge.

Additive migration adds revision(default1), archive, editorial timestamp, image disk; `blog_post_changes` stores historical article/admin IDs, action, revision, time and changed allowlisted before/after groups. Body/excerpt/meta-description/image key use hashes; no full request, URLs/files/tokens. No automatic deletion/backfill, generic observer or cascade to user/article deletion; no fabricated historical actor. Audit failure rolls back the write.

Existing HTML forms/public URLs/categories/rendering retained. Admin list adds archive filter/restore and scrollable focusable table; conflict retains input and offers explicit reload after copying changes. Visual editor/readiness/taxonomy/SEO/agent ownership are later phases.

## Tradeoffs and limits

Three small operations plus one storage collaborator introduce a narrow boundary and failure tests; they do not generalize all models or create a framework. Requests/controllers still own HTTP validation/redirects; future API must add its own actor/ownership/draft allowlist and reuse shared operations after that guard. BLOG-01 actor policy is admin-only.

Retained old files require deliberate inventory/retention later. Audit hashes prove observed differences, not content restoration, tamper-proof logging or physical-person attribution for shared accounts. Historical editorial time remains null rather than pretending old updated_at (also affected by views) proves editorial activity. Admin text/date null/default semantics stay compatible, except new default author Qammaris Editorial.

These operations own their transaction in current controllers; future outer transactions need explicit file compensation across that outer rollback. Crash between verified upload and DB commit can leave an orphan, never deletion of the current image. Cleanup failures are generic logged events, not proof that every stray file disappeared. Max dimensions limit accepted source size but no image processing occurs until BLOG-04.

Rollback must retain new tables/files and archive/disk/revision-aware code. Returning to a pre-BLOG-01 version can expose archived articles or misresolve new paths; use a forward fix or a compatible rollback build. Never down a populated audit table as routine rollback. [Runbook](../../runbooks/BLOG_FOUNDATION.md).

SQLite regression/replay and local Chrome evidence: [verification](../../verification/blog-01/README.md). Staging MySQL lock contention/DDL, genuine Safari/touch and production behavior remain Not confirmed.
