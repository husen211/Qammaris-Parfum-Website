# ORD-09 verification — admin push for new website orders (2026-10-17)

Local only; nothing deployed. Website 8011 (branch `modernization/ord-08-website-courier-cost`) with local-only VAPID keys and `OPENSSL_CONF` set (Windows), headless Chrome with notification permission.

| Check | Result |
|---|---|
| "Aktifkan notifikasi" as Staff Order | subscription saved; switch shows "Aktif di perangkat ini" and "Matikan" / "Kirim tes" |
| "Kirim tes" | accepted by the push service (`admin push sent {"sent":1}`); the admin worker displayed "Tes notifikasi Qammaris" |
| Real website checkout (QAM-0019, synthetic) | worker displayed "Pesanan baru dari website — QAM-0019 · 1 barang · Rp 799.000 · menunggu konfirmasi", url `/admin/orders/19` |
| Stale browser from an earlier run | removed automatically (`expired: 1`) |
| Phone width 390 | switch above the order list (bug found and fixed: only the first switch on the page was initialised) |
| Automated | `AdminPushTest` 5 (1 skipped on Windows without OpenSSL EC), `admin-push.test.mjs`, full suite 777 OK |

Screenshots: `after-switch-off-390.png`, `after-switch-on-390.png`, `after-switch-on-1440.png`.

Cleanup:
- The synthetic account, orders and subscriptions were removed by restoring the local DB backup, then re-running the additive migration.
- The App records of those orders were deleted, scoped to their IDs.

Not verified: a real Android phone, an iPhone (needs Home Screen install), and the production push service with production keys.
