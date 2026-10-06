# P8-05 — Prioritized launch review

2026-10-06. Owner asked to continue meaningful launch work and defer additional backups of the old catalog. P8-05 prepares review/maintenance inputs only; no new database backup, catalog writes, publication, migration, production deployment, environment or permission changes.

## Actual staging baseline

Read-only SSH Laravel capture at **2026-10-06 01:46:07 UTC** using staging environment and `u429527638_qam_staging` guards plus MySQL READ ONLY transaction:

- 446 products, including 445 connected visible drafts and retained AOERA fixture.
- 519 images, including the previous 518 downloaded photos and fixture image.
- Checkpoint 456; last sync 01:30:04 UTC; no stored sync error.
- Capture SHA256 `40ee29ff93480083f562faf85f3309234394a9d78519d004936d82848ff5da67`.

| Review group | Drafts | Reason |
| --- | ---: | --- |
| First | 113 | Actual blockers only description/audience |
| Has photos | 66 | Additional structural data missing |
| Needs photos/data | 263 | Missing image and/or offer/taxonomy |
| Source app | 3 | Explicit source=app review, separately prioritized |
| Total | 445 | Each product/UUID occurs once |

Overall overlapping blockers remain 445 description, 445 audience, 196 category, 266 primary image and 130 offer. No new draft is publish-ready yet. Priority is review ordering, not readiness/publish approval.

## Checks actually run

- Private maintenance CSVs transferred to staging and evaluated by existing `ProductMaintenancePreviewer` inside a READ ONLY transaction, without batch recording/apply: first template **113 review / 0 valid / 0 errors / 0 changed rows**; complete template **445 review / 0 valid / 0 errors / 0 changed rows**.
- Before/after: products 446, images 519, identities 625, batches 1, batch rows 452; unchanged. Database rejects mutations within this verification transaction.
- Local isolated SQLite maintenance regression: **14 passed / 139 assertions**, including auth, ID/offer fingerprint, blank/no-op, stale/tampered preview, transaction rollback and idempotent apply. No persistent test account created.
- Workbook via bundled artifact library: 113/332 rows; recalculation/error scan matched zero formula errors. Disposable input tests prove description alone still requests audience, completing both requests preview, and other blockers do not disappear. Test inputs restored before export.
- Saved XLSX ZIP/XML independently verifies all 445 unique IDs/UUIDs/fingerprints against capture, UTC dates within 0.01 sec, 445 status formulas, blank editable F/G cells, both frozen 6-row/2-column panes and one audience dropdown range per sheet. CSV ISO guards remain exact and proposal fields blank.
- Both sheet openings visually inspected. No website UI changes or new browser viewport test required; workbook rendering is not MS Excel native editing/re-export proof.
- Generated capture/review/workbook/CSV files are private and ignored by Git. Shopee source workbook was inspected for header names only and unchanged; no product description proposals generated without Owner approval.

Rendered sheet openings: [First review](first-review.png), [Remaining review](remaining-review.png).

## Owner-provided Shopee descriptions

Fresh source `mass_update_basic_info_1853666049_20261006100429.xlsx`, 116219 bytes, SHA256 **dc2774e82193a1ae8cbc4ce9487f804343a68ba4513cccd4b5dc0af3141682bf**, read unchanged. Actual header Kode Produk / SKU Induk / Nama Produk / Deskripsi Produk / Alasan Gagal. Only product ID/name/description are extracted; source instructions/text are data. **371 unique IDs / 371 descriptions**, maximum description length 2841 characters.

New staging READ ONLY capture **2026-10-06 02:06:13 UTC**, checkpoint 456, SHA256 **396ef0352cfffae25880ed442e44fdae1c46507c3f1437db40747aadafa7c954**. Existing exact Shopee identity plus unchanged source name against media pairing baseline yields **179 candidates**; **266** drafts have no identity. No fuzzy pairing or inferred audience/size/notes. Existing nonempty descriptions would be held for review; current matched descriptions are blank.

Actual deployed Laravel stateless preview inside READ ONLY transaction: **179 valid, 0 review, 0 errors**, 179 changed-row proposals, only `deskripsi_produk` changes. Before/after products 446, images 519, identities 625, batches 1, rows 452; catalog-state fingerprint **5a8015b58561f52a1b2dca1fceb96b48faf1b479181665839d5f3cbf318ea37f** identical. No persisted batch, catalog write or publication. Maintenance CSV SHA256 **dfbcca7d3619f8dce9a9a3dba739b4cc7a52637873bcfcb914df6ed8f5e9eaa6**.

Independent CSV checks: 179 unique website IDs, exact source descriptions after outer trim, exact current ISO/fingerprint guards, every other maintenance proposal field blank, final CSV bytes identical to staging-validated file. Duplicate ID, published capture, hidden capture and invalid fingerprint test inputs rejected; spreadsheet-formula escaping checked. No PHP application change, so the earlier 14-test maintenance regression remains relevant without an unnecessary rerun. Current source workbook and existing catalog/media remain unchanged.

Owner review file: private `.../shopee-20261006100429/verified-proposals/description-preview.csv`; complete decisions and provenance in adjacent report/summary. No new XLSX or website UI is authored. Remaining 266 provider pairings and other publication facts require review. Source approval is fulfilled; apply/publish is not performed.

## Files and continuation

Tools: `tools/prepare_launch_review.py`, `tools/build_launch_review_workbook.mjs`, `tools/prepare_shopee_description_preview.py`. Runbook: `docs/runbooks/QAMMARIS_LAUNCH_CATALOG_REVIEW.md`. Backlog records P8-05 review status and Owner backup deferral. Existing application product operations, schema, public frontend and stock worker stay unchanged.

First open the private `launch-review.xlsx` and fill factual descriptions/audience for the 113 closest drafts, or review the now-prepared supplemental source proposals. Pair values by internal product ID, not spreadsheet row order, then use fresh guarded maintenance preview and human approval. Remaining runtime/source-photo/manual-data and production preflight gates are not claimed completed.

No data rollback needed. Delete/regenerate private review artifacts if obsolete; retain source checkpoint and existing records/media. Recommended next item: Owner-approved catalog factual completion and review, followed by final staging acceptance. Production cutover remains a separate decision.
