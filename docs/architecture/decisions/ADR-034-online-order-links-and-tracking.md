# ADR-034 — Online order links, tracking, and staff task links

2026-10-07. Accepted for ORD-01. Implemented on review branch `modernization/ord-01-online-orders`; not deployed. Number ADR-033 is left for the parallel BLOG-02 work to avoid a collision; if unused, the gap is intentional.

## Problem

WhatsApp orders are coordinated by hand: product, recipient, sharelok/address, shipping fee, payment, and packaging are scattered across a chat. The Owner then retypes a summary for the staff group and asks for progress in the chat. ADR-028's cart checkout only opens a WhatsApp composer and stores nothing, so the store has no single order record.

## Owner decisions (2026-10-07)

1. Online orders **store recipient name, phone, address, and notes** in the website database, visible to admins and through that order's staff task link. Retention: **no automatic deletion**. This supersedes ADR-028's "no customer data in DB" rule **only for online orders**. Cart checkout remains unstored until a separately approved ORD-02.
2. Scope: admin-created order with a customer link and a mobile form, admin status/shipping/payment, a staff group message, tracking timeline, and a staff task link.
3. Delivery location inside Palu stays a **WhatsApp share location**. No browser geolocation.
4. The customer may correct details through the same link **until the admin marks Paid**. After that, the link is a read-only status page. An unfilled link expires after 7 days and can be regenerated.
5. Staff use a **secret per-order task link without login**. Holding the link is the only credential. The staff name is typed and remembered on the device, not authenticated. Staff may move shipping steps only (shipped, received/collected) and record an advanced shipping fee. They cannot touch payment, prices, or customer data.
6. Customers see a simplified timeline without staff names, funding, or internal notes.
7. Timeline UI follows the 21st.dev Timeline (preetsuthar17). It is reimplemented as a Blade/Tailwind component because the original is React + Radix + cva, and repository rules forbid a React conversion or new dependency.

## Decision

Three additive tables:
- `online_orders`: current state, details, shipping/payment fields, revision.
- `online_order_items`: an immutable price snapshot with historical product/variant IDs and no cascade.
- `online_order_events`: an append-only step/actor log that is both the timeline source and the audit trail.

No soft-delete, purge, or cascade from catalog/users. Cancellation is a state.

**Tokens.** 40 random characters. The SHA-256 hash is the lookup key, and an `encrypted`-cast copy (APP_KEY) lets admins re-copy links. Regeneration replaces the hash, so old links fail immediately. Unknown, replaced, and expired links share one neutral 404 page. Customer/staff responses send `no-store`, `no-referrer`, and `noindex`. Throttles are 30/min for GET and 10/min for POST.

**Steps (Owner simplification after review, 2026-10-07).** Manual tracking stays short: created → details → paid → shipped (driver/J&T booked and parcel leaving; courier required, J&T tracking number required) → received. Pickup: paid → collected. The customer link may mark received only while shipped; staff/admin can mark it otherwise.

**Single transition authority.** `App\Actions\Orders\OnlineOrderWorkflow` handles every edit and step:
- Each mutation locks the row and records its event in the same transaction.
- A step requires `from` = current stage and `to` = the next step for this fulfillment (pickup has no shipping step).
- Replaying an already-applied step is a no-op, so double taps are safe.
- Admin detail edits require the current `revision`, so customer/staff/other-tab changes are never overwritten.
- Admins can revert one step or restore a cancelled order; both are recorded as events.
- Payment requires a method; shipment requires a courier, and a J&T shipment also requires a tracking number.

`CreateOnlineOrder` accepts published products with an active positive-price offer regardless of availability, because the admin confirms stock in the chat. There is no reservation. The snapshot price is never rewritten by later catalog changes.

**Shipping money**, kept as two separate questions:
- Customer side: added to transfer / paid to driver / free.
- Store side, paying the driver: cashier cash / admin GoPay to staff / staff advances then reimbursed.

Staff record an advance; the admin marks it reimbursed. Group messages are generated plain text (WhatsApp formatting characters flattened via `InquiryWhatsApp::plainText`). When the admin asks staff to book, the message switches to "MOHON DIPESANKAN …" and includes recipient phone and location/address. Copy uses the Clipboard API with a select-text fallback; there is no automatic group sending.

Majoo stays the system for QRIS/member/receipt/points. A `recorded_in_majoo` flag prevents double entry; there is no integration.

## Consequences and limits

- The website now holds customer personal data indefinitely. Admin accounts and the staff link holders can read it. A leaked staff link exposes that order until it is regenerated or 7 days after closing; a leaked customer link until 30 days after closing.
- Staff attribution is a typed name, not an identity. Shared admin sessions cannot distinguish people (same limit as ADR-031).
- Items cannot be edited after creation (cancel and recreate). There are no discounts/price overrides, no keep/titip rule, no stock reservation, no payment gateway, and no automated WhatsApp sending.
- Rollback: revert the code. Do not drop populated order tables without an approved data decision. The migration's `down()` exists for empty local/staging rehearsal only.
