# Owner-approved staging Shopee pairing (P8-07)

Use the Owner's mapping CSV plus unchanged media/basic exports and a fresh read-only staging capture. Treat all SKU/provider IDs as exact strings, including leading zeros and long codes. Supplied candidate names may contain newlines; preserve every candidate.

```text
python tools/prepare_shopee_pairs.py <mapping.csv> <media.xlsx> <basic.xlsx> <private-staging-capture.json> <new-private-output-directory>
php artisan qammaris-app:shopee-pairs --file=storage/app/private/<pairs.json>
php artisan qammaris-app:shopee-pairs --apply=<exact-preview-id> --confirm
php artisan qammaris-app:shopee-pairs --images=<exact-applied-id> --confirm
```

Inspect persisted preview rows before apply: exact SKU→UUID→website ID, existing Shopee ownership, source-file hashes, draft visibility, existing description conflicts and image candidates. Only `sku`/`kuat` can write. Missing/duplicate SKU, tombstones, unmapped UUID, published/archived records and provider/copy conflicts remain unchanged. Never use a fuzzy fallback or demote the AOERA integration fixture. Existing photos are retained rather than reacquired.

No migration, account, credential, permission, frontend build or app-backend change. Deploy only immutable allowlisted PHP files through authorized staging SSH, verifying old/new hashes and existing modes; retain code originals and protected configuration/auth/assets hashes. Do not use the full release workflow, which changes review protection. No additional database backup now; production backup/cutover remains separate.

Use the existing bounded database image queue, independently of the persistent stock worker:

```text
php artisan queue:work database --queue=product-import-images --sleep=1 --tries=2 --timeout=85 --stop-when-empty --max-time=1200
```

Only downloaded/validated/stored images count. Confirm primary cover success, at most three active photos, stored-byte SHA/MIME/dimensions, queue drain and temporary worker exit. Retry transient failures through the same batch; never repeat successful image writes. A newly hidden, published or rebound target is blocked by the worker.

The private 56-row review at `/owner-review/p8-07-pairing/` displays real downloaded covers and all supplied candidates, starting with no selection. Search/status filters and selected-count feedback help Owner review. Export choices locally; do not pretend this export changes products. Applying manual choices is a later Owner-directed fresh-preview task. Occupied candidates may be recorded as correction proposals with `requires_conflict_review`; they are never silently rebound. Duplicate selected UUIDs prevent export. Serve only the standalone review files behind existing staging Basic Auth; check anonymous directory/assets remain 401 and Owner's existing Chrome session renders. Raw inputs/captures and environment files stay private.

New standalone public artifact directories/files use the host's normal creation modes (0755/0644) behind existing HTTP Basic Auth; private inputs use protected modes. Never chmod existing paths to make a review work. An initial new artifact created with private modes can be retained and superseded by a new properly created artifact path, without altering existing modes or protection. Verify actual anonymous/authenticated behavior before handing off its URL.

Verify retained IDs/slugs/offers/publication/availability, unchanged old media metadata/bytes and existing descriptions, expected new identities/descriptions/photos, exact missing-SKU list, sync checkpoint/worker/cron health and replay audit equality. Report actual draft photo count separately from published test fixture and review-only source covers.

Recovery: stop only the bounded image worker if necessary, disable the new command or restore exact PHP originals without chmod/env changes. Keep successful image files and audit; forward corrections require a fresh guarded preview. Code rollback does not undo draft data. Never replace production with staging data.

## Explicit Owner candidate choices (P8-07 follow-up)

Retain the submitted `qammaris-shopee-owner-choices-v1` file and the original review JSON privately. A choice is not permission to rebind an occupied Shopee identity. Unselected rows are absent from the new batch and remain unchanged. No automatic relabeling to `kuat`.

```sh
php artisan qammaris-app:shopee-pairs --choices=storage/app/private/<run>/owner-choices.json --review=storage/app/private/<run>/original-review.json --source-batch=3
```

Preview verifies the exact source batch, immutable source row hash, original description/photos/provenance, supplied candidate SKU, exported UUID/website ID/fingerprint and capture provenance. Current unique SKU/UUID, draft visibility, target fingerprint, provider ownership and blank/equal description remain mandatory. Invalid evidence rejects preview; stale/conflicting targets are held. Inspect the exact persisted preview before using the existing `--apply=<id> --confirm` and `--images=<id> --confirm` commands. The batch source is `owner-selected-shopee-candidates`; each row retains explicit choice and source/review hashes under the same bounded contract. No human account is invented.

Replay the exact applied batch to verify a no-op. Do not refresh a changed Owner fingerprint silently, override conflict flags or move an existing provider binding. Retain prior media and audit snapshots; correction needs a fresh guarded preview. No publication, schema or production changes are authorized here.
