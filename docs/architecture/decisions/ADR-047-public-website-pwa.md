# ADR-047: Installable public website (Qammaris Perfumes PWA)

Status: Proposed 2026-10-11 (PWA-01). Branch `modernization/pwa-01-public-pwa`, built on ORD-04. Not merged, not deployed. Flag `PUBLIC_PWA_ENABLED`, default off.

ADR number: 047 was the first number free on both lines. In the release candidate the colliding order ADRs became 048–051 (aliases kept); see the [index](README.md).

## Context

The Owner wants the public site installable in Chrome on Windows, Android and iPhone. The same origin already serves the Admin PWA "Qammaris Admin": scope `/admin`, worker `/admin/sw.js`, caches `qammaris-admin-*` ([ADR-050](ADR-050-admin-pwa.md)). The public site serves the cart, guest checkout, order status links and Pesanan Saya, so private data must not be cached. No push notifications are wanted.

## Decision

1. **Separate identity on the same origin.**
   - Manifest `/manifest.webmanifest`: id `/`, scope `/`, start_url `/`, name "Qammaris Perfumes", short name "Qammaris", lang `id`, standalone.
   - Theme `#FFFFFF`, background `#F9F9F7`.
   - Icons are PNG 192, 512, maskable 512 and Apple Touch 180: black circled mark on cream, built reproducibly by `tools/build_public_pwa_icons.py`. Admin keeps its white-on-black icons.
   - Only `layouts/app.blade.php` links the public manifest. Admin pages and admin login link only the Admin manifest.
2. **Worker `/sw.js`, scope `/`.**
   - Served without session or cookies, with `Service-Worker-Allowed: /`, `no-cache` and `nosniff`. The controller prepends `{version, enabled, offlineUrl}`.
   - Caches only fingerprinted `/build/assets/*`, the `/images/pwa/public-*` icons and the static `/offline` page, in `qammaris-public-static-{version}`.
   - Navigations are network-only, with `/offline` as the fallback. No HTML is ever stored.
3. **Never intercepted:** `/admin*`, `/login`, `/logout`, `/tugas-pesanan*`, `/integrations*`, `/api*`, `/sanctum*`, `/up`, cross-origin requests and every non-GET request (cart changes, checkout, order forms). The browser handles these as if no worker existed.
4. **Coexistence.**
   - The more specific registration controls a page, so pages under `/admin` belong to the Admin worker once it is registered. Before that, an `/admin` page opened from the public app is controlled by the public worker, which passes it through. Only the public build bundle gets cached (verified).
   - Neither worker deletes the other's caches or registration.
   - No `Clear-Site-Data` anywhere, because it is origin-wide and would sign out Admin and empty the cart.
5. **Kill switch `PUBLIC_PWA_ENABLED`** (default false).
   - When off: no manifest link (404), no registration, and the pre-PWA apple-touch icon.
   - Installed copies fetch `/sw.js`, get the self-removing worker, delete only `qammaris-public-*` caches and unregister only the `/` registration.
   - The page module also unregisters only the exact `/` scope.
6. **Offline ordering is impossible.**
   - The checkout form refuses to submit while the browser reports offline: nothing is sent or queued.
   - After a network failure the worker shows `/offline`, which points to Pesanan Saya and never resubmits. Checkout stays idempotent (`checkout_key`) for honest retries.
7. **No push, background sync, notification permission, VAPID or Firebase.**

## Consequences

- One origin hosts two installable apps. On Android, the public WebAPK scope `/` also covers `/admin`. Real-device behaviour for link capture and disambiguation between the two installed apps is **not verified**.
- Moving Admin to a subdomain would remove the overlap, but needs Owner approval and is not part of this ADR.
- iPhone: Safari "Add to Home Screen" uses the Apple meta tags and the 180 icon. Not verified on a real device.
- Rollback: set `PUBLIC_PWA_ENABLED=false` (config cache refresh). Code removal is not needed and storage is not wiped.
