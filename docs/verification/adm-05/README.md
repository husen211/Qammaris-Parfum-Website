# ADM-05 — Ordered products stand out on the order page

Owner 2026-11-03 (card #157): on a website order waiting for confirmation, the Owner read the gold "Langkah sekarang" card (shipping, address, paperbag) and only later noticed the products in the plain right-hand card. Below 1280 px the card sat at the very bottom of the page, after "Lainnya".

## Change

- The card is now "Produk dipesan · N barang" (N = sum of quantities), with a 56 px photo per line (the product's current primary photo, else the existing placeholder), the quantity in front of the name, size on the line below (`@ unit price` only when quantity > 1) and the line total on the right. Lines sit on a light grey tray inside a white card with a slightly stronger border; no new colour, and the brand gold stays reserved for the current step.
- Below `xl` (1280 px) the card comes right after the stepper, above "Langkah sekarang". From `xl` it still heads the right column: the step column spans two grid rows (`auto_1fr`), so recipient, customer and history cards follow the product card without a gap.
- The confirmation checklist reads "Cek stok N barang di Produk dipesan, …".
- Name, size and price come from the order's own snapshot; only the thumbnail looks up the current product (`OnlineOrderItem::product`, eager loaded with `primaryImage`). A product deleted or without a photo shows the placeholder.

No migration, no data change, no Order API or customer page change.

## Local check (2026-11-03)

Synthetic website order `QAM-9901` (three lines, quantities 1/2/1, two products with a photo and one without) copied from a local draft, plus a temporary Super Admin; both removed after the check. The local `APP_URL` port differs from the preview server, so the screenshot run rewrote image URLs from port 8011 to 8010 before capturing; the HTML itself was not changed.

| Viewport | Before | After |
|---|---|---|
| 1440 × 900 | [before-1440.jpg](before-1440.jpg) | [after-1440.jpg](after-1440.jpg) |
| 390 × 844, first screen | [before-390-first-screen.jpg](before-390-first-screen.jpg) | [after-390-first-screen.jpg](after-390-first-screen.jpg) |
| 390, where the products were | [before-390-produk-after-lainnya.jpg](before-390-produk-after-lainnya.jpg) (after the "Lainnya" divider) | [after-390-step-card.jpg](after-390-step-card.jpg) (step card follows the products) |

Measured: at 1200 px the product card now starts at 298 px (was 1632 px down the page); at 390 px it is on the first screen (was 1807 px down); no horizontal overflow at 390; at 1440 the recipient card starts 16 px under the product card.

Test: `WebsiteCheckoutOrderTest::test_order_page_shows_what_was_ordered_first_with_photo_quantity_and_count` (order of the card before the current step, count, quantities, `@` unit price, photo and placeholder, checklist wording, snapshot name kept after a catalog rename, single card in the HTML).

## Release (2026-11-03)

Owner approved ("gas rilis"). PR #158 merged as `524b9f9`; CI and Production release green; `/up` 200. Live, read-only in the Owner's admin session at 1707 px: QAM-0030 (completed website order) shows "Produk dipesan · 2 barang", both product photos loaded (no placeholder), lines "1× Inception 100ml · 100 ml · Rp 425.000" and "1× Conquer 100 ML · 100 ml · Rp 449.000". Nothing was submitted.

## Open limits

- Physical phone not tested.
- The thumbnail follows the product's current primary photo, not a photo at order time.
