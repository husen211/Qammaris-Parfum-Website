# ADR-062: Staff change an order after it was made, with the difference settled in the ledger

Status: accepted (ORD-27, Owner 2026-11-17). Released only on the Owner's explicit go.

## Context

An order's lines could not change after creation ("batalkan lalu buat ulang"), a change of delivery type was a change request for Super Admin, and a checkout ongkir was locked. The Owner: when stock runs out the product must be swappable; a customer who chose J&T but wanted same-day delivery had to get a new order. Changes must work also after payment, with the difference to pay or to return shown, and Staff Order must not be limited in ways that leave work for the Owner ("jangan kasih batasan staff yang bikin ribet admin").

## Decision

- **One operation** `ChangeOnlineOrder` (V2 only) receives the order's lines after the change and its delivery. Kept lines are matched by `line_id` (scoped to the order) and keep their price snapshot; new lines take the current catalog price (`orderableByAdmin`, price > 0). Same variant twice, no lines, or a foreign `line_id` is refused.
- **When**: draft, awaiting customer or active; not handed over; no Biteship driver booked or being booked. Also after payment and after packing; changed lines reset `preparation_status` to `not_started`.
- **Delivery**: pickup clears the ongkir; a Biteship option priced by `POST orders/{order}/v2/ubah/ongkir` for the *edited* lines (cart hash `ChangeOrderRequest::cartOf`, options kept per order by `ShippingQuotes::forOrder`) is applied with `chosen_by: staff`; otherwise a typed fee and payer replace the checkout price (its instant pin moves to `location_url`). Unchanged fee, payer and fulfillment keep the existing quote.
- **Money**: the ledger stays the authority. `basePaymentFromLedger` nets refunds when no refund decision exists, so a price increase after a returned overpayment shows what is left to pay. An overpayment is returned with `RecordOnlineOrderMoney::returnOverpayment` (amount = the overpayment itself, a `refund` ledger entry without a refund decision, so the order keeps going). A cancelled order still uses the refund decision (ADR-051).
- **Permissions** (Owner 2026-11-17): `orders.refund` opens to Staff Order (refund decisions, payouts, reversals, reconciliation) and `orders.cancel` lets Staff Order cancel any order not handed over, stating the refund when money came in. Owner-level override rules move to a new `orders.owner` ability (`OrderActor::isOwner`, releasing App claims), so they stay Super Admin.
- **History and customer**: event `order_changed` with lines, delivery, total and difference; staff get a WhatsApp message (`OnlineOrderMessages::orderChanged`) with the new total and what is left to pay or comes back. The customer link shows "Sisa yang perlu dibayar".
- **App**: the App keeps no order lines and re-reads `GET /orders/{id}` on every revision; changed lines, totals and payment state need no App change while online orders are read-only there.

## Consequences

- A sold-out product or a wrong delivery choice is fixed on the same order and code.
- Staff can move money decisions without the Owner; every one stays in the ledger and history.
- If the App's operational mode (`VITE_ONLINE_ORDERS_WORK_IN_APP`) is turned back on, its packing state should reset when the revision changes.
- Rejected: a separate "replacement order" linked to the old one would split payments across two orders and confuse the customer link.
