# Admin product history — AUD-07

Review-branch implementation, 2026-10-07. Owner approved the table and indefinite retention without automatic deletion; deployment/live migration remain separate. [ADR-031](../architecture/decisions/ADR-031-admin-product-change-history.md).

## What is covered

- Editor create/update: one record for the committed combined change, including new photo metadata and publication.
- Gallery primary, move and archive: selected image ID and complete affected active-gallery metadata (including promoted primary/order).
- Product archive and direct restore/publish: committed state transitions.
- No-op, invalid/unauthorized/foreign-child/readiness failure: no committed row. Audit failure also rolls back the mutation; retry the normal action only after the underlying problem is fixed.

Rows contain only changed snapshot groups. Description/notes/SKU/key hashes compare changes without copying their content. Active-image lists are ordered by ID for stable comparison, with primary/sort metadata separate. Archived files remain governed by the existing media rules, not this audit table. There is no text/file restoration button or history page in AUD-07.

## Read-only diagnosis

Use the already authorized Laravel operational shell on the correct environment. Do not print `.env`, configuration secrets, request bodies or customer data. Example with a known website product ID, not a SKU:

```php
$productId = 123; // replace with the actual website product ID
DB::table('product_admin_changes')->where('product_id', $productId)
    ->orderByDesc('id')->limit(20)
    ->get(['id', 'actor_id', 'action', 'image_id', 'changed_fields', 'created_at']);
```

For a specific authorized investigation, read that row's before/after in memory and compare its image IDs/hash values with the existing import row evidence. Do not dump whole tables, user records or credentials to chat/logs. Names and metadata are untrusted; a later UI must escape output, and an export must be allowlisted/formula-safe. Absence of a row means no covered committed change was recorded after activation; it does not prove no earlier/direct SQL/system change occurred.

An actor ID denotes the authenticated account. Missing/deleted actors keep the historical ID; do not guess a name or classify it as a separate automation. Imports/feed retain their own actor/batch/revision diagnostics. AUD-07 does not expose a write API, change credentials, rebind provider IDs or reset an import baseline.

## Approved future migration/release sequence

1. Review the stacked AUD PRs and schema change. Rehearse the selected migration on staging/MySQL with the normal target/recovery preflight; the local historical SQLite proof is not a production MySQL rehearsal.
2. On an explicitly Owner-approved prepared release/target with protected shared environment loaded, run only:

```sh
php artisan migrate --path=database/migrations/2026_10_07_000001_create_product_admin_changes_table.php --force --no-interaction
```

3. Confirm the migration is Ran and the new table initially empty. Existing products/IDs/slugs/offers/media/checkpoints remain unchanged. Repeating that command should do nothing. Do not backfill prior changes with guessed actors.
4. Activate through the existing GitHub/SSH release runbook. `tools/hostinger/deploy-production-release.sh` refuses pending migrations; do not bypass/remove that guard. Verify a scoped approved staging admin change/row and its no-op before production release acceptance.

This document does not execute or authorize production migration, test writes, deployment or next-phase work.

## Recovery and retention

Code-only rollback can retain the new table and rows; old code simply does not add rows. Forward-fix audit insertion/storage failures rather than silently allowing unaudited saves. `down()` drops the table: do not run migration rollback/refresh/reset against a populated history table. Deletion/export/retention changes require a separately approved scope and verified recovery. No automatic expiry/cleanup is installed. Size/runtime/backup retention beyond the local rehearsal are Not confirmed; include the table in the existing protected database backup/recovery procedure when released.
