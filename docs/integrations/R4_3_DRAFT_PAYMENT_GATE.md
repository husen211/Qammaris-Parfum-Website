# Draft r4.3 (text only) — payment before fulfillment

Status: **DRAFT for joint sign-off by the Website and App agents.** The frozen baseline remains [r4.2](QAMMARIS_ORDER_API_V1.md) until both sign. This draft changes no schema; the OpenAPI file and its hash (`b0e1f5d578b44b1d8cf2060090dc87802f8f551b42ba491ea1b3880b3053cb88`) stay as they are.

Origin: Owner UAT feedback 2026-10-10. Orders could be packed, booked with a courier and handed over before they were paid.

## Proposed addition to §8 (actions)

**§8.0 Payment before fulfillment.** The following are allowed only when `payment.status = paid`:

| Area | Actions |
|---|---|
| Claims | `POST /claims` for every task (`preparation`, `courier_booking`, `handover`) |
| Preparation | `POST /preparation` (`preparing`, `packed`) |
| Local courier | `POST /courier-requests` |
| J&T | `POST /jnt` with any status or tracking number before handover, and the QR upload |
| Handover | `POST /handover`, including `handed_to=jnt` |

Otherwise the Website answers `409 invalid_transition` with `details.reason = "payment_required"`.

- **Blocked statuses.** `unpaid`, `refund_pending` and `refunded` are not paid.
- **No exception for pickup, keep, cash or source.** Keep (D9) may hold stock unpaid but never allows packing or handover.
- **Losing paid status later.** A payment reversal or a price adjustment that leaves a remainder makes the order unpaid again, and the next step is refused until the payment is complete.
- **Not gated:**
  - `DELETE /claims/{task}` (release);
  - issues (open/resolve);
  - `POST /delivery`;
  - `PUT /costs/{expense_ref}`;
  - a tracking number added after a J&T pickup (the order may already be `completed`).
- **Replays.** A replay of an already-stored action (same `Idempotency-Key`) returns the stored response (§11).
  - The App does not re-check payment before replaying an action already in its outbox (same `client_action_id` = same `Idempotency-Key`). It sends the replay as-is, and the Website returns the stored response even if the payment status has changed since.
  - A new action after a payment reversal uses a new key, so it is gated.
  - App-internal: the App also refuses unpaid actions itself, with the same 409 and reason. It re-reads the order detail first, so an order that was just paid is never blocked by a stale projection.
- **Tracking number after J&T pickup.** Once `jnt.status = picked_up`, `POST /jnt` with only a `tracking_number` is metadata, not a step. Any App actor with `orders.handle` may send it **without a claim**, also on a `completed` order. Any status change still needs its claim (`courier_booking`, or `handover` for `picked_up`).

Effect on completion (§7, unchanged rule): because handover now requires `paid`, a handed-over order normally becomes `completed` in the same revision, unless an issue or open refund holds it.

## Proposed note in §9 (queue)

The queue rules do not change. An active unpaid order is still `needs_handling`, with `flags.unpaid = true`. The App shows `needs_handling` with `flags.unpaid` as "Menunggu pembayaran", without a packing action.

## Shared stepper (UI, not API)

Both systems derive the same five steps from r4.2 fields:

1. Konfirmasi pesanan (`lifecycle` `draft`/`awaiting_customer`)
2. Pembayaran (`active`, `payment.status ≠ paid`)
3. Siapkan pesanan (`paid`, `preparation ≠ packed`)
4. Pengiriman (`packed`, `handover = pending`)
5. Selesai (`handed_over` or `completed`)

## Evidence (Website)

Tests: `tests/Feature/OrderPaymentGateTest.php`.
- Signed API calls are rejected before payment: claim for every task, preparation, J&T status, J&T pickup, handover.
- After pickup, the `handover` holder, and an employee with no claim, can add the resi to a completed order.
- After a reversal, the same calls are rejected again; claim release works.
- `refund_pending` is blocked.
- Issues, costs and delivery are not gated.
- Admin PWA routes cannot bypass the rule.
- Paid pickup, local and J&T orders run every step.
- Concurrent staff actions give one change and one conflict.
