# Release plan — ORD-01..04 + PWA-01 (release candidate 2026-10-11)

**Status: preparation and review only.**
- The Owner approved the single-RC-PR path on 2026-10-11.
- **Not approved:** merging into `main`, any production deploy, production database access, or production configuration changes. Each needs its own explicit Owner approval.

Evidence:
- [RC merge audit, access, regression and security](../verification/rc-ord-pwa/README.md)
- [PWA-01](../verification/pwa-01/README.md)

## 1. Release candidate

| | |
|---|---|
| Branch / PR | `modernization/rc-ord-pwa-2026-10-11` → `main`, one Draft PR |
| Replaces | Separate merges of #27 (ORD-01 → main, conflicting) and #32 (ORD-02 → ORD-01, stacked). Both stay open until the RC is merged, then close as superseded. |
| Contains | `main` as of `c8e6530` + ORD-01, ORD-02a–e, ORD-03, ORD-04, PWA-01 + RC audit fixes |
| Contract | Order API OpenAPI rc.4.4, sha `10dcfa5051460fef87d5ab8666df97a06af79f4e43cbd434f093dc79ad51c5b5`, signed by both agents; byte-identical in App release PR #1 (`release/app-r4.4-rc1` @ `f020bc0`) |
| Migrations | 15 additive (ORD-01…ORD-04); PWA-01 has none |

**If `main` moves before merge:**
1. Merge the new `origin/main` into the RC branch. Never rebase: the PR history stays reviewable.
2. Re-run the audit commands in [the RC report §2](../verification/rc-ord-pwa/README.md):
   - grep main's new code for `role === 'admin'` or other hard-coded role checks;
   - put new `admin.*` routes into their area ability (`AdminRbacTest` fails otherwise).
3. Run SQLite (CI), MariaDB 11.8 and JS tests, and wait for green CI on the PR head.

## 2. Gates

| Gate | Status |
|---|---|
| Owner approval of the RC (merge) | **OPEN** |
| CI green on the final PR commit | See the PR checks; must be re-checked after any `main` merge |
| MariaDB 11.8.9 full suite | PASS 745/745 (RC) |
| Access/regression audit after merging main | PASS: 2 Super Admin regressions found and fixed, access matrix test |
| ADR numbering | DONE: order ADRs 048–051, aliases kept |
| Production backup + isolated restore + migration rehearsal on the copy | **OPEN**: needs separate Owner permission (§3) |
| Production configuration entered and checked on the server | **OPEN**: Owner (§4) |
| App production r4.4 | App dark launch ready after Owner approval + App backup; **activation conditions in §5** |
| Real devices (Android/iPhone install, Android link handling between the two installed apps) | **OPEN**: needed only before step 9 (public PWA) |

## 3. Production backup → isolated restore → migration rehearsal

Run only after the Owner separately allows taking a production backup. The procedure only **reads** production. It never runs a migration, a write or a schema change against the production database.

1. **Backup (Owner or an operator the Owner names, on the production host):**
   - Database:

     ```
     mariadb-dump --single-transaction --routines --triggers --hex-blob <db> > qam-prod-<date>.sql
     ```

   - Files: an archive of `storage/app` (product media; private J&T QR folder, empty before the release) and a copy of `.env`. Keep `.env` encrypted and separate. Never commit it or paste it in chat.
   - Record `sha256sum` of each file. Download them off the server and keep the server copy until the release window closes.
2. **Isolated restore** (a local MariaDB **11.8**, or a scratch database on a separate host; never the production server's database):
   - `CREATE DATABASE qam_prod_copy …; mariadb qam_prod_copy < qam-prod-<date>.sql`
   - Take the snapshot `S0`:
     - row counts: `products`, `product_variants`, `product_images`, `users`, `blog_posts`, `blog_media`, `fragrance_profiles`, `migrations`;
     - checksums: `MD5(GROUP_CONCAT(id,slug,base_price,publication_status ORDER BY id))` for products and `MD5(GROUP_CONCAT(password ORDER BY id))` for users. Hashes only; never print the values.
   - The copy holds customer and account data. Keep it on an encrypted disk, give nobody else access, and drop it after the release window.
3. **Rehearsal on the copy** with the RC code (`.env` pointing at the copy; `APP_ENV=staging`; every integration flag off; mail `log`):
   - `php artisan migrate:status`: expect exactly the 15 RC migrations pending and nothing else.
   - `php artisan migrate --force`: record the duration. Synthetic rehearsal: about 1 s.
   - Snapshot `S1` must equal `S0`.
   - `php artisan migrate:rollback --force` (this batch only, and only on the copy). Snapshot `S2` = `S0`.
   - `php artisan migrate --force` again; snapshot `S3` = `S0`.
   - Smoke on the copy: public catalog, product, Journal, quiz, admin login per role, product edit, blog edit (Super Admin), Pesanan Online empty list.
4. Record the result (durations, `S0`–`S3`, no values) in `docs/verification/rc-ord-pwa/`. **A difference blocks the release.**

Synthetic rehearsal already done (MariaDB 11.8.9): S0 = S1 = S2 = S3 = restore. See [pwa-01 §5](../verification/pwa-01/README.md).

## 4. Production configuration (names only — values are entered by the Owner on the server)

No secret is ever written in Git, chat, logs or command arguments.

Generate secrets on the server, show them to the Owner offline, and enter them in the panel. For example: `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`.

### 4.1 Website `.env`

| Variable | Deploy (dark) | Final | Notes |
|---|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | same | Production keeps the store WhatsApp number |
| `APP_URL` | `https://qammarisparfum.id` (Owner confirms the canonical host) | same | PWA install needs HTTPS |
| `SESSION_SECURE_COOKIE` | `true` | same | Session shared by the two PWAs |
| `SESSION_LIFETIME` / `ADMIN_IDLE_MINUTES` | `720` / `720` | same | Admin idle 12 h (ADR-049) |
| `QUEUE_CONNECTION` | `database` (confirm) | same | Webhook jobs go to queue `qammaris-app` (production worker). With `sync`, delivery would run inside the request (up to 10 s). |
| `ADMIN_PWA_ENABLED` | `false` (code default since RC `de303af`+1; set explicitly anyway) | `true` | Step 4 |
| `ORDERS_V2_ENABLED` | `false` | `true` | Step 5 |
| `ORDERS_SIMPLE_UX` | `false` | `true` | Step 5 (ORD-03 staff UX) |
| `ORDERS_WEBSITE_CHECKOUT` | `false` | `true` | Step 8; **also the emergency checkout stop** |
| `PUBLIC_PWA_ENABLED` | `false` | `true` | Step 9 |
| `QAMMARIS_ORDER_API_ENABLED` | `false` | `true` | Step 7 |
| `QAMMARIS_ORDER_API_CLIENT_ID` | empty | **secret** (App → Website) | = App `WEBSITE_ORDER_CLIENT_ID` |
| `QAMMARIS_ORDER_API_SECRET` / `_PREVIOUS` | empty | **secret** / empty | = App `WEBSITE_ORDER_API_SECRET` |
| `QAMMARIS_ORDER_WEBHOOK_ENABLED` | `false` | `true` | Step 7 |
| `QAMMARIS_ORDER_WEBHOOK_URL` | default | `https://api.qammarisapp.com/api/integrations/website/orders/events` | |
| `QAMMARIS_ORDER_WEBHOOK_CLIENT_ID` | default | `qammaris-website` | = App `WEBSITE_ORDER_WEBHOOK_CLIENT_ID` |
| `QAMMARIS_ORDER_WEBHOOK_SECRET` / `_PREVIOUS` | empty | **secret** / empty | = App `WEBSITE_ORDER_WEBHOOK_SECRET` |
| `QAMMARIS_APP_OWNER_IDS` | empty | Owner `id` from `https://api.qammarisapp.com/api/auth/me` (production App) | Without it, waiver decisions are refused (403) |
| `QAMMARIS_ORDER_APP_TASK_LINKS` | `false` | `true` after the App is live | Staff WhatsApp link opens the App order |
| `QAMMARIS_APP_ORDERS_URL` | `https://qammarisapp.com/orders` | same | Refused if it points to another environment |
| `QAMMARIS_ORDER_API_WEBSITE_ISSUES` | `false` | `false` | Issues come from the App |
| `STORE_WHATSAPP_TEST_NUMBER` | **empty** | **empty** | Test environments only |
| `BLOG_AUTOMATION_ENABLED` | unchanged (as on main) | unchanged | Not part of this release |

### 4.2 Server and runtime

- The cron `* * * * * php artisan schedule:run` must be active. It runs the webhook retry sweep every minute and `qammaris:orders:prune-api` at 03:30, next to the existing App catalog sync.
- Worker `tools/hostinger/production-qammaris-worker-watchdog.sh`: `queue:work database --queue=qammaris-app,product-import-images`. It already consumes the webhook queue, so no change is needed.
- After every `.env` change: `php artisan config:cache` (or `optimize`), then restart the worker (`php artisan queue:restart`).
- Data the Super Admin enters in the admin before step 8: at least one store payment account, added and **verified**.

### 4.3 App (for reference; the App agent and Owner own these)

- `WEBSITE_ORDER_API_URL=https://qammarisparfum.id/integrations/qammaris-app/orders/v1`
- `WEBSITE_ORDER_CLIENT_ID`, `WEBSITE_ORDER_API_SECRET`, `WEBSITE_ORDER_WEBHOOK_SECRET`, `WEBSITE_ORDER_WEBHOOK_CLIENT_ID`
- `ONLINE_ORDERS_ENABLED`
- Build-time `VITE_ONLINE_ORDERS=1`
- UAT and staging values are never reused.

## 5. Deployment sequence (every new feature off at first deploy)

Each step needs its own Owner go-ahead. Run the check before moving on.

| # | Action | Check | Rollback |
|---|---|---|---|
| 0 | Owner approves the RC. Merge the PR (merge commit, no squash). Production backup per §3.1 and its rehearsal per §3.3. | CI green on `main` | Revert the merge commit |
| 1 | **Website dark deploy:** code + `migrate --force` (15 additive) with every flag in §4.1 at its "Deploy" value | `migrate:status` clean. Public pages, Journal, quiz and About work. Admin login per role. Product edit and blog edit as Super Admin. Pesanan Online list empty. `/manifest.webmanifest` 404. `qammaris:order-api:preflight --json` (read-only; it refuses `APP_ENV=production` for integration testing, so read its config lines only) | Redeploy the previous revision; additive tables stay unused (§6) |
| 2 | **App dark launch** (App side): deploy with integration off after the App backup | App: order endpoints + webhook 404, 0 requests to the Website; existing modules OK. Wait **≥ 1 working day**. | App previous release |
| 3 | Super Admin accounts per [ADMIN_ACCESS](ADMIN_ACCESS.md); Staff Order accounts | Logins, role areas (see the access matrix) | Deactivate accounts |
| 4 | `ADMIN_PWA_ENABLED=true` | Install Qammaris Admin on the store phone; logout clears only admin caches | Flag off (self-removing worker) |
| 5 | `ORDERS_V2_ENABLED=true`, `ORDERS_SIMPLE_UX=true`; payment account added + verified | One internal admin-created order, paid → packed → handed over in the Admin PWA | Flags off; created orders keep their model |
| 6 | Production credentials (§4.1), `QAMMARIS_APP_OWNER_IDS` | Values present (preflight shows fingerprints only) | Clear values |
| 7 | **Joint activation, only when the App production on r4.4 is live** (App: `ONLINE_ORDERS_ENABLED=true` + rebuild `VITE_ONLINE_ORDERS=1`). Website: `QAMMARIS_ORDER_API_ENABLED=true`, `QAMMARIS_ORDER_WEBHOOK_ENABLED=true`, then `QAMMARIS_ORDER_APP_TASK_LINKS=true` | Read-only connection: signed sync without errors and 1 real webhook 2xx (Admin → Integrasi App: delivered, waiting 0, failed 0). Smoke one internal order: paid → packing → courier → handover with personal-money ongkir (1 expense + 1 reimbursement in the App; `costs`/`expense_ref` on the Website). Then `orders.handle` for staff. | §6.2 |
| 8 | `ORDERS_WEBSITE_CHECKOUT=true` | One customer checkout from a phone: order saved, WhatsApp opens once, staff confirm, App receives it | §6.3 (emergency stop) |
| 9 | `PUBLIC_PWA_ENABLED=true` after the real-device checks | [PUBLIC_PWA](PUBLIC_PWA.md) checks | Flag off |

Push notifications for new orders stay deferred (App and Website).

## 6. Rollback

### 6.1 Code and schema

- **Before any order exists:** redeploy the previous revision; `migrate:rollback --force` is allowed for this batch (rehearsed). Prefer leaving the additive tables in place.
- **After orders exist: never `migrate:rollback`.** The ORD-04 migration refuses while guest orders exist, and older batches would drop order tables. Roll forward (fix + redeploy) and switch features off with flags.
- Restoring the §3 backup erases every order created since. Only with explicit Owner approval, and after exporting those orders.

### 6.2 Order API / webhook problem

1. Website: `QAMMARIS_ORDER_API_ENABLED=false`, `QAMMARIS_ORDER_WEBHOOK_ENABLED=false`, `config:cache`.
2. App: `ONLINE_ORDERS_ENABLED=false` (endpoints 404).
3. Effect: staff keep working in the Admin PWA; the App keeps expenses and reimbursements and shows its outage state; nothing is lost.
4. After the fix: switch back on, then resend failed events in Admin → Integrasi App ("Kirim ulang"). Retries keep their event id, and the App ignores duplicates.

### 6.3 Emergency stop of new website checkouts (orders already received stay)

1. Set `ORDERS_WEBSITE_CHECKOUT=false`, then `php artisan config:cache`.
2. Effect:
   - The checkout returns to the pre-ORD-04 WhatsApp-only form, which saves nothing.
   - Every order already received keeps working: customer link, Pesanan Saya, admin list/detail, staff confirmation, payment and the App. This is covered by `WebsiteCheckoutOrderTest::test_emergency_stop_of_new_checkouts_keeps_existing_website_orders_working`.
   - Nothing is deleted or cancelled automatically.
3. Optional, Super Admin, per order: cancel unwanted drafts with a reason (they stay in the history).
4. Re-enable after the fix with the flag; no data step needed.

### 6.4 PWAs

`PUBLIC_PWA_ENABLED=false` or `ADMIN_PWA_ENABLED=false`. Each ships a self-removing worker for its own scope only. Never use `Clear-Site-Data`.

## 7. Monitoring (first 48 hours after each step, then daily for a week)

| Signal | Where | Action |
|---|---|---|
| New exceptions | `storage/logs/laravel.log` (order rejections are not logged by design) | Investigate; roll back the step's flag if user-facing |
| Webhook outbox: waiting > 0 for over 5 min, any failed | Admin → Integrasi App; `qammaris:order-api:preflight` counts | Check App health; resend; §6.2 if persistent |
| Worker and cron alive | Watchdog heartbeat; `jobs` table on queue `qammaris-app` not growing | Restart the worker per runbook |
| Draft website orders waiting for confirmation | Pesanan Online → filter | Staff confirm within business hours |
| Unpaid orders blocked from packing; open reimbursements | Pesanan Online flags | Normal workflow |
| Checkout 429s (6/min, 30/h per IP), customer reports | Customer messages | Usually benign; §6.3 if abuse |
| PWA: offline page while the site is up; install reports | Owner / customers | §6.4 |
| Owner end-to-end check | One real order per fulfilment type (pickup, Palu, J&T) | — |
