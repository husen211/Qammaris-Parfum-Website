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
