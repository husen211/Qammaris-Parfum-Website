# P7-15 — Relevant, typo-tolerant search

Status: implementation in review, 2026-10-07. No production deployment.

## Diagnosis and change

Read-only production reproduction: `/products?search=reverie` returned Reverie Aqua and SAFF CHNO. The former product search OR-ed name, brand and full description; CHNO's description contains `reverie`. Owner's screenshots also show `rverie` returning nothing.

Product search now considers name, brand and active offer size, with exact variant SKU matching. It excludes full descriptions and fragrance prose. App inbox searches source/current names and source brand, with exact source SKU/UUID matching. Shopee row search uses source name within the existing actor-owned batch and status scope. Brand/category searches use names; blog search retains title/excerpt, excluding article body. The catalog brand dropdown and manual Shopee target options share the client matcher. Navbar search reaches the same catalog operation.

Shared PHP matcher: bounded input, case/accent normalization, punctuation-separated words, exact/partial matches before conservative typos. Each query word must match. Alphabetic words of four or more characters tolerate one edit; eight or more tolerate two. Adjacent swaps count as one error. Numbers must match exactly; identifiers are exact only. A query such as `100ml` cannot match `50ml`. Default catalog search orders by relevance before best-seller/latest tie breakers. Explicit sort choices retain their ordering. Search never selects, binds, applies, or publishes an import row.

Eligibility filters precede candidate matching and remain on the final query: publication, hidden source, availability, taxonomy, admin access and import ownership are preserved. SQL CASE ordering uses bound IDs/scores, with no raw search interpolation. Existing pagination/query context remains.

## Verification actually run

- Full Laravel suite: **330 tests, 2228 assertions passed**.
- Node tests: **31 passed**, including shared PHP/JS fixtures and preserved manual selection.
- Vite production build, scoped Pint and `git diff --check`: passed. Existing DaisyUI/property and unrelated large about-page chunk warnings remain.
- Regression coverage: exact/typo/partial/multiword positives; CHNO-description negative; strict size/SKU, invalid array/wildcard input; filters/hidden/draft isolation; relevance/explicit sort; 30-result pagination; actor-owned Shopee rows and no data mutation; app inbox and brand/category/blog searches.
- Local metadata matching on the 445-product reference: warm searches about24–26ms, first query about60ms. This is a local search-operation measurement, not a production page/network benchmark.

Chrome browser: public `reverie` and native submitted `rverie` each showed one correct product; detail link and Kembali preserved the query. Desktop brand dropdown `afnna` found Afnan-related options without changing checkboxes. Admin source-row `rverie` and manual target filtering worked, with blank selection unchanged. Nonsense query showed the existing empty/reset state. Browser error/warning log was empty at the last check.

Widths320/375/390/768/1440 had no horizontal document overflow. Screenshots:

- [Owner before: typo](owner-before-typo.jpg), [Owner before: unrelated result](owner-before-results.jpg): supplied production phone captures, not agent captures.
- [Local after: mobile390](after-mobile.png), [desktop1440](after-desktop.png), [manual import options390](after-import-mobile.png).

The isolated preview copied an existing historical local SQLite reference (445 products, 350 published); it is not represented as current production data. Synthetic local account, source identities and one import batch existed only in that copy. No apply/publication was submitted. The task-owned server and entire preview copy were removed after verification; original reference database/media were retained.

## Limits and recovery

Physical iPhone/Safari/touch, screen reader, production MySQL/runtime and production behavior with this new code: **Not confirmed**. No search engine, dependency, migration, feed/price rule, product/media change or production credentials were introduced. PHP transliteration and browser NFKD are verified against shared Latin fixtures; equivalence for every writing system is not claimed.

Metadata scanning is intentionally sized for the current hundreds of records and adds a scoped metadata read. Profile before substantially growing catalog/import batches; do not infer suitability for arbitrary scale. Default best-seller ordering remains unchanged when no search is entered. Manual import selections remain human decisions.

Rollback: revert this feature's code/assets and rebuild using the existing pipeline. No database/data/media rollback is needed. Next action: review P7-15 and release/device acceptance, without beginning another feature.
