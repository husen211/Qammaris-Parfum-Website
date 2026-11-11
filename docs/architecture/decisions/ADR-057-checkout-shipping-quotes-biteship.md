# ADR-057 — Checkout prices ongkir through Biteship

Status: accepted (ORD-13, Owner 2026-11-11, card #160). Stage A of automatic ongkir; stage B (booking the courier from the admin, driver tracking) is separate.

## Context

Since ORD-04 a website order waits for Staff Order to type the ongkir before the total is final; the customer only sees "Menunggu konfirmasi staf". ADR-044 left `shipping_estimate` and `shipping_estimate_source` empty for this phase. The Owner first considered a Maxim-based table per kelurahan, then chose Gojek/Grab and J&T through one aggregator: Maxim has no public price API, and an aggregator can later book the courier too. A test account on Biteship (Starter plan, free; rates Rp5, tracking Rp10, maps Rp2 per request) priced GoSend and GrabExpress in Palu (Lolu Utara to Mamboro, Rp12.000–Rp39.500) and J&T EZ in Palu and to Makassar on 2026-11-11.

Owner decisions (2026-11-11):

- The customer picks GoSend or GrabExpress (instant, from a map pin) or J&T (from the destination area, also inside Palu).
- Instant ongkir from Rp20.000 gets 15% off; J&T 30% off, at most Rp30.000. Shown as a struck price with "Potongan ongkir N% dari Qammaris".
- Every product counts as 800 g (box and bubble wrap included), per unit.
- The ongkir charged at checkout never goes up for the customer; a costlier courier is the store's difference (J&T: the employee pays and is reimbursed as before).
- Location: our own centred pop-up before the browser's permission prompt, and a map that is easy to read.

## Decision

- **Server-side quotes.** `BiteshipClient` (area search, courier rates; errors never carry the key or a body) and `ShippingQuotes` (discount, session storage). `POST /cart/checkout/ongkir` prices instant couriers from `latitude/longitude` (pins outside a Palu bounding box are refused before Biteship is called) or J&T from a Biteship `area_id`; `GET /cart/checkout/wilayah` searches areas. Each returned option is stored in the session for 30 minutes, bound to the cart fingerprint (`checkout_quote`), and only its id goes to the browser. The browser never sends a price.
- **Discount in one place.** `ShippingQuotes::discounted()`: percent off from a threshold, optional cap, charged amount rounded down to Rp500, so rounding only adds to the customer's discount. Values live in `config('orders.shipping_quotes')`.
- **Locked on the order.** `CreateOnlineOrder::fromWebsiteCheckout` writes the picked option: `shipping_fee` = charged, `shipping_payer` = `added_to_transfer`, `shipping_estimate` = Biteship price, `shipping_estimate_source` = `api`, and the new nullable JSON `shipping_quote` (kind, provider, label, service, eta, price, charged, discount, percent, destination pin or area, weight, quoted_at). The total is final at once (`OnlineOrderStepper::totalIsFinal`). `OnlineOrderDetails::updateByAdmin` refuses a higher `shipping_fee` or "bayar ke driver" for such an order; lowering or free ongkir stays possible. The customer link no longer offers the ORD-10 choice for it.
- **Never a dead end.** No courier, Biteship down, a pin outside the area or an empty search offers "Ongkir dikonfirmasi admin" (`shipping_option = manual`), which is the ORD-04/ORD-10 flow unchanged.
- **Flag.** `ORDERS_SHIPPING_QUOTES` (default off) and a `BITESHIP_API_KEY` starting with `biteship_`; without both the checkout is exactly as before and the two endpoints return 404.
- **Front end.** `resources/js/checkout-shipping.js` (10 kB) on the checkout only; Leaflet 1.9.4 with OpenStreetMap tiles is a lazy chunk (≈44 kB gzip) loaded when the map opens. A fixed centre pin over a movable map, kecamatan shortcuts, "lokasi saya", a native `<dialog>` for the pop-up and the map, and an ARIA combobox for the J&T area. New dependency justified by the requirement: instant prices need coordinates.
- **Limits.** Rate limits `shipping-quote` (12/min, 120/h per IP) and `shipping-area` (30/min, 300/h), since each call is a paid Biteship request.

## Consequences

- The customer sees the final total before ordering; staff confirm stock only. The WhatsApp message carries the ongkir and total.
- The store carries the discount and any difference between the quoted and the booked price; `shipping_estimate` vs `shipping_fee` vs the recorded courier cost (ORD-08) shows it.
- The customer's pin is stored with the order and sent to Biteship; it is personal data like the address (same retention, hidden from `toArray()`, never logged).
- Order API v1 and the App are unchanged: `shipping_fee`/`shipping_payer` already feed the totals; `shipping_quote` is not serialized.
- Rollback: turn the flag off. The migration only adds a nullable column.
