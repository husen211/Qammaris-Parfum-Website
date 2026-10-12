# ADR-043 — Operational UX simplification for online orders (ORD-03)

Status: accepted for review (2026-10-10). Not deployed.

## Context

Manual UAT (Owner) showed that ORD-02 worked but took too many taps:
- every panel was open at once;
- some actions competed ("Mulai siapkan" next to "Konfirmasi packing");
- the order link required WhatsApp;
- iPhone zoomed into small fields;
- the customer page carried the full site navigation.

## Decision

1. **One contextual action.** The V2 order page derives the current step and shows it open first with one primary action: link, open issue, payment, packing, then shipping. Other steps are `<details>` with a one-line status. Exceptions ("Ada masalah", "Opsi lanjutan") open only when something needs attention: an open issue, a pending approval, or a refund. Forms, routes and revision checks are unchanged.
2. **Payment without a visible source.** The ledger entry records `majoo` (when "Sudah dicatat di Majoo" is ticked) or the internal value `admin_recorded`. The order field exposed by API v1 keeps its enum: `majoo` or `null`.
3. **Packing as one action.** Staff tick each line at its ordered quantity. The domain still requires exact quantities. Packing straight from "not started" was already valid.
4. **J&T main flow.** Request pickup → waiting → picked up. The QR (private file, admin upload/view) and the tracking number are optional and never block pickup.
5. **Ongkir for Staff Order behind a flag.** The new gate `orders.charge-shipping` lets Staff Order set the customer-charged shipping fee only while the transaction is not final: V2, open, nothing handed over, no money recorded. After any payment, a change is a price adjustment that Super Admin approves. Driver funding stays `orders.finance`, and every change is audited with old → new value.
6. **Customer payment preference behind a flag.** Transfer bank or QRIS, stored in `online_orders.payment_preference` while the order is unpaid. It never marks the order paid and never creates a Majoo QRIS. It is not part of API v1.
7. **Order link.** "Link Pesanan" offers Salin Link, Salin Pesan, and the native share sheet. WhatsApp is optional.
8. **Customer page.** Brand-only header (`minimal_chrome` section in the public layout), one page, "Sharelok" wording, short hints.
9. **No iOS auto-zoom.** On touch screens, every focusable text field in the admin shell and on the order page is at least 16 px. The rule is unlayered CSS so it wins over Tailwind's text-sm. Pinch zoom is not disabled.

**Flag:** `ORDERS_SIMPLE_UX`, default off. It gates the staff ongkir rule and the payment preference. The UI changes apply to V2 pages, which exist only where `ORDERS_V2_ENABLED` is on (UAT).

## Consequences

- Staff reach the next action without scrolling past unrelated panels. Order pages are 60–67% shorter at 390 px.
- Nothing in authorization is loosened outside the flag. With the flag on, the only widening is the staff ongkir rule, and only before any payment.
- API v1 r4.2 is unchanged. The agen App's plan 0021 confirmed no contract change.
- The legacy ORD-01 order page keeps its old layout.
