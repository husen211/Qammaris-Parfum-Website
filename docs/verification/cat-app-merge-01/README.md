# CAT-APP-MERGE-01 verification — drafts follow App merges

Date: 2026-10-29. Branch `fix-app-merge-follow`. Local only (SQLite, `php artisan serve` + Vite); nothing ran against production.

## Production evidence (read-only, Owner's admin session)

- "Disembunyikan aplikasi" 15 rows: 10 OTW products created in the App and merged on Majoo import, 5 Majoo items (empty bottles, paperbag).
- The 9 Website drafts linked to merged OTW UUIDs were all archived by hand (#477, #502, #639, #894–#897, #919, #934); none had a photo. Example still visible today: OTW "SAFF" (#934) next to the separate Majoo draft "SAFF & CO. EXTRAIT DE PARFUM - LAS POZAS 35ml".
- Admin Products default list: "Menampilkan 490 produk" (481 published/draft + 9 archived).

## Tests

- `QammarisAppCatalogWorkflowTest::test_otw_draft_follows_the_app_merge_into_the_majoo_product_in_either_order`
- `QammarisAppCatalogWorkflowTest::test_merge_into_a_majoo_product_that_already_has_a_page_retires_only_an_untouched_draft`
- `AdminProductArchivingTest::test_admin_catalog_exposes_clear_archive_and_restore_states` (updated: archived under the filter)
- `QammarisAppIntegrationTest::test_merge_and_other_department_tombstones_preserve_original_product_and_mapping` unchanged and passing (published products keep their mapping).
- Full local suite: 830 passed, 10 skipped, 3 failed (known local-only: ReleaseCheck VAPID, two J&T QR tests without GD).

## Browser (local)

Synthetic "Uji Merge" products were created through a faked App feed (OTW merged before the Majoo product arrived; OTW merged into a Majoo product that already had a draft) plus a temporary local admin. All rows, the sync checkpoint and the admin were removed afterwards.

| Check | 375 | 1440 |
|---|---|---|
| Before: default list ("Semua status") shows the archived "Uji Merge SAFF" among live drafts | — | [before-products-1440](before-products-1440.jpg) |
| After: default "Tayang dan draft" lists 2 products; archived only under Diarsipkan | [after-products-375](after-products-375.jpg) | [after-products-1440](after-products-1440.jpg) |
| After: "Produk dari aplikasi" Semua 2 — "Water of maluku" now follows the Majoo product (Rp 350.000, Tersedia); no duplicate | — | [after-app-products-1440](after-app-products-1440.jpg) |
