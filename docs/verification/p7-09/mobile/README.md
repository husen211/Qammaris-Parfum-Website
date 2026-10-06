# P7-09 mobile follow-up — 2026-10-06

Status: IN_REVIEW, frontend only; not deployed. Owner's new iPhone screenshots show the same gold card panel and retained outline as the earlier report. This continuation belongs to PR6, not the unrelated best-seller branch.

## Change and rationale

The existing PR6 patch removes full-card active/pending gold backgrounds, restores position without forcing pointer focus, and retains keyboard focus. This continuation removes the catalog name hover underline; mouse-only hover now uses a subdued text color without geometry changes. Click feedback remains brief text color followed by neutral text, with a separate loading indicator.

`resources/css/catalog.css` and `resources/views/products/index.blade.php` give mobile Filter and the native sorting select separate 46px controls aligned with the catalog gutter. Sorting is a visible native select instead of an invisible overlay. Filters retain GET query state and existing submission behavior. Selected brand styling follows the checkbox immediately, rather than the previous server-rendered selection. Search/filter text inputs use16px on narrow screens to avoid focus zoom.

The filter sheet uses dynamic viewport height and safe-area footer padding. Only the fields scroll; heading/close and apply/reset actions remain outside that area. Outer panel/form use overflow:clip because overflow:hidden still allowed scrollIntoView to scroll the outer sheet and hide the heading during verification. Browser verification after this correction: inner fields scroll1016px, outer panel scroll0; heading top121px and footer767–844px remain visible at390x844.

## Checks actually performed

- Chrome CSS viewports320x844,375x844,390x844,768x900,1440x900: no page horizontal overflow or horizontally clipped product names. Two catalog columns on phones; three at768. Local isolated reference has350 published products, normal24/page; no production catalog writes.
- Brand Afnan selected through the filter returns17 products; selection has immediate neutral background, filter count1. Native price-low sorting preserves brand and orders visible prices correctly. Search with a nonexistent term yields the existing recoverable zero-results state.
- Pointer product click/Kembali preserves brand+sort and y212 without focus, outline, shadow or card background. Desktop native Back likewise restores a neutral card. Keyboard Enter product/Kembali restores focus-visible with a solid outline. Keyboard accessibility was not removed to hide touch artifacts.
- Expanded brand list and lower price input: fields scroll independently while heading/footer stay visible after the overflow correction. Filter open/close and 320px sheet verified. Inputs and apply/reset controls remain reachable.
-20 Node tests passed, including native navigation and pending cleanup, pointer/keyboard return and hover guards.
-13 Laravel catalog discovery/card trust/hardening tests passed,100 assertions. Final Vite production build and git diff --check passed. Existing DaisyUI@property and about-lanyard chunk-size warnings remain.
- Final local console error/warning observation returned[]. Browser viewport override reset and owned verification tab closed. Existing local server retained.

Viewport resizing and mouse input are **not touch emulation**: mouse media remains true in this Chrome session. Actual iPhone Safari single-tap, scroll/no-stuck-hover and device keyboard/safe-area behavior remain **Not confirmed**. Earlier Owner touch waiver applied to the previous production release, not this follow-up.

## Evidence

- [Before390](before-390.png), [after390](after-390.png), [after320](after-320.png), [after375](after-375.png), [after768](after-768.png), [after1440](after-1440.png).
- [Filter390](after-filter-390.png), [filter320](after-filter-320.png), [expanded filter scrolled390](after-filter-scroll-390.png).
- [Pointer return390](after-return-390.png), [pointer Back1440](after-back-1440.png), [keyboard return1440](after-keyboard-1440.png).
- Earlier desktop before/after card evidence remains in the [parent report](../README.md); Owner's new screenshots supply the live iPhone before state.

Screenshots are raw captures; extension raster dimensions can differ from requested CSS viewport. Static screenshots do not prove touch timing.

## Impact and recovery

No schema, API, environment, product content, price, stock, identity mapping or media-file change. Ordinary local detail GETs may increment the fixture's existing view counters. No new accounts/fixtures/dependencies or production actions. Rollback: revert the frontend commits and rebuild; no data/media restoration needed. Documentation updated: this report and BACKLOG. Recommended next step is Owner iPhone review and a separately authorized P7-09 release; no next backlog item started.
