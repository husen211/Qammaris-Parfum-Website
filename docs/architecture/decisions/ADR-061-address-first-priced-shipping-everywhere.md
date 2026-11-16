# ADR-061: Address first, then priced shipping, on the checkout, the order link and the admin

Status: accepted (ORD-26, Owner 2026-11-16). Released only on the Owner's explicit go.

## Context

The checkout (ORD-16/ORD-19) asked "Dikirim ke alamat / Ambil di toko" first and then a Palu-only map pin before the kecamatan. The order link sent for admin-made and WhatsApp orders asked "Ambil di toko / Pengiriman Dalam Kota / Kirim ke luar kota" first and never showed a price: staff typed the ongkir by hand. The Owner: customers do not know the store is in Palu, so they cannot answer "dalam atau luar kota"; the address must come first and the shipping choices with prices after it. Staff filling an order themselves also had no automatic ongkir, and the Biteship driver step stayed out of sight until payment and packing.

## Decision

- **One shipping block** (`cart/_shipping.blade.php` + `checkout-shipping.js`) for the checkout and the order link: kecamatan or city first (Biteship area search), the map pin only for a destination around Palu (Palu, Sigi, Donggala), the full address, then Instan (after the pin), Reguler (J&T) and Ambil di toko (store address shown). Ambil di toko is a shipping choice (`shipping_option=pickup`), not a question before the address.
- **Prices stay on the server.** `ShippingQuotes` keeps every option it priced; the browser sends only an option id. The checkout's options are bound to the checkout key and the cart; an order's options (`forOrder()`) to the order id and its lines (`orderCart()`). The order link asks `POST pesanan/{token}/ongkir`.
- **Order link**: priced only while the details apply directly (awaiting the customer, or editable before packing), nothing is paid, no courier is booked, the ongkir is not free, and staff have not typed it by hand (`OnlineOrder::customerPicksShipping()`). Otherwise the form stays as before (ORD-23 pin).
- **Staff (A)**: "Hitung ongkir otomatis" in the Ongkir box prices an order staff filled: instant to its pin, J&T to its postcode (`jntToPostcode`), with the checkout's discount; staff choose one ("Pakai"). Only before any payment, without a booked courier, and never over a checkout price (`staffPricesShipping()`). Recorded in history with the old and new ongkir.
- **Who chose a price** is kept in `shipping_quote.chosen_by` (`customer_link` / `staff`; absent for the checkout). A checkout price keeps its locked rules and its pin. A chosen price may be chosen again before payment; moving the pin drops an instant price chosen for the old pin (`dropChosenShippingQuote()`), and its pin is read from `location_url`, where staff may move it.
- **Driver step (B)**: the upcoming Pengiriman step shows "Pesan driver Biteship setelah: Titik antar / Lunas / Packing" with what is done and what is missing.

## Consequences

- An admin-made or WhatsApp order whose customer picks a priced courier has a final total at once and can be paid straight from the link.
- A customer who reopens the link to change details picks the shipping again (the option id is not stored on the order).
- J&T for staff uses the postcode, not a searched area; an order without a postcode shows what is missing instead of a price.
- Rejected alternative: keeping "dalam/luar kota" and adding prices after it would still ask the customer something only the store knows.
