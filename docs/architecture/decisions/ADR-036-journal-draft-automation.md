# ADR-036 — Scoped machine writes for Journal drafts

Accepted for BLOG-05 implementation, 2026-10-08. Review branch `codex/blog-05-api` depends on BLOG-04. Activation/deployment requires BLOG-06 and separate authorization.

Agents need to create editorial drafts/media without a human admin session or Qammaris App credentials. Reusing admin would permit publish/catalog changes and lose human/machine attribution. Use Laravel Sanctum 4.3.3 with a separate BlogAutomationActor and hashed expiring tokens. Bearer-only API, bounded abilities, ownership, unpublished/unarchived state, field allowlists and per-actor limits enforce the boundary.

No generic repository or independent API writer. SaveBlogPost/SaveBlogMedia accept the explicit User|BlogAutomationActor union and BlogWriteAccess checks fresh actor/state after article/actor row locks. Human publishing behavior remains existing. Machine PATCH merges omitted fields from the locked draft, rejects publication input/new raw image tags, and uses owned media markers. Legacy omitted HTML remains intact. RecordBlogPostChange reuses actor_type/id columns for machine attribution; body/media/source metadata remain hashes. Assignment is a narrow admin action with its own revision/audit, no editorial timestamp change. No API assignment or publication route exists.

Create/upload keys use a DB unique actor+scope+key hash, payload checksum, pending/completed state and resource pointer/revision. Reservation precedes file work. Its completion callback runs inside the **existing shared mutation transaction**, alongside audit, so it cannot fail after committing an attachment/article but before recording replay. Existing action compensation covers completion failure. No outer transaction over preprocessed media or generic callback framework is introduced; the optional Closure is internal to these two real write paths.

Pending duplicate 409, changed-payload 409, committed replay 200 with current owned resource and original revision. No frozen body response or raw request storage. Hard-crash pending is not auto-expired/stolen; operator investigates before bounded repair. Completed pointers have no automatic pruning. DB uniqueness arbitrates PHP workers; three bounded transaction attempts handle transient deadlocks while revision/ownership continue to fail safely.

Default feature gate is false. Token management is privileged CLI, accepted ability allowlist and 90-day expiry. Production issue/create additionally requires an explicit activation flag; it does not substitute for approval. Disable/revoke work with gate closed. Human User does not gain HasApiTokens. Cookies/stateful SPA auth remain off; products are read-only public metadata. Public draft visibility remains existing published scope.

Focused tests cover hashed Bearer auth/expiry/revoke/scope, ownership/publication, PATCH preservation/revision, assignment/reassignment, upload MIME/foreign media/URL rejection, key conflict/pending/replay and rollback when audit/replay completion fails. Two independent local PHP workers share SQLite for simultaneous create/upload/update evidence, including rollback-safe retries. Local tests are not MySQL acceptance; staging/GD/cache/Owner/token activation are BLOG-06 gates.

Sanctum is the demonstrated new dependency explicitly selected in the approved plan. Composer lock adds only this package; existing advisories remain separate. Retention, credentials and database/media cleanup are operational decisions, not speculative background tasks.

[Contract](../../api/JOURNAL_AUTOMATION.md), [operator guide](../../runbooks/JOURNAL_AUTOMATION.md), [agent guide](../../runbooks/JOURNAL_AGENT_GUIDE.md), [evidence](../../verification/blog-05/README.md).
