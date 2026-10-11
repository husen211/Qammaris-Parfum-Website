# ORD-04 — Website Cart Checkout Integration (verification)

Date: 2026-10-10. Branch `modernization/ord-04-cart-checkout`, stacked on ORD-03 (`5468482`). Not merged, not deployed. Decision record: [ADR-044](../../architecture/decisions/ADR-044-website-checkout-orders.md).

Flags used in all evidence:
- `ORDERS_V2_ENABLED=true`
- `ORDERS_SIMPLE_UX=true`
- `ORDERS_WEBSITE_CHECKOUT=true`

With `ORDERS_WEBSITE_CHECKOUT` off, the old WhatsApp-only checkout is unchanged (tested).

## Flow before → after

| Step | Before (ADR-028) | After (ORD-04) |
|---|---|---|
| Checkout form | Name, phone, required address, postcode, note | Name, phone, **Cara pengiriman** (Ambil di toko / Pengiriman Instan — Kota Palu, 1–2 jam / Pengiriman Luar Kota — J&T), address only for delivery (+ optional kelurahan, kecamatan, kode pos), **Paperbag**, **Pembayaran** (Transfer bank / QRIS), note. Ongkir: "Menunggu konfirmasi staf". |
| Submit | Redirect straight to `wa.me`; nothing stored; cart kept for retry | Order saved (`QAM-xxxx`, lifecycle `draft` = "Menunggu konfirmasi website"), cart emptied, redirect to the order link. That page opens WhatsApp once and offers **Buka WhatsApp lagi**. |
| WhatsApp message | All items, prices and the full recipient data | Short: order number, name, delivery type, subtotal, request to confirm stock/ongkir/payment |
| Staff | Retype the chat into an order | Banner "N pesanan website menunggu konfirmasi" → order page opens on **Konfirmasi pesanan website** (details, 3-step checklist, ongkir in Pembayaran) → **Konfirmasi pesanan** → normal payment/packing/shipping |
| App | — | Draft: `queue=null` (no task). After confirm: `active`, `needs_handling` |

## Screenshots

Captured in real headless Chrome against a local server with a temporary SQLite DB and synthetic data, at 390×844 (touch) and 1440×900. Before = ORD-03 head `5468482`.

| | 390 | 1440 |
|---|---|---|
| Before: checkout | [390](before-checkout-390.jpg) | [1440](before-checkout-1440.jpg) |
| Before: validation | [390](before-checkout-errors-390.jpg) | [1440](before-checkout-errors-1440.jpg) |
| After: checkout | [390](after-checkout-390.jpg) | [1440](after-checkout-1440.jpg) |
| After: validation (all six fields) | [390](after-checkout-errors-390.jpg) | [1440](after-checkout-errors-1440.jpg) |
| After: filled (instant Palu) | [390](after-checkout-filled-390.jpg) | [1440](after-checkout-filled-1440.jpg) |
| After: saved / WhatsApp retry | [390](after-success-390.jpg) | [1440](after-success-1440.jpg) |
| Staff: order list banner | [390](after-admin-orders-390.jpg) | [1440](after-admin-orders-1440.jpg) |
| Staff: confirm step | [390](after-admin-confirm-390.jpg) | [1440](after-admin-confirm-1440.jpg) |
| Staff: after confirm (payment next) | [390](after-admin-confirmed-390.jpg) | [1440](after-admin-confirmed-1440.jpg) |
| Customer: after confirm | [390](after-customer-confirmed-390.jpg) | [1440](after-customer-confirmed-1440.jpg) |

### Browser results (both widths)

| Check | Result |
|---|---|
| Horizontal overflow | 0 px on every page |
| Inputs under 16 px on touch | none |
| Validation errors shown | 6 fields (`customer_name`, `customer_phone`, `delivery`, `customer_address`, `packaging`, `payment_preference`) |
| WhatsApp after save | opened automatically once (request recorded and blocked in the test browser, so nothing reached WhatsApp); reload shows "Buka WhatsApp lagi" |
| Back to `/cart/checkout` after the order | redirects to `/cart` (empty cart, no second order) |
| Staff confirm | chip "Aktif", next step "Pembayaran · Belum dibayar · Rp 925.000" (Rp 910.000 + ongkir Rp 15.000 set while draft) |
| JS errors | none |

Small targets flagged:
- "Ada masalah" on the admin page is the existing ORD-03 summary link and is unchanged.
- The 1440 admin filter inputs at 14 px are desktop-only (the 16 px rule targets touch).

## WhatsApp in test environments (ORD-07, before the UAT switch)

Real headless Chrome, `APP_ENV=uat`, isolated local instance, 390 (touch) and 1440.

| Mode | Result at 390 and 1440 |
|---|---|
| `STORE_WHATSAPP_TEST_NUMBER=080000009999` | Order saved. WhatsApp opened once, only to `wa.me/6280000009999` (request blocked in the test browser). "Buka WhatsApp lagi" on reload. [390](uat-wa-test-success-390.jpg), [1440](uat-wa-test-success-1440.jpg) |
| No test number | Order saved. No `wa.me` request at all. Store number absent from the HTML. Message preview (8 lines, order number) with **Salin pesan**. [390](uat-wa-none-success-preview-390.jpg), [1440](uat-wa-none-success-preview-1440.jpg) |
| Staff confirm after either mode | Aktif → Pembayaran (Rp 925.000 incl. ongkir) |

Headless Chrome denies clipboard access, so the copy button used its fallback: the text is selected with "Salin secara manual". Clipboard copy on a real phone over UAT HTTPS is not yet confirmed.

## Manual UAT switch and joint verification (2026-10-10)

Owner approved moving the Manual UAT from ORD-03 to ORD-04. The work was coordinated step by step with the App agent.

1. App backend stopped and App UAT cleaned (`--clean`; 3 App accounts kept).
2. Website stopped. `qam_uat` identity checked (port 3397, MariaDB 11.8.9, UAT account present), then backed up to `backups\qam_uat-pre-ord04-20261010-202527.sql` (dump complete).
3. Earlier ORD-03 test data removed from `qam_uat` only: 3 orders and 2 customers, plus all events, claims, outbox, idempotency keys, jobs and QR files. Kept: 2 accounts, 8 products/variants. The smoke-evidence databases (`qam_e2e`, `qam_e2e_r42`) live on a different server and were not touched.
4. UAT worktree switched to `0ba294a`. `ORDERS_WEBSITE_CHECKOUT=true` set in the UAT environment script only. Additive migration `2026_10_10_100001` applied. No store WhatsApp number in UAT.
5. App started. Connection check passed: signed sync 200, 0 orders, contract r4.2 `b0e1f5d5…cb88`.
6. One temporary checkout order through the HTTPS tunnel:
   - **6a** phone 390, touch: catalog → 2 products → checkout ("Pesan sekarang") → **QAM-0001** draft, `source=website`, no creator, no customer record.
     - No `wa.me` request; store number absent from catalog, product, checkout and success pages.
     - "Salin pesan" preview shown; back to checkout lands on the empty cart.
     - App: webhook processed, projection `draft`/`queue=null`, not listed, detail 409 "belum siap". **4/4 PASS**.
   - **6b** laptop 1440, Staff Order: ongkir Rp 15.000 while draft (rev 2) → **Konfirmasi pesanan** (rev 3, `website_confirmed`) → Aktif, Pembayaran Rp 250.000. Customer page shows "Dikonfirmasi toko".
     - App: rev 1–3 processed, listed `needs_handling`, detail 200. **PASS**.
   - **6c** App finish_packing plus a replay with the same key: rev 5, exactly 2 new events, 2 idempotency rows, nothing from the replay. **PASS** on both sides.
7. Final clean on both sides; the verification order was removed. See the final state in the report.

Screenshots via the tunnel:
- [phone checkout](uat-tunnel-phone-checkout.jpg)
- [phone saved](uat-tunnel-phone-success.jpg)
- [laptop list](uat-tunnel-laptop-orders.jpg)
- [laptop confirm](uat-tunnel-laptop-confirm.jpg)
- [laptop confirmed](uat-tunnel-laptop-confirmed.jpg)

## Revision after Owner UAT (2026-10-11): payment gate, stepper, payment instructions

Isolated local instance (temporary SQLite, synthetic data, `APP_ENV=uat`, synthetic WhatsApp test number), real headless Chrome, 390×844 touch and 1440×900. Before = the UAT version `0ba294a` with its own `vendor`/autoloader; after = this branch. Same seed for both: five orders, one per step (unpaid without ongkir, unpaid with ongkir, paid, packed J&T, done pickup).

| Check | Before (`0ba294a`) | After |
|---|---|---|
| Unpaid order, Admin PWA "preparation" request | Order became `preparing` | Refused: "Pesanan belum lunas…", shown at the top; order stays `not_started` |
| Primary action per state (390 and 1440) | Unpaid without ongkir already offered **Tandai Lunas** | Simpan ongkir → Tandai Lunas → Konfirmasi packing → Request pickup J&T → none (done) |
| Stepper (backend state) | none | `dcttt`, `dcttt`, `ddctt`, `dddct`, `ddddd` (done/current/todo) |
| Customer link, total final | no payment block | Total Rp 925.000, verified BCA account with Salin nomor, **Kirim bukti lewat WhatsApp** → `wa.me/6280000009999` (test number) |
| Pesanan Saya | – | Two orders opened in that browser, with status "Menunggu pembayaran" / "Sedang disiapkan" |
| Catalog labels (feed fresh) | "Tersedia saat diperiksa" (manual) | "Tersedia · dicek 10 Okt" (manual), "Tersedia" (connected) |
| Overflow / inputs < 16 px on touch | 0 / none | 0 / none (catalog search field is pre-existing and unchanged) |

Screenshots ([r2/](r2/)):

| | Before 390 | After 390 | After 1440 |
|---|---|---|---|
| Unpaid, no ongkir | [img](r2/before-admin-unpaidNoOngkir-390.jpg) | [img](r2/after-admin-unpaidNoOngkir-390.jpg) | [img](r2/after-admin-unpaidNoOngkir-1440.jpg) |
| Unpaid, ongkir set (instructions) | [img](r2/before-admin-unpaidWithOngkir-390.jpg) | [img](r2/after-admin-unpaidWithOngkir-390.jpg) | [img](r2/after-admin-unpaidWithOngkir-1440.jpg) |
| Paid → packing | [img](r2/before-admin-paid-390.jpg) | [img](r2/after-admin-paid-390.jpg) | [img](r2/after-admin-paid-1440.jpg) |
| Packed J&T → shipping | [img](r2/before-admin-packed-390.jpg) | [img](r2/after-admin-packed-390.jpg) | [img](r2/after-admin-packed-1440.jpg) |
| Done | [img](r2/before-admin-done-390.jpg) | [img](r2/after-admin-done-390.jpg) | [img](r2/after-admin-done-1440.jpg) |
| Customer link with payment | [img](r2/before-customer-payment-390.jpg) | [img](r2/after-customer-payment-390.jpg) | [img](r2/after-customer-payment-1440.jpg) |
| Pesanan Saya | – | [img](r2/after-mine-390.jpg) | [img](r2/after-mine-1440.jpg) |
| Catalog | [img](r2/before-catalog-390.jpg) | [img](r2/after-catalog-390.jpg) | [img](r2/after-catalog-1440.jpg) |
| Rekening pembayaran (Super Admin) | – | [img](r2/after-accounts-390.jpg) | – |

In the full-page admin captures at 390 the fixed bottom navigation appears mid-page; that is a capture artifact, not the layout.

Note on the first-round "before" images (2026-10-10): that worktree shared `vendor` through a junction, so its server code was the new branch while views were the old ones. The images show the old views correctly; the round-2 comparison above uses a fully separate old version.

Tests for this revision:
- `OrderPaymentGateTest` (10): signed API and Admin PWA bypass attempts on unpaid, reversed, short-paid and refund-pending orders; not-gated actions; resi after pickup without a claim; paid pickup/local/J&T end-to-end; keep; concurrent staff.
- `OrderStepperAndPaymentInstructionsTest` (4): one current step per state; one primary action on the page; verified accounts only; instructions after the ongkir.
- 23 older tests that packed or shipped before payment were rewritten to pay first (same intent); one renamed ("completes at handover of a paid order").
- Full suite: SQLite 556 passed / 7 MariaDB-only skipped; JS 42/42; Pint clean; **MariaDB 11.8.9 563/563** (includes 6-process concurrency races and the migration rehearsal with rollback guard).

## Manual UAT restart on ORD-04 revision + r4.3 (2026-10-11)

Owner approved; coordinated step by step with the App agent; no new Website code ran next to old App code.

1. Website UAT worktree → `54c2942`, later `67595ad`. App UAT → `9bfcd1e` (P0 + r4.3), later `c2f4886` (same App code plus smoke-instance support).
2. `qam_uat` identity checked (127.0.0.1:3397, MariaDB 11.8.9, UAT owner account), backup `qam_uat-pre-ord04-r2-20261011-091453.sql`, transaction data cleaned (3 earlier test orders), additive migration `store_payment_accounts`, one synthetic verified account ("BANK UJI - BUKAN REKENING ASLI"). App UAT backed up and cleaned (3 users kept).
3. Dummy products' manual stock check had expired (36 h rule); refreshed for the 6 published "UAT …" products only (`refresh-uat-stock.ps1`).
4. **Joint smoke on temporary databases** (Website `qam_uat_smoke` on :8150 ↔ App `qammaris_uat_smoke` on :8001; webhook local):
   - QAM-0001 local delivery: phone checkout → confirm with ongkir 44.000 → instructions with the test account → unpaid: Admin, signed API and App API/UI all refused (`payment_required`; the App sent nothing) → Tandai Lunas → App packing → courier → handover with the cost dialog (44.000, personal money) → completed; Website costs `staff_advance 44.000`, reimbursement `awaiting_proof`; App 1 expense + 1 reimbursement "Submitted"; revision 11 on both sides; 11/11 webhooks delivered.
   - QAM-0002 J&T: same unpaid refusals → paid → pack → picked up without QR/resi → completed → resi `UJI123456` by an employee without a claim (r4.3); cashier-cash cost, no reimbursement.
   - Event order on the Website: payment before any claim/packing; cost before handover.
   - Found and fixed: the Admin "Talangan belum diganti" chip ignored App cost reimbursements (API flag did) → `67595ad`, test added, verified on the smoke instance.
   - Teardown: smoke instance stopped, `qam_uat_smoke` dropped, smoke storage removed; App removed its smoke data.
5. Final start: Website on a new tunnel, App on a new tunnel, task links set to the new App URL. Read-only checks through the tunnel: logins (Super Admin phone, Staff Order laptop), 0 orders, product search, catalog labels, checkout page (3 delivery options, no store number), only the test account, no mixed content. `status-uat`: webhook target local, catalog sync off, mail log, no pending migrations; the only FAIL is the documented UAT exception (task links on).
6. Final counts: Website orders 0 (events, claims, costs, outbox, idempotency, jobs 0); App orders 0, reimbursements 0.

## UX polish after Owner UAT r4.3 (2026-10-11)

Isolated local instance (temporary SQLite, synthetic data, real headless Chrome, 390×844 touch), run twice: `APP_ENV=uat` (no test number) and `APP_ENV=production` (official number; WhatsApp requests blocked inside the test browser, so nothing was sent).

| Check | Result |
|---|---|
| Catalog navigation (phone) | Pesanan Saya icon next to the cart; "Pesanan Saya" in the Belanja menu; no overflow |
| Checkout paperbag | Pakai paperbag: Paperbag Rp 4.000, running total Rp 199.000; Tanpa paperbag: Rp 0, Rp 195.000; local delivery label "Pengiriman Dalam Kota — Kurir online, Kota Palu. Tiba 1–2 jam setelah pesanan diproses." |
| Success page, production | "Kirim ke WhatsApp"; WhatsApp opened once to `wa.me/6285144924931` with the prefilled order message; reload: no reopen; no copy fallback, no [UAT] |
| Success page, UAT | Store number never shown; copy fallback (no test number) |
| UAT opt-in (Super Admin, own browser) | Link becomes `wa.me/6285144924931?text=[UAT] Halo Qammaris…`; a normal visitor's page still has no store number |
| Status → Katalog → Pesanan Saya → detail | Works; list shows the order with total incl. paperbag; another browser: 0 orders |
| Received panel (local / J&T) | Above the items; motorbike / truck icon with text; timeline "Menunggu konfirmasi penerimaan dari Anda." |
| Admin order | Summary shows Paperbag; group message "Buka di Qammaris App: https://…/orders/{ULID}" (UAT: tunnel App URL; production: qammarisapp.com) |

Screenshots ([r3/](r3/)): [catalog](r3/production-catalog-390.jpg), [menu](r3/production-catalog-menu-390.jpg), [checkout paperbag](r3/production-checkout-paperbag-390.jpg), [success production](r3/production-success-390.jpg), [success UAT](r3/uat-success-390.jpg), [Pesanan Saya](r3/production-mine-390.jpg), [received local](r3/production-received-courier-390.jpg), [received J&T](r3/production-received-jnt-390.jpg), [admin](r3/uat-admin-order-390.jpg), [UAT opt-in](r3/uat-uat-optin-390.jpg).

Tests: `OrderPolishTest` (10) — history 20/10 days/other browser/forged key/delete/no session; paperbag 0/4.000, snapshot, replay, change after payment (Super Admin, overpayment, refund decision), API r4.4 totals; production WhatsApp; UAT opt-in (Super Admin only, per browser, [UAT], 404 in production); receipt panel/icons/copy; task links per environment; navigation. Full suite: SQLite 566 passed / 7 skipped, **MariaDB 11.8.9 573/573**, JS 42/42, build OK.

Total consistency: the Website total, the payment instructions and API r4.4 `totals.total` all include the same paperbag snapshot (tested). The Majoo cashier record is manual and must carry the same Rp4.000 line; it was not verified here.

## Tests

- `tests/Feature/StoreWhatsappEnvironmentTest.php`: 4 tests.
  - UAT without a number saves the order and shows a copyable message; no page contains the store number.
  - UAT with a test number opens only that number.
  - The store number is refused as a test number in every test environment.
  - Production keeps the store number and the WhatsApp-only flow.
- `tests/Feature/WebsiteCheckoutOrderTest.php`: 12 tests. They cover:
  - guest pickup;
  - three delivery types;
  - many products;
  - required choices;
  - price change and sold out (form and operation);
  - double tap and race;
  - a foreign checkout key;
  - WhatsApp not sent;
  - the success page;
  - a draft that cannot be paid and that the customer cannot edit;
  - staff confirm → payment;
  - cancel;
  - App serialization plus a signed `GET /orders/{id}` and `?lifecycle=draft` returning 200 with `queue=null`;
  - flag off;
  - rate limit.
- `OrderMigrationsMariaDbTest`: the ORD-04 migration is in the rehearsal set, and a new test shows `down()` stops while guest orders exist.
- Full suite on SQLite: 539 passed, 6 skipped (MariaDB-only), before the doc pass.
- Order suites on MariaDB 11.8.9 (isolated test server, port 3398): 145 passed, including the migration rehearsal and rollback guard.
- Pint clean; `npm run build` OK.

## Not verified / limits

- **No real phone or WhatsApp.** No real iPhone Safari or Android WhatsApp hand-off was tested. The automatic WhatsApp open uses a normal navigation and may land on the wa.me web page first.
- **Store number pinned (resolved, ORD-07).** Test environments now use `STORE_WHATSAPP_TEST_NUMBER` or a copyable message; see the section above.
