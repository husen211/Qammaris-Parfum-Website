# Order API v1 — staging, joint tests, secret rotation, App outage

For the Website operator and the Qammaris App agent. Decision: [ADR-041](../architecture/decisions/ADR-041-order-api-v1-implementation.md). Contract: [API v1](../integrations/QAMMARIS_ORDER_API_V1.md) (r4.1, frozen).

**Never in production without Owner approval.** Every step below is for local or staging environments with synthetic data.

## 1. Switches (`.env`)

| Variable | Meaning | Staging |
|---|---|---|
| `ORDERS_V2_ENABLED` | New orders use the V2 model (ADR-040) | `true` |
| `QAMMARIS_ORDER_API_ENABLED` | API v1 answers (otherwise 503) | `true` |
| `QAMMARIS_ORDER_API_CLIENT_ID` / `_SECRET` / `_SECRET_PREVIOUS` | App → Website HMAC | shared with the App out of band |
| `QAMMARIS_ORDER_WEBHOOK_ENABLED` | Website → App events | `true` |
| `QAMMARIS_ORDER_WEBHOOK_URL` | Event endpoint; for a joint test, the App's tunnel URL ending in `/api/integrations/website/orders/events` | tunnel |
| `QAMMARIS_ORDER_WEBHOOK_CLIENT_ID` / `_SECRET` / `_SECRET_PREVIOUS` | Website → App HMAC | shared out of band |
| `QAMMARIS_ORDER_APP_TASK_LINKS` | Group link opens the App instead of the Admin PWA | `false` until the App order page is live |
| `QAMMARIS_APP_ORDERS_URL` | App order page base | `https://qammarisapp.com/orders` |
| `QAMMARIS_APP_OWNER_IDS` | r4.2: comma-separated App user IDs of the Owners who may create or change a proof waiver. Server only. Empty means new waivers are refused. | the App's synthetic Owner ID(s) |
| `QAMMARIS_ORDER_API_WEBSITE_ISSUES` | r4.2: the Admin PWA may open V2 issues while the API is on | `false` until the App confirms it reads `opened_by_source` |

Secrets are exchanged out of band only. Never put them in Git, chat, tickets or logs.

While `QAMMARIS_ORDER_API_ENABLED=true`, new V2 issues (kendala) are recorded from the App only, until contract r4.2 is approved; the Website can still resolve existing ones. Before switching the API on, make sure no open V2 order has an issue opened in the Website.

After changing `.env`, run `php artisan config:clear`. The scheduler (`php artisan schedule:work`, or cron running `schedule:run`) must be running, because it delivers webhooks every minute.

**Check:** open **Integrasi App** (Super Admin). It shows which switches are on and which settings are filled, without revealing any secret.

## 2. Synthetic fixtures

```
php artisan qammaris:order-api:fixtures --actor=<super-admin-username>
php artisan qammaris:order-api:fixtures --actor=<super-admin-username> --reset   # new round
```

- The command prints JSON with six orders: `local` (2 items), `intercity` (J&T), `customerCourier`, `pickup`, `issue` (stock-issue simulation), `costs`. All are V2, active, unclaimed, with names starting with `E2E` and fake phones and addresses.
- Products are drafts of the inactive brand `E2E Sintetis` and never appear in the catalogue.
- Re-running reuses fixtures that are still clean.
- `--reset` cancels earlier open fixtures (it does not delete them) and creates six new ones.
- The command refuses `APP_ENV=production`.

## 3. Joint test with a tunnel

1. The App agent starts its local backend and tunnel, then sends the tunnel base URL.
2. Set `QAMMARIS_ORDER_WEBHOOK_URL=<tunnel>/api/integrations/website/orders/events`, then run `config:clear`.
3. Expose the Website API to the App, through a staging host or the Website's own tunnel. The App signs paths starting with `/integrations/qammaris-app/orders/v1/`.
4. Run fixtures, and give the six IDs to the App agent.
5. Exercise claim → preparation → courier/J&T → QR upload/download → handover → delivery → issues → costs. Every request carries `X-Request-Id`, which is stored on the order event for correlation.
6. Check **Integrasi App**: the pending count drains, and failures appear with **Kirim ulang** (resend).

## 4. Secret rotation (either direction)

1. Generate a new secret.
2. On the verifying side, set `*_SECRET_PREVIOUS=<old>` and `*_SECRET=<new>`, then run `config:clear`. Both secrets are now accepted.
3. Switch the signing side to the new secret.
4. Once no request signed with the old secret appears, clear `*_SECRET_PREVIOUS`.

## 5. When the App connection fails

- **Orders keep working.** Staff use the Admin PWA (**Pesanan Online**). The group link opens the Admin PWA while `QAMMARIS_ORDER_APP_TASK_LINKS=false`. Set it back to `false` if the App order page is down.
- **Webhooks queue** and retry at 0, 1 min, 5 min, 15 min, 1 h and 6 h, each time with a fresh timestamp and signature. After 24 h an event is marked failed and shown in **Integrasi App**.
- **Recovery:**
  1. Once the App is back, press **Kirim ulang** for each failed event, or let the App resync from `GET /orders` (the webhook is only a hint).
  2. A retried App write with the same `Idempotency-Key` within 7 days returns the stored answer and does not apply twice.
- **Stuck claim:** a Super Admin releases it on the order page, or the App owner releases it with a reason.
- **Kill switch:** `QAMMARIS_ORDER_API_ENABLED=false` makes the API return 503 at once, and `QAMMARIS_ORDER_WEBHOOK_ENABLED=false` stops deliveries. Both are non-destructive: no data changes.

## 6. Database checks before staging

SQLite does not show production locking. Run on MariaDB/MySQL, ideally the production engine and version (MariaDB 11.8 recorded in [audit-release](../verification/audit-release/README.md)), using an empty, isolated database:

```
DB_CONNECTION=mysql DB_DATABASE=<scratch> php artisan test tests/Feature/OrderMigrationsMariaDbTest.php tests/Feature/OrderApiConcurrencyTest.php
```

These tests run `migrate:fresh` on the configured database. Never point them at a database holding real data.

## 7. Preflight (read-only)

```
php artisan qammaris:order-api:preflight          # table
php artisan qammaris:order-api:preflight --json   # for logs/evidence
```

It reports PASS/WARN/FAIL and exits 1 on any FAIL. It checks:
- `APP_ENV` is not production;
- the four switches, with task links required to be off;
- each secret is set, at least 32 characters, and different per direction;
- the webhook target and the catalog sync never point at the production App;
- `MAIL_MAILER` is log or array;
- no due webhook is left untried for more than 3 minutes (a scheduler or worker is running);
- no migration is pending;
- no V2 order has a Website-opened issue, and every V2 order serialises under r4.1;
- the six fixtures pass the App's preflight.

Secrets are never printed. The output shows only an 8-character one-way fingerprint for `APP_KEY` and each secret. Compare these with production's to prove nothing is shared.

Every response outside production carries `X-Robots-Tag: noindex, nofollow`, in addition to staging Basic Auth.

## 8. Hosted staging — plan (Owner actions)

> **Superseded (Owner, 2026-10-09):** E2E does not run on the old Hostinger staging. Use the isolated staging in [STAGING_ISOLATED_PLAN](STAGING_ISOLATED_PLAN.md). The table below remains as the record of why the old staging was rejected.

The existing `staging.qammarisparfum.id` (see [Hostinger runbook](HOSTINGER_GITHUB_DEPLOYMENT.md)) is a separate site with its own database and `.env`, behind Basic Auth. Before it can serve ORD-02 E2E tests:

| Requirement | Current state | Needed |
|---|---|---|
| Separate from production | Same Hostinger account and OS user as production. Account-wide cron page. The deploy SSH key reaches both sites. | **Owner decides** whether that counts as separate. If not, use a separate hosting account or VPS. |
| Synthetic data only | Its database holds the real catalog (446 products, App catalog snapshot) | Owner creates a **new empty database and user** in hPanel for ORD-02 staging. The old staging database stays untouched. |
| Own secrets | `.env` from 2026-09/10. The App feed credential may be the same pair as production (P1-04 note). | New `.env` on the server, `0600`. New `APP_KEY`. New Order API secret per direction. `QAMMARIS_APP_API_KEY` empty, so no catalog sync. `MAIL_MAILER=log`. `SESSION_*` and cache on the new database. |
| ORD-02 code | hPanel Git branch is not ORD-02. Auto-deploy is off. | Owner selects `modernization/ord-02-online-order-redesign` in hPanel and deploys. Then run the **Staging release** workflow with the same full SHA: assets, additive migrations, `optimize`. |
| No indexing, restricted access | Basic Auth (`401` without credentials). Robots also `401`. | Keep Basic Auth and recheck after each deploy (the workflow re-installs it). The app also sends noindex. |
| No real messages or transactions | WhatsApp is only `wa.me` links. No payment, Majoo or courier API exists in the code. The only outbound calls are the order webhook, the App catalog client, the import image downloader and the R2 rehearsal. | `MAIL_MAILER=log`; catalog sync off; no Shopee or product imports and no R2 rehearsal on this staging. |
| Webhook worker | Only the existing staging catalog cron entries | Owner adds **one** cron entry for this staging path only: `php artisan schedule:run` every minute. The scheduler handles webhook delivery and pruning. With the database queue, also add `queue:work --stop-when-empty` every minute. Never touch the production entries. |
| Synthetic Super Admin | None (audit-release found no staging admin) | Create one with a synthetic username, with the password set directly on the server. |
| Config check and rollback | — | Run `qammaris:order-api:preflight` after each deploy. Rollback means turning the flags off, redeploying the previous ref (`staging/known-good-*`), and leaving the new database in place. Nothing is deleted. |

Then:
1. Run fixtures.
2. Run the preflight.
3. Point `QAMMARIS_ORDER_WEBHOOK_URL` at the App tunnel for each session.
4. Have the App agent run its harness.

## 9. Local isolated integration instance (first r4.1 smoke test)

Used when hosted staging is not yet available. Both agents work on the same machine. Nothing leaves it, and the instance is not reachable from the internet.

- Database: a separate MariaDB **11.8.9** process, the production version, from the official Windows zip with a verified SHA-256. It binds to 127.0.0.1 only and uses its own datadir with a random root password. Its scratch database contains only synthetic data.
- Instance:
  - `APP_ENV=staging` with a fresh `APP_KEY`;
  - database sessions, cache and queue;
  - a real `queue:work` and `schedule:work`;
  - catalog sync off and mail set to log;
  - task links off.
- Secrets: random per direction, kept in a local file outside both repositories and readable only by the current Windows user. The App agent is given the file path, never the values.
- Before handing over: preflight OK, plus a signed external HTTP smoke test (list and six details validated against OpenAPI; bad signature 401; unknown order 404; noindex header).
- Afterwards: stop the processes. Delete the scratch database and datadir, which contain only synthetic data.
