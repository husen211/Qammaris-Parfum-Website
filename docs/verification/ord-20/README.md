# ORD-20 verification: pin on the order link, so admin-made orders book the courier (local, 2026-11-12)

The Owner asked that an order made by admin also gets a delivery pin, like an instant website order, so staff can book the courier through Biteship from the admin.

## Change

- **The order link (customer page)** shows **Titik antar** for an in-city order without a checkout pin:
  - "Tentukan titik di peta" opens the same map as the checkout: place search, kecamatan shortcuts, "Temukan lokasi saya", and a fixed pin;
  - "Pakai titik ini" saves the point. It must be in Palu and surroundings.
  - Afterwards the page shows "Titik antar tersimpan" with "Lihat di Maps" and "Ubah titik".
  - "Atau kirim Sharelok lewat WhatsApp" stays as a second way when the store WhatsApp is live.
- **The pin is saved** as `https://www.google.com/maps?q=lat,lng` in the order's location link. Staff, the App and Maps use it as before.
- **The pin is locked** once a courier is booked or the parcel is handed over.
- **Admin:**
  - "Pesan kurir sekarang" appears for any packed, paid, in-city order with a pin, labelled "Titik antar dari link pesanan".
  - Without a pin there is a hint to ask the customer to set it on the link, or to paste a Google Maps link with coordinates. Manual booking stays.
- **Booking** sends that pin as the destination.

## Checks

| Check | Result |
| --- | --- |
| PHP (`BiteshipCourierBookingTest`) | Admin-made order: hint, no button. The customer sets the pin on the link (outside Palu refused). The link is saved and the event recorded. Staff see the button, and Biteship gets the pin. Once booked, the link hides the map and refuses a new pin. Coordinate links parse; short Sharelok links and points outside Palu do not. A website instant order keeps its checkout pin. 10 passed. |
| Checkout after moving the map and search into shared files | Pin on Palu Selatan still fills the kecamatan and prices Instan + J&T (headless, 390) |
| 390, order link of a synthetic admin order | Titik antar card ([shot](after-390-link-no-pin.jpg)); the map with kecamatan shortcuts ([map](after-390-link-map.jpg)); after "Pakai titik ini": "Titik antar tersimpan. Terima kasih." ([saved](after-390-link-pin-saved.jpg)) |
| 1440 | Saved state and map ([saved](after-1440-link-pin-saved.jpg), [map](after-1440-link-map.jpg)) |

- The admin card itself was checked through the PHP test (page markup), not with a screenshot.
- The synthetic order, its customer record and its rows were removed after the check.
