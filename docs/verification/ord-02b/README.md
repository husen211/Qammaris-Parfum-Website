# ORD-02b — Qammaris Admin PWA: local verification

Date: 2026-10-08. Owner approval: "Silakan lanjutkan ORD-02b (Admin PWA) … Selesaikan ORD-02b beserta pengujiannya, lalu laporkan hasil. Jangan melakukan merge/deploy production sebelum persetujuan." Status: IN_REVIEW on `modernization/ord-02-online-order-redesign`. Not merged, not deployed. Decision: [ADR-039](../../architecture/decisions/ADR-039-admin-pwa.md).

## Scope implemented

- Manifest `/admin/manifest.webmanifest`:
  - "Qammaris Admin" / "QAM Admin", `id`/`scope` `/admin`, `start_url` `/admin/orders?source=pwa`, standalone;
  - icons 192/512, maskable 512 and apple-touch 180, inverted from the existing logo mark;
  - shortcuts Buat pesanan / Pesanan online.
- Worker `/admin/sw.js`:
  - static cache only;
  - network-only navigations with a static offline page;
  - a failed form post is never re-posted from that page;
  - kill switch `ADMIN_PWA_ENABLED`.
- PWA resources load without sessions or cookies (`routes/admin-pwa.php`).
- Login and logout move to `/admin/login` and `/admin/logout`:
  - `/login` redirects there;
  - unauthenticated admin URLs land there and keep the deep link.
- Shared phones:
  - admin responses are `no-store, private`;
  - pages restored from the back/forward cache reload;
  - logout and forced logout delete only `qammaris-admin-*` caches, without `Clear-Site-Data`;
  - the header shows who is signed in and a Keluar button;
  - new `/admin/account` page.
- Mobile bottom navigation (Pesanan, Buat, Akun) for accounts with `orders.manage`. Staff Order no longer sees the extra menu.
- POST forms are blocked while offline, with a visible notice. Repeat taps are ignored while pending.
- Order creation `submission_token`: additive migration `2026_10_08_200001_add_submission_token_to_online_orders` with a unique key on `(created_by, submission_token)`.
- ADR renumbering after main took ADR-034–036 for the Journal: 034→037 (online orders), 035→038 (admin roles), new 039 (PWA). Old filenames remain as aliases.

## Checks actually run

- `php artisan test` full suite: **439 passed / 4975 assertions**. That is 415 before this item, plus 13 contract tests (`OrderApiContractTest`, contract r4) and 11 `AdminPwaTest`.
  - `AdminPwaTest` covers:
    - manifest installability fields and real icon sizes/types;
    - the scope covers admin routes only;
    - worker headers, scope and version, with no cookies;
    - static offline page;
    - kill switch;
    - guest/login/intended routing per role;
    - logout and forced logout landing plus the cache-clear flag, without `Clear-Site-Data`;
    - `no-store` on admin pages;
    - bottom nav items and current state;
    - public pages load no admin app;
    - account page;
    - one order per repeated submission.
  - `AdminRbacTest` now also pins the exact middleware of the six public admin entry routes.
  - Eight older tests now expect guests to be redirected to `/admin/login` instead of `/login`.
- `node --test tests/js/*.test.mjs`: **40 passed** (31 before + 9 new `admin-pwa.test.mjs`). The worker runs in a VM sandbox with fake caches/fetch:
  - precache contents;
  - activation drops old admin caches only;
  - successful admin pages are never cached;
  - JSON, uploads, POST and cross-origin requests are not intercepted;
  - assets are cache-first and errors are not cached;
  - the offline GET/POST variants;
  - the kill switch;
  - the page cache-clearing and registration helpers;
  - the submit guard.
- Pint on changed/new PHP: passed. `npm run build`: passed.
- Migration up → rollback → up on isolated SQLite: column added, removed, added again.

## Browser evidence

Real headless Chrome (DevTools Protocol) against the worktree on `127.0.0.1:8124`:
- isolated SQLite with synthetic accounts `pemilik` (Super Admin) and `andi` (Staff Order), and one synthetic product;
- cookie sessions and compiled views outside the repository;
- DB, random password and Chrome profile deleted afterwards.

Links visible in screenshots belong to that deleted DB. Raw results: [browser-checks.json](browser-checks.json).

| Check | Result |
|---|---|
| Install | `Page.getAppManifest` no errors; `Page.getInstallabilityErrors` **empty**; worker scope `http://127.0.0.1:8124/admin`, page controlled |
| Guest start URL | `/admin/orders?source=pwa` → `/admin/login` ([390](login-390.png)) |
| Staff Order | Lands on `/admin/orders`; header "Andi Uji · Staff Order" + Keluar; bottom nav Pesanan*/Buat/Akun; no extra menu ([390](staff-orders-390.png), [320](staff-orders-320.png), [create](staff-create-390.png), [account](staff-account-390.png)) |
| Widths | 0 px horizontal overflow at 320/375/430 on orders, create, account, order detail; 1440 owner ([1440](owner-orders-1440.png), [menu 390](owner-orders-menu-390.png)) |
| Cache contents while signed in | Only `/admin/offline`, the admin icon and three `/build/assets/*` files; no admin page or JSON |
| Double tap on Buat pesanan | 1 order. Replaying the same form token twice by `fetch` → both land on the same order |
| Offline (page) | Notice shown; a POST form submit is not sent and the page stays put ([390](offline-banner-390.png)); notice hides when online |
| Server unreachable | GET navigation → static offline page with **Coba lagi** ([390](offline-page-390.png)); failed form post → warning + **Buka daftar pesanan**, no retry/re-post ([390](offline-after-submit-390.png)) |
| Logout | `/admin/login` "Anda sudah keluar…" ([390](logout-390.png)); admin cache emptied (only the icon re-cached by the login page); a public `sessionStorage` key **kept**; Back button → `/admin/login` |
| Public site | `/products` not controlled by the worker, no manifest link |
| Super Admin | Lands on `/admin`; `/admin/login` while signed in → `/admin`; bottom nav hidden at 1440 |
| Console | No JS exceptions |

The full-page capture at 390 shows the fixed bottom navigation where the viewport ended (the order detail screenshot is taller than the viewport). On the device it stays at the bottom edge.

## Limitations

- Genuine Android install prompt, iPhone Safari "Add to Home Screen", standalone cookie behaviour on iOS, and real touch were **not** tested.
- Network loss was simulated in two ways: DevTools offline emulation for the page, and stopping the local server for the worker. No real flaky mobile network was used.
- The concurrent-duplicate path (unique-key race) is covered by code and the unique index, but was not exercised under real concurrent MySQL load.
- Admin and public site still share one session cookie, so logging out ends the cart in that browser too, unchanged from before.
- The ADR alias files point to Journal ADR-034/035, which exist on `main` and not yet on this branch. The links resolve after merging `main`.
