# Release plan — ORD-01..04 + PWA-01 (release candidate 2026-10-11)

Status: **plan only, NOT approved.** Nothing is merged to `main`, nothing is deployed, and production configuration is unchanged. Production deploy waits for the Owner's final approval of this release candidate. Evidence: [PWA-01 verification and RC report](../verification/pwa-01/README.md).

## 1. What the release candidate contains

| Line | Content | State before RC |
|---|---|---|
| `main` (live) | Catalog, Journal/blog automation, fragrance preference, About/FAQ, security patches | Deployed |
| ORD-01 | Online orders, customer/staff links | PR #27 → `main`, CONFLICTING |
| ORD-02a–e | RBAC, Admin PWA, V2 order model, money ledger, Order API v1 | PR #32 → `modernization/ord-01-online-orders` (stacked, draft) |
| ORD-03 | Operational UX | Inside the ORD-04 branch |
| ORD-04 | Guest checkout, payment gate, stepper, payment accounts, Pesanan Saya, paperbag (r4.4) | `modernization/ord-04-cart-checkout` |
| PWA-01 | Installable public website | `modernization/pwa-01-public-pwa` |

RC branch: `modernization/rc-ord-pwa-2026-10-11` = PWA-01 + a trial merge of current `origin/main`, with every conflict resolved and reviewed (see the verification page).

**Proposed integration (needs Owner approval):** one PR `modernization/rc-ord-pwa-2026-10-11` → `main`, reviewed as a whole. #27 and #32 are then closed as superseded, with a link to the RC PR. Merging #27 and #32 one by one is not recommended: #27 conflicts with `main` and #32 targets the ORD-01 branch, not `main`.

## 2. Release gates

| Gate | Status | Evidence / remaining action |
|---|---|---|
| Tests | PASS | RC: 742 PHPUnit on SQLite (7 MariaDB-only skips) and 59 JS, all passing. MariaDB 11.8.9: 739 pass. The 3 failures are 2 pre-existing on `main` (SQLite-only test fixtures) plus 1 random-ULID flake, which is fixed. |
| r4.4 consistency with the App | PASS (contract) / **OPEN (release)** | OpenAPI rc.4.4 sha `10dcfa50…c5b5` signed by both agents. The App production release with r4.4 must ship **together**: Website `totals.packaging_fee` is required by the App's contract tests. The App's release state needs confirmation from the App agent and Owner. |
| Migration rehearsal | PASS (synthetic) / **OPEN (production copy)** | MariaDB 11.8.9: `main` schema + synthetic data → backup → 15 pending migrations in about 1 s → rollback → re-migrate → restore. Catalog, users and password hashes are identical at every step. **Still needed:** the same rehearsal on a restored copy of the production database (Owner provides the backup; never on production itself). |
| Backup / restore | PLAN | §4. Restore was rehearsed locally (`mariadb-dump` → new database → identical snapshot). |
| Secrets / config | **OPEN** | Production values are set by the Owner on the server only (§3). `qammaris:order-api:preflight` passes on the RC config but deliberately refuses `APP_ENV=production`, so a production check is a manual list (§3). |
| Full order flow | PASS (local RC) | Checkout → confirm → unpaid refusal (409 `payment_required`) → payment → claim/pack/courier/handover/delivery over the signed API → ongkir cost with staff advance (`awaiting_proof`) → 13 signed minimal webhooks. Joint flow with the real App ran earlier on UAT (ORD-04). A joint smoke on hosted staging with the App's release build is still to do. |
| Monitoring | PLAN | §6 |
| Rollback | PLAN | §5 |
| PWA flag | READY | `PUBLIC_PWA_ENABLED` default off; enable/disable in [PUBLIC_PWA](PUBLIC_PWA.md). |
| Real devices | **OPEN** | Android Chrome and iPhone Safari install, and Android link handling between the two installed apps, are not tested on real devices. |

## 3. Production configuration (Owner, server-side only)

The values never go in Git, chat or logs.

- Keep: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`.
- `SESSION_LIFETIME=720` or more (admin 12-hour idle, ADR-049); `ADMIN_IDLE_MINUTES=720`.
- First deploy with every new feature **off**:

  ```
  ORDERS_V2_ENABLED=false
  ORDERS_WEBSITE_CHECKOUT=false
  ORDERS_SIMPLE_UX=false
  QAMMARIS_ORDER_API_ENABLED=false
  QAMMARIS_ORDER_WEBHOOK_ENABLED=false
  QAMMARIS_ORDER_APP_TASK_LINKS=false
  QAMMARIS_ORDER_API_WEBSITE_ISSUES=false
  PUBLIC_PWA_ENABLED=false
  ADMIN_PWA_ENABLED=false   # set explicitly: the code default is true
  ```

- Before the API is switched on: separate **production** credentials per direction, never the UAT/staging ones:
  - `QAMMARIS_ORDER_API_CLIENT_ID` / `QAMMARIS_ORDER_API_SECRET` (App → Website);
  - `QAMMARIS_ORDER_WEBHOOK_SECRET` (Website → App) and `QAMMARIS_ORDER_WEBHOOK_URL`;
  - `QAMMARIS_APP_OWNER_IDS` (otherwise waiver decisions are refused);
  - `QAMMARIS_APP_ORDERS_URL` (planned `https://qammarisapp.com/orders`; confirm).
- `STORE_WHATSAPP_TEST_NUMBER` stays **empty** in production. Production always uses the store number.
- At least one store payment account must be added and verified by the Super Admin before website checkout is switched on.
- The staging deploy key is never used for production.

## 4. Backup before the release

1. Download a full database dump (`mariadb-dump --single-transaction --routines --triggers`) and a copy of `storage/app` (product media, private J&T QR folder) and `.env`. Keep them off the server and record their checksums.
2. Restore the dump into a scratch database (never production) and compare row counts and checksums for `products`, `product_variants`, `users`, `blog_posts`.
3. Rehearse the RC migrations on that restored copy (§2). Record timings and the result.
4. Keep the `public/build.previous-<timestamp>` asset folder from the deploy workflow (no automatic cleanup).

## 5. Release order and rollback

| Step | Action | Rollback |
|---|---|---|
| 0 | Owner approves the RC; merge the RC PR into `main` (no squash rewriting history of #27/#32) | Revert the merge commit |
| 1 | Deploy code with all flags off (§3) and run `php artisan migrate --force` (15 additive migrations) | Redeploy the previous revision. The migrations are additive: tables and columns stay unused while flags are off. `migrate:rollback` is only safe **before any order exists**; after that, roll forward (fixes) and keep the data. |
| 2 | Smoke (public pages, Journal, quiz, admin login per role, product edit) | Step 1 rollback |
| 3 | `ADMIN_PWA_ENABLED=true`, Super Admin accounts per ADMIN_ACCESS, staff accounts | Flag off |
| 4 | `ORDERS_V2_ENABLED=true` (admin-created orders) | Flag off; existing orders keep their model |
| 5 | Order API + webhook on, **together with the App production release on r4.4**; joint smoke with one internal order | Both flags off; the App shows its outage state; the outbox keeps events for resend |
| 6 | `ORDERS_WEBSITE_CHECKOUT=true` after a verified payment account exists | Flag off; checkout returns to the WhatsApp-only form; existing orders stay |
| 7 | `PUBLIC_PWA_ENABLED=true`; check per [PUBLIC_PWA](PUBLIC_PWA.md) | Flag off (self-removing worker) |

Each step is a separate Owner go-ahead. Destructive rollback (restoring a backup) needs explicit Owner approval and must not overwrite orders created after the release.

## 6. Monitoring after each step

- `storage/logs/laravel.log`: new errors; order rejections are expected and not logged.
- Admin → Integrasi App: outbox waiting and failed counts (`qammaris:order-api:preflight` also prints them). Failed webhooks are resent from that page.
- Pesanan Online list: draft website orders waiting for confirmation; open reimbursements.
- Checkout rate limit (6/min, 30/h per IP): watch for 429s reported by customers.
- PWA: Chrome DevTools checks from [PUBLIC_PWA](PUBLIC_PWA.md); report any offline page shown while the site is up.
- First 48 hours: the Owner checks one real order end to end per fulfilment type.

## 7. Before merging to `main` (housekeeping found by the RC)

- Done in the RC: the colliding order ADRs are now ADR-048–051, with aliases at every old filename (DOC-ADR-01).
- `main`-only tests that fail on MariaDB (`BlogAutomationTest` SQLite trigger, `StoreAboutTest` address escape) are recorded in the backlog. CI runs SQLite.
