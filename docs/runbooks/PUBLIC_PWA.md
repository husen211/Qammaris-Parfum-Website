# Public website PWA — enable, check, disable (PWA-01)

Decision: [ADR-047](../architecture/decisions/ADR-047-public-website-pwa.md). Evidence: [verification](../verification/pwa-01/README.md). Admin PWA: [ADR-039](../architecture/decisions/ADR-039-admin-pwa.md).

## Switch

| `.env` | Effect |
|---|---|
| `PUBLIC_PWA_ENABLED=false` (default) | No manifest (404), nothing registered, the site looks exactly as before PWA-01. Installed copies remove their worker and `qammaris-public-*` caches on the next visit. |
| `PUBLIC_PWA_ENABLED=true` | `/manifest.webmanifest` linked from public pages, `/sw.js` registered with scope `/`. |

After changing it, run `php artisan config:cache` (or the deploy's `php artisan optimize`). `ADMIN_PWA_ENABLED` is separate and stays as it is.

## Enable (production, after Owner approval)

1. Deploy a build that includes PWA-01. `public/build` must be fresh: the worker version follows `public/build/manifest.json`.
2. Set `PUBLIC_PWA_ENABLED=true`, then refresh the config cache.
3. Check over HTTPS. Installation needs HTTPS (localhost is the only exception):
   - `curl -sI https://<domain>/manifest.webmanifest` → `200`, `application/manifest+json`, no `Set-Cookie`.
   - `curl -sI https://<domain>/sw.js` → `200`, `Service-Worker-Allowed: /`, `Cache-Control: no-cache`, no `Set-Cookie`.
   - `curl -s https://<domain>/offline` → static page "Tidak ada koneksi internet".
   - Chrome desktop: DevTools → Application → Manifest shows "Qammaris Perfumes" with no errors; the install icon appears in the address bar.
   - Application → Service workers lists `/` (`/sw.js`) and, after an admin login, `/admin` (`/admin/sw.js`).
   - Application → Cache Storage shows only `qammaris-public-static-*` and `qammaris-admin-static-*`, holding build assets, icons and the two offline pages. Never HTML pages, cart, checkout, order links or JSON.
4. Real devices (manual, not automatable here):
   - Android Chrome: Install app → opens standalone with the cream icon.
   - iPhone Safari: Share → Add to Home Screen → title "Qammaris" with the 180 icon.
   - With both apps installed, check that a `/admin/...` link opens Qammaris Admin and a product link opens Qammaris Perfumes. Record the result. Android link handling between two apps on one origin is not verified yet.

## Disable or roll back

1. Set `PUBLIC_PWA_ENABLED=false` and refresh the config cache. That is the whole rollback: no deploy, migration or data change.
2. Installed copies receive the self-removing worker on their next visit. Only the public worker and public caches are removed. The Admin worker, admin caches, cookies, the cart session and Pesanan Saya stay.
3. A home-screen icon may stay on a phone. It opens the normal website in the browser. Customers can remove it themselves.
4. Never use `Clear-Site-Data` to force cleanup: it is origin-wide and would sign out Admin and empty carts.

## Rebuild the icons

`python tools/build_public_pwa_icons.py` regenerates `public/images/pwa/public-*` from `public/images/logo-black2.png`. The Admin icons are not touched.

## What the public worker never does

It never caches HTML, cart, checkout, order status links, Pesanan Saya or JSON. It never touches `/admin`, `/login`, `/tugas-pesanan`, `/integrations`, `/api` or any non-GET request. It never sends, queues or retries an order offline. It never shows notifications or asks for notification permission.
