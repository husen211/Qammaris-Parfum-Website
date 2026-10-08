# ADR-040 — Order state cutover and payment/refund ledger

Status: accepted for review (ORD-02c slice 2, 2026-10-09). Not deployed.

## Context

ORD-02c adds separate order state dimensions while the ORD-01 screens still drive `stage`. Slice 1 marked every order cancelled after payment as `refund_pending`. Owner review (2026-10-09) rejected that:
- refunds must not be assumed;
- partial and full refunds, and manual reconciliation, must be supported;
- `refund_pending` may only mean money that is really still owed;
- refund approval and correction stay with Super Admin, with an audit trail.

## Decision

1. **Cutover per order, at creation.**
   - `online_orders.state_model` is `legacy` or `v2`. Existing and backfilled rows are `legacy`.
   - Legacy rows are driven only by the ORD-01 workflow, and a saving hook copies `stage` into the dimensions one way.
   - V2 rows are driven only by V2 operations (slice 3). The hook skips them, and the ORD-01 step operations refuse them.
   - There is no mid-life conversion. New orders become `v2` when the V2 path is enabled (slice 3). Legacy rows finish on the legacy path.
2. **Append-only ledger** `online_order_payments`:
   - entry types: `payment` or `refund`;
   - basis: `recorded`, `reconciled` or `reversal`;
   - a mistake is cancelled by a reversal row (`reverses_id` unique), never edited or deleted.
3. **Refund decision on the order:** `refund_due_amount`, reason, decided by and decided at. A later change is logged as a correction.
4. **Refund status:**
   - derived from the decision and the ledger: `not_required`, `pending`, `partial` or `refunded`;
   - `needs_reconciliation` when history is incomplete, which is every ORD-01 cancel after payment.
   - The contract `payment_status` becomes `refund_pending` only for `pending`/`partial`, and `refunded` when fully returned. `needs_reconciliation` keeps `paid`, so no debt is assumed.
5. **Reconciliation.** A Super Admin states three amounts: received, owed, and already returned. These are written as `reconciled` ledger rows plus the decision, with validation that owed ≤ received and returned ≤ owed. If the history is unknown, the order stays flagged.
6. **Authorization.** A new gate `orders.refund` (Super Admin only) covers refund decisions, refund payouts, reversals and reconciliation. Recording a payment needs `orders.manage`. Every operation checks the expected revision (no double recording) and writes an event:
   - `payment_recorded`, `refund_decided`, `refund_corrected`, `refund_recorded`, `ledger_reversed`, `reconciled`.

## Consequences

- For legacy rows, "paid" still comes from `stage`. The ledger informs refunds only.
- For V2 rows, "paid" is derived from the ledger against the customer total.
- Legacy admin accounts can no longer approve refunds; this capability is new and Super Admin only.
- Migration `2026_10_09_000001` corrects slice-1 data: legacy cancelled-after-payment rows become `paid` + `needs_reconciliation`.
- The API v1 contract is unchanged. `needs_reconciliation` is internal and is not an open refund.

## Rollback

Revert code. The ledger table and columns are additive and may stay. Rolling the migration back drops the ledger, so export it first if any real entries exist.
