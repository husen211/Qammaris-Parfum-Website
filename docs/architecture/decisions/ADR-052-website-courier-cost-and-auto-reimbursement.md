# ADR-052 — Courier cost on the Website, automatic approved reimbursement in the App

Status: accepted for review (ORD-08, Owner 2026-10-17). Not deployed.

## Context

The order flow was split between the Website and Qammaris App, and the Owner found it confusing:
- **Ongkir entered twice:** the Website "Toko bayar driver dengan" and the App "Catat ongkir". The App button stayed visible after a cost existed, so the fee was recorded twice.
- **Too many reimbursement steps:** the staff advance → the App expense → a Submitted reimbursement → proof → Owner approval → payment.

How the store works:
- Staff Order is one shared Website account on the store laptop, used by Andi and Ikrar.
- The App "Admin Toko" role is also shared.

The Owner decided:
1. All order work happens on the Website. The App is a backup display and the place where reimbursements land.
2. The real courier fee is entered when the courier is booked, together with "Uang siapa": Andi / Ikrar / Kasir / Customer bayar driver. The names must match the App employee accounts.
3. An employee advance becomes their reimbursement automatically: already approved, proof optional.
4. The staff group link opens the Website order page ("Buka pesanan").

## Decision

- **Website-origin cost (contract r4.5).** The Website stores one `online_order_costs` row per order with `origin=website`, `advanced_by_name` and the recording admin. It owns amount, funding and advancer, and bumps `source_version` on each correction.
- **The App owns only the reimbursement.** It imports the cost by `expense_ref` and creates the named employee's reimbursement as Approved, with an Owner proof waiver recorded as the standing decision. It then reports the status back with a higher version. The Website accepts only the reimbursement fields for such a cost.
- **Name matching instead of IDs.** The Website has no App user IDs for the shared account. A unique, case-insensitive match against active employees keeps the setup simple. No match → no reimbursement, a visible issue, and an Owner notification. Nothing is dropped silently.
- **One step in the UI.** "Kurir sudah dipesan" and "Request pickup J&T" require the fee and the payer. "Ubah ongkir kurir" edits the same record, and is locked once the reimbursement is paid. The old `driver_funding` choice is removed from the V2 page; the column and the ORD-01 page are kept.
- **Waiver authorization unchanged.** Approval still carries an Owner waiver from an allowlisted App Owner (r4.2). The App sends it as its first active Owner, which must be on `QAMMARIS_APP_OWNER_IDS`.
- **Advancer list.** `QAMMARIS_ORDER_STAFF_ADVANCERS` (default `Andi,Ikrar`): changing staff is a configuration change, not code.

## Consequences

- Courier money is recorded once, by the person who booked the courier. Employees no longer submit or chase approval. The Owner only pays.
- Proof is optional by Owner decision. The audit trail is the Website event (who recorded it, amount, payer) plus the App history entry "Otomatis disetujui".
- A wrong amount is corrected on the Website. Once paid, corrections go through the Owner in the App.
- Release requires both sides together:
  - Website migration (additive) and r4.5;
  - the App build with the importer;
  - `QAMMARIS_APP_OWNER_IDS` containing the App Owner;
  - `QAMMARIS_ORDER_APP_TASK_LINKS=false`, so the group link opens the Website.
  These are Owner actions at release.
- The App still lets staff record a cost themselves (backup path) only while no active cost exists for the order.
