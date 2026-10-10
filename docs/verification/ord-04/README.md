# ORD-04 — Website Cart Checkout Integration (verification)

Date: 2026-10-10. Branch `modernization/ord-04-cart-checkout`, stacked on ORD-03 (`5468482`). Not merged, not deployed. Decision record: [ADR-044](../../architecture/decisions/ADR-044-website-checkout-orders.md).

Flags used in all evidence:
- `ORDERS_V2_ENABLED=true`
- `ORDERS_SIMPLE_UX=true`
- `ORDERS_WEBSITE_CHECKOUT=true`

With `ORDERS_WEBSITE_CHECKOUT` off, the old WhatsApp-only checkout is unchanged (tested).

## Flow before → after

| Step | Before (ADR-028) | After (ORD-04) |
|---|---|---|
| Checkout form | Name, phone, required address, postcode, note | Name, phone, **Cara pengiriman** (Ambil di toko / Pengiriman Instan — Kota Palu, 1–2 jam / Pengiriman Luar Kota — J&T), address only for delivery (+ optional kelurahan, kecamatan, kode pos), **Paperbag**, **Pembayaran** (Transfer bank / QRIS), note. Ongkir: "Menunggu konfirmasi staf". |
| Submit | Redirect straight to `wa.me`; nothing stored; cart kept for retry | Order saved (`QAM-xxxx`, lifecycle `draft` = "Menunggu konfirmasi website"), cart emptied, redirect to the order link. That page opens WhatsApp once and offers **Buka WhatsApp lagi**. |
| WhatsApp message | All items, prices and the full recipient data | Short: order number, name, delivery type, subtotal, request to confirm stock/ongkir/payment |
| Staff | Retype the chat into an order | Banner "N pesanan website menunggu konfirmasi" → order page opens on **Konfirmasi pesanan website** (details, 3-step checklist, ongkir in Pembayaran) → **Konfirmasi pesanan** → normal payment/packing/shipping |
| App | — | Draft: `queue=null` (no task). After confirm: `active`, `needs_handling` |

## Screenshots

Captured in real headless Chrome against a local server with a temporary SQLite DB and synthetic data, at 390×844 (touch) and 1440×900. Before = ORD-03 head `5468482`.

| | 390 | 1440 |
|---|---|---|
| Before: checkout | [390](before-checkout-390.jpg) | [1440](before-checkout-1440.jpg) |
| Before: validation | [390](before-checkout-errors-390.jpg) | [1440](before-checkout-errors-1440.jpg) |
| After: checkout | [390](after-checkout-390.jpg) | [1440](after-checkout-1440.jpg) |
| After: validation (all six fields) | [390](after-checkout-errors-390.jpg) | [1440](after-checkout-errors-1440.jpg) |
| After: filled (instant Palu) | [390](after-checkout-filled-390.jpg) | [1440](after-checkout-filled-1440.jpg) |
| After: saved / WhatsApp retry | [390](after-success-390.jpg) | [1440](after-success-1440.jpg) |
| Staff: order list banner | [390](after-admin-orders-390.jpg) | [1440](after-admin-orders-1440.jpg) |
| Staff: confirm step | [390](after-admin-confirm-390.jpg) | [1440](after-admin-confirm-1440.jpg) |
| Staff: after confirm (payment next) | [390](after-admin-confirmed-390.jpg) | [1440](after-admin-confirmed-1440.jpg) |
| Customer: after confirm | [390](after-customer-confirmed-390.jpg) | [1440](after-customer-confirmed-1440.jpg) |

### Browser results (both widths)

| Check | Result |
|---|---|
| Horizontal overflow | 0 px on every page |
| Inputs under 16 px on touch | none |
| Validation errors shown | 6 fields (`customer_name`, `customer_phone`, `delivery`, `customer_address`, `packaging`, `payment_preference`) |
| WhatsApp after save | opened automatically once (request recorded and blocked in the test browser, so nothing reached WhatsApp); reload shows "Buka WhatsApp lagi" |
| Back to `/cart/checkout` after the order | redirects to `/cart` (empty cart, no second order) |
| Staff confirm | chip "Aktif", next step "Pembayaran · Belum dibayar · Rp 925.000" (Rp 910.000 + ongkir Rp 15.000 set while draft) |
| JS errors | none |

Small targets flagged:
- "Ada masalah" on the admin page is the existing ORD-03 summary link and is unchanged.
- The 1440 admin filter inputs at 14 px are desktop-only (the 16 px rule targets touch).

## Tests

- `tests/Feature/WebsiteCheckoutOrderTest.php`: 12 tests. They cover:
  - guest pickup;
  - three delivery types;
  - many products;
  - required choices;
  - price change and sold out (form and operation);
  - double tap and race;
  - a foreign checkout key;
  - WhatsApp not sent;
  - the success page;
  - a draft that cannot be paid and that the customer cannot edit;
  - staff confirm → payment;
  - cancel;
  - App serialization plus a signed `GET /orders/{id}` and `?lifecycle=draft` returning 200 with `queue=null`;
  - flag off;
  - rate limit.
- `OrderMigrationsMariaDbTest`: the ORD-04 migration is in the rehearsal set, and a new test shows `down()` stops while guest orders exist.
- Full suite on SQLite: 539 passed, 6 skipped (MariaDB-only), before the doc pass.
- Order suites on MariaDB 11.8.9 (isolated test server, port 3398): 145 passed, including the migration rehearsal and rollback guard.
- Pint clean; `npm run build` OK.

## Not verified / limits

- **Shared Owner UAT not used.** It was not switched to this branch, at the App agent's request: the Owner is doing their own ORD-03 test there and wants to create the first order themselves. A UAT run needs Owner approval and the two-sided clean afterwards.
- **No real phone or WhatsApp.** No real iPhone Safari or Android WhatsApp hand-off was tested. The automatic WhatsApp open uses a normal navigation and may land on the wa.me web page first.
- **Store number pinned.** The store number is pinned to the real Qammaris number by existing code (`StoreInfo`), so test environments open the real store chat. Testers must not press Send.
