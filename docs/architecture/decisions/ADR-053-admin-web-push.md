# ADR-053 — Web Push for new website orders (admin)

Status: accepted for review (ORD-09, Owner 2026-10-17). Not deployed.

## Context

Website checkout orders arrive as drafts that Staff Order must confirm. All order work now happens on the Website (ADR-052). Staff and the Owner only noticed new website orders by reloading Pesanan Online. The Owner asked for a push notification from the Website admin itself and chose to delay the ORD-08 release for it.

## Decision

- **Standard Web Push.** VAPID with aes128gcm, via `minishlink/web-push` ^9. This adds a dependency: Web Push needs ECDSA/ECDH, which hand-written crypto should not do.
- **The existing admin service worker** (`/admin`, ORD-02b) shows the notification and opens the order page on tap. Push therefore requires `ADMIN_PWA_ENABLED=true`.
- **Subscriptions** are stored per browser endpoint (`admin_push_subscriptions`, unique endpoint hash) for the signed-in account. Only accounts with `orders.manage` (Super Admin, Staff Order) can subscribe and receive. Expired endpoints (404/410) are deleted.
- **Trigger:** a new website checkout order, after commit, as a queued job. A replayed checkout key sends nothing.
- **Payload:** code, item count, total and status only. No phone number, address or customer name, because shared devices show it on lock screens.
- **Keys** come from the environment (`ADMIN_PUSH_VAPID_*`), generated with `php artisan admin-push:vapid`. Empty keys turn the feature off completely.

## Consequences

- The Owner must generate and set the VAPID keys once on the server (secret handling).
- Each device turns notifications on itself. iPhone needs the admin installed to the Home Screen.
- Delivery depends on the browser vendor's push service (Google/Apple/Mozilla) and the queue worker. Order handling never depends on push: the order list stays the source of truth.
