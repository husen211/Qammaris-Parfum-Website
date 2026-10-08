# Journal machine API operations

BLOG-05 code review only, 2026-10-08. No production actor/token issued, no staging/production migration or deployment performed. BLOG-06 separately covers staging/MySQL/GD rehearsal, Owner acceptance and production authorization. Existing pending-migration deployment guard remains.

## Activation after separate approval

1. Review stacked BLOG-01–05 PRs and additive migration `2026_10_08_000001_add_blog_automation.php` using `migrate:status`. Existing IDs/slugs/content/media/author stay; new actor/replay/token tables and nullable owner field only. Do not seed/reset existing database.
2. Rehearse on approved staging with MySQL (InnoDB), persistent public blog storage, GD/WebP and shared cache. SQLite local concurrency evidence does not establish MySQL production acceptance. Check package/DB capabilities, stale/replay/ownership/expiry/403/429/error behavior, human admin and existing public pages.
3. After activation approval, set `BLOG_AUTOMATION_ENABLED=true` on that environment and rebuild config cache. Disabled default permits code/schema rollout without opening this API. HTTPS/TLS is required; reject/redirect plain HTTP at hosting. No cross-origin SPA cookies or feed credential reuse.
4. Run `php artisan blog:automation create --name="Editorial agent"` in the intended environment. It prints machine ID only, no token. For production, the command additionally requires `--activate-production`, which records operator intent; the flag does not replace Owner authorization.
5. Run `php artisan blog:automation issue <actor-id> --name="Journal writer" --ability=blog:read --ability=blog:write --ability=blog:media --ability=catalog:read`. Production also requires `--activate-production`. Empty ability options default to those four bounded abilities. Tokens expire 90 days. Capture the once-shown secret directly to the agent secret manager; never commit/paste it into chat/CI/request logs. Do not run this in a shared transcript. No token-management HTTP endpoint exists.
6. Owner may assign existing **drafts only** from editor → Kelola akses agent untuk draft. Save editor changes first; access form is separate. Reassign or choose Tanpa agent to revoke article access. Actor deactivation also closes all its tokens; historical attribution remains.
7. Verify scoped read/create/upload/update using synthetic scoped staging drafts, deliberate denied publish/foreign-ID attempts and simultaneous requests. Observe audit actor_type=machine and hash-only body history. Then rotate/revoke rehearsal tokens and clean approved synthetic data. Production credential activation is a separate step after staging passes.

## Revocation and rollback

`php artisan blog:automation revoke <actor-id> --token=<owned-token-id>` deletes exactly that actor's token. `php artisan blog:automation disable <actor-id>` closes all its API access while retaining actor/tokens/history. These containment commands work while API is disabled; do not delete actor/history. Issue new token after approved rotation; previous secret cannot be recovered from SHA-256 hash.

To close the entire endpoint, set BLOG_AUTOMATION_ENABLED=false and rebuild config cache. Application rollback retains additive tables/replay records/article ownership/originals/variants; do not migrate:rollback or delete public storage as code rollback. Prior blog application ignores nullable ownership. Admin human session/feed credentials remain separate.

## Pending retry / failure recovery

- Read client key **from the requesting actor through a secure channel**, hash locally and inspect only actor+scope+hash with authorized server tooling. No raw key/body logging or public debug endpoint. Successful keys retain pointers indefinitely in this stage.
- Completed record: retry identical payload/key; response uses current own draft state, never a historical publication write. 403 after publication/reassignment is expected and is not permission to bypass ownership.
- Pending record: do not automatically expire/steal it. Check active requests, actor, created time, article/media history and DB transaction outcomes. Completion/audit/mutation are atomic, but a hard worker/storage crash may leave an unreferenced file or reservation. Stop competing clients before repair. Record the finding and seek bounded repair authorization; do not mass-delete/reset the table or blindly generate another key.
- Handled failure: shared action compensates only its own new files and transaction; reservation is released. Retry same key. Cleanup failure emits exception class only for follow-up. DB deadlock retries are bounded at 3 and preserve revision guards.
- Logs carry exception class/code only for API failures, never SQL/body/token/secret/whole request. 500 is generic. Verify reverse proxy/server/APM logs also redact Authorization before activating external agents.
- Limits: 60 actor/min, 10 upload/min and 120 IP/min pre-auth; shared cache is needed across PHP workers. 429 Retry-After is authoritative; do not rotate tokens to bypass actor limits.

## Evidence and current limits

[BLOG-05 evidence](../verification/blog-05/README.md), [API contract](../api/JOURNAL_AUTOMATION.md), [ADR-036](../architecture/decisions/ADR-036-journal-draft-automation.md). Local file multipart + two PHP workers are covered; staging/MySQL/native Owner flow/physical Safari and credential operations on production remain unperformed. Existing SEC-DEP-01/02 dependency findings remain in backlog and are not silently upgraded by Sanctum installation.
