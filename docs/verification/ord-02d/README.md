# ORD-02d — Admin PWA pages for V2 orders: local verification

Date: 2026-10-09. Owner approval: "Silakan lanjutkan ORD-02d … Laporkan ORD-02d beserta screenshot dan hasil tes sebelum melakukan deployment." Status: IN_REVIEW on `modernization/ord-02-online-order-redesign`. Not merged, not deployed. The order API contract (r4.1 baseline) was not changed.

## Scope implemented

- **Create order (Staff Order, mobile-first).**
  - Choose the source: WhatsApp, Instagram, or another channel.
  - Search products.
  - Search a repeat customer by name or WA number and pick a confirmed saved address, or mark the person as a new repeat customer.
  - Choose **"Data sudah lengkap"** (customer never opens the form) or **"Kirim link ke customer"**.
  - Customer, address and recipient data are applied in the same transaction as the order (`CreateOnlineOrder` options).
- **V2 order page** (`admin/orders/v2/show`). Every action is a V2 domain operation (`OnlineOrderFulfillment`, `RecordOnlineOrderMoney`, `OnlineOrderDetails`, `OnlineOrderCustomers`, `OnlineOrderAdjustments`, `OnlineOrderChangeRequests`). Sections:
  - payment, with an append-only list; Staff Order may mark Lunas;
  - preparation and packing, with an exact quantity per item;
  - delivery: pickup, local courier (store or customer books), J&T steps with optional tracking number, handover, delivered;
  - keep, with deadline and manual stock-set-aside;
  - issues, price adjustments, customer change requests;
  - customer link and invite message, staff group message;
  - repeat customer and saved addresses, recipient details, cancel, history.
- **Super Admin finance panel:** refund decision and correction, refund payout (partial allowed), ledger reversal, reconciliation. Also shown on ORD-01 pages for legacy orders that need reconciliation.
- **V2 data paths without the legacy workflow.**
  - Customer form: applies directly while unpaid and not started, otherwise becomes a change request. Product prefilled, three receiving options, two paperbag options.
  - Customer "Pesanan sudah saya terima" uses the V2 delivery operation.
  - The ORD-01 workflow (`update`, `advance`, `regenerateLink`, `submitCustomerDetails`, …) and ORD-01 bearer staff links refuse V2 orders.
- **Group message (V2).** Links to the order in the Admin PWA (login required) instead of a bearer task link (D6), and includes keep information.
- **States.**
  - Each form posts its revision. A conflict shows "Pesanan berubah" with fresh data.
  - Validation errors appear inside the section the page jumps back to.
  - Risky actions ask for confirmation; a cancelled confirm sends nothing.
  - Buttons show a loading label and lock against double taps.
  - Every list has an empty state.
- **Order list.** Queue/payment/keep badges for V2 rows; filters and banners for reconciliation, open refunds, open issues and active keeps.

## Found and fixed during browser checks

| Finding | Fix |
|---|---|
| Payment/refund forms had a field named `method`, which shadows `form.method` in the DOM. The PWA submit guard (double-tap lock, loading label) and the navigation script threw on those forms. | Fields renamed `payment_method`/`refund_method`; the guard reads `getAttribute('method')`. Regression tests: JS shadowing case, and no admin view may contain `name="method"`. |
| Selected "Siapa memesan kurir?" option rendered white on white (conflicting classes). | Classes built without conflict, plus a ✓ marker. |
| Selected product row cramped at 390 px. | Name/price take the full row; quantity and delete wrap below. |
| The new section menu widened content; the old overflow check missed it because admin `<main>` clips horizontally. | `contain: inline-size` on the menu. The check now measures every visible box against the viewport (outside intentional scroll areas). |

## Checks actually run

- `php artisan test`: **483 passed / 5482 assertions**, exit 0. That is 475 before ORD-02d, plus 8 new `AdminOrderV2PagesTest` tests:
  - IG order with complete data and a new repeat customer;
  - repeat customer, saved address and link mode;
  - payment, conflict, packing validation, courier, handover, delivery through the pages;
  - J&T, issue and keep;
  - Super Admin panel with Staff Order denied (403);
  - customer change request plus legacy endpoints refusing V2;
  - customer receipt confirmation plus legacy reconciliation panel;
  - no `name="method"` fields.

  One older test was updated to use the V2 customer path.
- `node --test tests/js/*.test.mjs`: **42 passed**. New: busy label, and a guard with a shadowed `form.method`.
- Pint on changed PHP: passed. `npm run build`: passed.
- **No new migrations** in ORD-02d.

## Browser evidence

Real headless Chrome (DevTools Protocol) against the worktree, `ORDERS_V2_ENABLED=true`:
- isolated SQLite with synthetic accounts (`pemilik` Super Admin, `andi` Staff Order), synthetic products, one repeat customer with a saved address, and two ORD-01 orders (one cancelled after payment);
- the DB, random password and Chrome profile were deleted afterwards.

Screenshots are full-page captures at CSS-pixel scale. In tall mobile captures the fixed bottom navigation appears where the viewport ended. Raw results: [browser-checks.json](browser-checks.json).

| Check | Result |
|---|---|
| Before (ORD-01 page) | [390](before-legacy-detail-390.jpg), [1440](before-legacy-detail-1440.jpg) |
| Create, IG + repeat customer + saved address + link mode | [form 390](create-390.jpg), [customer chosen 390](create-customer-chosen-390.jpg) → [awaiting customer 390](detail-awaiting-link-390.jpg) ("Berikutnya: Kirim link ke customer…") |
| Create, WA + new customer + complete data | "Customer tidak perlu mengisi form" → [active 390](detail-active-390.jpg) |
| Conflict | Payment form with a stale revision → "Pesanan berubah …" in the section; no payment recorded ([390](conflict-390.jpg)) |
| Payment → packing → courier → handover | Wrong quantities rejected per item ([390](pack-validation-390.jpg)); correct packing, Maxim requested, handed over → Selesai + Lunas ([390](detail-completed-390.jpg)) |
| Keep + issue | Keep 24 h with stock set aside, open stock issue ([390](keep-issue-390.jpg), [320](detail-keep-320.jpg)) |
| Confirmation | Cancel with the confirm dismissed: page and revision unchanged |
| Super Admin | Approve discount → "Lebih bayar"; refund decided Rp 20.000; Rp 10.000 paid → "Refund sebagian" ([390](money-partial-refund-390.jpg), [1440](detail-1440.jpg)) |
| ORD-01 reconciliation | Flagged legacy order ([before](legacy-reconcile-before-390.jpg)) → received/owed/returned 300.000 → "Refund selesai" ([after](legacy-reconcile-after-390.jpg)) |
| Customer form | Product prefilled, 3 receiving options, 2 paperbag options ([390](customer-form-390.jpg)) |
| List | [390](index-390.jpg), [1440](index-1440.jpg), create [1440](create-1440.jpg) |
| Widths | 39 checks at 320/375/390/430 (list, create, three order pages) plus screenshots: **0 px** beyond the viewport |
| Console | No JS exceptions after the fixes above |

## Limitations

- Real Android/iPhone touch, the install prompt, and real WhatsApp sending were not tested. The clipboard was not exercised in headless Chrome.
- Concurrency and migrations were verified on SQLite only. **MySQL migration and concurrency tests are required before staging** (Owner instruction) and are part of the ORD-02e preparation.
- V2 orders exist only when `ORDERS_V2_ENABLED=true`. The flag stays off until release is approved.
