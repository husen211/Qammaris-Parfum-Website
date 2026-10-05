# Owner handoff — staging API activation

Scope: internal-app **backend Node** at `api.qammarisapp.com` and Laravel website staging. Do not redeploy the app frontend, modify app database or switch to the website production receiver.

## Current status — 2026-10-05

Owner explicitly authorized website staging SSH and the existing signed-in Chrome hPanel session. The existing `QAMMARIS_APP_API_KEY` and `QAMMARIS_APP_WEBHOOK_SECRET` were read from staging env into memory only, then entered into these backend environment variables:

- `WEBSITE_API_KEY`: **terisi**, same staging value.
- `WEBSITE_WEBHOOK_SECRET`: **terisi**, same staging value.
- `WEBSITE_WEBHOOK_URL`: `https://staging.qammarisparfum.id/integrations/qammaris-app/webhook`.

Only these three variables were added/applied. hPanel saved them and restarted/redeployed the same backend commit **921569b4**, completed at **15:48:01 UTC / 22:48:01 WIB**. No pair rotation, clipboard transfer, secret output/file or filesystem permission change occurred. Secret session variables were cleared afterward. Staging env is authoritative; do not blindly restore an older local secret store.

Feed authentication now returns **401 without key / 200 with key**. Initial database-worker synchronization completed: **452** snapshots, checkpoint **452**, `has_more=false`, six hidden tombstones retained. Catalog products remain **0**; a feed snapshot does not create or publish a website product. Evidence and safe product example: [P8-03 verification](../verification/p8-03/README.md).

## Website staging process supervision

The two entries below are **already saved** in staging hPanel, each once per minute. Do not add duplicates:

```text
bash /home/u429527638/domains/staging.qammarisparfum.id/public_html/storage/app/private/p8-03-worker-watchdog.sh
bash /home/u429527638/domains/staging.qammarisparfum.id/public_html/storage/app/private/p8-03-scheduler.sh
```

Both protected heartbeat files advance. After verifying cron execution and exact staging process identity, temporary `schedule:work` was stopped. One actual PHP integration worker is running under flock; signed duplicate/older wakeups were consumed, queue pending became zero, snapshots/checkpoint stayed unchanged. The original wrapper PID file was stale: verify the live process working directory, command and dedicated queue before any process-control action. Never terminate a process from a PID file alone.

The scheduler definition queues reconciliation every **30 minutes**. Watchdog restart recovery passed: the worker stopped at **15:56:28 UTC**, one job remained durable, and the cron started a new worker and consumed the job at **15:57:02 UTC**, retaining checkpoint **452** and clearing no source state. Recovery took about **34 seconds**; OS reboot was not tested. Actual half-hour cron execution passed at **16:00 UTC / 23:00 WIB**: task DONE, worker success **16:00:04 UTC**, queue pending **0**, checkpoint **452**, last_error **null**, remaining feed empty and has_more=false. Three historical failed jobs from the earlier source outage are retained. Do not erase/retry unrelated failed jobs.

## Next Owner action — one selected availability test

Select **one** safe source product by name and size. Wait for the website team to prepare and review its exact staging product/UUID pair before changing availability. Then report that product sold out and delete the report (or record inbound through the normal SOP), allowing real source webhook → worker → staging label latency to be measured. No operational product has been changed by the website agent.

A price-only edit exercises revision delivery but does **not** change the website selling price, which remains a reviewed proposal. Source product names/SKUs are matching assistance only; mapping uses the application's UUID. No automatic catalog/price/media imports are authorized by activation.

Complete the remaining P8-03 runtime matrix: actual mapped availability/revert, old/duplicate revision guard, hidden/OTW/unknown behavior. Then review P8-04 launch identities; do not start broad mapping automatically.

## Confidentiality and rollback

No secret values are needed in chat, repository, screenshots, logs or command arguments. Keep existing protected environment values; store presence-only operational evidence. If panel access is unavailable, Owner can transfer values directly from authorized protected storage into the backend editor without sending them through chat.

Retain the protected staging backup, source snapshots, checkpoint and audit. Prefer forward fix; do not reset the cursor, drop integration tables or delete catalog/media as automatic rollback. Pausing integration requires only approved staging process/env changes. **Website production cutover and changing the webhook target to production require separate Owner approval.**
