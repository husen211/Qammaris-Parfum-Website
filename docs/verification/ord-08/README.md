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
