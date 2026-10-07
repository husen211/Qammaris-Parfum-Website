# ORD-01 — Pesanan Online: verifikasi lokal

Date: 2026-10-07. Owner approval: rencana ORD-01 disetujui di sesi ini (data customer disimpan tanpa hapus otomatis; link customer + link tugas staf; sharelok via WA; edit sampai Dibayar; timeline customer sederhana; komponen timeline 21st.dev). Status: IN_REVIEW. Branch `modernization/ord-01-online-orders` dari `origin/main` `c6e2dfd`. Belum ada deploy/migrasi staging atau produksi.

## Scope implemented

- Additive migration with three tables (`online_orders`, `online_order_items`, `online_order_events`). Models, `CreateOnlineOrder`, and `OnlineOrderWorkflow` (single transition authority).
- Support: `OnlineOrderMessages` (staff group/customer invite/location) and `OnlineOrderTimeline` (field allowlist per audience). Blade timeline component.
- Routes and views:
  - Customer: `GET/POST /pesanan/{token}`.
  - Staff: `/tugas-pesanan/{token}` (+ `langkah`, `talangan`).
  - Admin: `admin/orders` (index/create/show/update/advance/revert/cancel/reimburse/regenerate/product-search).
  - The sidebar placeholder "Orders (Coming Soon)" becomes "Pesanan Online" (desktop + mobile).
- `InquiryWhatsApp` gains public `textUrl`/`shareUrl` and `plainText`; existing checkout behavior is unchanged.
- No new package, no change to the cart/checkout flow, no change to catalog data.

## Checks actually run

- `php artisan test --filter=OnlineOrderTest`: **12 passed / 121 assertions** (`tests/Feature/OnlineOrderTest.php`). Coverage:
  - snapshot price and hashed/encrypted token;
  - rejection of draft/zero-price offers and non-admins;
  - customer submit with normalization and private headers;
  - `required_if` intercity and dropped address for pickup;
  - edit until paid, then read-only;
  - unknown/expired/replaced links (customer and staff);
  - transition validation and idempotent replay;
  - staff limited to shipping steps (no payment), J&T tracking number required, advance → reimburse, no internal data shown to the customer;
  - revision conflict, revert, cancel/restore;
  - both group-message variants and the location URL allowlist;
  - escaping/flattening of untrusted text;
  - cart checkout still stores no order.
- `php artisan test` full suite, final state after all view tweaks: **394 passed / 2787 assertions**.
- `node --test tests/js/*.test.mjs`: 31 passed. Pint `--test` on all changed/new PHP: passed. `npm run build`: passed, with the existing large 3D chunk warning unrelated. `git diff --check` and `composer validate --strict`: passed.
- Migration up → rollback → up on an isolated SQLite DB holding the browser test data: clean; product rows unchanged (3 → 3).

## Browser evidence

Real Chrome (headless, driven through the DevTools Protocol by a scratch script) against the worktree running on `php -S 127.0.0.1:8123`.
- Data: an **isolated SQLite DB** with three synthetic products and a temporary synthetic admin account. The local MySQL and the original repository were not touched.
- Cleanup: the DB, credentials, and Chrome profiles were deleted after the checks.
- The tokens visible in the screenshots belong to that deleted DB.
- Raw results: [browser-checks.json](browser-checks.json).

| Check | Result |
|---|---|
| Before | [admin sidebar 1440](before-admin-sidebar-1440.png), [admin mobile menu 390](before-admin-menu-390.png): "Orders (Coming Soon)" disabled |
| Admin create | Product search shows 3 results; clicking twice adds quantity 2; redirect to detail. [create](admin-create-1440.png), [new detail](admin-show-new-1440.png), [empty list](admin-index-empty-1440.png) |
| Customer form | No horizontal overflow at 320/375/390 (`scrollWidth − innerWidth = 0`). [320](customer-form-320.png), [390](customer-form-390.png) |
| Validation | Empty submit: 4 Indonesian messages; summary receives focus. [screenshot](customer-validation-390.png) |
| Conditional fields | Intercity makes the full address required and shows postcode; pickup hides the address |
| Keyboard | Tab order: name → HP → delivery option (radio group) → paperbag → note → submit; the radio card shows a solid outline on focus-visible |
| After submit | Location-sharing CTA + timeline + recipient data + "Ubah data". [screenshot](customer-status-submitted-390.png) |
| Reduced motion | Active icon: `animation-name: pulse` normally, `none` with `prefers-reduced-motion: reduce` |
| Admin paid + staff-books mode | Group message "MOHON DIPESANKAN MAXIM …" with HP, location, fee/talangan note, staff link. [1440](admin-show-paid-1440.png), [390 no overflow](admin-show-paid-390.png) |
| Staff | Name remembered after the first step; driver booked → shipped → talangan recorded. [task](staff-task-390.png), [after](staff-after-shipped-390.png) |
| Completion | Admin reimburses + marks Received; full timeline + activity log. [screenshot](admin-show-completed-1440.png), list [1440](admin-index-1440.png)/[390](admin-index-390.png) |
| Customer final | 6 steps completed; staff name not visible. [390](customer-status-completed-390.png), [1440](customer-status-completed-1440.png) |
| Invalid link | Neutral 404 page with a WhatsApp button. [screenshot](customer-invalid-link-390.png) |
| Console | Only the expected 404 resource for the invalid link; no JS exception |

The first admin screenshots captured only the viewport because the admin shell scrolls inside `<main>`. The capture script unclips the shell for screenshots only; no UI change. After review, the staff-message textarea was enlarged from 11 to 15 rows so the task link is visible, and the customer-chat link gained a 44px target.

## Limitations and risks

- Not tested: genuine touch/iPhone Safari, real sending/receipt in WhatsApp, clipboard on a real device. Headless Chrome refused the clipboard, so the button showed the "teks sudah dipilih" fallback state.
- Staging MySQL/production has no migration yet. Concurrent locking was tested only on SQLite.
- The staff link is a bearer link: anyone holding it can mark shipping steps. The staff name is not authenticated.
- Customer data is stored with no retention policy (Owner decision); see ORD-05.
- No item edits/discounts after creation, no keep/titip, no reservation.

## Rollback

Revert the branch commits. The additive tables can be left in place. Do not drop tables that already contain orders without a separate data decision.
