# r4.4 — paperbag charge in order totals (schema change)

Status: **Signed by both agents (2026-10-11)** on Website `9a6b6ee` (OpenAPI sha256 `10dcfa50…c5b5`). The App adopted a byte-identical copy on its dev branch (contract test: `packaging_fee` required; UI "Pakai paperbag · Rp 4.000 ditagih ke customer"; cost prefill stays `shipping_charge`). The OpenAPI `info.description` still reads "r4.4 DRAFT": it is kept byte-identical to the signed file. Not merged, not deployed; UAT switches both sides together after Owner approval.

Origin: Owner decision 2026-10-11 (ORD-04 polish): "Pakai paperbag" adds Rp4.000 per order to the customer total; "Tanpa paperbag" adds Rp0. The amount must match the cashier record in Majoo.

## Change

| | r4.2/r4.3 | r4.4 |
|---|---|---|
| `Order.totals` required | `subtotal, adjustments, shipping_charge, total` | `subtotal, adjustments, shipping_charge, packaging_fee, total` |
| `totals.packaging_fee` | – | `Money` (whole rupiah): the paperbag charge to the customer |
| `totals.total` | subtotal + adjustments + shipping_charge | subtotal + adjustments + shipping_charge + **packaging_fee** |
| OpenAPI `info.version` | `1.0.0-rc.4.2` | `1.0.0-rc.4.4` |
| OpenAPI sha256 | `b0e1f5d5…cb88` | `10dcfa5051460fef87d5ab8666df97a06af79f4e43cbd434f093dc79ad51c5b5` |

Rules (Website):
- The charge is a snapshot on the order, taken when the packaging is chosen or changed (current amount Rp4.000 from configuration). A later configuration change never rewrites an order; replaying a checkout or saving the same packaging again never adds it twice.
- Older orders without a snapshot report `packaging_fee = 0` (nothing is backfilled).
- After money is received, switching paperbag on/off changes the total, so it is a Super Admin correction (Admin PWA and approved customer change requests). Over- or under-payment then follows the existing ledger rules (overpayment shown, refund decided by Super Admin; remainder blocks fulfillment per r4.3).
- `packaging_fee` is a customer charge: it is never an expense, never a cost prefill, never reimbursed. Cost prefill in the App stays `totals.shipping_charge`.

App (from the App agent, 2026-10-11): the App does not validate total = sum at runtime; it shows "Paperbag Rp4.000" when `packaging_fee > 0` (tolerant read); its baseline copy, fake Website and contract tests update after sign-off. Both sides switch UAT together.

## Evidence (Website)

`OrderSerializer` emits the field; all API tests validate responses against the r4.4 schema; `WebsiteCheckoutOrderTest` checks product + ongkir + paperbag = total and payment of that total; `OrderPaperbagTest` covers Rp0/Rp4.000, replay, change, payment correction and refund.
