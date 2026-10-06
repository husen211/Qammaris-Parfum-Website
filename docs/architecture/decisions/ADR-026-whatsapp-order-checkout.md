# ADR-026: Recipient checkout and WhatsApp ordering

Status: Accepted by Owner for implementation; release pending. Date: 2026-10-06.

## Problem and decision

The existing basket asks customers about stock and omits recipient information, although the connected app already controls availability and price. Owner now requests direct ordering with name, phone and full shipping address, still delivered through WhatsApp. This explicitly supersedes ADR-020's inquiry-only public journey, not its route/query safety or current-database resolution.

Keep session cart and Blade. Add a checkout GET to the existing POST path. Require bounded recipient fields at the server; postcode and note optional. Only available, published products with active, positive-price offers can proceed. Sold-out/OTW remain visible with restock contact; unknown remains neutral/contact-only. Do not inspect legacy variant numeric stock or add availability expiry.

Resolve current product/offer data at checkout GET and POST. Bind the review to a server/session fingerprint of identity, quantity, name, size, price and availability; reject changed reviews before redirecting. This small guard prevents ordering at a price unseen by the customer, without inventing DTOs, repositories or a persistent quote system.

WhatsApp message contains items, quantities, current prices, subtotal and recipient data. Shipping fee/payment stay in the chat. Opening the composer does not prove sending, order acceptance or payment; keep the cart for retries. Customer fields are not persisted in catalog/order tables or logged by application code. Validation recovery uses temporary Laravel session old input. HTTPS hosting remains required; checkout and outgoing redirect use no-store/no-referrer. The destination WhatsApp URL necessarily contains the recipient text; browser/WhatsApp retention is outside this application and production proxy/APM body logging must be verified before release.

## Future payment gateway plan (not implementation)

1. Define actual order acceptance, shipping fees/service areas, numerical quantity handling and cancellation with Owner. Current boolean availability cannot reserve a requested quantity.
2. Add persistent orders and immutable order lines with reviewed prices, recipient access/retention and explicit unpaid/paid/cancelled states through a separately approved schema change. Give retries an idempotent order identity; never treat a browser return as payment proof.
3. Choose a provider after these rules are settled. Keep payment calls in a focused Laravel operation, validate signed provider notifications, enforce idempotent status transitions/amount checks and reconcile payment failures. Use a sandbox first, then approved release.

No provider, database schema, shipping price or payment claim is invented in P7-12. No new dependencies. Revert the bounded code commit to restore the old journey; existing sessions/catalog/media remain compatible.
