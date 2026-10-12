# ADR-045: Payment before fulfillment and the order stepper

Status: Proposed 2026-10-11 (ORD-04 revision after Owner UAT feedback). Branch `modernization/ord-04-cart-checkout`. Not merged, not deployed. API contract: text-only r4.3 ([R4_3_DRAFT_PAYMENT_GATE](../../integrations/R4_3_DRAFT_PAYMENT_GATE.md)), signed by the Website and App agents on 2026-10-11; the OpenAPI schema and hash are unchanged.

## Problem

In Owner UAT, an unpaid order could be packed, booked with a courier and handed over. Only the UI suggested an order of steps; no domain action checked payment, so the Admin PWA and the App API could both skip it. The order page also showed every step's card and button at once.

## Decision

1. **Domain rule, no exceptions.** Preparation (start, pack), claims for every task, local courier requests, J&T requests/QR/tracking before handover, J&T pickup and handover need `payment_status = paid`. `OnlineOrderFulfillment::assertPaid` throws `PaymentRequired`; the API maps it to `409 invalid_transition` with `details.reason = payment_required` (r4.2 `Error.details` is a free object).
   - Refund pending/refunded and partial payments are not paid. A reversal or a total increase makes the next step wait again.
   - Keep (D9) still holds stock unpaid; it never allows packing or shipping.
   - Not gated: claim release, issues, costs, delivery confirmation, setting who books the courier, and a resi after a J&T pickup (metadata; no claim needed, also on a completed order — App review).
2. **Consequence.** Because handover now needs `paid`, a handed-over order normally completes in the same revision. The customer "Pesanan sudah saya terima" button and the customer timeline follow `delivery_status`, not the lifecycle.
3. **Stepper from backend state.** `OnlineOrderStepper` derives Konfirmasi → Pembayaran → Siapkan → Pengiriman → Selesai from r4.2 fields; the App derives the same steps. The order page renders only the current step's partial (one primary action), finished steps as one line, later steps inert; issues, keep, link, options, finance and history are disclosures. A refused action for a hidden step still reports its reason at the top.
4. **Payment instructions.** Once the total is final (pickup, or ongkir and payer set), staff copy "Total & Instruksi Pembayaran" (order number, subtotal, ongkir, total, accounts, proof instruction) or send it to the customer chat. The customer's Transfer/QRIS choice stays a preference; nothing marks paid automatically.
5. **Official accounts are Owner configuration.** `store_payment_accounts`: Super Admin adds; a separate verification with the re-typed number must pass; accounts are never edited or deleted (deactivate and add). Only verified, active accounts reach instructions or customers; without one the text says the account follows in the chat.
6. **Customer page.** After the total is final: total, verified accounts with copy, and "Kirim bukti lewat WhatsApp". Header links Katalog and Pesanan Saya. Pesanan Saya lists the orders this browser session opened; raw tokens stay in the server-side session and details still open through their token. No login, no persistent identity.
7. **Availability copy.** Connected products say "Tersedia" only while the App feed is fresh (last successful sync within `QAMMARIS_APP_AVAILABILITY_STALE_MINUTES`, default 120, and no error); otherwise "Tersedia · stok belum diperbarui". Status is never downgraded (ordering still works; staff confirm stock). Manual products show their check date instead of "saat diperiksa".

## Alternatives rejected

- UI-only disabling: the reported bug; the API and stale pages bypass it.
- New queue value `awaiting_payment`: breaking enum change; the App shows `needs_handling` + `flags.unpaid` as "Menunggu pembayaran" instead.
- Allowing claims before payment: a claim makes the queue `preparing` and blocks other staff later.

## Consequences

- Fixtures start paid; tests pay before fulfillment (`PaysOrders`). Old tests that encoded "pack before pay" were rewritten, not deleted.
- Rollback: revert the commits; the only schema addition is the empty-by-default `store_payment_accounts` table (dropped by its migration's `down()`).
