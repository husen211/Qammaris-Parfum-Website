# P8-03 — Owner-selected AOERA staging pair

Prepared 2026-10-05. Owner selected **AOERA MAJESTIC 50 ML** and explicitly requested preparation of its staging pair/mapping. This is a retained, protected staging fixture for the pending availability test; it is not launch catalog curation or a production mapping.

## Exact pair and baseline

| Item | Verified value |
| --- | --- |
| Website environment / database | staging / dedicated staging MySQL |
| Website product ID | **1** |
| Website name / size | **AOERA MAJESTIC 50 ML / 50 ml** |
| Staging detail URL | `https://staging.qammarisparfum.id/products/p8-03-aoera-majestic-50-ml` |
| Provider / source UUID | `qammaris_app` / `00360de8-31bd-4982-bd73-ddaaba2d9658` |
| Reviewed source SKU / brand | `AOERAMAJESTIC50ML` / Aoera; assistance only, not mapping keys |
| Source availability / hidden | **available / false** |
| Applied source revision | **59** |
| Global checkpoint at preparation | **452**; unrelated products also contribute to this number |
| Website label / publication | **Tersedia / published**, publicly visible behind staging Basic Auth |
| Initial fixture offer price | **Rp180.000**; fixed fixture value, not automatic source price application |
| Mapping identities / availability audit | **1 / 1**; actor `qammaris_app` |

Staging was empty before preparation. Created only one product, one active 50 ml offer, one Aoera fixture brand, one **Uji integrasi (staging)** category and one primary placeholder image. Description explicitly identifies the record as a technical fixture: category, audience **Unisex**, and placeholder are test values, not verified perfume facts. No real photo was imported or hotlinked.

Media key `products/p8-03-aoera-placeholder.svg` is a verified byte-for-byte copy of the bundled `public/images/product-placeholder.svg`, written to the configured staging **public** disk. It is used only for this fixture. No production/source-app data, existing credentials, filesystem permissions, real catalog/media or local preview database were modified.

## Operations and checks actually run

1. Reviewed the allowlisted synchronized source snapshot: exact name, UUID, Aoera brand, active, not hidden, price 180000. An unrelated local preview lookup found no matching product; local MySQL was unavailable and was not repaired or modified.
2. Created the draft and offer/image using existing `SyncSingleOffer` and `AttachProductImage`; `EvaluateProductPublicationReadiness` returned **no blockers**.
3. Ran `qammaris-app:map 1 00360de8-31bd-4982-bd73-ddaaba2d9658` without confirmation and reviewed its exact identity preview.
4. Applied that same pair with `--confirm`; cached revision **59** replayed and one availability audit was created. Published only this fixture via `PublishProduct`, retaining readiness checks. Offer ID, price, slug and image IDs stayed unchanged through mapping/publication.
5. Repeated the same confirmed mapping: exit **0**, identity count **1**, audit count unchanged. No cursor reset or bulk map.
6. At **16:11:47 UTC**, staging Laravel HTTP-kernel detail and catalog requests each returned **200** with the selected name, `available` state and **Tersedia** label; no legacy freshness text. First CLI render attempt incorrectly ran with console-only provider behavior and returned 500 (`footerCategories` missing). A process-only HTTP-context override corrected that verification setup; no application/config-file edit was needed.
7. Unauthenticated external detail request returned **401**. In-app Browser and Chrome could not open the protected page because review Basic Auth credentials were unavailable to automation. Browser/mobile/desktop visual acceptance and actual external authenticated 200 are **not confirmed**; protection was not relaxed. No page screenshot or new regression suite run; no application code changed.

## Ready for Owner action

Open the staging URL using existing review Basic Auth directly in the browser; never send credentials through chat. The loopback preview at `127.0.0.1:8000` remains separate and is not this live staging fixture.

Report **this exact source product** sold out through the normal application workflow, then allow the website team to observe its newer source revision, signed webhook delivery, worker audit and **Habis** label before deleting the report. The newer revert should restore **Tersedia**. Prepare/compare the source `stock_status_at`, website audit `created_at`, local checkpoint, product label and unchanged price/URL/media. Do not infer that revision 59 is the global checkpoint, or that synthetic wake-up tests prove real app delivery latency.

## Owner sold-out test — 2026-10-05

Owner reported this selected product sold out, then asked the website team to check. No manual reconciliation or source mutation was triggered during this verification.

| Observation | Result |
| --- | --- |
| Source detail GET | **200**, same UUID/name, sold_out, hidden=false |
| Source revision / global checkpoint | **453 / 453**, up from product revision 59 / checkpoint 452 |
| Source stock_status_at | **2026-10-05 16:51:56.923 UTC** |
| Website snapshot/audit commit | **2026-10-05 16:52:07 UTC**, actor qammaris_app, available → sold_out |
| Observed status-to-commit delay | Approximately **10 seconds** (about 10.1 by stored timestamps; audit time has second precision and cross-system clock offset was not independently measured) |
| Website product | **Habis**, still published, not hidden, price **180000**, size **50 ml**, same slug and image ID **1** |
| Worker state at 16:52:49 UTC | Queue pending **0**, last_error **null**, last_synced_at **16:52:07 UTC** |
| Mapping/audit | One existing identity; two availability audits: initial revision **59** and sold-out revision **453** |
| Catalog/detail render at 16:53:19 UTC | Both application HTTP **200**, selected name and sold_out state, **Habis** label, no legacy freshness text |

The automatic application happened between the half-hour reconciliation ticks, consistent with the configured webhook path. Exact source webhook send/ingress 202 timestamps were not captured; no accessible staging domain access-log files were found at the domain logs path. Do not label this as directly observed webhook arrival timing or browser paint latency. Authenticated browser/mobile/desktop acceptance remains unconfirmed because review Basic Auth access was unavailable to automation.

## Owner return-to-available test — 2026-10-05

Owner then returned the selected product to available and requested verification. No manual sync or source mutation was triggered during this check.

| Check | Observed result |
| --- | --- |
| Source detail | HTTP **200**, availability **available**, hidden **false**, revision/change_seq **454** |
| Source status time | **16:55:03.862 UTC** |
| Website application time | Cache and audit **16:55:17 UTC**, approximately **13 seconds** after source status time |
| Website state | Product **1**, **Tersedia**, published, not hidden; checkpoint **454** |
| Audit | Revision **454**, actor **qammaris_app**, **sold_out → available**; three availability audits including baseline 59 and sold-out 453 |
| Queue/sync | Pending jobs **0**, last sync **16:55:17 UTC**, last error **null** |
| Retained fields | Offer price **180000.00**, size **50 ml**, existing slug and image ID **1** unchanged |
| Server-side HTTP-context render | Catalog and detail **200** at **16:56:49 UTC**, available markers and **Tersedia** label, no legacy freshness text |

The selected product's automatic sold-out/return-to-available cycle is verified. These delays compare stored timestamps; clock offsets, exact real webhook ingress/202 time and authenticated browser paint latency were not independently captured. Browser visual review and mapped hidden/OTW/unknown scenarios remain P8-03 gates. Production cutover/P8-04 broad launch mapping has not started.

## Retention / rollback

Keep the single fixture and exact mapping available for the Owner's test. After that test, archive the fixture with the existing reversible product operation; retain IDs, external identity, availability audit and placeholder file. Do not delete source snapshots, reset checkpoint, delete/rebind mappings, reseed staging or drop tables. Any cleanup of retained media is separate approved work.
