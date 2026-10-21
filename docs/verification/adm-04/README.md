# ADM-04 — Clearer order page flow and member points (2026-10-21)

Owner request (card #85), on the first day of real use: make it obvious which step staff work on now, without a redesign.

## What changed

- **Current step.** The card of the step to work on is tinted in the brand gold with a stronger border and a "Langkah sekarang" label above it. Done steps stay one line with a check; later steps stay dashed. Order, steps and actions are unchanged.
- **Chevrons.** The small "▾" became a visible chevron that turns when a card opens (all order page cards).
- **"Ada masalah?"** with a question mark.
- **Divider "Lainnya"** between the routine steps and the exception cards (Keep, Ada masalah?, Link Pesanan, Opsi lanjutan).
- **Opsi lanjutan.** Description "Penyesuaian harga, poin member, batalkan pesanan". New type **Poin member**: a fixed rupiah discount, no reason field (recorded as "Poin member"); Potongan and Tambahan still need a reason. "Terapkan penyesuaian" is now a solid button instead of looking like a field.

## Checks

- New `AdminOrderPageFlowTest` (2 tests): exactly one current step, gold card on the payment step, no "▾", divider before the exception cards, finished order has no current step; member points without reason applied (-Rp15.000, approved, total lowered) while a plain discount without reason is refused.
- Full Laravel suite: 777 passed, 10 skipped, 3 failed (no GD in XAMPP PHP, Windows behaviour; same on `main`). Pint clean.
- Browser (synthetic SQLite DB, synthetic Staff Order, removed afterwards), 390 and 1440:

| | Before | After |
|---|---|---|
| Packing step 390 | [before](before-packing-390.png) | [after](after-packing-390.png) |
| Packing step 1440 | [before](before-packing-1440.png) | [after](after-packing-1440.png) |
| Payment step 390 | [before](before-payment-390.png) | [after](after-payment-390.png) |
| Opsi lanjutan 390 | [before](before-advanced-390.png) | [after, Poin member](after-advanced-390.png) |

UX rationale: one colour cue on the single card that needs action, a label that says so in two words, and a quiet divider so exceptions read as exceptions; layout and wording staff learned on day one stay in place.

Rollback: revert the PR (views, CSS, controller validation, tests).
