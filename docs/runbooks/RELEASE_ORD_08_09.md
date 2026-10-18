# Release ORD-08 + ORD-09 (+ App Daftar Tunggu request) — Owner runbook

Status: prepared and re-verified 2026-10-17; Owner approved the release on 2026-10-17 (push, PR, merge, deploy after the safety gates). The release also carries the inode fix (OPS-INODE-2026-10: post-deploy prune, [runbook](HOSTINGER_INODE_CLEANUP.md)). The agent can do the Git/GitHub steps (push, PR, merge, revert) when the Owner says so. Server steps marked **(server)** need the Owner's SSH or hPanel.

| Repo | Branch | Contains |
|---|---|---|
| Website | `modernization/ord-08-website-courier-cost` | ORD-08 courier cost + "Uang siapa" from App employees, Owner GoPay choice, no "Kurir tiba", rupiah separators, Super Admin without packing, no false "Pesanan berubah"; ORD-09 admin push; Order API r4.5 draft; `symfony/http-foundation` security patch; `qammaris:release-check`; release pruning after each deploy |
| App | `feat/ord-08-website-courier-cost` | import of website costs → auto-approved reimbursement, App order page view-only, employees endpoint (+ approver), rename propagation, Daftar Tunggu product requests (ADR 0016) |

Version compatibility, checked in code and tests:
- New App + old Website: safe. The App only acts on `origin=website` costs; the old Website never sends any.
- Old App + new Website: safe for data. The old App ignores the new cost fields. Staff advances are then not reimbursed automatically, which is why the App goes first.
- The only gap: between the App deploy and the Website deploy, the App order page is view-only and the old Website has no courier-cost form. So both go out in **one session**.

## 1. Before (Owner)

1. **Backups (server).**
   - Website: fresh production DB dump with checksum, as in [RELEASE_ORD_PWA_RC §3.1](RELEASE_ORD_PWA_RC.md), stored off-server. Orders exist since 2026-10-11, so the 2026-10-11 backup is not enough. Stream it to the laptop (`ssh … 'mariadb-dump … | gzip' > file`) so nothing large is written on the hosting.
   - App: MongoDB backup the usual way.
2. **App accounts.**
   - Andi and Ikrar are active `EMPLOYEE` accounts with their real names.
   - The release check (step 4) confirms both, plus the approving Owner.
3. **Website `.env` (server, names only).** No values in chat or Git:
   - `QAMMARIS_ORDER_APP_TASK_LINKS=false`: the staff group link opens the Website order page;
   - `QAMMARIS_APP_OWNER_IDS` contains the App ID of the **first active Owner** in the App. The release check shows that ID if it is missing;
   - `ADMIN_PWA_ENABLED=true`, plus the queue worker running (already part of production).

## 2. App first

1. Agent, on the Owner's go: push the branch, open the PR, merge to `main`. Hostinger auto-deploys. There is no new env and no index change.
2. Check: the reimbursement list and the order list load.

## 3. Website: migrations first, then merge

The deploy script refuses pending migrations, so use the proven procedure ([RELEASE_ORD_PWA_RC §5a](RELEASE_ORD_PWA_RC.md)):

1. Agent: push the branch and open the PR. Run the "Production release" workflow with `workflow_dispatch` on the branch: build only, no deploy. The artifact is `production-<sha>`.
2. **(server)** Unpack the package in a private directory, verify `sha256`, link `.env`, use a private `storage/`, then run `php artisan migrate:status`. It must show **exactly these 3 Pending**:
   - `2026_10_17_100001_add_website_origin_to_online_order_costs`
   - `2026_10_17_100002_add_advanced_by_app_user_id_to_online_order_costs`
   - `2026_10_17_200001_create_admin_push_subscriptions_table`

   All three are additive. Existing App costs become `origin=app`; this was verified on MariaDB 11.8.9 with existing rows. The live release keeps working.
3. **(server)** `php artisan migrate --force`, then `migrate:status` shows 0 pending. Check that `/up` and the home page respond. Remove the private directory.
4. Agent, on the Owner's go: merge the PR. CI deploys: caches, health check with automatic rollback if it fails, and a worker restart.

## 4. Turn on push and check (server, about 2 minutes)

From the live release (`$root/current`):

```
php artisan admin-push:vapid      # prints 2 lines; nothing stored
```

1. Put the 2 printed lines plus `ADMIN_PUSH_VAPID_SUBJECT=mailto:<owner email>` in the shared `.env`. Keep the private key secret.
2. Then:

```
php artisan config:cache
php artisan queue:restart         # the worker reloads the push config
php artisan qammaris:release-check
```

`qammaris:release-check` is read-only and prints one table:
- migrations;
- App integration on;
- staff link opens the Website;
- the App's active employees by name;
- the approving Owner on the allowlist;
- push keys valid;
- admin PWA on;
- queue.

Every line must be PASS. A WARN or FAIL names the exact setting to fix.

Then check the post-deploy prune (first run on the server): the deploy log has no `Warning: old releases were not pruned`; `ls -A $root/releases` shows the new live release, the previous live release (`535d5f3`) and one more, and no `.incoming-*` / `.deploy-*`; `shared/` is untouched; `inode-report.sh` stays well below 80%.

## 5. Smoke (about 10 minutes)

1. Admin login as Super Admin and as Staff Order. Pesanan Online loads.
2. Staff Order, one internal order:
   - paid (success message shows) → packing → "Kurir sudah dipesan" with ongkir 15.000 + an employee → "Sudah diserahkan ke kurir" right away;
   - the App shows that employee's reimbursement as **Disetujui**; the Website shows "reimburse disetujui".
3. Push on the store laptop: Notifikasi pesanan → Aktifkan → **Kirim tes** arrives. Then one website checkout from a phone shows "Pesanan baru dari website".
4. App Admin Toko: the order page is view-only with "Buka di Website". Daftar Tunggu: a product request saves.

## 6. Rollback

- **Website code:** agent reverts the merge commit on `main`, and CI deploys the previous code. The 3 migrations stay; the old code ignores the new columns and table. Never run `migrate:rollback` on production once costs exist.
- **Push only:** empty the three `ADMIN_PUSH_VAPID_*` values, then `config:cache` and `queue:restart`. Push is off; nothing else changes.
- **App:** agent reverts the App merge commit, and Hostinger redeploys.
  - Imported website costs stay as App expenses and approved reimbursements. Pay or reject them as usual.
  - To let staff work in the App again: rebuild with `VITE_ONLINE_ORDERS_WORK_IN_APP=1`.
- **Integration emergency:** [RELEASE_ORD_PWA_RC §6.2](RELEASE_ORD_PWA_RC.md) (API/webhook flags off; staff keep working on the Website).
