# Admin push notifications (ORD-09)

Push notification to Super Admin and Staff Order when a customer orders on the website. The notification shows the order code, item count, total and "menunggu konfirmasi". It never shows the phone number or address, because it appears on lock screens of shared devices. Tapping it opens the order page.

## Release (Owner, once per server)

1. After the release is live, generate the keys on the server from the live release (the command ships with it):
   ```
   php artisan admin-push:vapid
   ```
   The command prints `ADMIN_PUSH_VAPID_PUBLIC_KEY=…` and `ADMIN_PUSH_VAPID_PRIVATE_KEY=…` and stores nothing.
2. Put both lines, plus `ADMIN_PUSH_VAPID_SUBJECT=mailto:<owner email>`, in the server `.env`. Keep the private key secret: never in Git or chat. Changing the keys later means every device must turn notifications on again.
3. Check two settings:
   - `ADMIN_PWA_ENABLED=true` must stay on, because the admin service worker receives the push;
   - the queue worker must be running, because the push is a queued job (`SendAdminPush`).
4. The table `admin_push_subscriptions` comes with the release migrations ([RELEASE_ORD_08_09](RELEASE_ORD_08_09.md)). After editing `.env`, run `php artisan config:cache`, then `php artisan queue:restart` (the worker reloads the keys), then `php artisan qammaris:release-check`.

Empty keys = the feature is off. Nothing is offered and nothing is sent.

## On each device

- **Laptop/Android Chrome:** Admin → Pesanan Online. The switch **Notifikasi pesanan** sits in the sidebar on desktop and above the order list on phones. Press **Aktifkan notifikasi**, allow it in the browser prompt, then press **Kirim tes**.
- **iPhone/iPad:** Safari → Share → **Add to Home Screen**, open **QAM Admin** from the icon, then turn it on. iOS only allows web push from installed apps.
- **Shared devices:** the subscription belongs to whoever is signed in on that browser. When another account signs in, it takes the subscription over.
- **Turning it off:** **Matikan** on that device. A browser that uninstalls or expires is removed automatically on the next send.

## Troubleshooting

- "Diblokir browser": allow notifications for the site in the browser settings, then reload.
- No switch visible: either the browser does not support push, or `ADMIN_PWA_ENABLED`/the VAPID keys are not set.
- Nothing arrives: check the queue worker and `storage/logs` for `admin push sent {"sent":N,"expired":M}`.
- Local Windows PHP: OpenSSL needs `OPENSSL_CONF` pointing at an `openssl.cnf` (e.g. XAMPP `php/extras/ssl/openssl.cnf`) to sign pushes. Linux servers and CI do not need this.
