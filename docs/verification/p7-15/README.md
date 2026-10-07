# P7-15 — Relevant, typo-tolerant search

Status: released and verified on production, 2026-10-07. The initial implementation record below is historical; the final release checkpoint supersedes pending deployment statements.

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

## Owner-authorized release — 2026-10-07

After reviewing the proposed order (release the ready search, perform final checks, then a separate read-only repository audit before overall planning closure), Owner agrees: “oke setuju”. This authorizes the concrete P7-15 release through the existing main CI/production pipeline, without data/credential/schema changes or a refactor. Physical Safari/touch remains an explicitly unverified device scenario.

Preflight: current production d7a511504068720a31be510d16588e12b39a9f06,22 migrations Ran, worker/scheduler heartbeats02:27:02UTC,448 products/1080 image records, jobs0/failed0, checkpoint467, last sync02:00:04UTC/errorabsent,454 cached sources. Read-only browser `rverie` still showed0 before deployment. PR14 head58ec754 had both checks green and was merged as669de1130093ad0391e9beeee7f068c40555ac6a. Main CI37562107686 passed. Production run37562153305 and live verification are pending at this checkpoint.

## Final production checkpoint

[PR14](https://github.com/husen211/Qammaris-Parfum-Website/pull/14) is merged; main CI37562107686 and [production run37562153305](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37562153305) succeeded. First deploy attempt failed before upload with runner-to-host SSH connection timeout; previous code remained active. One retry of the same failed job/artifact succeeded, with no credential/configuration change. Server revision is669de1130093ad0391e9beeee7f068c40555ac6a; deployment log02:33:23UTC (09:33:23Asia/Bangkok).

Actual MySQL/read-only post-release query:448 products,371 public/77 drafts,1080 image records, jobs0/failed0, checkpoint467/errorabsent, last sync02:30:03UTC. Scheduler heartbeat02:34:01UTC and worker watchdog02:34:02UTC. Feed without key401/with existing key200, has_morefalse/next_seq467. Credentials stayed in server memory; only presence/status printed. Exact/typo query each returned only Reverie; `reverie 50ml` and nonsense returned no products.

HTTP `/up`200, public typo search200/one card, wrong-size search200/zero cards; `.env` HEAD403. Automatic review rejected an initial GET proposal for `.env` because it could retrieve secrets; that command did not run. The accepted status-only HEAD alternative did not retrieve file content. No server security or file permissions were changed.

Actual Chrome production390x844 and1440x900 measured390/document375 and1440/document1425, with loaded product photo and no horizontal overflow. Native search submission, correct detail link and Kembali with retained query passed. Desktop `afnna` brand search shows Afnan-related options without selecting them; Escape closes/focuses the disclosure. Existing authenticated Owner session: admin product `rverie` and app inbox `rverie` each show one correct product. No login/reset/new account, admin POST/import/apply/publication, cart or customer submission. Normal detail viewing can increment its existing view counter; no catalog identity/content/price/stock/media mutation was submitted. Console showed only browser-extension deprecation warnings, no captured application errors.

Screenshots: [production before390](production-before-mobile.png), [after390](production-after-mobile.png), [after1440](production-after-desktop.png), [authenticated admin1440](production-admin-search.png). Additional requested320/375/768 overrides did not resize the inactive public tab (measurements stayed1440); no new production proof is claimed at those widths. Their earlier local checks remain valid. Temporary viewport reset and owned tabs closed.

Physical Safari/touch, production native file chooser/Shopee upload/apply, real new upstream stock/price event latency, screen-reader and broader post-release observation remain **Not confirmed**; retain these as bounded P9 verification, not P7-15 search regressions. Overall program is not closed. Next is P9 verification, then the Owner-approved separate evidence-first clean-code/documentation audit; no audit/refactor started in this release.

Recovery: retain previous d7a511504068720a31be510d16588e12b39a9f06. Revert/forward-fix code through the existing CI/release pipeline if needed; no schema/data/media restoration or cursor reset. Follow-up release evidence is documentation/screenshots only and does not request a second code deployment.
