# ORD-13 — Automatic ongkir at checkout

Owner 2026-11-11 (card #160). Decision and limits: [ADR-057](../../architecture/decisions/ADR-057-checkout-shipping-quotes-biteship.md). Business rules: "Ongkir otomatis di checkout (ORD-13)" in [BUSINESS_RULES](../../product/BUSINESS_RULES.md). Account and key: [runbook](../../runbooks/BITESHIP.md).

## Biteship account (Owner's Chrome, 2026-11-11)

- Starter plan, team = the Owner only; personal verification "Sedang ditinjau" (submitted, not yet approved).
- Couriers installed by the Owner: Gojek, Grab, J&T (3 of 3). Pickup address corrected with the Owner's permission: area Palu Barat (Biteship 94221; 94111 maps to Mantikulore/Palu Timur in Biteship), detail "Qammaris Perfumes, Jl. Sis Aljufri, Siranindi (depan Hotel Palu City), …"; the pin (Jl. Sis Aljufri) was not moved.
- Test key "qammarisAPI" (Rates, Order, Tracking) created by the Owner and pasted into the local `.env` through a hidden prompt; the agent never saw the value. `.env` is ignored by Git.

## Live rate check (test key, from the store, 500 g, before the build)

| Destination | GoSend | GrabExpress |
|---|---|---|
| Lolu Utara | Rp15.000 | Rp12.000 |
| Birobuli Selatan | Rp23.000 | Rp20.000 |
| Tondo | Rp31.000 | Rp28.000 |
| Mamboro | Rp39.500 | Rp37.000 |
| Pantoloan | Rp70.500 | — |

J&T EZ (2–3 days): Palu Rp8.000, Makassar Rp23.000. A pin in the sea returned code 40001010 (no courier). About 12 paid requests in total.

## Local check (2026-11-11)

Two synthetic products ("Uji Ongkir Oud" 100 ml Rp425.000, "Uji Ongkir Musk" 50 ml Rp189.000; local catalog products are all "unknown" availability) and the real Biteship test key.

- Kurir instan, pin in Palu Selatan (±3,6 km): GoSend Rp20.500 → **Rp17.000** with "Potongan ongkir 15% dari Qammaris", GrabExpress Rp18.000 (under Rp20.000, no discount); the cheapest charged option is preselected; total 803.000 + paperbag 4.000 + ongkir = Rp824.000.
- J&T to Makassar, 3 products (2,4 kg → 3 kg): Rp69.000 → **Rp48.000**; J&T to Palu Barat: Rp24.000 → Rp16.500.
- Switching instant → J&T → instant keeps each delivery's own pick (one radio group; fixed during the check).
- Order QAM-0019 (synthetic): `shipping_fee` 17.000, `shipping_estimate` 20.500 (`api`), `shipping_quote` GoSend, total final; customer page shows the struck price and discount; WhatsApp text "Ongkir GoSend: Rp 17.000 (potongan ongkir 15% dari Qammaris)", "Total: Rp 824.000", "Mohon konfirmasi stok dan pembayarannya."; admin shows courier price, discount, charged amount and "Buka titik antar di Maps" outside the collapsed ongkir form.
- Location: the built-in browser pane has location blocked, so it went straight to the map with the "Izin lokasi diblokir" hint (by design); headless Chrome (prompt state) showed our centred pop-up first. The dialog backdrop needed plain CSS (`::backdrop` did not get Tailwind's variables).

| View | Before | After |
|---|---|---|
| 390 delivery choice | [before-390-delivery](before-390-delivery.jpg) | [after-390-delivery](after-390-delivery.jpg) |
| 390 location pop-up | — | [after-390-location-popup](after-390-location-popup.jpg) |
| 320 location pop-up (272 px wide, whole card visible) | — | [after-320-location-popup](after-320-location-popup.jpg) |
| 390 map, fixed centre pin, kecamatan shortcuts | — | [after-390-map](after-390-map.jpg) |
| 390 couriers with struck price | — | [after-390-couriers](after-390-couriers.jpg) |
| 390 order summary | [before-390-summary](before-390-summary.jpg) | [after-390-summary](after-390-summary.jpg) |
| 390 J&T area search / price | — | [after-390-jnt-search](after-390-jnt-search.jpg), [after-390-jnt](after-390-jnt.jpg) |
| 1440 pop-up / map / couriers | — | [after-1440-location-popup](after-1440-location-popup.jpg), [after-1440-map](after-1440-map.jpg), [after-1440-couriers](after-1440-couriers.jpg) |
| Admin ongkir block 390 / 1440 | — | [after-390-admin-ongkir](after-390-admin-ongkir.jpg), [after-1440-admin-ongkir](after-1440-admin-ongkir.jpg) |

## Owner review on localhost (2026-11-11) and fixes

The Owner found, in their Chrome:

1. **Map tiles "Access blocked" (OpenStreetMap 403).** Cause: the checkout's `Referrer-Policy: no-referrer`; a tile fetched without a Referer returns the 6,7 kB "Access blocked" image, with a Referer the real 38 kB tile (checked with curl on tile 15/27294/16466). Fix: the tile layer sends `referrerPolicy: strict-origin` (origin only). Not related to location permission.
2. **No usable map without location permission / after "allow once" and a refresh.** The map now opens in every case with its own hint (allowed, refused, refused earlier, no GPS, unsupported) and a **place search** (Photon) above the kecamatan shortcuts.
3. **Pin jumped down-right while dragging.** Cause: the lift effect set the CSS `translate` property, which replaced Tailwind's centring translate. The lift was removed; the pin is static.
4. **Round locate icon unclear.** Now a labelled pill "Temukan lokasi saya".

Re-checked in headless Chrome through the DevTools protocol (permission set per case):

| Case | Result | Screenshot |
|---|---|---|
| Location refused | No pop-up, map opens with the refused hint, real tiles load, search "Basuki Rahmat" lists 4 streets (RT/RW removed from the detail), choosing one moves the map, "Pakai titik ini" prices GrabExpress Rp19.000 / GoSend Rp18.000 | [cond-denied-map-390](cond-denied-map-390.jpg), [cond-search-390](cond-search-390.jpg), [cond-search-chosen-390](cond-search-chosen-390.jpg) |
| Mouse drag of the map | During the drag the pin centre (195, bottom 419) equals the map centre (195, 419) | [cond-dragging-390](cond-dragging-390.jpg) |
| Location allowed (position emulated at −0,9045, 119,8721) | No pop-up, map jumps to the position, "Lokasimu ditemukan", pin ±1,6 km from the store | [cond-granted-map-390](cond-granted-map-390.jpg) |
| Never asked | Our pop-up first; "Izinkan lokasi" then the browser refuses (headless) and the map still opens with the hint | [cond-prompt-popup-390](cond-prompt-popup-390.jpg), [cond-after-allow-390](cond-after-allow-390.jpg) |
| Desktop 1440 | Search, shortcuts, labelled locate button, full tiles | [cond-map-1440](cond-map-1440.jpg) |

Chrome's real "Allow this time" prompt and a phone's GPS cannot be driven headless; every outcome they can produce (granted, refused, unavailable) is covered above.

## Checkout tidy-up and payment step (Owner 2026-11-11)

Synthetic products and orders again (QAM-0020: 1 pcs J&T to Palu Barat; QAM-0021: 2 pcs), temporary admin removed afterwards.

| View | Result | Screenshot |
|---|---|---|
| Checkout 390, J&T | Destination, J&T Rp8.000 → Rp5.500 (30%), then one address field; no kelurahan/kecamatan/postcode, no payment choice; button "Lanjut ke pembayaran" | [pay-390-checkout-jnt](pay-390-checkout-jnt.jpg), [pay-390-checkout-bottom](pay-390-checkout-bottom.jpg) |
| Order page, 1 pcs | "Langkah berikutnya: pembayaran", total, Transfer bank / QRIS "Segera tersedia", "Kirim bukti bayar ke WhatsApp"; no automatic WhatsApp before paying | [pay-390-pay-now](pay-390-pay-now.jpg) |
| Order page, 2 pcs | Clock "Menunggu admin cek stok", "Uji Ongkir Musk × 2 perlu dicek", WhatsApp button; no payment yet; checkout button said "Pesan & minta cek stok" | [pay-390-waiting](pay-390-waiting.jpg) |
| Admin confirmation | "Customer sudah bisa bayar langsung …" / "Perlu cek stok: Uji Ongkir Musk 50 ml × 2 …" | [pay-390-admin-pay-now](pay-390-admin-pay-now.jpg), [pay-390-admin-stock-check](pay-390-admin-stock-check.jpg) |
| Admin QRIS card 1440 | Empty state with upload (PNG/JPG/WebP, min 300 × 300, max 4 MB) | [pay-1440-admin-qris](pay-1440-admin-qris.jpg) |

Local has no verified bank account and no store WhatsApp number (ORD-07), so the page says the account is sent on WhatsApp and shows the proof text for copying; with accounts and a store number it lists them and the button opens WhatsApp (covered by tests).

## Tests

- `CheckoutShippingQuoteTest` (11, four for the payment step: single pieces pay at once and the choice opens WhatsApp; 2 pcs or staff-priced ongkir wait and payment opens after confirmation; QRIS upload rights, size check, payment with QRIS and removal; J&T fills kecamatan and postcode). Earlier seven: instant pricing and discount, the request sent to Biteship (key header, couriers, 800 g × quantity, store origin), order locking and display, WhatsApp text; J&T area search and pricing; discount rules; forged / other delivery / expired / other cart options refused; no courier, Biteship down and outside the area fall back to "dikonfirmasi admin"; staff may lower but not raise or send to the driver; flag off or no key leaves the checkout as before. The pin becomes `location_url`.
- `tests/js/checkout-shipping.test.mjs` (7): preselecting the cheapest charged option, discount text, distance text, when our pop-up shows, every failure keeps a way to order, a hint for every location outcome, place search results (labels, no duplicates, no RT/RW).
- Full suite: 844 passed, 10 skipped, 3 known local-only failures (J&T QR without GD ×2, ReleaseCheck VAPID). JS 101 passed. Pint on the changed files passed. `npm run build`: `checkout-shipping` 10 kB (4,2 kB gzip), lazy `map` 150 kB (43,6 kB gzip).

## Open limits

- Physical iPhone/Android not tested: the native location prompt and GPS accuracy were not exercised (headless and pane only).
- Live-mode prices not checked yet (test key); stage B (booking from the admin, webhook, driver tracking) not started and needs the Biteship verification approved.
- OpenStreetMap standard tiles and Photon search are used under their fair-use policies (low volume, attribution, identifying referrer).
