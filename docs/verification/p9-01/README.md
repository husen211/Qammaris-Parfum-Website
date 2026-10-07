# P9-01 — Bounded production acceptance

Status: **IN_REVIEW**. Read-only production acceptance completed on 2026-10-07; device/native-upload/new-source-event limits remain below. This is not overall planning closure or authorization to start refactoring.

## Scope and deployed revision

Owner approved continuing after P8-10 production release. Production serves `0dc6924479ae108177c467a380b3978b1f89c70b` from [PR15](https://github.com/husen211/Qammaris-Parfum-Website/pull/15). Its [production workflow](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37565946279) succeeded; deployment timestamp 03:17:25 UTC. P9 changed documentation/evidence only. No additional deployment, migration, environment/credential/permission change, forced sync, checkpoint reset, product/import/publication POST, media download or order submission was performed by the agent.

The Owner used admin during this observation. These are live checkpoints, not a frozen catalog. Ordinary product-detail GETs may increment the existing view counter; browser navigation also uses ordinary sessions/cache. The agent did not change catalog business data. Owner browser tabs were left open; dedicated verification tabs were closed and viewport overrides reset.

## Runtime and integration

| Check | Observed result |
|---|---|
| Production `/up` | HTTP 200 |
| Source changes feed without key | HTTP 401 |
| Source changes feed with existing configured key | HTTP 200; after_seq=467 returns 0 changes, next_seq=467, has_more=false |
| Unauthenticated admin products | HTTP 302 to authentication |
| Environment-file protection | HEAD-only HTTP 403; content not fetched |
| Webhook without signature | HTTP 401; no job enqueued |
| Cached source/checkpoint | 454 snapshots; checkpoint 467; last_error absent |
| Scheduled reconciliation | last_synced_at 03:30:16 UTC; not manually triggered |
| Worker/queues at 03:54:04 UTC | One PHP worker for qammaris-app,product-import-images; jobs 0, failed_jobs 0; schedule:work 0 |
| Minute scheduler/watchdog | Both heartbeats advance from 03:36:02 to 03:54:01 UTC |
| Runtime safety settings | debug=false; secure/HTTP-only cookies; SameSite=lax; retry_after=120, deployed worker timeout=90 |
| Machine configuration | API key and webhook secret **terisi**; values never emitted |

Evidence: [baseline](production-baseline.json), [final runtime](runtime-final.json). Authorized SSH read production metadata/files in place. Feed authentication used the existing protected configuration in memory, outputting HTTP/pagination metadata only. No secrets or whole source snapshots were saved to this report.

The live empty feed proves connectivity and an exhausted current checkpoint; it does not prove current event latency or exercise pagination. Existing initial-sync/revision/HMAC tests and staged event evidence remain historical, not new P9 live-event proof.

## Catalog and media

At 03:36:27 UTC: **448 website products, 375 published/public, 73 drafts, 1,119 active image records**. All 375 published products pass the actual `EvaluateProductPublicationReadiness` operation. Every active image path exists on the configured Laravel product disk; zero missing files and zero remote/hotlinked image paths. This checks existence, not a new checksum comparison of every image byte.

All 448 connected website products have a cached UUID source snapshot. Visibility, availability, restock ETA, valid-source active offer prices and base prices show **zero differences** against those snapshots. This is snapshot parity, not a fresh fetch of every source product. Six invalid-source-price cases retain their prior data; 33 visible valid-price drafts lack a single active offer. The inbox therefore shows 39 price/size review cases. They are not 39 mismatched published prices.

All 73 drafts lack active primary media and descriptions. Other overlapping readiness blockers: gender 73, category 49, offer 39, brand 2. No automatic completion or publication was attempted. These counts do not assert that all missing content is absent from Shopee; this task did not re-match new exports.

## Real Owner import observed

The existing actor-attributed batch6 was applied by a human at **03:29:27 UTC** while P9 was running. The agent did not click apply/recheck/choice/retry/publish.

- 375 matched/resolved rows; 33 updated, 342 skipped-no-changes, 0 blocked product rows.
- 31 image rows completed; 2 completed with guarded outcomes.
- **39 photo objects stored**, 2 cover outcomes held. Current admin review shows **2 needing work / 373 complete**.

Evidence: [batch observation](owner-batch-observation.json), [held photos](photo-guards.json), [admin screen](admin-batch-1536.jpg). This is real persisted import/worker outcome evidence. The exact native file-selection/upload path that created this batch was not observed. Product publication attribution beyond the batch's import audit is not confirmed; the increase from P8's 371 public products is not attributed to agent work.

The two held covers belong to **Hawas Elixir Edp 100 Ml (658)** and **Qammaris Signature Cache Extrait De Parfum 50ml (472)**. Both are published with two current active photos. `ShopeeContentImageTarget::assert` detects that website media changed since the proposal's baseline and preserves it. This is a media conflict guard, not proof of a CDN outage. Do not force replacement. Review the current media and use a current pair of exports/new inspection or an explicitly reviewed manual upload if a cover replacement is still intended. Repeated download retry alone cannot reconcile an unchanged stale media baseline.

Bounded follow-up finding: the same held-row notice offers **Coba unduh foto lagi** alongside re-upload guidance, which can imply that retry solves a stale target. Record for a separately scoped copy/recovery adjustment; no code change in P9.

## Browser acceptance actually exercised

Chrome on production, with its mouse input and viewport overrides:

1. Public `rverie` returns exactly one Reverie Aqua result. Native card click opens the correct detail/100 ml/Rp 304.000/Tersedia. **Kembali** retains the search URL/result. No order was submitted.
2. The mobile gallery has three photos; **Foto berikutnya** changes the selected photo to the second and its visible main image is loaded. [Gallery screenshot](gallery-mobile.jpg) captures the first photo before that click, not the second-photo state. Lazy offscreen related images were not counted as failures.
3. The 375-product catalog uses 24 cards per page. The five measured catalog sizes below show no horizontal overflow; long card names remain readable and bottle/pack images are contained. They are production data, not a small fixture.
4. Mobile Filter opens the sheet. Selecting available and applying navigates to `availability=available` with 259 results. [Filter screenshot](filter-mobile.jpg) is the open sheet before selecting the availability value.
5. Header cart link opens `/cart` directly, with the empty state and catalog recovery link. `/cart/checkout` with this empty cart returns to cart with a review alert. A populated recipient form, WhatsApp composition/send and order validation were not exercised in this read-only pass. [Cart screenshot](cart-empty-mobile.jpg).
6. Authenticated admin product search `rverie` yields one Reverie. Editor GET shows 3/3 photos, complete readiness and **readOnly=true** for its connected selling price (304000). Cancel returns to the filtered list. No form was saved.
7. App inbox `rverie` yields the same single product/source price, checkpoint 467 and successful last sync; aggregate tabs show 73 draft, 0 unlinked, 39 price review and 6 hidden. **Sinkronkan sekarang** and **Buat pratinjau draft** were not pressed. [Inbox screen](app-inbox-1536.jpg).
8. Existing Shopee review GET shows work-only results and specific media hold reasons. No mutation controls were submitted.

| Actual viewport | Document client / scroll width | Evidence |
|---|---|---|
| 320 × 844 | 305 / 305 | [Catalog](catalog-320.jpg) |
| 375 × 844 | 360 / 360 | [Catalog](catalog-375.jpg) |
| 390 × 844 | 375 / 375 | [Catalog](catalog-390.jpg), [typo search](public-search-mobile.jpg) |
| 768 × 844 | 753 / 753 | [Catalog](catalog-768.jpg) |
| 1440 × 900 | 1425 / 1425 | [Catalog](catalog-1440.jpg) |
| Admin actual 1536 × 770 | 1536 / 1536 | [Batch](admin-batch-1536.jpg), [inbox](app-inbox-1536.jpg) |

Measurements: [browser-widths.json](browser-widths.json). Admin remained at the actual 1536 px despite requested overrides on the other active tab; no claim of new production mobile-admin verification. Responsive admin fixture verification is recorded separately in P8-10.

## Remaining limits and acceptance status

- **Physical iPhone Safari / genuine touch: Not confirmed.** All five responsive widths report mouse-hover capability. Owner device feedback requested, not received at this checkpoint. Mouse sizing is not one-tap/scroll/touch proof, and the earlier release waiver is not reused as a pass.
- **Native file selection/upload: Not confirmed.** One attempt to select the already supplied Informasi Dasar XLSX through the browser file chooser failed because the Chrome extension lacks access to local file URLs. No file was selected/submitted and no extension permission was changed. Both inputs still displayed No file chosen. Do not bypass this restriction to claim browser upload success. Real Owner apply/download outcomes above are independently confirmed.
- **New production upstream event timing, live duplicate/older revision and outage recovery: Not confirmed in P9.** No Owner source-product change requested as an automatic production mutation. Historical tests/staging evidence do not establish a new production 7–15 second measurement.
- **Populated production checkout/WhatsApp send: Not exercised.** Empty navigation only in P9; previous isolated tests and release observations remain separate.
- **Long-term operational stability:** This is a bounded observation, not continuous monitoring. No automation created.

P9-01 is IN_REVIEW: the read-only runtime/catalog/browser subset passes; the stated live/device/upload criteria remain explicitly limited. No percentage or 100% planning completion is asserted.

## Validation, documentation and recovery

No application tests/build were rerun solely for these documentation changes. Deployed P8-10 main CI was green (339 Laravel tests/2,306 assertions and 31 Node tests in its recorded verification); P9's actual checks are the read-only server/feed/HTTP/browser checks above. Local documentation validation checks JSON parsing and whitespace.

Affected documentation: `docs/planning/BACKLOG.md`, `docs/architecture/MASTER_PLAN.md` and this evidence directory. The branch also carries the earlier P8-10 docs-only release record, leaving application code unchanged. Do not merge documentation just to cause another production deployment during this acceptance pass.

No agent catalog/media/config mutations require data rollback. Do not undo Owner activity, remove stored photos, reset checkpoint or restore old batch proposals. For the deployed code, the retained previous release is `669de1130093ad0391e9beeee7f068c40555ac6a`; code rollback is an independently authorized operation. Prefer the existing guarded forward recovery for unfinished import media, preserving audits and current files.

Recommended next item, **not started**: the approved evidence-first clean-code/documentation repository audit, once P9's acceptance limits are reviewed. Use existing AGENTS/business rules/architecture/backlog/ADRs/runbooks; first identify specific current problems and outdated context, then propose bounded refactors. No payment gateway, broad rewrite or automatic data remediation belongs to this task.
