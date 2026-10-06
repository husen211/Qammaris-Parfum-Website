# Launch catalog review (P8-05)

Owner direction: prioritize launch catalog completeness and defer additional backup of the old catalog. This workflow is read-only; it does not apply maintenance, publish products, change staging identities/permissions, or deploy production.

## Review the closest products first

The 2026-10-06 staging capture contains 445 connected visible drafts. **113** already have valid brand/concentration, one size/price offer and a primary image. Their actual readiness blockers are description and audience only. Another 66 have photos but need additional data; 263 need photos/data; 3 source=app records need explicit review. Counts are mutually exclusive; missing-field counts overlap.

Private outputs: `storage/app/private/p8-05-review/20261006/`:

- `outputs/p8-05/launch-review.xlsx`: Owner-editable review, tabs `Prioritas pertama` (113) and `Kekurangan lainnya` (332). Cream F/G cells are factual description/audience proposals; audience options are Pria/Wanita/Unisex. A completed row requests preview, never promises publication. Other blockers remain visible even when F/G are filled.
- `maintenance-first.csv`: 113 current IDs and exact ISO timestamp/fingerprint guards in maintenance-v1 column order.
- `maintenance-all.csv`: equivalent template for all 445 drafts.
- `launch-review.csv`, `summary.json`: review classification and capture provenance. These are not import files.

Untouched maintenance templates repeat only a safe current name for context; other maintenance fields are blank. Blank means keep current data. Source price, stock status, slug, images, identity and publication are not proposed mutations.

The XLSX is **not an import template**. Its UTC snapshot date is a typed Excel date; the exact ISO guard remains in the CSV. Workbook and CSV order may differ. Pair reviewed values by product ID, never copy rows by position. Do not edit reference identity/guard columns. Only actual Owner-supplied or explicitly approved source facts may fill proposals; do not infer Unisex from missing data or invent descriptions.

## Generate a new package

First capture current linked visible staging drafts through existing Laravel models, publication-readiness operation and row-fingerprint service in a consistent read-only MySQL transaction. Include only allowlisted catalog fields, source UUID/source/revision/hidden, current offer and image count; no environment credentials or raw integration request bodies. Guard environment/database before querying. Label the resulting JSON `qammaris-launch-review-v1` with environment staging, captured_at, checkpoint and data.

From the repository root, with a new existing private output directory:

```powershell
python -B tools/prepare_launch_review.py <private-capture.json> <private-output-directory>
node tools/build_launch_review_workbook.mjs <private-capture.json> <private-output-directory>/outputs/p8-05/launch-review.xlsx
```

Use the existing bundled Python/Node runtimes; no package installation. The workbook tool resolves the installed artifact library from `QAMMARIS_REVIEW_NODE_MODULES` if needed. Source/output paths must resolve inside storage/app/private. Outputs refuse overwrite. File contents remain outside Git; tools and instructions alone are committed.

## Complete and preview

1. Review the 113 first. Supply an actual description and audience; there is no auto-apply/publish and no assumed launch subset.
2. Pair proposals by website product ID with fresh timestamp/fingerprint guards. A saved workbook is review evidence, not authority to refresh guards silently or overwrite concurrent changes.
3. Use existing admin maintenance preview. If a product/offer changed, inspect the difference and obtain a fresh reviewed proposal; never bypass stale validation. Blank cells preserve values. Preview and explicit human apply use existing P6 operations/audit.
4. Re-evaluate publication readiness after approved maintenance and media review. Publication remains a separate Owner decision, including approval of photos and launch subset.

Shopee basic information export may contain source descriptions, but no proposals from it have been generated in this item. Owner approval is pending. If approved later, use only existing exact Shopee identity and mark older-export provenance; keep current feed identity/name/price/status and do not automatically assign ambiguous matches.

Staging has no Laravel human users in the last verified baseline. HTTP Basic Auth permits public review, not admin catalog mutation. Creating persistent staging admin access requires separate explicit Owner authorization; this item creates none.

## Verification and rollback

Read-only staging preview of both untouched templates: first 113 review/no-op, all 445 review/no-op; zero errors and changed rows. Product/image/identity/batch/row counts retained. Local maintenance tests: 14 passed, 139 assertions.

Workbook was recalculated, error-scanned, visually rendered for both tabs, checked for preserved IDs/UUIDs/fingerprints/UTC dates, dropdowns/panes and all blank synthetic test inputs. MS Excel native editing/re-export has not been tested. No website UI changed.

There is no data rollback: remove unwanted private review artifacts or regenerate a fresh package. Retain existing catalog/media/checkpoint. Any future apply must use the existing guarded human maintenance workflow; any production cutover remains separately approved.
