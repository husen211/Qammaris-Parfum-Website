# ORD-08 verification — courier cost recorded once on the Website (2026-10-17)

Branches: Website `modernization/ord-08-website-courier-cost`, App `feat/ord-08-website-courier-cost`. Local only; nothing deployed.

## Automated

| Side | Check | Result |
|---|---|---|
| Website | `phpunit` full suite (PHP 8.4, `memory_limit=1G`) | 768 tests OK, 7 skipped |
| Website | `OrderCourierCostTest` (5), `OrderApiContractTest` (17) | OK |
| App backend | `vitest` full suite | 103 files, 1259 tests OK (before the username fallback); `online-orders-website-costs` 5/5 after it |
| App frontend | `OnlineOrders.test.tsx`, `OrdersPlan0023.test.tsx` | 35 OK |
| App | `tsc --noEmit` backend + frontend; ESLint on changed files | clean |

## Joint local run (Website 8011 + App 8000, synthetic data, removed afterwards)

1. QAM-0008 (local delivery, paid, packed). An empty "Kurir sudah dipesan" is refused with "Isi ongkir ke kurir dan uang siapa", and nothing is booked.
2. Maxim, Rp15.000, **Andi** → Website: "reimburse sedang dikirim ke Qammaris App".
3. Webhook → App import → reimbursement **Approved** for the employee with username `andi` (local display name "Test Team"), Rp15.000, waiver by the local Owner → reported back → Website: "reimburse disetujui".
4. QAM-0009 (J&T): Request pickup with Rp27.000, **Ikrar** → App reimbursement Approved for `ikrar`, Rp27.000 → Website "reimburse disetujui".
5. Staff group message line: `Buka pesanan: http://127.0.0.1:8011/admin/orders/task/<public id>`.

Screenshots (after): `after-validation-390.png`, `after-booking-form-390.png`, `after-approved-390.png`, `after-jnt-form-390.png`, `after-jnt-approved-390.png`, `after-approved-1440.png`, `after-edit-1440.png`.

The "before" state (Maxim + "Sudah dipesan" only; "Toko bayar driver dengan" under Ongkir for Super Admin) was not captured on this run.

Cleanup:
- The synthetic Staff Order account and both orders were removed by restoring the local SQLite backup taken before the run, then re-applying the additive migration.
- App test records (2 reimbursements, 2 expenses, 2 projections, 10 webhook events, 4 notifications) were deleted from local `qammaris_dev`, scoped to the two synthetic order IDs.

## Not verified

- Production names: the App accounts for Andi/Ikrar must have that display name or username, and the production App Owner must be in `QAMMARIS_APP_OWNER_IDS`.
- The App order screen in a real browser (covered by the component test only).
- Real phones and Safari.

## Round 2 — Owner follow-up (2026-10-17)

Same setup. Synthetic accounts `ord08-owner` (Super Admin) and `ord08-staff` (Staff Order), with two orders. Everything was removed afterwards by restoring the local DB backup taken just before the run and deleting the App records of the two order IDs.

1. **Super Admin.** Typed ongkir `10000` shows as **10.000**. Saved as "ditambahkan ke transfer", then Tandai Lunas (amount shown 809.000). The packing section shows "Menunggu packing oleh staf toko", with no packing button.
2. **Staff Order.** Packed both orders. "Uang siapa" listed the local App's real employees: QA Staff / Test Team / Test Team 2 / Kasir / Customer bayar driver. Booked with **Test Team**, Rp12.000 → the App created an **Approved** reimbursement for that account, linked by ID → Website shows "reimburse disetujui".
3. **Super Admin booking.** Options: Uang saya (GoPay Owner) / Kasir / Customer bayar driver, with no employee names. The cost was saved as `owner_fund` with no reimbursement. Found and fixed: the summary first read "dibayar customer ke driver"; it now reads "uang Owner (GoPay)" (regression test added).
4. **Qammaris App as Admin Toko (5173).** No "Dilakukan oleh" and no action buttons. Shows "Ongkir dari customer: Rp 10.000 · sudah termasuk pembayaran customer", "Rp 12.000 uang Test Team" and "Reimburse: Disetujui, menunggu dibayar", plus "Buka di Website".

Screenshots: `round2-*.png` (390px and 1440px).

Tests: Website 770 + 7 courier-cost tests, JS 69; App backend 1262, App frontend 1386 (+1 timing-only failure in `button.test.tsx`, which passes 23/23 when rerun alone).
