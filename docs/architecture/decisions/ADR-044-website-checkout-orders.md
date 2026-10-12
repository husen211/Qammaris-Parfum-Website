# ADR-044: Website cart checkout saves a guest order

Status: Proposed 2026-10-10 (ORD-04, branch `modernization/ord-04-cart-checkout`). Not merged, not deployed. Behind the flag `ORDERS_WEBSITE_CHECKOUT` (default off; also needs `ORDERS_V2_ENABLED`).

Partially supersedes [ADR-028](ADR-028-whatsapp-order-checkout.md): with the flag on, the cart checkout stores the recipient data in an order. ADR-028's catalog re-check, review fingerprint, no-store/no-referrer, and "opening WhatsApp is not proof" rules stay. With the flag off, ADR-028 applies unchanged.

## Problem

The Owner asked for cart orders to be saved permanently, with an order number, before the customer goes to WhatsApp (ORD-04, 2026-10-10). Without this:
- a lost or unsent chat loses the order;
- staff retype the cart into the order system.

There must be no second order system, no fake admin identity, and no customer login.

## Decision

1. **One domain.** `CreateOnlineOrder::fromWebsiteCheckout()` creates a normal V2 `online_orders` row. Items are price snapshots, the order gets the usual tokens and code, and the existing outbox applies.
   - It does not reuse the admin `handle()`, which requires `orders.manage`.
   - The order has `created_by = null` and `source = website`.
   - The `created` event uses `actor_type = customer`.
   - No user account is created or impersonated.
2. **Starts as `draft`, shown as "Menunggu konfirmasi website".** The lifecycle already exists in contract r4.2, with `queue = null`, so the App shows no task and its deep link says "Pesanan belum siap ditangani".
   - While the order is draft:
     - payment cannot be recorded;
     - packing, keep and courier steps need `active`;
     - the customer link is read-only (changes go through the chat);
     - Staff Order may set ongkir and correct details.
   - Staff Order confirms it with `OnlineOrderFulfillment::confirmWebsiteOrder()`. That is a Website admin only action: revision +1, event `website_confirmed`. The order then becomes `active` and follows the existing payment → packing → shipping → reimburse flow.
   - Cancel works as for any unpaid order.
3. **Server-side catalog check twice.**
   - The controller keeps the ADR-028 review fingerprint.
   - Inside the creating transaction, the operation re-reads every line and rejects the order with `CheckoutChanged` (no order written) if a line:
     - is no longer published, active or Tersedia, or
     - has a unit price that differs from the reviewed one.
4. **Idempotency.**
   - The checkout page issues a session-bound UUID (`checkout_key`, unique column).
   - A double tap, refresh or retry with that key returns the first order. A unique-constraint race also resolves to the winner.
   - A key that is not the session's key is refused, so one customer cannot fetch another's order.
5. **Success page = customer link** (`/pesanan/{token}`, 40-char random token, SHA-256 lookup, 7 days while unconfirmed).
   - A one-time flash makes the page open WhatsApp once.
   - Reloads and Back show "Buka WhatsApp lagi" instead.
   - The short WhatsApp message carries the order number, name, delivery type and subtotal. It does not repeat the phone number or address, which stay in the order.
   - The order is saved before WhatsApp opens, so a failed or unsent chat never loses it.
6. **Abuse limits.**
   - CSRF (web group).
   - Rate limit `website-checkout`: 6 per minute and 30 per hour per IP.
   - Validated, bounded fields with whitespace normalized; Blade escaping on output; WhatsApp text through `plainText`.
7. **No repeat-customer record from a phone number.** Linking a customer remains an explicit Staff Order action (ORD-02d).
8. **Phase-2 extension points, empty for now.**
   - `online_orders`:
     - `district` / `subdistrict` (optional at checkout);
     - `shipping_estimate`;
     - `shipping_estimate_source` (allowed sources in `config('orders.shipping_estimate_sources')`).
   - `product_variants.weight_grams` and the snapshot `online_order_items.weight_grams` are nullable with no default. An unknown weight is null, never an assumed 1 kg.
   - The final charge stays `shipping_fee`, and "Menunggu konfirmasi staf" is shown while it is empty.
   - No zone, distance, Maxim, GoSend or RajaOngkir calls are made.
9. **Contract.**
   - No API change: no new serialized field; OpenAPI r4.2 hash unchanged.
   - The App agent confirmed draft handling on 2026-10-10 (App commit `c098e81`) and asked that the read API serve drafts with 200 and queue `null`. That is verified by `WebsiteCheckoutOrderTest`.

## Consequences

- **Privacy.** With the flag on, checkout recipient data (name, phone, address, area, note) is stored like other online orders: visible to admins and the App's detail view of active orders, with no automatic deletion. That retention policy is still ORD-05.
- **Rollback.**
  - Turning the flag off restores the WhatsApp-only checkout.
  - Code rollback is safe while no guest order exists.
  - The migration's `down()` stops if guest orders exist instead of inventing creators.
- **Open-link limits.** A draft link expires after 7 days. The order itself stays visible to staff until it is confirmed or cancelled.
- **Store number in test environments (ORD-07, Owner 2026-10-10).** `StoreInfo::getWhatsappNumberAttribute` used to pin every store number to the real store.
  - **Test environments.** In `config('store.test_environments')` (`local`, `development`, `testing`, `uat`, `staging`), it now returns `STORE_WHATSAPP_TEST_NUMBER`, or nothing. The real store number is refused as a test number.
  - **No number.** No `wa.me` link is built: product inquiries, footer and store page lose the button, and the checkout success page shows the message with **Salin pesan**.
  - **Checkout without a number.** Checkout itself no longer needs a number when it saves the order.
  - **Production.** Any other `APP_ENV`, production included, keeps the previous rule. Production behavior therefore does not depend on its exact `APP_ENV` value, which is not recorded in the docs.
  - Tests: `StoreWhatsappEnvironmentTest`. PHPUnit runs with a synthetic test number.
