# Release candidate ORD-01..04 + PWA-01 — merge audit, access, regression and security (2026-10-11)

- Branch `modernization/rc-ord-pwa-2026-10-11`: Draft PR to `main`, review only.
- Owner approved the single-RC path for preparation and review. **There is no approval to merge or deploy.**
- Release plan: [RELEASE_ORD_PWA_RC](../../runbooks/RELEASE_ORD_PWA_RC.md).
- PWA evidence: [pwa-01](../pwa-01/README.md).
- All tests and smokes used synthetic data on isolated databases. No production, UAT or dev database was used.

## 1. Integration

| | |
|---|---|
| Base | `origin/main` `c8e6530`; no new main commits since the RC merge (checked before the final push) |
| Order line | ORD-01 (#27) → ORD-02 (#32) → ORD-03 → ORD-04 → PWA-01 |
| PRs #27/#32 | Left open, to be closed as superseded **after** the RC is merged |

Files changed by both lines and reviewed by hand:

| File | Result |
|---|---|
| `routes/web.php` | main's single `['auth','admin']` group was split into RBAC areas. Journal editor/media/taxonomy/automation → `can:blog.manage`; fragrance preference → `can:catalog.manage`. No new permission was created. |
| `bootstrap/app.php` | Order API v1 and Journal automation API each keep their own error envelope; Journal API failures are still logged without payloads. |
| `app/Providers/AppServiceProvider.php` | Checkout and fragrance/blog rate limiters kept. |
| `app/Http/Controllers/StoreController.php` | main's About page reads WhatsApp from `StoreInfo::whatsapp_link`, so the ORD-07 protection (no official number in test environments) applies. Location page unchanged. |
| `resources/views/layouts/app.blade.php` | Public PWA head + main's canonical URL. |
| `resources/views/components/navbar.blade.php` | main's "Journal" label + ORD "Pesanan Saya" link. |
| `resources/views/products/show.blade.php` | main's icon markup + ORD availability detail. |
| CSS, Vite inputs, `.env.example` | Both sides kept. |

## 2. Access and regression audit

Searching main's code for hard-coded `role === 'admin'` found three places that the order line's RBAC would break. The RBAC adds `super_admin` and `staff_order` next to the legacy `admin` role.

| # | Location (from main) | Problem after merge | Fix in RC | Test |
|---|---|---|---|---|
| 1 | Blog store/update/revision requests, `BlogWriteAccess`, `ChangeBlogPostArchive` | Super Admin refused (403) when writing the Journal | `blog.manage` ability (Super Admin + legacy admin) | `ReleaseCandidateAccessTest::…write_the_journal…` fails with the old check |
| 2 | `FragranceProfileStore::authorize` | Super Admin refused when applying/reviewing fragrance profiles | `catalog.manage` on the fresh user row; deactivated accounts refused | `FragranceProfileStoreTest::test_order_rbac_roles…` fails with the old check |
| 3 | main tests expecting `/login` for guests on `/admin/*` | ORD-02b sends admin guests to `/admin/login` (Admin PWA scope) | Tests updated | — |

Access matrix (`ReleaseCandidateAccessTest`, GET pages):

| Area | Guest | Customer | Staff Order | Legacy admin | Super Admin |
|---|---|---|---|---|---|
| Journal: list, create, edit, media, automation, taxonomy | → `/admin/login` | 403 | 403 | 200 | 200 |
| Catalog: fragrance preference, products, app products | → `/admin/login` | 403 | 403 | 200 | 200 |
| Orders | → `/admin/login` | 403 | 200 | 200 | 200 |
| Owner only: users, App integration, payment accounts | → `/admin/login` | 403 | 403 | 403 | 200 |

A deactivated Super Admin is signed out to `/admin/login`. `AdminRbacTest` also checks that every `admin.*` route carries `admin` + its area ability.

Checkout and orders on the merged code:
- Local RC smoke (33 checks passed): checkout → staff confirm → unpaid refusal → payment → claim/pack/courier/handover/delivery over the signed API → ongkir with staff advance → signed minimal webhooks. Super Admin also opens dashboard, Journal, taxonomy, fragrance preference, products and users. See [pwa-01 §5](../pwa-01/README.md).
- New `WebsiteCheckoutOrderTest::test_emergency_stop_of_new_checkouts_keeps_existing_website_orders_working`: with `ORDERS_WEBSITE_CHECKOUT=false`, an existing website order still opens by customer link, in Pesanan Saya and in admin, and Staff Order can still confirm it. Nothing is deleted.

## 3. Test results (commit `37d41d8` + docs)

| Suite | Result |
|---|---|
| PHPUnit SQLite, without `public/build` (as CI) | 745 passed, 7 skipped (MariaDB-only) |
| PHPUnit MariaDB 11.8.9 (production version) | **745/745** |
| JS (`node --test`) | 59/59 |
| GitHub CI | see the PR checks on the final commit |

MariaDB baseline. The two failures that also occurred on `main` are fixed, and both were test-only:

- `BlogAutomationTest::test_failed_idempotency_completion…` used a SQLite trigger (`RAISE(ABORT)`), which is invalid on MariaDB, and DDL commits the test transaction there. The same failure (the completion `UPDATE` throws) is now injected with `DB::beforeExecuting`. The rollback assertions (no media row, no audit row, no idempotency row, no files) run on both drivers.
- `StoreAboutTest::test_stored_address_is_escaped…`: `AppServiceProvider` caches `store_info` at boot. On a persistent test database the table exists at boot, so an empty `StoreInfo` was cached before the test inserted its row, and the page showed the fallback address. The escaping itself was never wrong (the stored address is escaped in markup and JSON-LD once read). The test now forgets the cache after inserting.
- **Functional impact: none.** This was proven by reproducing both failures on a clean `origin/main` and tracing their cause. The `store_info` boot cache (1 hour) is existing production behaviour on main and is unchanged.

## 4. ADR numbering

main released ADR-037–040 (fragrance preference). The order decisions that used the same numbers are now:

| Was (order branches) | Now | Topic |
|---|---|---|
| ADR-034, then ADR-037 | **ADR-048** | Online order links, tracking, staff task links |
| ADR-035, then ADR-038 | **ADR-049** | Admin roles, abilities, user management |
| ADR-039 | **ADR-050** | Qammaris Admin PWA |
| ADR-040 | **ADR-051** | Order state cutover and payment/refund ledger |

- Decision texts are unchanged (new heading + an alias note).
- Every old filename stays as an alias page pointing to the canonical file.
- Current docs and code comments use the new IDs; dated evidence keeps its wording and its links resolve through the aliases.
- The index lists ADR-032–051 once each, and a link check found 0 broken relative links.

## 5. Security audit — public PWA and Admin PWA on one origin

| Topic | Finding | Status |
|---|---|---|
| Two installs | Public: id/scope `/`, "Qammaris Perfumes". Admin: id/scope `/admin`, "Qammaris Admin". Distinct ids, names, icons and start URLs (tested). Chrome lets the most specific scope control `/admin` (verified). | OK |
| Android link handling with both apps installed | The public WebAPK scope `/` includes `/admin`. Which app opens an `/admin` link is decided by Android. | **Not tested on a device**: test before `PUBLIC_PWA_ENABLED=true` |
| Session | One Laravel session cookie for the origin: path `/`, HttpOnly, SameSite=Lax, `secure` from `SESSION_SECURE_COOKIE` (must be `true` in production). Both apps share it, as two browser tabs do. | Production must set `SESSION_SECURE_COOKIE=true` |
| Shared phone | Admin logout invalidates the whole session (`invalidate` + new CSRF token), so a cart in the same browser is emptied too. Pesanan Saya uses its own `qam_orders` cookie and stays. Same behaviour as before PWA-01. | Known, accepted (ADR-050) |
| Admin pages | `Cache-Control: no-store, private`; Admin worker caches only build assets, admin icons and the static offline page; navigations network-only; logout deletes only `qammaris-admin-*`. | OK (tests) |
| Public pages | Public worker caches only build assets, public icons and `/offline`. It never caches HTML, cart, checkout, order links, Pesanan Saya or JSON, and never intercepts `/admin`, login, staff links, API, integrations or non-GET requests. | OK (tests + Chrome Cache Storage check) |
| Cross-clearing | Neither app deletes the other's caches or registration. No `Clear-Site-Data` anywhere. | OK (tests + one-session flip) |
| Kill switches | `PUBLIC_PWA_ENABLED=false` ships a worker that removes only `/` and `qammaris-public-*`. `ADMIN_PWA_ENABLED=false` removes only `/admin` and `qammaris-admin-*`. Cookies and the admin session stay (verified in Chrome). | OK |
| PWA resources | Manifests, workers and offline pages are served outside the `web` group: no session, no `Set-Cookie`. Workers have `no-cache` + `nosniff`. | OK (tests) |
| Notifications | No push, sync, VAPID or permission prompt in either app. | OK |
| Offline ordering | The checkout refuses to submit offline. Admin forms are blocked offline by `guardSubmit`. Neither app resends anything. | OK |

## 6. App coordination (2026-10-11, agent messages)

The App release build is branch `release/app-r4.4-rc1` @ `f020bc0`, Draft PR #1 (review only).
- Local verification: backend 1173/1173 and frontend 1331/1331. The flag-off production build returns 404 for every order endpoint and the webhook.
- OpenAPI is byte-identical (sha `10dcfa50…c5b5`).
- The App repository has no CI.

Agreed:
- Production Order API URL for the App: `https://qammarisparfum.id/integrations/qammaris-app/orders/v1` (the Owner confirms the canonical host).
- Webhook target: `https://api.qammarisapp.com/api/integrations/website/orders/events`.
- Task links: `https://qammarisapp.com/orders`.
- Fresh production credentials per direction, generated on the server, given to the Owner offline and entered in both panels; UAT values are not reused.
- Owner id from `/api/auth/me` in the production App.

The App adds two steps before joint activation:
1. A dark launch with the integration off, at least one working day earlier.
2. `ONLINE_ORDERS_ENABLED=true` plus a frontend rebuild with `VITE_ONLINE_ORDERS=1` at activation.

Both are in the release plan.
