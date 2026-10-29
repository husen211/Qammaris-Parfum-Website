# ORD-12 verification — App drafts in admin orders

Date: 2026-10-29. Branch `feat-order-app-drafts`. Local only (SQLite, `php artisan serve` + Vite); nothing ran against production.

## Production evidence that motivated the item (read-only, Owner's admin session)

- "Produk dari aplikasi": Semua 481, Draft 96, Perlu pasangan 0, Periksa harga 58, Disembunyikan aplikasi 15.
- BSP Black Edition 50ml: draft, Tersedia, App price Rp 189.000, has an offer → not in the order search only because it is a draft.
- Periksa harga: 47 of 58 are Majoo drafts with a valid App price and no offer (example Inception 100ml, Rp 425.000); 11 have no valid App price.
- Drafts: 38 of 96 have a priced offer and become orderable with this change.

## Tests

- `OnlineOrderTest::test_admin_orders_app_drafts_the_app_still_shows_but_not_manual_hidden_or_archived_ones`
- `QammarisAppCatalogWorkflowTest::test_otw_draft_gets_its_offer_when_the_app_price_arrives_and_the_name_has_a_size`
- `QammarisAppCatalogWorkflowTest::test_owner_fills_drafts_that_were_waiting_for_a_price_once_and_only_eligible_ones`
- Full local suite: 828 passed, 10 skipped, 3 failed. The 3 failures are the known local-only ones (ReleaseCheck VAPID, two J&T QR tests without GD).

## Browser (local)

Temporary synthetic data: four App-linked drafts ("Uji ORD12 …") and one local test admin, all removed afterwards together with the test order QAM-0017 and its outbox/history rows.

| Check | 375 | 1440 |
|---|---|---|
| Before: search "Uji ORD12" → "Produk tidak ditemukan." | [before-order-search-375](before-order-search-375.jpg) | — |
| After: priced draft selectable with "Belum tayang"; drafts missing size/price listed with reason and "Lengkapi" | [after-order-search-375](after-order-search-375.jpg) | [after-order-search-1440](after-order-search-1440.jpg) |
| Order created from the draft (QAM-0017, Rp 425.000); customer link shows the placeholder image | [after-customer-link-375](after-customer-link-375.jpg) | — |
| App products page: waiting drafts block; button filled "Uji ORD12 Waiting 50ml" (50 ml · Rp 189.000), Periksa harga 1 → 0 | [after-fill-block-375](after-fill-block-375.jpg) | [after-fill-block-1440](after-fill-block-1440.jpg) |

Console: only local missing-asset 404s and refused connections to the local App webhook, unrelated to this change.
