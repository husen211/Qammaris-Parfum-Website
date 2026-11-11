# ORD-14 verification — courier booking through Biteship (local, 2026-11-11)

Branch `ongkir-otomatis`, local server `127.0.0.1:8010`, local SQLite, the Owner's Biteship **test** key (no balance used). Synthetic data only: product "Uji Ongkir Oud" (900026), order QAM-0023 placed through the real checkout (Kurir instan, pin in Palu Timur, GoSend Rp15.000), temporary Super Admin `ord14-verify@example.test` (removed after the check).

## Automated

- `tests/Feature/BiteshipCourierBookingTest.php` (7 tests, 96 assertions, Biteship faked):
  - booking sends the customer's courier, `courier_type=instant`, `reference_id=<code>-1`, the checkout pin and 800 g per item; a second click books nothing;
  - webhook `allocated` saves the driver quietly (same revision) and shows it to staff and the customer (no driver phone, no Sharelok request); `picked` = handover + cost `owner_fund` Rp21.000 reported by "Biteship", event by "Biteship (otomatis)"; `delivered` = received; one cost only;
  - a refusal shows Biteship's reason, keeps the step free, and a retry is attempt 2 (`-2`);
  - `courierNotFound` (camelCase) frees the step; cancel sends the reason and is attributed to the admin; no cost;
  - only packed instant orders with the pin get the button; staff-made orders and the flag off keep the manual form and send nothing;
  - webhook: wrong or missing secret → 404, empty body (Biteship's URL check) → 200, unknown order → 200 without a Biteship call;
  - website order payment card: total, "Cara bayar", no "Salin Total & Instruksi Pembayaran".
  - the booking admin's account removed before pickup: the cost is still recorded, without a recorder.
- `OrderCourierCostTest`: Super Admin sees "Konfirmasi packing".
- Full suite: 854 passed, 10 skipped, 3 failed — the known local-only failures (two J&T QR tests need PHP GD, `ReleaseCheckTest` needs VAPID keys).

## Real Biteship test mode (from the admin page)

1. Konfirmasi pesanan → Tandai Lunas (payment card without the instruction block) → Konfirmasi packing as **Super Admin**.
2. **Pesan kurir sekarang** (GoSend): Biteship accepted, status "Mencari driver", ongkir kurir Rp15.000, tracking link on `track.biteship.com`.
3. **Perbarui status**: re-read from Biteship, still `confirmed`.
4. **Batalkan kurir** with a reason: Biteship reports the order `cancelled`; the step is free again ("Kurir sebelumnya: Dibatalkan").
5. **Pesan kurir sekarang** again with GrabExpress: accepted, Rp11.000 (attempt 2).
6. After the last UI tidy-up (switch hidden while booked, quiet handover fallback, customer's courier always preselected): cancelled again (attempt 3) and booked GoSend again; attempt 4 stays active ("Mencari driver") for the Owner's check.
7. Webhook on the running server: empty body 200, wrong secret 404, a body claiming `allocated` for the real order → 200 and the status stayed `confirmed` (the body is never trusted; the order is re-read).

Not done: moving the test order to later statuses in Biteship's dashboard needs a "Webhook URL (Test)" in the Owner's Biteship settings (an account setting); the later statuses are covered by the automated tests.

## Screenshots

| Before | After |
|---|---|
| Super Admin: "Menunggu packing oleh staf toko" ([ORD-08](../ord-08/round2-owner-3-packing-by-staff.png)) | ![Super Admin packing](after-390-super-admin-packing.jpg) |
| Manual "Kurir sudah dipesan" form only ([ORD-08](../ord-08/after-booking-form-390.png)) | ![Book through Biteship, 390](after-390-admin-biteship-book.jpg) ![1440](after-1440-admin-biteship-book.jpg) |
| — | ![Booked, 390](after-390-admin-biteship-booked.jpg) ![1440](after-1440-admin-biteship-booked.jpg) |
| — (customer page had no courier block) | ![Customer courier](after-390-customer-courier.jpg) |
| Website order payment card with "Salin Total & Instruksi Pembayaran" (unchanged for staff-made orders) | ![Payment card, 390](after-390-admin-payment-website.jpg) ![1440](after-1440-admin-payment-website.jpg) |

## UX notes

- One primary action per state: "Pesan kurir sekarang" while unbooked; once booked, the status card leads and "Sudah diserahkan ke kurir" becomes a quiet fallback with a one-line explanation.
- The customer's courier is preselected and labelled "pilihan customer"; the other stays one tap away for a driver-not-found retry.
- The "Toko / Customer" switch is hidden while a Biteship driver is booked (cancel first), so staff cannot pick a state the server refuses.
- The customer sees plain Indonesian statuses and "Lacak driver"; the driver's phone stays with staff.
