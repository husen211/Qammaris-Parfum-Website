# Journal release and recovery

BLOG-01–05 are installed on production through the approved2026-10-08 release. [Evidence](../verification/blog-06/README.md) is the runtime reference; older phase reports are dated implementation evidence. Order implementation is excluded. Production machine API remains OFF/no credentials; [activation](JOURNAL_AUTOMATION.md) requires a separate approved operation.

## Repeatable release procedure

1. Review the exact blog candidate against current main. Keep order/source/catalog changes out. Run relevant regressions, full Laravel/JavaScript/build and dependency review. Do not call outstanding advisories clean.
2. For a candidate archive, manual production-workflow dispatch **only builds**: deploy requires successful same-repository main-push CI via workflow_run. Verify the actual workflow/ref conditions and resulting deploy=skipped; do not weaken gates.
3. Verify archive checksum and exact revision. Extract into a private candidate outside public routing; link that environment's protected env and persistent media; isolate framework/log/cache directories. Never copy env/secrets/uploads into an artifact.
4. Obtain specific DDL authorization, inspect all pending migrations, and allowlist only the reviewed files. BLOG initial rollout used000002–000005 and2026_10_08_000001. MySQL DDL is not transaction-rollback-safe; inspect partial failures, do not blind retry/fresh/down.
5. Rehearse staging first. Compare original article columns, user IDs and unrelated catalog fingerprints on the server; export only aggregate pass/fail evidence. Temporary staging actors/records need explicit scope and complete rollback. Remove only exact new fixture media paths.
6. Immediately before approved production DDL, create/verify a protected server-only recovery point. Never print/download data into the release evidence. Verify the selected DDL and no-op replay, unchanged original columns/catalog and no pending migration.
7. Compile config/routes/views on the candidate before activation. Merge only the reviewed exact head. Existing GitHub CI/release pipeline preserves shared env/storage, refuses pending migrations and checks exact current main. Normal dispatch does not replace this activation path.
8. Verify server revision/deployment log, legacy article URLs and images, public200/admin redirect, assets/SEO/schema/sitemap, genuine touch and relevant browser forms. Confirm actual PHP worker cwd and scheduler/watchdog. Report timing/coverage limits, not a universal zero-error promise.

## Recovery

Keep API disabled until credential activation is approved. To contain later API access, disable the gate/config-cache or a specific actor/token through the bounded commands. Human admin/feed credentials remain separate.

Preserve all added schema, history/replay pointers, original/variant files and shared runtime. Prior code ignoring archived_at or owned media/components is not a generally safe rollback after editorial writes. Prefer a tested compatible forward fix. If rollback is needed, approve the exact code/visibility/media recovery path and verify it; never migrate down/populate-reset/purge files as an automatic application rollback.

A SQL recovery snapshot is not permission to overwrite the active database; rehearse restoration on an authorized isolated target first. Candidate/recovery retention cleanup is a separate bounded operation.

## Owner follow-up

Review legacy alt fields and hardcoded price/stock prose without automatic rewriting or unpublishing. Use live product components for future recommendations. Complete the Owner editor walkthrough and production upload check, then separately provision scoped agent credentials if requested. Search Console/indexing and physical-device observations are not established by successful CI.
