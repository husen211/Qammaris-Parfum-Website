# ADR-046: Guest order history, paperbag charge, direct WhatsApp and App task links

Status: Proposed 2026-10-11 (ORD-04 polish after Owner UAT r4.3). Branch `modernization/ord-04-cart-checkout`. Not merged, not deployed. The paperbag API field is r4.4 ([R4_4_DRAFT_PACKAGING_FEE](../../integrations/R4_4_DRAFT_PACKAGING_FEE.md)), signed by both agents on 2026-10-11.

## Decisions

1. **Pesanan Saya, 10 days, no login.** The existing `CustomerOrderHistory` (no second system) now persists per browser:
   - Cookie `qam_orders`: a random 64-character key, encrypted by Laravel, HttpOnly, Secure on HTTPS, SameSite=Lax, 10 days.
   - Server tables `guest_order_histories` (key SHA-256, expiry) and `guest_order_history_entries` (order ID, opened time). No order token, recipient data or account; nothing in localStorage.
   - Up to 20 newest orders; entries older than 10 days are hidden; the list expires 10 days after the last new order; "Hapus Riwayat dari Perangkat" deletes the server row and the cookie.
   - The Laravel session lifetime (admin policy) is untouched. Details still open through each order's link and follow the customer-link rules. Another browser or device sees nothing.
2. **Paperbag Rp4.000 per order** (Owner rule). `online_orders.packaging_fee` is a snapshot set when packaging is chosen or changed (`config('orders.paperbag_fee')`), part of `customerTotal()` and therefore of payment status, overpayment and refunds.
   - Shown at checkout (choice and running total), on the status page, in the admin summary, in the payment instructions and in the checkout WhatsApp message.
   - Never doubled: replays and repeated saves keep one value. Older orders stay 0 (no backfill).
   - Switching paperbag after money was received is a Super Admin correction (admin edit and approved change requests).
   - API: `totals.packaging_fee` in the r4.4 draft. The Majoo cashier entry must include the same Rp4.000 line (manual check).
3. **WhatsApp.** Production: after saving, the order page opens a prefilled chat to the official number once (flash) and offers **Kirim ke WhatsApp**; refreshes never reopen it; no copy step. Test environments keep the ORD-07 protection for every visitor. A Super Admin may opt **their own browser** in to the official number for 120 minutes (encrypted cookie, route only in test environments). Every test-environment message starts with `[UAT]`. The production number is unchanged.
4. **Status page and copy.** The receipt button stays at the top with a motorbike (local) or truck (J&T) icon and text; the timeline says "Menunggu konfirmasi penerimaan dari Anda."; local delivery is "Pengiriman Dalam Kota", "Tiba 1–2 jam setelah pesanan diproses" (the enum `local_delivery` is unchanged).
5. **Staff group link.** "Buka di Qammaris App: {QAMMARIS_APP_ORDERS_URL}/{public_id}" when App task links are on, so an installed PWA can capture it; login return and browser fallback are the App's (confirmed by the App agent). `OrderTaskLink` refuses a URL of the other environment (test → production App, production → temporary tunnel) and then uses the Website task route. Production URL: planned `https://qammarisapp.com/orders`, confirm at release.

## Consequences

- Two additive migrations; rollback drops the history tables and the snapshot column (no business data in either).
- A cleared browser or a new device loses the list by design; the order links remain valid.
