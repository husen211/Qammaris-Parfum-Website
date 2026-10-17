# Release ORD-08 + ORD-09 (+ App Daftar Tunggu request) — Owner runbook

Status: prepared 2026-10-17 after the pre-production audit. **Nothing deployed.** Every step below needs the Owner's explicit go.

| Repo | Branch | Contains |
|---|---|---|
| Website | `modernization/ord-08-website-courier-cost` (pushed) | ORD-08 courier cost + "Uang siapa" from App employees, Owner GoPay choice, no "Kurir tiba", rupiah separators, Super Admin without packing, no false "Pesanan berubah"; ORD-09 admin push; Order API r4.5 draft; `symfony/http-foundation` security patch |
| App | `feat/ord-08-website-courier-cost` (local, not pushed) | import of website costs → auto-approved reimbursement, App order page view-only, employees endpoint, rename propagation, Daftar Tunggu product requests (ADR 0016) |

## 1. Before (Owner)

1. **Backups.**
   - Website: take a fresh production DB dump the usual way ([RELEASE_ORD_PWA_RC §3.1](RELEASE_ORD_PWA_RC.md)), with checksum, kept off-server.
   - App: take a MongoDB backup.
2. **App accounts.** Andi and Ikrar exist as active `EMPLOYEE` accounts with the right names. Note the App ID of the Owner account that will approve: the first active `OWNER`.
3. **Website `.env`** (names only; values never in chat or Git):
   - `QAMMARIS_APP_OWNER_IDS` contains that Owner App ID;
   - `QAMMARIS_ORDER_APP_TASK_LINKS=false`;
   - `ADMIN_PWA_ENABLED=true`; the queue worker is running;
   - ORD-09 keys:
     - on the server, from the **current** release, run `php artisan admin-push:vapid`. If the command does not exist yet, run it after step 3;
     - enter `ADMIN_PUSH_VAPID_PUBLIC_KEY`, `ADMIN_PUSH_VAPID_PRIVATE_KEY` and `ADMIN_PUSH_VAPID_SUBJECT`. Empty keys = push off; nothing breaks.

## 2. App first (safe with the old Website)

Do steps 2 and 3 in **one session**. Once the new App is live, its order page is view-only, and the old Website has no courier-cost form yet. So the Website must follow the same day.

The new App only reads `origin=website` costs. The old Website never sends them, so the App can go live first.

1. Push `feat/ord-08-website-courier-cost`, merge to `main`, deploy the App the usual way. There is no new App env and no Mongo migration.
2. Check:
   - existing reimbursements and the order list load;
   - `GET /api/integrations/website/orders/employees` without a signature → 401.

## 3. Website: migrations first, then merge (deploy refuses pending migrations)

`tools/hostinger/deploy-production-release.sh` refuses a release with pending migrations. Use the same controlled procedure as [RELEASE_ORD_PWA_RC §5a](RELEASE_ORD_PWA_RC.md), with this release's package:

1. Build the package with `workflow_dispatch` on the branch (build only). Upload it to a private directory on the server, verify its `sha256`, and link `.env`. Use a private `storage/`.
2. `php artisan migrate:status` must show **exactly these 3 Pending** and everything else Ran:
   - `2026_10_17_100001_add_website_origin_to_online_order_costs`
   - `2026_10_17_100002_add_advanced_by_app_user_id_to_online_order_costs`
   - `2026_10_17_200001_create_admin_push_subscriptions_table`

   All three are additive: new columns are defaulted or nullable, plus one new table. The live release keeps working.
3. Run `php artisan migrate --force`, then `migrate:status` must show 0 pending. Check `/up` and the home page on the live release, then remove the private directory.
4. Merge the PR to `main`. CI deploys: config/route/view cache, health check (automatic rollback if it fails), queue restart.

## 4. After (smoke, about 10 minutes)

1. Admin login as Super Admin and as Staff Order: Pesanan Online loads.
2. Staff Order, one internal order:
   - paid → packing → "Kurir sudah dipesan" with ongkir 15.000 + an employee → "Sudah diserahkan ke kurir" right away (no "Pesanan berubah");
   - the App shows that employee's reimbursement as **Disetujui**; the Website shows "reimburse disetujui".
3. Push:
   - on the store laptop: Notifikasi pesanan → Aktifkan → Kirim tes arrives;
   - one website checkout from a phone → "Pesanan baru dari website" arrives.
4. App Admin Toko: the order page is view-only with "Buka di Website". Daftar Tunggu: a product request can be saved.

## 5. Rollback

- **Website code:** redeploy the previous revision. The three additive migrations can stay; old code ignores them. Never `migrate:rollback` on production once costs exist.
- **Push only:** empty the `ADMIN_PUSH_VAPID_*` keys, then `config:cache`. Push turns off; nothing else changes.
- **App:** redeploy the previous App release.
  - Website costs already imported stay as App expenses and approved reimbursements. Pay or reject them in the App as usual.
  - To let staff work in the App again: rebuild the App with `VITE_ONLINE_ORDERS_WORK_IN_APP=1`.
- **Integration emergency:** as [RELEASE_ORD_PWA_RC §6.2](RELEASE_ORD_PWA_RC.md) (API/webhook flags off; staff keep working on the Website).
