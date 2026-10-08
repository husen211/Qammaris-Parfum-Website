# BLOG-01 — local implementation evidence

Date:2026-10-07. Branch `modernization/blog-01-foundation`, base main `c6e2dfd`. **IN_REVIEW, not deployed.** Owner asked to implement the Journal plan, with BLOG-01 first and one phase at a time. [Scope/remaining program](../../planning/QAMMARIS_JOURNAL.md), [ADR-032](../../architecture/decisions/ADR-032-blog-write-foundation.md), [runbook](../../runbooks/BLOG_FOUNDATION.md).

Implementation commit `b6335b3` pushed; [draft PR26](https://github.com/husen211/Qammaris-Parfum-Website/pull/26) attached. Both branch-push and PR CI PHP/Node jobs passed for that application commit ([push](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37620011673), [PR](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37620092049)). No merge/main push or production release. Subsequent evidence-only edits do not imply later CI observations.

## Outcome and files

Shared save operation sanitizes HTML, preserves slug/ID, verifies replacement before DB switch and retains old image. Fresh locked revision prevents stale edit/archive/restore. Archive removes public visibility without deletion; restore returns draft. Audit is committed with mutation; failure rolls back and compensates only new file. No-op/views preserve revision/editorial time; post-commit cache failure cannot delete a committed image.

| Files | Role |
|---|---|
| `app/Actions/Blog/{SaveBlogPost,ChangeBlogPostArchive,RecordBlogPostChange}.php` | Explicit admin writes, short locked transaction/history and safe compensation |
| `app/Services/BlogMediaStorage.php`, `config/media.php` | Configurable disk, validated/verified upload, new-file cleanup |
| `app/Exceptions/BlogPostConflict.php`, admin blog requests/controller | Required revision, admin validation,409/browser recovery, generic error logs |
| `app/Models/BlogPost.php`, `routes/web.php` | Archive visibility, legacy/new image URL resolver, authenticated restore route |
| `resources/views/admin/blog-posts/{create,edit,index}.blade.php` | Default new author, revision/conflict UI, archive/restore and table scrolling |
| `database/migrations/2026_10_07_000002_add_blog_write_safety.php` | Four additive safety fields + blog_post_changes table |
| `tests/Feature/BlogWriteSafetyTest.php` |21 new regression cases |

## Actual checks

- Baseline before work:7 Blog tests/33assertions passed.
- Final narrow suite:21 BlogWriteSafety tests/138assertions passed. Covers create/allowlist/actor hashes; legacy media/ID/slug retention; storage failure/unverified write; DB/audit failure cleanup; stale/missing revision; browser old-input/reload; no-op/views/editorial time; archive public/category/detail/sitemap exclusion; restore draft; stale/archive/no-op/auth guards; extension/dimension validation; existing author/meta fallback; real-commit cache failure.
- Additive migration rehearsal in a separate in-memory SQLite connection: four synthetic existing articles covering all existing categories and legacy image-path forms. Every original column including IDs/slugs/body/authors/media/dates/views compared identical before/after, revision1/new nullable fields verified, history empty. Replaying selected migration left one ledger entry, no data rewrite. It is not a production snapshot or MySQL DDL proof.
- Final full Laravel suite: **403 passed /2804assertions**. Intermediate run exposed fixture leakage from committing the shared test transaction; fixed by moving that real-commit cache test to its own in-memory connection. Final suite passed with isolation restored.
- JavaScript: **31 passed** (`node --test tests/js/*.test.mjs`). Vite production build succeeded; existing DaisyUI `@property` and large 3D chunk warnings remain unrelated to BLOG-01. No JS/package/lockfile change.
- Changed PHP Pint, strict Composer metadata (`validate --strict --no-check-publish`) and final diff/document checks: passed as recorded by final review.

## Browser and screenshots

Real Chrome, dedicated loopback8012 application, isolated disposable SQLite and one synthetic local admin/article. No staging/production account/catalog copied. Applied only the local fixture migration. Viewport overrides are mouse testing, not touch emulation.

- Before/after admin list at390×844 and1440×900. Requested viewport documented; browser chrome/scrollbars can affect captured content dimensions. Five actual DOM widths320/375/390/768/1440 had page scrollWidth equal viewport width; table scroll remains inside labeled focusable region. Keyboard focus onto Edit/Arsipkan scrolled mobile table to the actions instead of clipping them.
- Archive action redirected to Arsip with preserved article; restore activated with Enter, success message + Draft observed. Native confirmation initially stalled automation; Owner closed/resolved it and browser recovery then observed the completed archive. Do not claim unattended dialog-control reliability.
- Two editor tabs: first saved title; stale tab rejected, kept typed title and offered explicit reload. Enter on reload recovered latest title. Screenshot shows stale input and error. Empty archive/list and public archive visibility additionally covered by HTTP tests, not all by browser.
- No console errors in the checked editor tab. Native file chooser/upload was not exercised; file validation/storage/compensation covered by tests. Mouse viewports do not confirm genuine iPhone Safari.
- Initial fixture used a missing legacy image (`images/about-section.jpg`); corrected only the disposable fixture to an existing public image for after captures. This is not a production asset repair or proof of missing production media. Before images retain that fixture's broken thumbnail; layout/state comparison is valid, image change is fixture-only.

| Evidence | Screenshot |
|---|---|
| Desktop before | [before-desktop](before-desktop.jpg) |
| Desktop archive controls | [after-desktop](after-desktop.jpg) |
| Archived article | [archived-desktop](archived-desktop.jpg) |
| Restored draft, narrow capture | [restored-narrow](restored-narrow.jpg) |
| Mobile before | [before-mobile](before-mobile.jpg) |
| Mobile after | [after-mobile](after-mobile.jpg) |
| Mobile keyboard actions | [mobile-actions](mobile-actions.jpg) |
| Stale edit | [conflict-desktop](conflict-desktop.jpg) |
| Mobile editor | [editor-mobile](editor-mobile.jpg) |

UX rationale: archive is explicit/recoverable; stale edits retain user's writing and require current data before another save; mobile operations remain available through a contained scroll region. Broader editor grouping/labels/Journal visual changes belong to BLOG-02/03.

## Data, cleanup, limits and recovery

Production DB/data/media/env/credentials unchanged, no deploy, no dependency installed. Migration exists but was executed only in local disposable/in-memory databases. No historical content backfill or broad rewrite. After browser verification: preview server stopped, port8012 no listener; dedicated preview directory/database/fixture account removed, one session file referring to that unique loopback target removed. Browser test tabs closed/viewport reset. Pre-existing untracked `tools/__pycache__/` left untouched.

Not confirmed: staging/production MySQL DDL/locks/concurrent requests, genuine Safari/touch, live upload/server limits/GD/WebP, production runtime. Audit is metadata/hash history, not body/media restore snapshots or tamper-proof storage. Crash/failed compensation can leave new orphan files; old image is retained. No history UI, visual editor, new publication readiness/taxonomy, responsive image generation or API implemented here.

Recovery: keep additive schema/audits/files and archive/disk/revision-aware build. Pre-BLOG-01 code can expose archived articles/misresolve new images; use compatible rollback or forward fix. Do not down a populated history table. [Detailed runbook](../../runbooks/BLOG_FOUNDATION.md).

Documentation updated: Journal roadmap, current board, business rules, architecture/master plan, ADR-032/index, foundation runbook and this evidence. Next recommended **BLOG-02**, not started automatically. P9 acceptance is still separate/open.
