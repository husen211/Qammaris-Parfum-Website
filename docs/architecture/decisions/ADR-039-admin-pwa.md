# ADR-039 — Qammaris Admin PWA

Status: accepted for review (ORD-02b, 2026-10-08). Not deployed.

## Context

Staff handle online orders on shared store phones. Owner asked for an installable admin app with its own "Qammaris Admin" identity, login inside the app, no personal data cached on the phone, safe login/logout on shared phones, and no effect on the public site. Earlier Owner correction: `Clear-Site-Data` must not be used because it is origin-wide; the public site keeps catalog state in `sessionStorage` and shares the session cookie with the cart.

## Decision

1. **Identity and scope.**
   - The manifest is at `/admin/manifest.webmanifest`: name "Qammaris Admin", short name "QAM Admin", `display: standalone`, and `start_url` `/admin/orders?source=pwa`.
   - Icons are the existing Qammaris mark inverted (cream on `#1A1A1A`), so the app differs from the public black-on-white favicon.
   - `id` and `scope` are `/admin` **without a trailing slash**, because the dashboard URL is `/admin`.
   - A route-table test proves that every route under `/admin` is an `admin.*` route and that no public route starts with `/admin`.
2. **Login inside scope.**
   - `GET/POST /admin/login` and `POST /admin/logout` are the canonical admin entry points.
   - Unauthenticated `/admin/*` requests redirect there. Signed-in users opening `/admin/login` go to their area.
   - `GET /login` redirects to `/admin/login`; `POST /login` and `POST /logout` keep working for old clients.
3. **Service worker** `/admin/sw.js`, served by Laravel with `Service-Worker-Allowed: /admin` and `Cache-Control: no-cache`:
   - caches only `/build/assets/*`, `/images/pwa/*` and the static offline page, in caches named `qammaris-admin-static-<version>`; the version follows the Vite manifest, the worker source and the offline page;
   - navigations are network-only; on failure it shows the static offline page;
   - a failed non-GET navigation is marked so that page offers the order list, never a re-post;
   - JSON, uploads, form posts and cross-origin requests are not intercepted;
   - `localStorage`, IndexedDB and `Clear-Site-Data` are never used.
4. **Resources without sessions.** The manifest, worker and offline page are loaded from `routes/admin-pwa.php` without the `web` middleware group. They set no cookies and contain no account or customer data.
5. **Shared phones.**
   - Admin responses and the admin login send `Cache-Control: no-store, private`. A page restored from the back/forward cache reloads, so the server re-checks the session.
   - Logout, idle expiry, deactivation and access changes all land on `/admin/login` with a one-time flag. That page then deletes only the `qammaris-admin-*` caches.
   - The mobile header always shows the signed-in name and role, plus a **Keluar** button.
   - The 12-hour idle limit, no remember-me, and the `auth_version` revocation from ADR-038 stay unchanged.
6. **Mutations are online-only.**
   - While the phone is offline, POST forms are not sent, and a visible notice says so.
   - A pending submit ignores repeat taps. Buttons are disabled after the form data is built, so the clicked button's value is still sent.
   - Order creation carries a per-form `submission_token` (UUID). An additive unique key `(created_by, submission_token)` makes a double tap or retry return the first order.
7. **Mobile navigation.**
   - Every account with `orders.manage` gets a bottom navigation (Pesanan, Buat, Akun) below `md`, with safe-area padding.
   - Staff Order sees no extra menu. Full admins keep the existing menu.
   - `/admin/account` shows the signed-in identity, last login, idle limit, password change and logout.
8. **Kill switch.** `ADMIN_PWA_ENABLED=false` returns 404 for the manifest, removes the manifest link, and makes installed copies unregister their worker and delete admin caches.

## Consequences

- No offline data entry. That is intentional: orders must not live on a shared phone.
- Admin back/forward navigation always re-fetches pages (`no-store`).
- The scope string `/admin` would also match a future public path such as `/administrasi`. The scope test fails if such a route is added.
- The public site and the admin still share one session cookie. Logging out of the admin ends that browser's whole session, including a cart in that browser, exactly as before ORD-02b. Separating the cookies is not part of this item.
- iPhone standalone mode and real touch devices were not tested; see the [verification](../../verification/ord-02b/README.md).

## Rollback

Set `ADMIN_PWA_ENABLED=false` first. Installed copies then remove their worker on their next update check. After that, code can be reverted. The `submission_token` column is additive and nullable; old code ignores it.
