# ADR-021 — Qammaris App availability integration

Status: Accepted — 2026-10-05, explicit Owner instruction to implement the Laravel receiver, feed worker and 30-minute reconciliation.

## Context

The internal app owns employee sold-out/inbound/OTW reports and Majoo stock imports. Its final contract is `docs/integrations/website-handoff.md` section 6 in the internal-app repository. Laravel consumes it; this is not an API exposing website mutations to AI or other callers.

## Decision

1. Keep Laravel, Blade, Eloquent, MySQL and existing catalog IDs/URLs/media. Use UUID mapping under `qammaris_app`; no automatic name/SKU matching, remapping, draft creation or publishing.
2. A stateless POST `/integrations/qammaris-app/webhook` verifies lowercase hex HMAC-SHA256 over Unix timestamp + `.` + exact raw body. Tolerance is 300 seconds; validate bounded input and throttle 120 requests/minute/IP. No session login or CSRF exemption for other routes.
3. Acknowledge 202 only after dispatch to Laravel's **database** queue `qammaris-app` succeeds. Configuration/queue failure returns 503. Duplicate signals may enqueue duplicate wakeups; revision guards and a shared worker lock make writes idempotent.
4. One feed operation serves webhook jobs and reconciliation every 30 minutes. Always start from the local checkpoint, never a webhook sequence. GET uses the dedicated read-only `X-Api-Key`, HTTPS and no redirects. Validate a whole page before any writes.
5. Checkpoint, allowlisted latest source snapshots, linked product updates and before/after audit commit in one page transaction. Serialize on the checkpoint row and reject an obsolete fetched page. Process at most five pages/job, then enqueue a continuation.
6. Source revision equals its last `change_seq`. Older/equal revisions are no-op. The source contract guarantees a safe watermark and a stable sequence; Laravel cannot establish upstream transaction visibility independently.
7. Linked products take availability **as supplied**, without expiry or outage downgrade. This supersedes ADR-003's 36-hour rule for `availability_source=qammaris_app`. Unlinked/manual products retain existing semantics. Keep status timestamps for internal diagnosis only.
8. Store upstream `hidden` independently of website publication. Public published scopes and `isPubliclyVisible()` exclude hidden records without archiving/deleting them. `isPublished()` retains its publication meaning for admin completeness and last-image protection. Merge targets remain review data; never auto-rebind identity. OTW ETA never changes availability.
9. Human forms/provider CSV cannot overwrite connected availability. Price/name/brand/department/source remain snapshot review data, not catalog mutations. Admin publication, price, URLs, offer/SKU and media remain unchanged.
10. Explicit UUID mapping has a CLI preview and `--confirm`, reusing `MapExternalProductIdentity` and the same availability operation to replay the cached latest snapshot. This prevents missing an initial state when mapping occurs after the feed checkpoint advanced.

## Consequences and scope

Three integration tables and two product metadata columns are additive; existing product values are not backfilled. Laravel database jobs already exist. No new dependency, generic repository, event bus or new permission model is required.

Machine attribution is `qammaris_app`, recorded with UUID/revision and before/after availability metadata. Credentials, full request bodies and extra upstream fields are never retained. Queue errors and worker exceptions use safe messages.

P8-01 implements the backend. The separate focused UI item P8-02 now implements connected public labels/restock copy and hides checked-time copy on connected products, preserving legacy behavior. Shopee image import and price review UI are separate work. Worker/scheduler activation, secrets and production migration require deployment approval and are not executed here.

## Rollback / forward fix

Before integration writes, rollback the new migration only after checking no source/audit data needs preserving. After activation, prefer stopping delivery/worker under an approved operational change and forward-fixing. Export integration state/audit first; do not drop populated tables or reset the cursor. Do not roll back to code that ignores hidden tombstones or expires connected availability. No file/media cleanup is part of rollback.


Owner-approved P8-04 exception (2026-10-06): ADR-022 permits a separate feed-authoritative draft preparation CLI. Stock/webhook worker still does not create drafts or alter price/media. No automatic publication.
