# ABOUT-01 / STORE-HOURS / SEC-DEP — Approved production release

2026-10-08. Owner explicitly approved “gass rilis” after the concrete PR34–37 release question. All four PRs merged through the existing GitHub path; no order PR included. This supersedes earlier pending-production remarks in the linked implementation reports, not unrelated P9/Journal acceptance limits.

## Reviewed merge and deployed application

| PR | Scope | Merge revision |
|---|---|---|
| [34](https://github.com/husen211/Qammaris-Parfum-Website/pull/34) | Saturday–Thursday09–21WITA, Fridayclosed |95731b24313bc434e2f423e00272ff2a04f5072b|
| [35](https://github.com/husen211/Qammaris-Parfum-Website/pull/35) | Existing Journal API activation / Windows guidance docs |6d688b8912a494a1dd3d444734ee8c12e1476ced|
| [36](https://github.com/husen211/Qammaris-Parfum-Website/pull/36) | Experience-store About / nine photos / six sourced reviews / Reel / SEO |93beda3092a287d53bf61ecdbc736596535f38ae|
| [37](https://github.com/husen211/Qammaris-Parfum-Website/pull/37) | Scoped npm/CommonMark security patch |fb7ed2819260fde138332836fad3bd47bb99fe4d|

Two documentation conflicts after PR34/35 were resolved by retaining About/store-hours and the actual previously approved API-activation context; final About CI37756284882 passed. Security branch synchronized; CI37756369570 passed on its final head before merge. Final main [CI37756531772](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37756531772) passed471Laravel/3695assertions,31Node tests and Vite build. [Production37756616901](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37756616901) build/deploy succeeded. Server `release-revision.txt` independently matchedfb7ed28; deployment log09:27:46UTC. Documentation-only successors do not change this reviewed application tree; record their own normal CI/deploy rather than fabricating a newer code test.

## Actual live verification

- About HTTP200 at320/375/390/768/1440:oneH1,nine gallery items,six review cards,no broken photo/horizontal overflow/JS error/About3D requests. Canonical and parsed AboutPage/Store/Breadcrumb schema match visible Saturday–Thursday09:00–21:00 hours; Friday excluded; no review schema. [Browser](browser.json), [screenshots](screenshots/). Desktop hero/reviews images visually inspected.
- Genuine Chromiumtouch390px(maxTouchPoints1,hoverfalse):one tap opens gallery/dialog,Escape closes; one tap opens FAQ. Existing initial local gesture/focus proofs remain in [About](../about-experience/README.md); physical iPhoneSafari not exercised.
- Explicit one tap constructs portrait Instagram iframe with Qammaris account rendered and direct fallback visible. Playback itself not exercised; third-party availability remains outside website control. [Reel](reel.json).
- `/up`, `/store/location`, `/blog`, `/products`, `/login`, `/sitemap.xml` returned200. Location visible hours correct. Three publicly linked article URLs returned200 and valid BlogPosting schema; no draft visibility/publication mutation performed.
- Anonymous automation request returned401. Existing local machine token read only in memory:taxonomy/owned-draft GET returned200; no token printed, rotated, issued, copied to cloud or stored in Git, and no article create/update/upload/publish request. [API](api-read.json). Production gate stayed enabled for the existing actor/token; this release did not activate another identity.
- After browser/API checks:one actual worker on current release,fresh minute watchdog/scheduler,zero pending migrations,zero LaravelERROR entries since final deployment. CommonMark2.10.3 installed. [Runtime](runtime-after.json).

## Data, media and diagnostics

453products/1131product-image rows/411variants and one machine actor/token remained stable across reads. Article counts were18 at the closest before/after activation observation,20 at the later final read while concurrent Journal activity continued; do not claim a frozen DB or attribute that activity without further evidence. This release performed no editorial/catalog/order/business writes, migration, restore, reseed or image replacement. Public article GET can advance internal views and authenticated GET can advance normal token usage metadata. Persistent env/storage retained by deploy script;27curated static WebP assets distributed via Git, originals retained at Owner paths.

Initial read-only runtime helper mistakenly queried nonexistent`product_offers` instead of verified`product_variants`, producing one diagnostic ERROR on the previous release. Corrected the helper; final deployment-window errors remainedzero. An initial browser assertion was case-sensitive despite CSS uppercase labels; source HTML had correct hours and the normalized visible-text check passed. No application code fix or weakened deployment guard was used for either checker fault.

No new test/customer/admin account. Temporary local fixtures from implementation were removed. Release helpers remain only until evidence is committed, then cleaned. No raw DB rows, customer data, tokens, env, request bodies or private log lines included.

## Current status / recovery / remaining scope

ABOUT-01, bounded STORE-HOURS and SEC-DEP01/02 release criteria complete. Business rules/architecture/backlog/source notes and dated evidence updated. Live Owner visual review is available at https://qammarisparfum.id/store/about; it is not an invented completed physical-device or SearchConsole test. Google Maps itself still showed23:00 at the earlier source check; changing that external profile is an Owner task, not performed here. Rating is a dated4,9 observation; six selected five-star reviews do not recalculate it.

Existing deploy retains previous code, shared DB/media/env; health failure restores prior current link. No schema rollback is needed for this release. Prefer a scoped forward fix; reverting security locks can reintroduce advisories. Do not purge media/schema or change tokens during recovery. BLOG-06 legacy alt/price prose, physical Safari/production upload/SearchConsole/load and P9/source-event/protected-media acceptance remain separate; order work stays excluded. Next: Owner live About review, without automatically starting a new phase.
