# r4.5 — courier cost recorded on the Website (schema change, DRAFT)

Status: **DRAFT (2026-10-17)**, written on both sides by the same agent at the Owner's request ("Saya kerjakan dua-duanya"). The App adopts a byte-identical copy on its branch `feat/ord-08-website-courier-cost`. Not merged, not deployed. Both sides switch together after Owner review.

Origin: Owner decision 2026-10-17 (ORD-08). All order work happens on the Website. Staff Order (one shared account on the store laptop, used by Andi and Ikrar) enters once, when the courier is booked:
- **Ongkir ke kurir**: what the courier was actually paid;
- **Uang siapa**: Andi / Ikrar / Kasir / Customer bayar driver.

An employee advance becomes that employee's reimbursement in Qammaris App, **already approved, proof optional**. Before ORD-08, the same courier fee was entered twice: once as `driver_funding` on the Website and again as an expense in the App. Approval then took several more steps.

## Change

| | r4.4 | r4.5 |
|---|---|---|
| `Cost.origin` | – | optional `app` \| `website`; absent = `app` |
| `Cost.advanced_by` | – | optional `{display_name}` \| null: employee whose own money paid a website cost |
| `Cost.source_version` | – | optional integer: the Website's version of the cost |
| `Cost.reported_by` | `ActorRef` | `ActorRef` \| null; null for `origin=website`, ActorRef for `origin=app` |
| `CostRequest` on a website cost | – | only `reimbursement` and `source_version` are applied |
| OpenAPI `info.version` | `1.0.0-rc.4.4` | `1.0.0-rc.4.5` |
| OpenAPI sha256 | `10dcfa50…c5b5` | see `tests/Unit/OrderApiContractTest.php` |

## Rules

Website:
- One website-origin cost per order (`expense_ref` = `exp_<32 hex>`). It is created when "Kurir sudah dipesan" or "Request pickup J&T" is submitted, and can be corrected afterwards. Each correction bumps `source_version`.
- Funding: a named employee → `staff_advance` (reimbursement `submitted`, proof `pending`); Kasir → `cashier_cash`; Customer bayar driver → `customer_direct` (both `not_applicable`).
- The employee names come from `QAMMARIS_ORDER_STAFF_ADVANCERS` (default `Andi,Ikrar`). They must equal the App account names.
- Corrections are refused once the reimbursement is `paid`.
- An App `PUT …/costs/{ref}` on a website cost applies only the reimbursement fields, and only when `source_version` is higher. A report whose reimbursement amount no longer matches the Website amount is a stale no-op. Waiver authorization is unchanged from r4.2.

App:
- On every order detail read (webhook, reconcile with `flags.open_reimbursement`, detail opened), the App imports each `origin=website` cost under the same `expense_ref`.
- A staff advance is matched by name to exactly one active `EMPLOYEE` (case-insensitive). The App creates that employee's reimbursement with status **Approved**, with a proof waiver by the first active Owner ("Otomatis disetujui …", Owner 2026-10-17). It then reports `approved`/`waived` back with version = max(App, Website) + 1, sent as that Owner, who must be on the Website allowlist.
- A correction moves or adjusts the reimbursement while it is not Paid. A change to Kasir or Customer deletes it.
- An unmatched name keeps the expense, sets `importIssue`, notifies the Owner, and sends nothing back.
- Website costs cannot be voided in the App, and the App shows no second "Catat ongkir".

## Evidence

- Website: `tests/Feature/OrderCourierCostTest.php`, plus the updated `OrderPaymentGateTest`, `OrderOperationalUxTest` and `OrderApiContractTest`.
- App: `backend/tests/integration/online-orders-website-costs.test.ts`, plus `frontend/src/pages/__tests__/OnlineOrders.test.tsx`.
