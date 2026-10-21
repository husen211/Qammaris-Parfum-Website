# ORD-10 — Customer chooses "Metode pembayaran ongkir" (2026-10-21)

Owner request (card #75): on the order link and the website checkout, the customer also picks how the ongkir is paid, so staff no longer choose it; plus a small tidy-up of the online-order flow. Branch `modernization/ord-10-ongkir-payment-method`. Local only; nothing deployed.

## What changed

- **Customer link and checkout:** new required question **Metode pembayaran ongkir** with **Sekalian transfer** / **Bayar langsung ke driver**, shown only for **Pengiriman Dalam Kota**. J&T is not asked: its ongkir is always part of the payment (stored as `added_to_transfer`). Pickup is not asked.
- **Storage:** the choice fills the existing `shipping_payer`. No migration; Order API v1 contract and serializer unchanged (schema test passes).
- **When the link may set it:** V2 order, not a draft, no money received yet (a partial payment also locks it), not made free by the store, and the form is applied directly (not a change request). Checked once in `OnlineOrder::customerSetsShippingPayment()`, used by the request, the action and the view.
- **Paid to the driver = total already final.** `OnlineOrderStepper::totalIsFinal()` now treats `cash_to_driver` as final without an amount, so payment instructions appear right after confirmation. The actual courier fee is still recorded at "Kurir sudah dipesan" (Ongkir ke kurir), where "Uang siapa" already defaults to **Customer bayar driver**.
- **Customer page:** ongkir row shows "Dibayar langsung ke driver" (with the amount once known), recipient data shows "Metode pembayaran ongkir".
- **Checkout WhatsApp message:** adds "Ongkir: bayar langsung ke driver" for that choice.
- **Staff page tidy-up:** label "Dibayar" → "Metode pembayaran ongkir"; hint that the customer already chose; summary no longer says "belum diisi" for driver-paid ongkir; the confirmation checklist and the "total not final yet" sentence follow the chosen method.

## Tests and checks

- New `OrderShippingPaymentChoiceTest`: 4 tests (checkout choice/validation/J&T/pickup, link choice → staff prefill → final total without ongkir, switch to J&T, free ongkir and any payment lock the choice).
- Existing tests updated only for the new required field (`WebsiteCheckoutOrderTest`, `OrderOperationalUxTest`) and the reworded staff hint (`AdminOrderV2PagesTest`).
- Full Laravel suite with a CI-like `.env.testing` (copy of `.env.example`): 774 passed, 10 skipped, 3 failed. The same 3 tests fail on unmodified `main` on this laptop (no GD extension in XAMPP PHP; `ReleaseCheckTest` Windows behaviour) and pass in CI.
- Pint clean on changed PHP; `node --test tests/js/*.test.mjs` 71/71; `npm run build` OK.

## Browser walk-through (synthetic SQLite DB, synthetic accounts, local only)

Mobile 375/390 and desktop 1440:

1. Order link: pickup/J&T hide the question, local delivery shows it; submitting without a choice shows "Pilih metode pembayaran ongkir." in the summary and under the field; with "Bayar langsung ke driver" the status page shows the method.
2. Staff (Staff Order): method preselected with the hint; payment instructions available at once (Rp 284.000, ongkir excluded) → Tandai Lunas → packing → "Uang siapa" preselected **Customer bayar driver**, Maxim, Rp 15.000 → Kurir sudah dipesan ("dibayar customer ke driver") → Sudah diserahkan ke kurir → Pesanan selesai.
3. Website checkout (1440): summary switches to "Dibayar langsung ke driver"; saved order QAM-0002, WhatsApp text carries the ongkir line; staff confirm → copied instructions show "Ongkir: dibayar langsung ke driver" and total Rp 95.000.

Screenshots (full page, headless Chrome):

| | Before | After |
|---|---|---|
| Order link 390 | [before-link-390](before-link-390.png) | [after-link-390](after-link-390.png) |
| Order link 1440 | [before-link-1440](before-link-1440.png) | [after-link-1440](after-link-1440.png) |
| Checkout 390 | [before-checkout-390](before-checkout-390.png) | [after-checkout-390](after-checkout-390.png) |
| Checkout 1440 | [before-checkout-1440](before-checkout-1440.png) | [after-checkout-1440](after-checkout-1440.png) |
| Staff payment step | — | [390](after-staff-payment-390.png), [1440](after-staff-payment-1440.png) |

The synthetic database, accounts and temporary `.env.testing` were removed after the run.

## UX rationale

The question sits next to "Pembayaran" because it is the same money decision, and only appears where it matters (a local driver). Two short options, the Owner's wording, one line of help that states the consequence (not part of the total). Staff keep one action: type the amount, or skip it when the driver is paid directly.

## Limits

- Not tested on a physical phone or iPhone Safari.
- A customer who switches from local delivery to pickup keeps the earlier ongkir method on the order (pickup ignores it; same as before this change).
- Orders created before this change keep the method staff set; nothing is backfilled.

## Rollback

Code-only: revert the PR. No schema or data change; values already saved in `shipping_payer` stay valid for the old code.

## ORD-11 — order link in the checkout WhatsApp message

Owner request (card #77, 2026-10-21, before releasing ORD-10): the chat a customer sends after a website checkout only named the order number, so staff searched for it by hand.

- `InquiryWhatsApp::websiteOrderMessage()` now ends with `Link pesanan untuk admin: …`, built by `OrderTaskLink::for()`, the same link as the staff WhatsApp group: `/admin/orders/task/{public ID}` (random ULID, no contact data). With `QAMMARIS_ORDER_APP_TASK_LINKS=true` it would be the App order URL, exactly like the group link.
- The customer sees the link in their own message; it only leads to the admin login.
- Test `test_checkout_message_carries_a_staff_link_that_opens_the_order_after_login`: the message ends with the link and has no phone number; the WhatsApp button sends the same text; the link redirects to the order page; a guest is sent to the admin login; a Staff Order account opens the order.
- Browser (375, synthetic DB): checkout J&T order QAM-0001 → copied message ends with the link → as a guest the link opens `/admin/login`; after signing in as a synthetic Staff Order the browser lands on `/admin/orders/1`; clicking the link again opens the order directly. Synthetic DB and account removed afterwards.

