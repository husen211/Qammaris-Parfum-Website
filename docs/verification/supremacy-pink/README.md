# Supremacy Pink/Purple production correction — 2026-10-07

Owner explicitly requested correction after investigation. This is a two-product data repair, not AUD-03, a general identity-rebind feature, or a code deployment.

## Evidence and outcome

- Production batch6 row2167: Shopee42131600634, **Afnan Supremacy Pink Pour Femme Eau de Parfum100ML**, targeted website Purple879. Purple's description began SUPREMACY PINK POUR FEMME and its three photos depicted Pink.
- Purple879: UUID `f9b89ff7-7900-49b6-ac07-a012eea8c04d`, SKU6290171002055, Rp460000, sold_out. Pink686: UUID `84a10c0b-c5ba-4d9c-a622-d8d7969327f7`, SKU6290171002048, Rp479000, available. Their app identities were distinct and correctly named. Pink was a blank draft; Purple was published with Pink content.
- Existing Shopee exact identity is preferred by `ShopeeContentPreviewer::preview`, so repeated imports retained the wrong historical binding. How/when the original binding was first created is **Not confirmed**; it is not proved to be a current fuzzy-matcher error. No Purple source row appeared in the latest Supremacy-filtered375-product batch; the Owner independently confirmed Purple is absent from their Shopee catalog.
- `tools/correct_supremacy_pink.php` restricts execution to production MySQL `u429527638_qam_launch`, the exact IDs/UUIDs/SKUs/brand/source row, empty Pink baseline and published Purple with three Pink photos and100ml offer. Content fingerprints, source validation, file checksums and database locks protect the approved state.
- Preview saved before-state of both products, all offers/images/identities and source row in **private** persistent storage. Independent copies of the three photos were checksum-verified; original files retained. Rehearsal executed readiness and audit within a transaction and verified rollback before apply.
- Apply committed one transaction: only Shopee42131600634 moved to Pink686; Pink completed with100ml, Wanita, active Eau de Parfum category and existing Pink description. `SyncSingleOffer` reads Pink's own app snapshot for price; `AttachProductImage` attaches the copied files; `PublishProduct` checks readiness. Purple becomes draft/nonactive, incorrect description cleared, three images soft-archived. Neither app UUID, either existing slug, Purple's existing offer, nor source stock/price changed.
- Audit **batch8**, contract `owner-pink-fix-v1`, contains two before/after rows attributed to the original Owner batch actor and an explicit machine-SSH execution note. Private applied receipt retained. Batch6/source row/history unchanged: older applied results still describe their historical target; the next export/import resolves the corrected current identity to Pink. Ordinary `MapExternalProductIdentity`/import occupied-code guards were not weakened.

## Checks actually run

- Local PHP syntax check and remote PHP syntax check: passed.
- Four focused correction tests:29assertions, passed; cover precise transfer/preservation, database rehearsal rollback, stale content, missing-photo failure rollback.
- Related suites `ProductExternalIdentityTest`, `ProductDraftAndOfferTest`, `ShopeeContentImportTest`, plus correction tests: **53tests,264assertions, passed**. Includes occupied identity protection and realistic375-row import.
- Production preview, rehearsal and verify: passed. Purple draft/no active images/no incorrect description; Pink published/readiness passes/three active images/one primary. Verification reports Rp479000/available for Pink and retained Rp460000/sold_out for Purple.
- HTTPS HEAD: Pink existing `/products/supermacy-pink-pour-femme` **200**; Purple existing `/products/afnan-supremacy-purple-pour-femme-edp-100-ml` **404**, as expected for draft.
- Chrome production public Pink:100ml/Rp479000/Tersedia/correct Pink description; three gallery images loaded at640,638,640px natural widths. Admin app inbox independently shows Pink Tayang and Purple Draft with their original SKUs/source prices.
- Public screenshot at the existing desktop viewport1521×818: [production-pink.png](production-pink.png). No UI implementation or responsive-layout changes; no new mobile/Safari behavior test claimed.
- Initial production execution was rejected by automatic review as an unverified script; local regression proof and separate remote lint preceded accepted preview/rehearsal/apply. An optional subsequent `apply` replay probe was rejected as unnecessary production mutation risk. It was not executed or bypassed; runtime replay remains **Not confirmed**. The applied receipt branch is present, and database uniqueness/guarding prevents silent additional correction. Re-run verification instead of apply.

## Retention and recovery

Private artifacts under the Laravel local disk: `supremacy-pink-20261007/preview.json` and `applied.json`; private operational script under `storage/app/private/correct_supremacy_pink.php`. No credentials or raw environment copied/printed/committed. Original Purple photo files and soft-deleted metadata remain; copied Pink files have independent paths.

Forward fix is preferred: inspect current app identity/source, keep Pink published if correct, leave Purple draft until true Purple content is supplied. Do not remap source UUIDs, rename Purple to impersonate Pink, reset checkpoints, bulk-recheck/apply other rows, or overwrite history to conceal the mismatch.

If Owner separately requests rollback, first compare current content/media/identity against audit8 after-state and inspect newer source revisions. In one reviewed transaction restore the exact prior Shopee owner879, restore Purple's original description/publication/active flag and its three soft-archived image records, return Pink to its prior blank draft and archive new Pink image metadata/offer as appropriate. Preserve IDs/slugs, all original/copy files and both audit records; restore source price/status only through the normal feed, never replay old snapshots over newer source changes. Record compensating audit. Actual restoration after committed correction has **not** been run; rollback was verified for the transaction rehearsal, not falsely claimed as a full production restore drill.

No schema, credentials, API contract, deployment, frontend code or other products changed. The source/display spelling `Supermacy` and existing slug were preserved. Next proposed maintainability item remains AUD-03, requiring separate Owner execution direction.
