# P8-03 — staging activation evidence

Observed 2026-10-05. **Partially activated; end-to-end acceptance pending.** Owner authorized website staging deployment/env/processes and internal Node backend integration env/restart only. No website production cutover, app frontend change or app database access occurred.

## Release and isolation

- Staging: `https://staging.qammarisparfum.id`; receiver: `/integrations/qammaris-app/webhook`.
- Application SHA: `1867d83cb527f7573ce1046392860a83f64c8e65`. Previous: `7f712c48b1f4f39823345f6cba3998c93224d9b5`.
- [CI 37326362172](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37326362172): success. [Staging release 37326698406](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37326698406): success; R2 rehearsal disabled.
- PHP 8.2.33, APP_ENV staging, debug disabled, dedicated staging MySQL. Baseline products/variants/images/users: **0 each**. No reseeding, production snapshot replacement, product creation, price application or media write.
- Protected backup: `storage/app/private/p8-03-20261005-143646` on staging. Database dump, previous env, root .htaccess, source revision and assets retained. Directory 0700; sensitive files restricted. Compressed dump integrity checked; actual restore rehearsal **not performed**.
- Existing pending additive migrations plus `2026_10_05_000001_create_qammaris_app_integration` applied through release. Checkpoint **0**; no product backfill.

## Secret and access boundary

Two distinct CSPRNG 32-byte hex values generated without printing. Local storage: Windows user-bound DPAPI outside Git, restricted to this user and SYSTEM. Initialization retries preserve the pair. Website API key: **terisi**; webhook secret: **terisi**; env and cached config **0600**. No values in repository, command arguments or report.

App backend panel visible in browser inventory, but control attempts timed out. No alternate app access attempted. Owner configures source env/restart through [secure handoff](../../runbooks/QAMMARIS_APP_OWNER_ENV_HANDOFF.md).

## Actual acceptance

| Check | Observed | Result |
| --- | --- | --- |
| Source feed without key | **503** | Pending app env/restart; expected 401 |
| Source with protected key, after_seq=0 and limit=200 | **503** | Pending; expected 200 |
| Initial drain/order/pagination | Snapshots **0**, checkpoint **0** | Not confirmed |
| Staging root/catalog without Basic Auth | **401**, challenge present | Protected |
| Adjacent webhook-like URL unauthenticated POST | **401**, no Laravel payload | Protected |
| Exact unsigned POST webhook | **401**, invalid_signature | HMAC rejection, no CSRF 419 |
| Correct HMAC, invalid payload | **422**, invalid_payload | Raw-body signature verified |
| Correct HMAC, synthetic UUID/revision signal | **202**, accepted | Durable enqueue verified |
| Worker after accepted signals | Retry attempts observed; safe feed_sync_failed; checkpoint **0** retained | Worker/outage handling verified; source application pending |
| Scheduler | */30 definition; actual qammaris-app:sync and job at **2026-10-05 15:00 UTC / 22:00 WIB** | Invocation verified; successful reconciliation pending |
| Owner source change to public label within 7–15 seconds | Not performed | Not confirmed |
| Duplicate/old no-op, hidden, MySQL page application | Isolated regression coverage; live source unavailable | Runtime proof pending |

No source product example is available: source returns 503, staging catalog is empty. Do not invent counts, UUIDs or mappings.

## Hosting limits

Root-only Basic Auth did not protect the effective public entry directory; the initial THE_REQUEST expression blocked signed delivery. Tested fix installs marked auth blocks at **both** rewrite levels with an exact webhook URI environment exception. Laravel exposes POST only, GET returns 405, every accepted POST requires HMAC. Catalog and adjacent paths remain protected. Final workflow installer executed twice with unchanged contents on retry. These are hosting observations, not general LiteSpeed compatibility claims.

Dedicated queue:work database (qammaris-app, sleep 1, timeout 60, tries 5) and schedule:work started using nohup/flock, observed alive across SSH sessions for over nine minutes. flock and PHP proc_open are available; **crontab binary absent**, no system cron installed. Temporary staging processes do not prove automatic crash/reboot recovery. Protected value-free watchdog/scheduler scripts and PID/lock files are retained for Owner hosting cron setup. Do not run cron scheduler and schedule:work indefinitely together.

## Validation and remaining gate

- This session local suite: **222 tests / 1487 assertions passed**; Pint passed integration/presentation PHP. SQLite/fake HTTP does not prove live source/MySQL integration.
- Deployed stateless receiver with throttle 120/min verified; no broad CSRF exemption.
- No activation UI changes/new screenshots; [P8-02 browser evidence](../p8-02/README.md) remains local presentation proof.
- Complete P8-03 after Owner app env/restart and supervision: drain source feed, review one staging mapping, Owner availability change/revert, latency measurement, live revision/hidden/pagination/recovery and successful reconciliation. No automatic next phase or production switch.

Rollback: retain backup/tables/checkpoint/audit/media. Stop only recorded integration processes if pausing. Restore protected env/auth/assets only within approved staging rollback, rebuild config and restore 0600. Prefer forward fix after source writes; no automatic table drop, cursor reset or production rollback.
