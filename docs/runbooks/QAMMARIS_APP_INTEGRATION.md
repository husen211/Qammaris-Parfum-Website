# Qammaris app webhook and feed

Implementation is P8-01 / ADR-021. This runbook describes activation; it does not authorize deployment, secret changes or production mapping.

The staged runtime acceptance matrix, Basic Auth ingress decision, progress denominator and remaining launch stages are in [QAMMARIS_APP_LAUNCH_READINESS.md](QAMMARIS_APP_LAUNCH_READINESS.md). The existing staging release pipeline does not start the integration worker/scheduler; deploying code alone does not establish API connectivity.

## Configuration

Owner supplies the same API key and webhook secret as the app's `WEBSITE_API_KEY` / `WEBSITE_WEBHOOK_SECRET`. Laravel names:

```dotenv
QAMMARIS_APP_BASE_URL=https://api.qammarisapp.com/api/public/v1
QAMMARIS_APP_API_KEY=
QAMMARIS_APP_WEBHOOK_SECRET=
```

Never put real values in Git, command arguments or logs. Blank values disable delivery (webhook 503, reconciliation command queues nothing). No employee JWT is used. The API base URL must be HTTPS without embedded credentials/query/fragment. The worker always uses connection `database`, independently of `QUEUE_CONNECTION`.

Webhook URL: `https://<approved-website-domain>/integrations/qammaris-app/webhook`. Final deployed domain is **not confirmed**. HMAC uses `X-Qammaris-Timestamp` (Unix seconds), `.`, then unchanged raw JSON bytes; signature is lowercase hexadecimal in `X-Qammaris-Signature`. Clocks must agree within 300 seconds. On retry the app signs a new header timestamp. Receiver returns 401 for invalid signature, 422 for invalid signed body, 413 for >16 KiB, 429 for throttling, 503 for unavailable config/queue, and 202 only after durable enqueue.

## Approved activation sequence

1. Verify backup/restore, additive migration plan, queue database configuration and deployment approval on staging first. Do not seed or replace the existing database. Apply migrations through that approved deployment; no product backfill occurs.
2. Owner configures secrets in both environments and the app's webhook URL. Rebuild Laravel config cache as part of the approved deployment and restart workers after code/config changes.
3. Run a supervised persistent worker (existing Hostinger/Supervisor capability is **not confirmed**):

```text
php artisan queue:work database --queue=qammaris-app --sleep=1 --timeout=60 --tries=5
```

Queue `retry_after` must exceed 60 seconds (repository database default: 90). The overlap lock expires after 80 seconds. On one host use the existing shared file cache; all workers must see the same lock store. Do not use process-local array cache for deployed workers. Integration queues are separate from image jobs.

4. Install/verify the existing Laravel scheduler invocation once/minute:

```text
php artisan schedule:run
```

`qammaris-app:sync` runs every 30 minutes and enqueues the same worker. Initial sync is the same command, starting from persisted checkpoint 0. Never infer deletion from absent rows.

5. Verify a signed webhook -> 202 -> job consumed -> checkpoint advances. Use a synthetic product on staging; check sold-out, inbound, OTW, hidden, duplicate/retry and outage retention. Do not trigger real operational changes just to test production.

## Source review and explicit mapping

`qammaris_app_products` holds latest allowlisted source snapshots, including unmatched products and upstream price proposals. This is internal review data, not a public endpoint or an admin import UI. No source rows automatically create/publish website products.

For an approved exact product UUID mapping, run a read-only preview first:

```text
php artisan qammaris-app:map <website-product-id> <source-uuid>
```

After verifying the pair, rerun with `--confirm`. This writes only the external identity and source availability/hidden/ETA, with audit, and replays cached state even if the cursor already passed it. Existing mappings cannot be rebound. This command does not match SKU/name or copy source prices. Do not run broad production mappings without reviewed pairs and an approved data plan.

## Diagnosis / recovery

- `qammaris_app_sync_states`: checkpoint, last successful page time and safe `last_error`.
- `qammaris_app_products`: UUID, latest revision, source snapshot; unmatched rows remain available for review.
- `qammaris_app_changes`: append-only machine attribution and before/after per product/revision.
- Queue `jobs` and `failed_jobs`: worker progress/failures. One job handles up to five pages and queues another if needed. Backoff: 10 seconds, 1 minute, 5 minutes, 15 minutes.
- Source 401/503, redirects, malformed pages and failed DB writes leave the current page checkpoint intact. Prior committed pages remain committed. Fix the cause, then use the normal reconciliation command/approved retry; no cursor reset needed.
- Source outage never expires/downgrades a connected product. Unmapped data never implies a missing/deleted catalog product. Restock ETA does not make a product available.
- Without an active worker, 202 only means durable acceptance, not a completed sync. Without a scheduler, missed/restart-lost source webhooks are not reconciled.

## Rollback

Prefer forward fix after source writes. Retain tables, mappings, audit and checkpoint. Reverting to old public code can expose hidden products or reintroduce stock expiry. Export state before any separately approved schema rollback; never delete products/media, reset the cursor or remove mappings as an automatic rollback.

## Verification boundary

Local feature tests use isolated SQLite and synthetic secrets with fake HTTP, including a real database-queue worker invocation. Production MySQL locking, upstream credentials/network, actual process manager and public endpoint deployment require staging verification. No live API call or production mutation is part of local verification.

P8-02 implements connected labels/restock text and removes connected checked-time/verification messaging from cards/detail/inquiry/WhatsApp. Local browser checks use synthetic states, not a live source connection. Before cutover verify the same labels on a mapped staging product through actual webhook/worker delivery. Legacy/manual products intentionally retain their existing labels and freshness rules.
