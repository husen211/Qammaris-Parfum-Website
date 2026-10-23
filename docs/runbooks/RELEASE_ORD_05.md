# Release ORD-05 (customers saved automatically, Pelanggan menu, removal) — Owner runbook

Owner asked on 2026-10-21 to prepare this release: backup, migration, then merge; the customer backfill comes later, after the Owner has seen its preview ([CUSTOMER_BACKFILL](CUSTOMER_BACKFILL.md)).

The deploy script refuses pending migrations, so the one ORD-05 migration runs first from the verified build package in a private directory, while the current release stays live. This is the procedure already used for ORD-08/09 ([RELEASE_ORD_08_09 §3](RELEASE_ORD_08_09.md), [RELEASE_ORD_PWA_RC §5a](RELEASE_ORD_PWA_RC.md)).

| | |
|---|---|
| PR | #81 `modernization/ord-05-customers` (up to date with `main` at `4accc92`) |
| Package | GitHub run `39610549077` (workflow_dispatch, build only, no deploy), artifact `production-418894aade800a22fca13881e699f1cf22568a8b` |
| Migration | `2026_10_21_100001_prepare_customers_for_auto_save_and_anonymizing` (additive) |

**Why the live release keeps working after the migration:** it only makes `customers.created_by` and `customer_addresses.confirmed_by` nullable (the current code always fills them) and adds `customers.anonymized_at` / `anonymized_by` (unused by the current code).

Paths below: `root=/home/u429527638/domains/qammarisparfum.id`, `shared=$root/shared/qammaris-website`.

## 1. Backup (Owner, before anything else)

Fresh database dump streamed to the laptop, the same way as on 2026-10-17 (nothing large is written on the hosting):

```
ssh <production-ssh> 'mariadb-dump --single-transaction --routines --triggers --hex-blob <database> | gzip' > qam-prod-2026-10-21-before-ord05.sql.gz
sha256sum qam-prod-2026-10-21-before-ord05.sql.gz
```

Use the same credentials method as on 2026-10-17; never put a password in the command line, chat or Git. Keep the file off-server and encrypted.

## 2. Upload the package (Owner)

The agent downloads the artifact to `C:\projects\Qammaris App\release-packages\ord05-418894a\` (two files: `production-release.tar.gz` and `.sha256`). Then:

```
ssh <production-ssh> 'mkdir -m 700 -p ~/domains/qammarisparfum.id/shared/qammaris-website/ord05-migrate-418894a'
scp production-release.tar.gz production-release.tar.gz.sha256 <production-ssh>:domains/qammarisparfum.id/shared/qammaris-website/ord05-migrate-418894a/
```

## 3. Migrate from the package (Owner, SSH)

```
root=/home/u429527638/domains/qammarisparfum.id; shared=$root/shared/qammaris-website; dir=$shared/ord05-migrate-418894a
cd "$dir" && sha256sum -c production-release.tar.gz.sha256 && tar -xzf production-release.tar.gz
test "$(cat release-revision.txt)" = 418894aade800a22fca13881e699f1cf22568a8b && echo revision-ok
ln -s "$shared/.env" .env
mkdir -p storage/logs storage/framework/cache storage/framework/views storage/framework/sessions bootstrap/cache
php artisan migrate:status --no-interaction | grep -i pending
```

**STOP** unless exactly one line is Pending: `2026_10_21_100001_prepare_customers_for_auto_save_and_anonymizing`. Do not run `config:cache`, `optimize` or `queue:*` in this directory.

```
php artisan migrate --force --no-interaction
php artisan migrate:status --no-interaction | grep -ci pending    # must print 0
curl -fsS -o /dev/null -w '%{http_code}\n' https://qammarisparfum.id/up   # 200
cd "$shared" && rm -rf "$dir"
```

If any step fails: STOP and tell the agent. Never `migrate:rollback` on production; a restore from the step 1 backup needs explicit Owner approval.

## 4. Merge (agent, on the Owner's go)

The agent merges PR #81. CI on `main` deploys: nothing is pending, caches, health check with automatic rollback, worker restart. The agent then checks the live site and the Pelanggan menu with the Owner.

## 5. Later: backfill

`php artisan qammaris:customers:backfill` (preview, counts only) from `$root/current`; `--apply` only after the Owner agrees with the counts. See [CUSTOMER_BACKFILL](CUSTOMER_BACKFILL.md).

## Not done in this preparation

- The migration was rehearsed on MariaDB 10.4 (isolated), not on a copy of production on 11.8. The change is two nullable columns and two nullability relaxations; the step 3 status check and the step 1 backup are the safety net.

## Release record (2026-10-23)

- Owner chose to skip the database backup (additive migration only).
- Package `production-418894a…` uploaded and checksum verified on the server. Artisan first failed with a masked `Class "request" does not exist`; the real error was a missing `bootstrap/cache` in the package (now in step 3). Exactly one migration was pending; `migrate --force` ran it in 31 ms; 0 pending; `/up` 200; private directory removed.
- The deploy of `32700b9` (refused on 2026-10-21) needed two re-runs: its refusal had left `releases/qammaris-32700b9…` and `releases/.deploy-32700b9…`, which block a re-run of the same revision. Both were removed (not live); the 4th attempt deployed, health check passed, `current` = `qammaris-32700b9…`, `/admin/customers` redirects guests to login. Finding recorded as OPS-DEPLOY-01.
- Backfill preview on production: 8 orders without customer → 0 to existing, 5 new customers, 7 links, 1 skipped. `--apply` not run; waits for the Owner.
