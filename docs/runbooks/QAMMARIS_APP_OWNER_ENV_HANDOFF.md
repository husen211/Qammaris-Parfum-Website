# Owner handoff — staging API activation

Scope: internal-app **backend Node** at `api.qammarisapp.com` and Laravel website staging. Do not redeploy the app frontend, modify app database or switch to the website production receiver.

## Current status — 2026-10-05

Owner explicitly authorized website staging SSH and the existing signed-in Chrome hPanel session. The existing `QAMMARIS_APP_API_KEY` and `QAMMARIS_APP_WEBHOOK_SECRET` were read from staging env into memory only, then entered into these backend environment variables:

- `WEBSITE_API_KEY`: **terisi**, same staging value.
- `WEBSITE_WEBHOOK_SECRET`: **terisi**, same staging value.
- `WEBSITE_WEBHOOK_URL`: `https://staging.qammarisparfum.id/integrations/qammaris-app/webhook`.

Only these three variables were added/applied. hPanel saved them and restarted/redeployed the same backend commit **921569b4**, completed at **15:48:01 UTC / 22:48:01 WIB**. No pair rotation, clipboard transfer, secret output/file or filesystem permission change occurred. Secret session variables were cleared afterward. Staging env is authoritative; do not blindly restore an older local secret store.

Feed authentication now returns **401 without key / 200 with key**. Initial database-worker synchronization completed: **452** snapshots, checkpoint **452**, `has_more=false`, six hidden tombstones retained. Catalog was **0** after initial sync; the Owner-selected AOERA follow-up now adds **one** explicit staging fixture. A feed snapshot itself does not create or publish a website product. Evidence and safe product example: [P8-03 verification](../verification/p8-03/README.md).

## Website staging process supervision

The two entries below are **already saved** in staging hPanel, each once per minute. Do not add duplicates:

```text
bash /home/u429527638/domains/staging.qammarisparfum.id/public_html/storage/app/private/p8-03-worker-watchdog.sh
bash /home/u429527638/domains/staging.qammarisparfum.id/public_html/storage/app/private/p8-03-scheduler.sh
```

Both protected heartbeat files advance. After verifying cron execution and exact staging process identity, temporary `schedule:work` was stopped. One actual PHP integration worker is running under flock; signed duplicate/older wakeups were consumed, queue pending became zero, snapshots/checkpoint stayed unchanged. The original wrapper PID file was stale: verify the live process working directory, command and dedicated queue before any process-control action. Never terminate a process from a PID file alone.

The scheduler definition queues reconciliation every **30 minutes**. Watchdog restart recovery passed: the worker stopped at **15:56:28 UTC**, one job remained durable, and the cron started a new worker and consumed the job at **15:57:02 UTC**, retaining checkpoint **452** and clearing no source state. Recovery took about **34 seconds**; OS reboot was not tested. Actual half-hour cron execution passed at **16:00 UTC / 23:00 WIB**: task DONE, worker success **16:00:04 UTC**, queue pending **0**, checkpoint **452**, last_error **null**, remaining feed empty and has_more=false. Three historical failed jobs from the earlier source outage are retained. Do not erase/retry unrelated failed jobs.

## Latest verification — 2026-10-06 local

Owner's AOERA sold-out/return-to-available cycle passed automatically (revision 453/454, approximately 10/13 seconds by stored timestamps). Latest revision/checkpoint **456**, five audits, queue empty, no sync error. Owner logged into the existing review Basic Auth; live catalog/detail acceptance passed at **1440×900 / 390×844**. Deployed rollback tests proved rare-state/old-revision behavior with synthetic HTTP; these do not substitute for actual source-originated rare-state delivery or measured browser transition timing. [Latest verification and remaining gates](../verification/p8-03/runtime-follow-up.md).

The staging watchdog was repaired after its persistent child inherited Hostinger's cron lock on **fd 3**. Its nohup launch now includes **`3>&-`**, verified against the value-free repository script `tools/hostinger/staging-qammaris-worker-watchdog.sh`. Bash syntax passed; original mode 0700 unchanged; existing two cron commands remain unchanged. One refreshed worker holds only the application worker lock; both minute heartbeats advance. Keep the backup `storage/app/private/p8-03-worker-watchdog-before-fd-fix-20261005.sh` but prefer forward fix: restoring it would reintroduce the blocking provider lock. Check `/proc` cwd/executable/argv/descriptors, not just the PID file.

AOERA remains a **placeholder fixture**, not launch-ready product imagery. No Shopee image matching/import or permanent production R2 cutover occurred. Review actual launch identities and real media separately after remaining P8-03 gates.

## Historical Owner action — prepared AOERA availability test

Owner selected **AOERA MAJESTIC 50 ML**. Its staging fixture is ready: website product **1** → UUID `00360de8-31bd-4982-bd73-ddaaba2d9658`, revision **59**, label **Tersedia**. Open `https://staging.qammarisparfum.id/products/p8-03-aoera-majestic-50-ml` with review Basic Auth directly in the browser. Report this exact source product sold out; let the website team observe Habis before deleting the report and verifying Tersedia. No operational product has been changed by the website agent. [Fixture and verified baseline](../verification/p8-03/aoera-staging-test.md).

A price-only edit exercises revision delivery but does **not** change the website selling price, which remains a reviewed proposal. Source product names/SKUs are matching assistance only; mapping uses the application's UUID. No automatic catalog/price/media imports are authorized by activation.

Complete the remaining P8-03 runtime matrix: actual mapped availability/revert, old/duplicate revision guard, hidden/OTW/unknown behavior. Then review P8-04 launch identities; do not start broad mapping automatically.

## Confidentiality and rollback

No secret values are needed in chat, repository, screenshots, logs or command arguments. Keep existing protected environment values; store presence-only operational evidence. If panel access is unavailable, Owner can transfer values directly from authorized protected storage into the backend editor without sending them through chat.

Retain the protected staging backup, source snapshots, checkpoint and audit. Prefer forward fix; do not reset the cursor, drop integration tables or delete catalog/media as automatic rollback. Pausing integration requires only approved staging process/env changes. **Website production cutover and changing the webhook target to production require separate Owner approval.**
