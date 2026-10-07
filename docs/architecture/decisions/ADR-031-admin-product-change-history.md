# ADR-031 — Narrow admin product change history

2026-10-07. Accepted for AUD-07: Owner approved one audit table and retention without automatic deletion. Review-branch implementation only; no live migration or release.

## Concrete need and tradeoff

AUD-F11 identified that imports/feed persist attribution while ordinary product copy/gallery edits do not. A later import conflict cannot reliably identify the admin action that changed its content/media baseline. AUD-05 already provides the shared editor transaction. Reusing import batches would invent file/source/proposal semantics for a single manual edit; ordinary rotating logs would not commit atomically with the product. One focused recorder and one additive table solve this bounded problem. No observer, event bus, generic auditing package, API or history dashboard.

## Storage and boundary

`product_admin_changes` stores product/actor IDs, optional selected image ID, action, changed-field list, before/after allowlisted values and timestamp. Only changed groups are stored. Product metadata and offer values remain readable; descriptions/notes/SKUs/image keys use SHA-256 of their JSON representation. No request body, file bytes/name/URL, user name/email, credentials, IP/session/device identifier, customer recipient or source API payload is copied. Hashes identify a change; they cannot restore the text/file and are not proof against a database administrator altering the ledger.

Historical IDs intentionally have no cascading/nullable foreign keys, so deletion approved in some future task cannot erase/rewrite attribution. Only product operations supplied with persisted application models create rows. Product/image ownership and route authorization remain existing guards; this table is historical evidence rather than a live relational association. Ordinary product deletion remains archive. Index `(product_id, created_at)` supports the demonstrated per-product diagnostic query; no speculative indexes.

Editor create/update requires an explicit authenticated admin actor and records one combined outcome, including attachments/publication, in the same transaction. Existing edits lock/read current product state before capturing before-values. Media primary/move/archive and direct restore accept the actor explicitly; their trusted non-human callers keep existing behavior/audit contracts by omitting it. Manual product archive uses a focused transaction to coordinate its existing state mutation and history. No-op/validation/ownership/readiness failures do not produce committed history; audit failure rejects/rolls back the mutation, retaining existing media and cleaning newly uploaded editor files.

Feed/import tables retain their separate source/batch/revision evidence; no duplicate human record or invented actor for background operations. No historical backfill. This ledger identifies the authenticated account, not a physical person: an automation using the same admin session cannot be distinguished. Scoped machine actors/write API remain separate future work. Taxonomy/blog/direct SQL and unaudited trusted operation calls are outside this scope.

## Retention, release and recovery

Owner selected retention without automatic deletion. No purge command/schedule/config or permission changes. Access is existing authorized operational tooling; no new public/admin route. Routine diagnostic output is IDs/actions/changed fields/timestamps only. Before/after names/metadata remain untrusted stored data if a later interface renders/exports them.

Apply the additive migration on an explicitly approved target before activating new code. Existing deployment pending-migration refusal must remain. Code rollback leaves the audit table/rows intact; do not roll down/drop a populated history table or rewind feed/import state. MySQL migration/locking and production volume/latency are not confirmed by SQLite tests. [Runbook](../../runbooks/ADMIN_PRODUCT_AUDIT.md), [verification](../../verification/aud-07/README.md).
