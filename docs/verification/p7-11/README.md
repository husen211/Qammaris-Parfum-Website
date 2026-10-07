# P7-11 — Catalog filter dropdowns and search toolbar

Owner requests compact dropdowns for brand and other catalog filters. Active surface is `/products`, desktop sidebar and mobile filter dialog only. Branch `codex/catalog-filter-dropdowns` starts from production/main `7b86b00`, independently of unreleased PR6/PR7/PR8. No release authorization is inferred.

## Outcome and rationale

- Replace the long exposed brand list with a native disclosure dropdown: bounded scroll, local brand-name search, selected count, and multiple checkboxes. Desktop waits for **Terapkan brand**, avoiding a page reload per checkbox. No-results feedback is explicit; hidden search matches remain selected/submitted.
- Category, Peruntukan and availability use the same native-select styling on both surfaces. Price bounds live in a second disclosure, retaining arbitrary existing min/max parameters rather than introducing fixed price bands. Native select popups remain browser/OS controlled.
- One shared Blade partial provides both filter forms. Keep the existing GET allowlist, visibility rules, query/order, pagination and detail context. Legacy unknown URLs remain accepted without adding unknown as a new choice.
- Search is above results on both surfaces; desktop search, result count and sorting share one aligned row. Shared client draft state preserves edited controls across search/sort submissions and reopening the mobile dialog.
- Mobile dialog fits content, bounded at88dvh, with scrolling and existing bottom apply/reset actions. Native disclosures support keyboard Enter; Escape closes a disclosure and returns focus before a second Escape closes the dialog. Outside clicks collapse an open disclosure. No pointerdown navigation, new dependency, animation, card effect or navbar redesign.

## Checks actually run

- Focused PHPUnit: **10 tests /86 assertions**, including a new regression proving normalized multi-brand, category, gender, availability, price and sort parity across desktop/mobile forms and omission of stale page inputs. Existing query/price/visibility/detail context regressions pass.
- **12 existing Node regressions passed**, JavaScript syntax check, scoped Pint, production Vite build and whitespace checks passed. Existing DaisyUI `@property` and about-lanyard bundle warnings remain.
- Chrome CSS viewports320,375,390,768 and1440px: zero document horizontal overflow. Default mobile/desktop screenshots and320px open brand/price cases inspected. Brand panel and dialog have independent bounded scrolling; buttons remain reachable.
- Mobile Afnan + Unisex + available returned6 historical fixture products. Searching `afnan`, then price-low sorting retained all three filters. Brand search no-match showed its empty message and zero visible options. Escape returned focus to summary while keeping the dialog open; closing the dialog restored the Filter trigger/aria-expanded=false.
- Desktop Afnan + Armaf selections stayed on the same URL until explicit apply; together returned45 historical fixture products. Native page2 preserved both brands. Armaf Odyssey Aoud Edition detail and Kembali preserved both brands/page2. Applying250000–600000 removed page2, retained both brands; category Eau de Parfum and price-low sorting retained these bounds and displayed ascending prices. A no-match product search showed the empty state; reset restored the unfiltered catalog.
- Final captured browser error/warn log was empty. Browser navigation intermittently timed out in the extension; subsequent fresh DOM/URL observations confirmed completed navigation. Do not infer actual website latency from those tool errors.
- Physical iPhone/Safari or genuine touch emulation is **Not confirmed**. Native select appearance on those devices and production serving remain release verification items. No deployment performed.

## Evidence and isolation

Before/after use a disposable SQLite copy of the historical350-public/95-draft UI reference, with zero users. Real local photographs are borrowed read-only from the retained fixture. No original fixture, environment, production/staging DB, product uploads, prices, availability, source mapping or API was changed. Own preview server/copy/router/sessions are removed after verification.

| Surface | Before | After |
|---|---|---|
| Desktop catalog | [1440](before-1440.png) | [1440](after-1440.png) |
| Phone catalog | [390](before-390.png) | [390](after-catalog-390.png) |
| Phone filter | [Previous P7-08 filter reference](../p7-08/filter-390.png) | [390](after-390.png) |
| Brand dropdown | — | [390](after-brand-390.png), [desktop](after-brand-1440.png), [320](after-brand-320.png) |
| Price dropdown | — | [320](after-price-320.png) |

Measurements: [browser-widths.json](browser-widths.json). A clipped glimpse of the next product is not introduced; existing grid/card presentation is retained. Viewport raster captures may exclude the desktop scrollbar due to Windows/browser scaling.

## Files, impact and recovery

Changed catalog index, new shared filter partial, catalog CSS, catalog-discovery JS, one existing feature test, backlog and this evidence. No controller/domain/schema/DB/media/env/credential/package/deployment changes. User-facing taxonomies and backend search/filter/order rules are unchanged.

Rollback: revert this frontend commit and rebuild assets together. No database/media restoration. PHP-rendered controls and JavaScript state must ship in the same release.

Recommended next action: Owner review and separately authorized release of this item. Other pending PRs remain separate; do not start another phase.
