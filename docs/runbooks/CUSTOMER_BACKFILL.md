# Customer backfill (ORD-05) — production runbook

Links existing online orders to saved customers with the same rule as new orders: the same WhatsApp number is the same customer. Website drafts that were never confirmed are skipped. Addresses of orders already handed over are kept. Nothing is sent to Qammaris App (no new revision).

**Needs its own Owner approval and a fresh backup.** Deploying ORD-05 does not run it.

## 1. Before

1. ORD-05 is deployed and its migration ran (`2026_10_21_100001_prepare_customers_for_auto_save_and_anonymizing`; additive).
2. Fresh production database dump with checksum, stored off-server, as in [RELEASE_ORD_PWA_RC §3.1](RELEASE_ORD_PWA_RC.md).

## 2. Preview (read-only)

From the live release (`$root/current`):

```
php artisan qammaris:customers:backfill
```

It prints one row of counts only (no names or numbers): orders without a customer, links to existing customers, new customers, links to new customers, and skipped orders (no name or valid number). Share the counts with the Owner. Nothing is written.

## 3. Apply (after the Owner agrees with the counts)

```
php artisan qammaris:customers:backfill --apply
```

- One transaction per order; already linked orders are left alone, so running it again links 0.
- Each linked order gets a "menghubungkan pelanggan" history line; kept addresses get "menyimpan alamat".
- Check: Admin → Pelanggan shows the customers; a few orders show their customer.

## 4. Rollback

The links are plain columns (`online_orders.customer_id`, `customer_address_id`) plus new `customers`/`customer_addresses` rows. Restoring the backup reverts them but also erases every order and change made after it, so prefer a forward fix: move an order to another customer from its page, or edit the customer in Pelanggan.
