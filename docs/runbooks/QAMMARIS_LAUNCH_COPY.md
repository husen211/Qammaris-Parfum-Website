# Staging wave-one copy and Owner review (P8-06)

Owner explicitly authorizes Shopee copy cleanup and evidence-backed audience on the fixed 113 photographed feed drafts. This is separate from publication approval. No production backup now; backup immediately before an independently approved cutover.

## Private preparation

Use the unchanged Owner basic export and existing exact Shopee media-pairing baseline. Capture current staging draft fingerprints, actual publication blockers and verified website cover bytes read-only, with staging/database guards. Verify the original 113 cohort and every timestamp/fingerprint; never silently refresh stale proposals. The 266 photo-less drafts are outside the scope.

```text
python tools/curate_shopee_launch_copy.py <Shopee-basic.xlsx> <private-capture.json> <private-media-baseline.json> <new-private-output-dir>
python tools/test_curate_shopee_launch_copy.py
python tools/build_launch_copy_review.py <private-wave-one.json> <private-cover-capture.json> <new-private-owner-review-dir>
```

No packages installed. Outputs are private/ignored. `wave-one.json` preserves source SHA, original/cleaned text, removals, provider mapping, proposal and audience evidence. Maintenance CSV proposes only description/gender; blank gender means retain unknown. Correction CSVs are review files, not import contracts. The offline HTML safely escapes source text, embeds verified website covers, supports search/clear/unknown/conflict filters and has no publish operation. Serve only its own directory on loopback for review; never expose storage/app/private or repository root.

The approved review is also available at `https://staging.qammarisparfum.id/owner-review/p8-06-wave-one/` behind existing staging HTTP Basic Auth. Anonymous requests must remain 401, including its CSV/CSS/JS files. Existing Owner browser must render without an auth/account change. Only the five standalone review files belong there; source XLSX, raw capture, maintenance CSV and environment files stay private. It is a snapshot for Owner, not the public catalog or a dynamic stock screen.

## Authorized staging draft apply

Use the existing authorized staging SSH session. Confirm app environment staging, expected staging database, original per-file PHP hashes/modes and fresh 113-row preview. Current deployment is a permission-preserving allowlisted PHP overlay, not the full workflow that rewrites auth/permissions. Keep immutable code originals and overlay manifest; no chmod, env/cache/auth/assets/cron/account/schema changes or extra DB backup.

```text
php artisan qammaris-app:launch-copy --file=storage/app/private/<curated-copy.csv>
php artisan qammaris-app:launch-copy --apply=<exact-preview-batch-id> --confirm
```

`launch-copy-v1` uses existing guarded Laravel maintenance, with null human actor IDs and explicit Owner-authorized CLI source label. It refuses production, human maintenance batches, published/hidden/nonconnected/non-Shopee/incomplete-photo rows and protected-field proposals. Record exact batch ID and operational Owner authorization. Inspect persisted row changes/before snapshots before apply; a stale/invalid/failed batch is not forcibly reactivated. Repeating the applied batch must preserve field/audit bytes.

After apply, check exactly 113 descriptions and only clear audiences, unchanged other product fields/offers/media/identities, retained 332 other drafts including 266 without photos, existing AOERA publication unchanged, and current checkpoint/worker. Re-evaluate publication readiness; unknown/conflicting gender remains a blocker. Do not claim all 113 ready.

## Owner decision and recovery

Open the private visual review, review photos/copy, correct flagged audience by ID and explicitly approve an exact staging subset. That approval comes before publication using existing readiness/product operations. No published page is created to bypass draft protection. Production cutover and webhook URL change remain separate approvals.

Code rollback restores exact original PHP files and disables the new command; retain curated draft copy and audit. Applied fields are not automatically undone by code rollback. Use before snapshots for a fresh reviewed forward correction. No data/media deletion or full staging DB copy into production.

## Refreshing the current photographed cohort after pairing

The original113/266 counts above are historical. Owner approved continued preparation of current photographed drafts after P8-07; image-less drafts stay untouched. Capture the current pairing schema plus `expected_updated_at` directly from Laravel's `toIso8601String()`, current row fingerprints and actual readiness. Do not infer a timezone from raw SQL timestamps.

```text
python tools/prepare_launch_audience_followup.py <unchanged-Shopee-basic.xlsx> <fresh-private-capture.json> <new-private-output>
python tools/test_launch_audience_followup.py
php artisan qammaris-app:launch-copy --file=storage/app/private/<run>/maintenance-audience.csv
```

Only structurally complete photographed visible connected Majoo-source drafts with unchanged exact Shopee description, blank audience and clear evidence produce gender-only proposals. Current human fields are retained; missing/conflicting evidence is a correction row. Other photographed taxonomy/offer gaps are separate read-only evidence; no price/size/category proposal is applied. Inspect the exact persisted preview against source CSV/evidence, then apply the exact batch with the existing command and verify replay and all protected attributes/media/source snapshots.

Capture after apply and prepare a new review. The actual no-blocker IDs define `publication-candidates.csv`, with product UUID/slug/price/size/audience and exact concurrency guards; it is a read-only approval list, not maintenance input. `build_launch_copy_review.py` accepts 1..1000 distinct explicitly captured rows and exact cover membership/checksums. Never publish from source confidence alone.

Current protected review: `https://staging.qammarisparfum.id/owner-review/p8-06-launch-ready/`. Six allowed review files only: HTML/CSS/JS, two correction CSVs and publication-candidate CSV. Raw basic export/capture/evidence/maintenance input stay private. Use a fresh new path, retaining earlier review snapshots; do not change authentication or permissions on existing paths. Confirm anonymous401 for directory/every file and actual Owner Chrome at390x844/1440x900. Retain media/guards/audit and verify the actual browser download against the approval-list hash. Owner must approve this concrete staging subset before publication; production and its final backup/cutover remain separate.

## Approved 122-product staging publication — completed 2026-10-06

Owner explicitly approved the exact list SHA256 `c9eda30849a7e439fdcdeb6be4cd958e6f5f71a146da81b0550b652d35b48013`. `tools/staging_publish_launch_wave.php` is a fixed operator tool for this run, not a general import/publication interface. It accepts only that private staging CSV, exactly 122 distinct IDs/UUIDs, excluding fixture ID 1. It checks actual staging environment, connected MySQL driver/database, draft visibility, provider ownership, source visibility/hash, immutable preview/row hashes, timestamps and actual publication readiness including stored cover existence. It uses existing `PublishProduct` under an outer transaction; protected-field/relationship differences abort all writes. No secret is read/output by the tool.

The unchanged script is retained privately on staging at `storage/app/private/p8-06-publication-20261006/staging_publish_launch_wave.php`. From the staging application root only:

```text
php storage/app/private/p8-06-publication-20261006/staging_publish_launch_wave.php preview
php storage/app/private/p8-06-publication-20261006/staging_publish_launch_wave.php rehearse
php storage/app/private/p8-06-publication-20261006/staging_publish_launch_wave.php apply
```

Preview persisted batch 6 and 122 exact approved rows. Inspect before apply. Rehearsal called the real publisher/audit writes and rolled them all back. A separate private failure probe rejected the last row and proved no partial publication/audit change. Approved apply recorded 122 before/after rows with contract `launch-publish-v1`, explicit Owner-approved source label and null human actor IDs; no fake admin. Repeating the applied batch returns persisted audit digest without writing. Do not reactivate a stale/invalid/failed batch, broaden the list, or adapt this script for production.

After apply: 123 published including unchanged integration fixture; 323 drafts including all 95 without photos. Public scope exactly equals approved IDs plus fixture. IDs/slugs/prices/offers/media/identities/availability/source snapshots retained; only publication state/timestamps changed. Browser detail reads may subsequently increment existing view counters. Draft routes remain 404. Verify catalog/search/filters/pagination/gallery/copy/inquiry at mobile and desktop; remove only the operator's own inquiry item, do not send WhatsApp. API/cron/worker and protected config/auth/media hashes remain healthy. Evidence: `docs/verification/p8-06/README.md` and `publication/` screenshots; raw captures/audit stay private.

Recovery is a reviewed forward unpublish/correction of this exact batch when authorized, using stored before snapshots and fresh guards; preserve identifiers, slugs, media and audit. Code rollback/removal of the operator tool alone leaves publication intact. Never restore a whole staging database or reverse later stock changes. Production backup/preflight/cutover/webhook URL change are separate Owner-approved steps; no extra old-data backup during this run.

## Five named Owner corrections/publications — completed2026-10-06

Do not broaden the original122-row publication script. Exact named approval is captured in private `p8-06-owner-followup-20261006/approved-corrections.csv` (SHA256 fa63aa3c03bf037e4eab0b8eb1988783b12ed79531407fe94629028ef603227e). Fixed `tools/staging_publish_owner_corrections.php` is retained at the same private staging directory. Run only from the staging app root using preview, rehearse, apply as separate modes. Guard refuses production/otherDBs/lists; only29/34/35/36/350 and unchanged expected blockers/price/UUID/source/media state are authorized. Inspect persisted batch7/rows first. Rehearsal rolls back corrections, new offers, publication and audit writes; final-row failure rollback was also verified. Apply5 already completed; replay returns applied audit digest without writes. Keep nullable actor attribution explicit Owner-approved SSH operation; never fabricate a human admin.

Current128 published comprises127 real launchproducts + unchangedfixture;318 drafts,95 withoutphotos remain invisible. Two new offers35=100ml/Rp549000 and36=30ml/Rp255000; all316 old offers and1017 images retained. Original matched descriptions remain unchanged, including BaliCliff's original marketing audience wording; Owner-approved audience filter is Pria. Evidence `docs/verification/p8-06/owner-followup/` and README. Public detail reads can increment view counts afterward. Recovery is a separately authorized guarded forward correction/unpublish of exact batch7 using before snapshots, retaining identities/slugs/media/audit and later stock changes. Code removal alone does not revert fields. No full stagingDB restore/copy into production. Production preflight/backup immediatelybefore cutover/webhook URL switch remain separate; not executed here. Owner accepted observed OTW labels and requested no additional testing; do not claim a new timedsource-app OTW cycle.
