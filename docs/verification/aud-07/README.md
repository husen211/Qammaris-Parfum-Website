# AUD-07 — Admin product change history verification

**Subsequent release:** PR24 is merged and production `9325df4` activated; [MySQL/runtime/release proof](../audit-release/README.md). The dated implementation evidence and its remaining verification limits below are retained; pre-release status is superseded.

2026-10-07. IN_REVIEW; implementation local/review branch only. Owner approved AUD-07 and then the additive table with retention without automatic deletion. Scope AUD-F11. No deployment/live migration or next phase. [ADR-031](../../architecture/decisions/ADR-031-admin-product-change-history.md).

## Outcome and files

- `RecordProductAdminChange`: current DB snapshot allowlist, stable ID-ordered offers/gallery, description/notes/SKU/key hashes, changed groups only, known action/persisted actor and active transaction requirement. Query Builder insertion, no model observer/framework/new public route.
- `2026_10_07_000001_create_product_admin_changes_table`: one empty table/index, historical product/actor/image IDs, action/fields/before/after/time. IDs intentionally do not cascade/null with future separately authorized parent deletion.
- `SaveProductEditor`: explicit actor required from both controller callers; existing product lock/read before-values; one combined outcome after offer/upload/publication, same transaction and existing new-file compensation.
- `ArchiveProduct`: narrowly wraps the existing archive state operation with lock/history transaction. `PublishProduct` and gallery primary/move/archive accept an optional explicit admin actor; trusted existing non-human callers omit it and retain original separate audit contracts.
- `AdminProductController`, `AdminProductImageController`: actor from authenticated request, never submitted actor_id. URLs, redirects, error/retry behavior and route/middleware/CSRF remain.
- `AdminProductAuditTest`: actor spoof/privacy, changed-group/no-op, gallery actions/selected ID, archive/restore replay, validation/foreign child/permission failure, protected last photo, editor/media/archive audit failure, outer rollback, system actor separation and historical-ID retention.
- Docs: rules, architecture, plan/backlog, audit follow-up, ADR index/ADR-031, [operational runbook](../../runbooks/ADMIN_PRODUCT_AUDIT.md) and this evidence. No competing context/agent document.

## Checks actually run

- Baseline relevant editor/media/archive tests: **43passed /217assertions**.
- Red regression: draft created but no history table existed; recorded failure before implementation.
- Intermediate editor/media/import/integration suite: **110passed /695assertions**.
- Final new history suite: **14passed /110assertions**.
- Final full `php artisan test --compact`: **382passed /2666assertions**.
- `node --test tests/js/*.test.mjs`: **31passed**.
- Strict Composer metadata valid. Targeted Pint fixed formatting, then final check passed. Final `git diff --check` passed; all changed context/runbook/ADR/evidence relative links valid.
- No frontend files/assets changed. No local Vite build, browser viewport/screenshots or real device test claimed for AUD-07; CI frontend build is checked separately on the PR.

All feature tests use SQLite memory/synthetic accounts/fake disk. No human account or staging/production record was mutated. Existing imports/feed/price/availability/publication and parent/media integrity regressions pass in the full suite.

## Migration rehearsal and data/media safety

A fresh private workspace copy of `storage/app/private/p7-07-preview/catalog.sqlite` contained445historical products. Run only the selected new migration, then replay the same path: table created successfully; replay “Nothing to migrate”; one new migration bookkeeping entry and empty `product_admin_changes` table. SHA-256 fingerprints of the row contents of all21original non-migration tables remained identical. No old IDs/slugs/offers/media/users/checkpoints/import rows were rewritten. These are historical local data, not current production counts. Source was opened read-only and untouched.

No existing schema columns/indexes changed; no new uploaded file, cloud storage or source contract change. Only disposable local rehearsal DB and synthetic test/fake-storage state. The exact workspace-scoped rehearsal directory was resolved/verified and removed; absence confirmed. Source snapshots and unrelated pre-existing `tools/__pycache__/` were retained. Tests use disposable in-memory records/fake storage; no persistent test account or browser/server session was created. Actual production MySQL DDL/lock behavior/latency/table size and backup retention remain Not confirmed.

## Limits, diagnostics and recovery

No backfill, purge, audit UI/export, physical-human/machine discrimination, full text restoration or tamper-proof guarantee. Account IDs establish the authenticated admin session; shared-session automation is indistinguishable. Taxonomy/blog/direct SQL/unattributed trusted calls remain outside scope. Product name/metadata remain untrusted if rendered later; descriptions/notes and paths are hashes. Existing import/feed history remains separate; the recorder does not reset any proposal fingerprint/baseline.

Admin mutations wait for a product lock and their own history insertion; editor file IO remains inside that transaction. Concurrent MySQL load is not measured by SQLite. Audit storage failure intentionally rejects the mutation, preserving safe retry instead of silently omitting history.

The current production release script refuses pending migrations. An explicitly approved staging/MySQL rehearsal and targeted migration must precede activation of this code; do not blindly run all migrations or bypass the guard. Code rollback retains the new table/history; migration down/drop/refresh would destroy history and is not the routine recovery path. Prefer a narrow forward fix for insertion failures. Safe read-only diagnosis and target/release ordering are in the runbook.

Recommended next: review/consolidate the stacked audit PRs and separately authorized release/P9 acceptance. There is no approved AUD-08, API/payment/cloud work or automatic next phase. Existing P1/P8/P9 unknowns remain open.
