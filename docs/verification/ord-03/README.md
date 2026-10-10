# ORD-03 — Operational UX Simplification (verification)

Date: 2026-10-10. Branch `modernization/ord-03-operational-ux`, stacked on ORD-02 (`modernization/ord-02-online-order-redesign`).

Not merged, not deployed. Decision record: [ADR-043](../../architecture/decisions/ADR-043-order-operational-ux.md).

Active surfaces:
- **Admin panel:** create order and the V2 order page.
- **Customer order link page:** public-facing but task-oriented.

The legacy ORD-01 order page is unchanged.

## Flow before → after

| Area | Before (ORD-02) | After (ORD-03) |
|---|---|---|
| Create order | Products, then "Pesanan dari", customer, recipient (two long cards). Sticky "Buat pesanan" over the form. 14px search fields. | **1. Produk → 2. Pelanggan → 3. Data penerima → Buat pesanan.** Source under "Opsi lanjutan". Button at the end, not sticky. Picking a repeat customer switches to "Isi sekarang". |
| Order page | Section chips plus a "Berikutnya" banner, then **every** panel open (Bayar, Packing, Kirim, Keep, Kendala, Harga, Link, Pelanggan, Detail, Batal, Riwayat). | The **current step** opens first with one primary action. Other steps collapse to a one-line status. "Ada masalah" and "Opsi lanjutan" open only when needed. |
| Payment | Amount, method, and a required "Dikonfirmasi dari" (chat/bukti/Majoo) choice. | Amount, method (pre-selected from the customer's preference), and "Sudah dicatat di Majoo". The button is **Tandai Lunas**. The source is recorded internally. |
| Packing | "Mulai siapkan" **and** a quantity form "Konfirmasi packing" at the same time. | One action: tick each line (`2× item`), then **Konfirmasi packing**. The button stays disabled until every line is ticked. |
| J&T | "Pickup sudah diminta", "QR sudah ada", and "Sudah dipickup" buttons, plus a resi form. | **Request pickup J&T → "Menunggu kurir J&T" → Sudah di-pickup J&T.** QR upload/view and resi sit under "Opsi lanjutan" and never block pickup. Resi can follow later. |
| Ongkir | Super Admin only, inside the long detail form. | In the Pembayaran section. Staff Order can set it under the UAT flag until the first payment; after that it goes through a price adjustment. |
| Link | "Link customer & WhatsApp": an input field, a WhatsApp button, and long instructions. | **Link Pesanan:** Salin Link, Salin Pesan, and **Bagikan** (native share sheet, shown only where supported). WhatsApp is optional. |
| Customer form | Full public navbar and footer. Long address tutorial. "Kirim lokasi lewat WhatsApp" with a step-by-step tutorial. | Brand header only, one page. Short hints. **Kirim Sharelok**. Payment choice Transfer bank / QRIS (flag), stored as a preference that never marks the order paid. |

Page height at 390 px (CSS px):

| Page | Before | After |
|---|---|---|
| Create order | 1347 | 961 (−29%) |
| Waiting for customer | 4483 | 1776 (−60%) |
| Unpaid | 5349 | 1780 (−67%) |
| Paid, intercity | 4490 | 1652 (−63%) |
| J&T waiting | 4291 | 1657 (−61%) |
| Keep | 4852 | 1945 (−60%) |
| Customer form | 2344 | 1694 (−28%) |

## Integrity kept

- **Payment:** every form still posts its revision. A double tap or a second staff member gets "Pesanan berubah", never a second entry.
- **Payment source:**
  - The payment ledger keeps the source: `majoo`, or the internal value `admin_recorded`.
  - The order field `payment_confirmation_source` belongs to API v1, so it stays within its enum (`majoo` or `null`).
- **Packing:** the server still requires the exact ordered quantities.
- **Permissions:**
  - **Unchanged:** price adjustments are still approved by Super Admin only; refunds, reversals and reconciliation stay Super Admin only; driver funding stays `orders.finance`.
  - **New:** `orders.charge-shipping`. Super Admin and legacy Admin always qualify. Staff Order qualifies only with `ORDERS_SIMPLE_UX` on, on a V2 order that is open, not handed over, and has **no recorded money**.
- **Audit:** every ongkir change writes "ongkir X → Y" to the order history.
- **API v1 (r4.2):** unchanged. The payment preference is Website-internal. The agen App confirmed its plan 0021 needs no contract change.
- **Data:** no domain service, audit event or existing data was removed. The only schema change is the additive migration `online_orders.payment_preference`, whose rollback was rehearsed on MariaDB.

## Tests

| Run | Result |
|---|---|
| `OrderOperationalUxTest` (new) | 6 passed, 66 assertions. Covers: payment without visible source plus API schema; staff ongkir with the flag off, on, after payment, driver funding; staff cannot approve prices or refunds; customer page without navbar plus payment preference; packing one action plus conflict; J&T flow plus private QR. |
| Full suite, SQLite | 527 passed, 6 skipped (the MariaDB-only tests) |
| Full suite, MariaDB 11.8.9 | **533 passed**, including the migration rehearsal with the new migration and the multi-process concurrency tests |
| `node --test tests/js/*.test.mjs` | 42 passed |
| `npm run build` | built |
| Pint | passed |

## Browser (headless Chrome, synthetic data, touch emulation on phone widths)

The phone widths emulate touch, and `(pointer: coarse)` matched on all of them.

Checks at 320, 375, 390, 430 and 1440 px, on 7 pages each: create, waiting for customer, unpaid, paid intercity, J&T waiting, keep, customer form.

| Check | Before (390) | After (all 35 captures) |
|---|---|---|
| Focusable text fields under 16 px (iOS auto-zoom) | 6 of 7 pages | **0** |
| Sticky or fixed elements inside a form | 1 (create) | **0** |
| Horizontal overflow | 0 | **0** |

Pinch zoom is not disabled: the viewport meta has no `maximum-scale` or `user-scalable`.

Screenshots at 390 px:
- create: [before](before-create-390.jpg) / [after](after-create-390.jpg)
- waiting for customer: [before](before-awaiting-390.jpg) / [after](after-awaiting-390.jpg)
- unpaid: [before](before-unpaid-390.jpg) / [after](after-unpaid-390.jpg)
- paid intercity: [before](before-paidIntercity-390.jpg) / [after](after-paidIntercity-390.jpg)
- J&T waiting: [before](before-jntWaiting-390.jpg) / [after](after-jntWaiting-390.jpg)
- keep: [before](before-keep-390.jpg) / [after](after-keep-390.jpg)
- customer form: [before](before-customer-form-390.jpg) / [after](after-customer-form-390.jpg)

Other widths (after): `after-{create,unpaid,customer-form}-{320,375,430,1440}.jpg` in this folder.

Full-page mobile captures show the fixed bottom navigation where the viewport ended. That is a capture artifact, as in ORD-02d.

## Limitations

- **Real iPhone Safari not tested.** Auto-zoom was checked through computed font sizes under touch emulation. The Owner's phone test in UAT is the confirmation.
- **Native share sheet:** shown only where `navigator.share` exists. Desktop Chrome usually hides it, leaving Salin Link and Salin Pesan.
- **Clipboard on HTTP:** the clipboard API needs a secure context. On plain-HTTP local access, the fallback copy may be refused; the HTTPS UAT tunnel works.
- **UAT not yet updated:** the UAT instance still runs ORD-02 (`693e6f9`). Switching it to ORD-03 with `ORDERS_SIMPLE_UX=true` is the next step and needs the Owner's go-ahead.
- **App side:** the agen App's own ORD-03 counterpart (plan 0021) is a separate implementation. Its smoke test on the UAT Website is coordinated separately.
