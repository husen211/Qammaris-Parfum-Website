# PWA-01 — verification and release-candidate report (2026-10-11)

- Branch `modernization/pwa-01-public-pwa`, on top of ORD-04 `50c43d6`.
- Release candidate `modernization/rc-ord-pwa-2026-10-11` = PWA-01 + a trial merge of `origin/main`.
- Synthetic data only, on an isolated SQLite instance (:8190) and an isolated MariaDB 11.8.9 (:3398). No UAT, dev or production database was used.
- Decision: [ADR-047](../../architecture/decisions/ADR-047-public-website-pwa.md). Operations: [PUBLIC_PWA](../../runbooks/PUBLIC_PWA.md), [release plan](../../runbooks/RELEASE_ORD_PWA_RC.md).

## 1. Admin PWA audit (before building)

| Item | Finding |
|---|---|
| Admin identity | id/scope `/admin`, start `/admin/orders?source=pwa`, "Qammaris Admin", worker `/admin/sw.js` (`Service-Worker-Allowed: /admin`), caches `qammaris-admin-*`. |
| Where linked | `layouts/admin.blade.php` and `auth/login.blade.php` only. Login does not extend the public layout. |
| Overlap risk | A public scope `/` contains `/admin`. A page is controlled by the most specific registration, so Admin keeps `/admin` once its worker exists. Before that, an `/admin` page is controlled by the public worker, which passes it through. |
| Install risk | Two installable apps on one origin. Android WebAPK link handling for `/admin` links with both installed is not verifiable here. Moving Admin to a subdomain needs Owner approval and was not done. |
| Storage risk | Both lines avoid `Clear-Site-Data`. Logout clears only `qammaris-admin-*`. The public kill switch clears only `qammaris-public-*`. |

## 2. Automated tests

| Suite | Result |
|---|---|
| `PublicPwaTest` (new, 9) | Manifest identity and installability fields, icon files and sizes, shortcuts public-only; Admin vs public identity differs (id, name, short name, start, scope, theme, icon bytes); worker headers, no cookies, `qammaris-public-` prefix, no push/sync/notification/`Clear-Site-Data`/storage APIs; offline page static, no CSRF token, only the precached icon; PWA routes without any middleware; public pages link only the public app and admin pages only the Admin app; kill switch (404 manifest, unregistering worker, old icon, Admin unaffected); default off; private pages stay `no-store`. |
| `tests/js/public-pwa.test.mjs` (new, 10) | Worker in `node:vm`: precache only offline page and icon; activation keeps admin and other caches; navigations network-only and never cached; admin, login, staff link, integrations, cross-origin and all non-GET requests not intercepted; build assets cache-first in the public cache only; error responses not cached; offline fallback; kill switch removes only public caches; registration and unregistration touch only scope `/`. |
| PWA-01 branch, full | SQLite **575 passed** / 7 MariaDB-only skips; MariaDB 11.8.9 **582/582**; JS **52/52**; Pint clean on changed PHP. |
| RC (with `main`), full | SQLite **742 passed** / 7 skips; JS **59/59**; MariaDB 11.8.9 **739/742**. One was a random-ULID flake in `OrderApiOutboxTest` (fixed on both branches). Two are pre-existing on `origin/main` itself on MariaDB (`BlogAutomationTest` uses a SQLite trigger; `StoreAboutTest` address escape), reproduced on a clean `origin/main` checkout. |

Local note: `php artisan test` starts a child process without `-d memory_limit`, so the RC's image tests (from `main`) hit Herd's 128 MB limit. CI's PHP has no limit. Run `vendor/bin/phpunit` with `-d memory_limit=1G` locally.

## 3. Real Chrome (headless Chrome, Windows 11, CDP)

Each phase uses a fresh profile on a short path. Chrome's Cache Storage fails under the long scratchpad path (Windows MAX_PATH), which is a harness issue.

| Phase | Check | Result |
|---|---|---|
| on | Public worker registered at `/`, manifest parsed with no errors, identity "Qammaris Perfumes"/"Qammaris"/id `/` | PASS |
| on | `Page.getInstallabilityErrors` on `/` → `[]` (**installable in Chrome on Windows**) | PASS |
| on | Admin login links only the Admin manifest; app ids differ (`/` vs `/admin`); `/admin/orders` controlled by `/admin/sw.js`, Admin installable | PASS |
| on | Both workers registered side by side: `/` → `/sw.js`, `/admin` → `/admin/sw.js` | PASS |
| on | Catalog, add to cart, cart and checkout under the public worker; checkout saved `/pesanan/{token}`; Pesanan Saya lists it | PASS |
| on | Offline (`navigator.onLine=false`): checkout not sent, message "Pesanan belum dikirim…", button stays usable | PASS |
| on | Cache Storage: only `qammaris-public-static-*` and `qammaris-admin-static-*`, holding build assets, icons and the two offline pages; no HTML page, cart, checkout, order link, JSON or admin page | PASS |
| offline (server stopped) | `/`, `/cart`, `/pesanan-saya` show the static offline page; `/admin/orders` shows the **Admin** offline page; no recipient data available | PASS |
| flip (one session) | Server restarted with `PUBLIC_PWA_ENABLED=false`: public worker and caches gone, Admin worker and caches kept, session cookies kept, Admin still signed in on `/admin/orders` | PASS |
| install | `PWA.install` is not available in headless Chrome. Clicking Install in a headed browser is a manual Owner check. | NOT RUN |

An earlier "killed" run reported Admin signed out. That came from the harness hard-killing Chrome between phases (cookies not flushed), and the one-session flip run above supersedes it.

Devices: **Android Chrome and iPhone Safari were not tested on real devices** and are not claimed as passing.

## 4. Cache and session security audit

| Area | Result |
|---|---|
| Cached by the public worker | Only `/build/assets/*` (fingerprinted, public), `/images/pwa/public-*` and `/offline` (static, no CSRF, no session). Verified in Cache Storage after a full checkout and Pesanan Saya visit. |
| Never cached | Every HTML page, cart and cart data JSON, checkout, `/pesanan/{token}`, Pesanan Saya, `/tugas-pesanan`, admin, login, API, integrations. Navigations are network-only; responses are not written. |
| Not intercepted at all | `/admin*`, `/login`, `/logout`, `/tugas-pesanan*`, `/integrations*`, `/api*`, `/sanctum*`, `/up`, cross-origin, every non-GET request. |
| Session/cookies | Manifest, worker and offline page are served outside the `web` middleware (no session, no `Set-Cookie`; tested). The worker has no cookie or storage API; the kill switch kept cookies (tested). |
| Origin-wide wipes | No `Clear-Site-Data` anywhere. The public code deletes only `qammaris-public-*` caches and only the exact `/` registration. |
| Notifications | No push, sync, notification permission, VAPID or Firebase (tested by source scan and manifest check). |
| Offline ordering | The checkout refuses to submit offline; the offline page never resubmits; checkout stays idempotent (`checkout_key`). |
| Residual risk | An `/admin` page opened from the public app **before** the Admin worker exists is briefly controlled by the public worker. It passes the page through, and only the public `admin-pwa` JS bundle can be cached (seen in the test; no private data). |

## 5. Release candidate (trial merge of `main`)

`origin/main` was 52 commits ahead (Journal automation, fragrance preference, About/FAQ, security patches). PR #27 (ORD-01 → main) is CONFLICTING and PR #32 (ORD-02 → ORD-01) is a stacked draft, so neither was merged. The RC branch resolves:

| Conflict | Resolution |
|---|---|
| `routes/web.php` | `main`'s Journal editor/media/taxonomy routes join `can:blog.manage`; fragrance-preference admin routes join `can:catalog.manage`. These are the abilities of the same areas, so no new permission is created. `main`'s single `['auth','admin']` group no longer exists. |
| Blog writes | `role === 'admin'` checks became the `blog.manage` ability (Super Admin + legacy admin). Without this, a Super Admin would get 403 on Journal edits after the merge. Legacy admins are unchanged. |
| `bootstrap/app.php` | Order API v1 and the Journal automation API each keep their own error envelope and logging. |
| Layout, CSS, Vite, rate limiters, `.env.example`, docs | Both sides kept (public PWA head + `main`'s canonical URL). |
| Tests | RBAC route map knows the Journal and fragrance route names. Guests on `/admin/*` go to `/admin/login` (Admin PWA rule). The migration rehearsal rolls back the ORD files by path because `main`'s `2026_10_10` preference migrations interleave by date. |

- **Migration rehearsal (MariaDB 11.8.9):** `main` schema + synthetic catalog/users → `mariadb-dump` → RC `migrate` (15 pending, about 1 s) → `migrate:rollback` → `migrate` → restore of the dump into a new database. The product checksum, user count, legacy-admin count and password-hash digest are identical at all five points.
- **RC smoke (local release-like instance, `APP_ENV=staging`, all order flags + PWA on, synthetic secrets, local signed webhook receiver):** 33 checks PASS, plus the installability check explained in the last bullet.
  - Public pages from both lines.
  - Checkout with paperbag.
  - Staff ongkir + confirm; unpaid claim refused 409 `payment_required`.
  - r4.4 totals 100.001 + 15.000 + 4.000 = 119.001.
  - Payment, then claim/prepare/pack/courier/handover/delivery over the signed API; ongkir cost with staff advance (`awaiting_proof`).
  - Completed order with `open_reimbursement`.
  - Customer paperbag row and Pesanan Saya.
  - Super Admin opens dashboard, Journal, taxonomy, fragrance preference, products and users.
  - 13 webhooks, all with valid signatures and the minimal 5-field body.
  - Installability reported `in-incognito` in the isolated context (a Chrome rule); the same page in a normal context has no errors.
- **Order API preflight** on the RC config: OK (expected warnings: no fixtures, no App owner IDs).

## 6. Screenshots

| | Mobile 390 | Desktop 1440 |
|---|---|---|
| Home (unchanged look, PWA on) | [home-390](screens/home-390.png) | [home-1440](screens/home-1440.png) |
| Offline page | [offline-page-390](screens/offline-page-390.png), [fallback while server down](screens/offline-fallback-390.png) | [offline-page-1440](screens/offline-page-1440.png) |
| Checkout offline guard | [checkout-offline-guard-390](screens/checkout-offline-guard-390.png) | — |
| Pesanan Saya under the worker | [pesanan-saya-390](screens/pesanan-saya-390.png) | — |
| Admin under its own worker | [admin-orders-390](screens/admin-orders-390.png) | [RC admin order](rc/rc-admin-order-1440.png) |
| RC Pesanan Saya | [rc-pesanan-saya-390](rc/rc-pesanan-saya-390.png) | — |

**UX rationale.** No page was redesigned: with the flag off the markup matches the pre-PWA site, and with it on only head tags change. The offline page uses the site's cream background, the charcoal button and the uppercase tracked labels. It has a single primary action (Coba lagi), and Pesanan Saya is the safe place to check an order. Its copy states plainly that nothing is sent automatically. The offline checkout message sits in the existing live feedback line under the order button, so screen readers announce it and the button stays usable for a retry.

## 7. Known limitations

- Real Android and iPhone install and link handling are untested (§3).
- Windows install click is untested in a headed browser (installability criteria pass).
- iOS does not support the web app manifest's maskable icon or shortcuts. It uses the Apple meta tags and the 180 icon.
- Production configuration, production credentials, an App production release on r4.4 and a rehearsal on a copy of the production database are Owner/App actions ([release plan](../../runbooks/RELEASE_ORD_PWA_RC.md) §2–§4).

## 8. GO / NO-GO

**PWA-01 code: GO for review** (flag off by default; rollback = flag).

**Production release of ORD-01..04 + PWA-01: NO-GO today.** Blockers:

1. The Owner has not approved the RC or the integration path (one RC PR replacing #27/#32).
2. Migration rehearsal on a restored **production** copy is not done (needs an Owner-provided backup).
3. Production secrets and config are not set or validated on the server (per-direction API/webhook credentials, owner IDs, `SESSION_LIFETIME`, verified payment account).
4. The App production release on r4.4 is not confirmed; both sides must ship together for the API step.
5. Real-device checks (Android/iPhone install, link handling between the two installed apps) are not done before enabling `PUBLIC_PWA_ENABLED`.
6. The ADR-number collision must be renumbered in the RC PR (documentation only).

Once 1–3 are done, the release can go step by step with every flag off, per [§5 of the plan](../../runbooks/RELEASE_ORD_PWA_RC.md). Items 4 and 5 gate only their own flags.
