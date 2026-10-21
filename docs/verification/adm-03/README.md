# ADM-03 — Tandai Lunas needs the Majoo check (2026-10-21)

Owner request (card #83): staff must enter a payment in the cashier (Majoo) before marking it here, with a short prompt instead of more text.

## What changed

- The payment form keeps amount and method; the old optional "Sudah dicatat di Majoo" tick box is gone.
- **Tandai Lunas** (or **Catat pembayaran** for a later part) opens a native dialog: bottom sheet on phones, centred on desktop. It shows the amount and method and two checks, **Bukti pembayaran sudah dicek** and **Sudah dicatat di kasir (Majoo)**. **Ya, tandai lunas** stays disabled until both are ticked; Batal or Esc closes it and clears the checks.
- The server refuses a payment without the Majoo check ("Catat dulu pembayaran ini di kasir (Majoo), lalu tandai lunas."), so the ledger always records Majoo. Order API payments from the App are unchanged.

## Checks

- `OrderOperationalUxTest`: refused without Majoo (nothing recorded), dialog markup and disabled confirm, ledger and order source Majoo, API schema valid. Route tests updated to send the Majoo check.
- Payment-related suites: all pass except the two J&T QR tests that need GD (fail identically on `main` on this laptop).
- Browser (synthetic SQLite DB, synthetic Staff Order, removed afterwards): dialog opens with "Rp 189.000 · Transfer bank", confirm disabled with one tick and enabled with both, Batal closes; a real submit marked the order Lunas.

| | Before | After |
|---|---|---|
| Payment 390 | [before-payment-390](before-payment-390.png) | [after-dialog-390](after-dialog-390.png), [both checked](after-dialog-checked-390.png) |
| Payment 1440 | [before-payment-1440](before-payment-1440.png) | [after-dialog-1440](after-dialog-1440.png) |

UX rationale: one prompt at the moment of the action, two short checks naming exactly what must already be done, no extra paragraph on the page.

Rollback: revert the PR (view, controller, tests).
