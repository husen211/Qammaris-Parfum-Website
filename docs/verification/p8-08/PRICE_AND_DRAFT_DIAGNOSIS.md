# Production price and remaining-draft diagnosis — 2026-10-06

Owner requests verification of automatic existing prices and reports missing Shopee content on remaining drafts. This is a read-only diagnosis, not a deployment, historical replay, publication or provider rebind.

## Automatic prices

Production revision remains `7b86b00d8d669b6b91e414cb9668090c8509baa9`, installed at11:35:08UTC. At12:03:59UTC there was one actual active-offer mismatch: Dreamland100ML, website264000/source264001, source revision459 accepted at11:26:21UTC by the pre-release stock-only worker. Its historical audit has no price-sync result. Deployment does not replay an already accepted revision or reset the cursor. See `SyncQammarisAppFeed`, `ApplyQammarisAppAvailability`, `SyncQammarisAppPrice`, `SyncSingleOffer` and ADR-025.

Before any corrective write, fresh guards found source revision460 had changed Dreamland back to264000; website/cache/live single-product API agreed. The guarded one-price repair therefore stopped without creating an audit batch or changing data. Do not apply an outdated preview after the source changes.

Actual new production event461 updates **Utopia100ML398000→398001**, audit at12:10:24UTC, `price_sync=synced`. Public `https://qammarisparfum.id/products/utopia-100-ml` returned200 and contained398.001 at12:19:27UTC. This proves a newer source price reached the worker, audit, website offer/base mirror and public HTML. Source edit time/webhook latency was not measured.

Read-only verification at12:12:34UTC: checkpoint461, upstream next_seq461/has_morefalse/new_rows0;390 connected visible products with valid source prices and active offers match both website offer and base_price;0 mismatches;queue0/failed0/no stored sync error. Draft aggregate at12:19:27UTC:91 valid base prices match,0 mismatches,4 invalid source prices. Four invalid-price sources and51 drafts without an active size/price offer account for the remaining55 price-review cases; missing offer is not proof of a missing source price. No arbitrary price or size is inferred.

## Shopee content gaps

Actual production:95 drafts, all95 without active media,0 with nonempty descriptions. The absence in website storage does **not** prove absence from the Owner's Shopee files. Existing launch captures are historical reference only; selected examples below were verified against current production.

Read-only original XLSX inspection: `mass_update_media_info_1853666049_20261005201835.xlsx` and `mass_update_basic_info_1853666049_20261006100429.xlsx`. Each has371 product rows, with Shopee product IDs joining the media/basic files. Extracted ZIP/XML directly after openpyxl rejected an invalid existing activePane value; source bytes were not changed.

| Product | Exact source evidence | Production result | Diagnosis |
| --- | --- | --- | --- |
| Project1945 Heiress of Minahasa | Both files row68; mediaE:G and basicD populated | Draft,0 photos,no description,price329000 matches source | Original Owner CSV marks Shopee55362309206 `tidak_ketemu`; supplemental import deliberately held it |
| Riiffs Freeze | Both files row173; mediaE:G and basicD populated | Draft under spelling Riffs Freeze,0 photos,no description,price598000 matches | Original CSV marks50465122104 `tidak_ketemu` |
| Project1945 Velvet Toraja | Both files row352; mediaE:G and basicD populated | Draft,0 photos,no description,price298000 matches | Original CSV marks41881757124 `tidak_ketemu` |
| Liquid Brun Limited Edition150ML | Both files row51; original CSV56262079706 `kuat` | Published,3 photos,description,price769000 matches | This source is already imported; separate Liquid burn draft469000 lacks confirmed size/version correspondence |
| Mark & victor Eu de Spice | Both files row204; original CSV48962107043 `kuat` | Published,3 photos,description,price239000 matches | This specific source is already imported |

Mpf Conquer is not Mykonos Conquer; Dicium Azure is not Clarity/Auralis/Passion/Thea/Enigma. No exact names for Mpf Conquer, Dicium Azure, Savior70ml or Abadi were found in the provided media export. Current Shopee listings beyond that supplied export are **Not confirmed**.

The original17 `tidak_ketemu` rows were not included in the298 accepted matches or53 Owner choices. Comparing these17 against the existing95-draft launch reference finds plausible name candidates for13, including the3 proven misses above. Similarity is discovery evidence only; sizes, current UUID ownership, existing Shopee links and readiness must be verified before any apply. Do not claim13 approved matches or95 products absent from Shopee.

## Admin terminology and Majestic

`resources/views/admin/app-products/index.blade.php` uses technical terms “snapshot”, “pratinjau draft”, and “Perlu pasangan”; the Owner finds these confusing. These controls cover historical unlinked records, not routine new-product synchronization.445 launch products already have app UUID identities. The sole unlinked source is AOERA MAJESTIC50ML, which previously mapped to the explicitly excluded staging test fixture (see production launch decision and P8-07 evidence). It needs an intentional real-product decision, not an automatic rebind to an unrelated product. Other linked products do not need Owner pairing every time Majoo is imported.

## Scope and next action

No production catalog write, migration, media download/deletion, source-app change, credential change, code deployment or cursor reset. Temporary probes are ignored local operational files; source keys were used in memory only and never output. Automatic approval review rejected a proposed full-catalog local export; it was abandoned in favour of server-side aggregates and bounded named examples, without persisting production catalog records locally.

Documentation only. No application tests, build or UI screenshots required/run for this read-only diagnosis. Existing P7-09 PR6 remains independently pending Owner release direction. Recommended follow-up: a focused current-source/identity-validated supplemental preview for the17 missed Shopee rows, beginning with Velvet Toraja/Heiress/Freeze, and then readiness-checked enrichment/publication of approved matches. Simplify inbox wording within an approved admin UI item. Raw XLSX uploader P8-09 and bulk publication have not been started by this diagnosis.
