# ADM-02 — Simpler Pesanan Online filters (2026-10-21)

Owner request (card #79): the order list had 14 filters in a dropdown and needed a "Tampilkan" click; too much in daily use.

## What changed

- The dropdown and the "Tampilkan" button are replaced by three tabs, **Aktif · Selesai · Dibatalkan**. Each tab is a plain link, so it applies on tap and works without JavaScript. "Aktif" is unchanged: everything not finished or cancelled, including orders waiting for courier pickup.
- The search box stays (code, name or phone) and runs on Enter. A search looks in every status, so a finished or cancelled order is found from any tab; the page says "Hasil cari … di semua status" with "Hapus pencarian".
- The special filters (Ada kendala, Talangan belum diganti, Refund, Rekonsiliasi, Menunggu konfirmasi website, Keep, …) are no longer listed. They stay reachable from the existing attention banners and old links; the page names the active one with "Hapus filter".
- Desktop: tabs and search on one row. Empty state follows the tab or the search.
- No data, schema or API change. `AdminOnlineOrderController::TABS` lists the tabs; `FILTERS` keeps the other keys for the banners.

## Checks

- New `AdminOrderListFiltersTest` (2 tests): tabs replace the dropdown, one current tab, waiting-for-pickup order stays in Aktif, Selesai/Dibatalkan tabs, search across statuses, banner filter note.
- Full Laravel suite with a CI-like `.env.testing`: 777 passed, 10 skipped, 3 failed; those 3 laptop-only failures (no GD in XAMPP PHP, Windows behaviour) also fail on unmodified `main`.
- Browser (synthetic SQLite DB, synthetic Staff Order, removed afterwards): 375 tab tap to Selesai, Enter search found a finished order; screenshots at 390 and 1440.

| | Before | After |
|---|---|---|
| List 390 | [before-list-390](before-list-390.png) | [after-list-390](after-list-390.png) |
| List 1440 | [before-list-1440](before-list-1440.png) | [after-list-1440](after-list-1440.png) |
| Search 390 | — | [after-search-390](after-search-390.png) |
| Banner filter 1440 | — | [after-banner-filter-1440](after-banner-filter-1440.png) |

UX rationale: the order rows already show each status, so the list only needs the three buckets staff switch between; the banners surface what needs attention without a filter menu.

Rollback: revert the PR (view and controller only).
