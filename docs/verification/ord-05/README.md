# ORD-05 — Customers saved automatically, Pelanggan menu, removal on request (2026-10-21)

Owner request and decisions (card #67, 2026-10-21): customers are remembered without a manual "save" click; Super Admin and Staff Order see them; the same WhatsApp number is the same customer; existing production orders join through a previewed backfill; personal data is removed only manually by a Super Admin. Branch `modernization/ord-05-customers`. Local only; nothing deployed.

## What changed

- **Automatic customer** (`RememberCustomer::link`), inside each order transaction:
  - staff create an order with complete recipient data (the "Simpan sebagai pelanggan langganan" box is gone);
  - the customer submits the order link;
  - staff edit details until they are complete;
  - staff confirm a website checkout (drafts that are never confirmed create nothing).
  An explicitly chosen customer in the create form is kept. Staff can still move an order to another customer from its page (family members sharing a number).
- **Address memory** (`rememberAddress`) at handover to the courier or J&T pickup; an identical address is reused, not duplicated.
- **Pelanggan menu** (sidebar, phone menu and Admin PWA bottom bar) for Super Admin and Staff Order: list with order count and last order, search by name or number, detail with order history, saved addresses (archive), edit name/note.
- **Hapus data pelanggan** (Super Admin, gate `customers.delete`): type HAPUS to confirm. Refused while any linked order is still running or a webhook for it is still being delivered. Clears name, number, address, postcode, district, location, note on the customer and its orders, empties change-request payloads, redacts delivered/failed webhook bodies, archives addresses, and bumps each order's revision so Qammaris App receives the cleared copy. Order codes, items, payments and history stay. The removed customer disappears from the list and the customer picker; the number may order again as a new customer.
- **Backfill** `qammaris:customers:backfill`: preview (counts only) unless `--apply`; no revision bump, so nothing is sent to the App. Production run needs separate Owner approval and a backup: [runbook](../../runbooks/CUSTOMER_BACKFILL.md).
- **Migration** (additive): `customers.created_by` and `customer_addresses.confirmed_by` nullable (a customer's own link has no admin), `customers.anonymized_at` / `anonymized_by`. Rehearsed on a fresh SQLite database and, with `OrderMigrationsMariaDbTest` (up, rollback, re-up, ORD-04 guard), on an isolated throwaway MariaDB 10.4.32; the production version 11.8 is not rehearsed in this task.

## Tests and checks

- New `OrderCustomerDirectoryTest` (5 tests): link + confirmed website order by number, address kept once at handover and no manual-save box, Pelanggan pages and access (Staff Order yes, customer role no, Staff Order cannot remove), removal rules and cleared App copy (API schema valid, no name/number left in webhook bodies), backfill preview/apply without webhooks.
- Updated: `OnlineOrderCustomersTest` (new same-number rule), `AdminRbacTest` (customers routes in the order area, removal needs `customers.delete`), `AdminPwaTest` (four bottom-bar items).
- Full Laravel suite (CI-like env): 780 passed, 10 skipped, 3 failed; those 3 also fail on unmodified `main` on this laptop (no GD in XAMPP PHP, Windows behaviour). Pint clean.

## Browser (synthetic SQLite DB and synthetic accounts, removed afterwards)

Orders created, paid, packed and handed over through the real actions; 390 and 1440:

| | Before | After |
|---|---|---|
| Create order, recipient filled | [before-create-390](before-create-390.png) | [after-create-390](after-create-390.png) |
| Pelanggan list | — | [390](after-customers-390.png), [1440](after-customers-1440.png) |
| Customer detail (Staff Order) | — | [390](after-customer-staff-390.png), [addresses 390](after-customer-addresses-390.png), [1440](after-customer-staff-1440.png) |
| Removal section (Super Admin) | — | [390](after-customer-owner-delete-390.png) |

Interactive: as Super Admin, "Hapus data pelanggan" for a customer with an active order was refused with "Pelanggan ini masih punya pesanan yang berjalan…".

UX rationale: saving is invisible work, so it happens automatically at the points where the data is trustworthy (complete details, confirmed checkout, delivered address). The list shows only what staff use to recognise a customer; removal is tucked away, Super Admin only, and needs typing HAPUS.

## Limits

- Copies already held by Qammaris App before a removal are updated only through the new order revision; the App's own stored history is the App's responsibility.
- Free-text staff notes on issues or the order (`staff_note`) are not scanned for personal data.
- Orders whose number differs from the customer's later number stay with the customer they were linked to.
- Not tested on a physical phone; rehearse the migration on MariaDB 11.8 (production version) before release.

## Rollback

Code: revert the PR. Schema: the down migration drops the two added columns and keeps the nullable creator columns. Backfill links: see the runbook (forward fix preferred).
