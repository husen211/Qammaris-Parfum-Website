# ADR-059 — The production deploy runs listed, reviewed migrations

Status: accepted (OPS-DEPLOY-02, Owner 2026-11-11, releasing ORD-13/14).

## Context

`tools/hostinger/deploy-production-release.sh` refused any release with a pending migration. Every schema change therefore needed a manual step first: upload the build package over SSH, then run the migrations from a private copy (RELEASE_ORD_05, RELEASE_ORD_08_09). For ORD-13/14 the Owner had no SSH password at hand, the connection was refused, and the agent may not open a production shell. The Owner asked for releases to work "like usual": merge and done.

## Decision

- The deploy still lists the candidate release's pending migrations before the switch. It runs `php artisan migrate --force` itself **only when every pending name is listed** in `tools/hostinger/auto-migrations.txt`, a file that ships in the release package. Any unlisted pending migration refuses the deploy exactly as before.
- A name is added to the list in the same PR as the migration, and only for an additive change: a new table or a nullable/defaulted column, with no data rewrite and no drop. Review of that PR is the Owner review the old guard asked for.
- The migrations run from the candidate release while the old code stays live. If they fail, the deploy stops and removes the candidate. If the health check fails after the switch, the code is restored and the additive columns stay; the old code ignores them.
- After migrating, the deploy re-checks that nothing is pending and appends the migrated names to `deployments.log`.

## Consequences

- Releases with additive migrations are a plain merge; no SSH or build-package step.
- A risky migration (data rewrite, drop, rename, non-null without default) is simply not listed, so it still needs the manual runbook and a backup decision.
- Partial failure leaves a partly migrated database with the old code live. That is acceptable only because listed migrations are additive; this is why the list exists.
- `migrate:rollback` on production remains forbidden.
