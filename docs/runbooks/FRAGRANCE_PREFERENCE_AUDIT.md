# PREF-01 read-only audit runbook

Scope: operator-only public catalog capture plus legacy baseline; offline normalization/review. Not a migration, importer, profile build, customer feedback endpoint or publish command. [Review/status](../verification/pref-01/README.md); [approved phase plan](../planning/QAMMARIS_FRAGRANCE_PREFERENCE.md).

## Capture

Use the existing approved SSH operator context; never paste credentials or copy `.env`. Do not run source SQL against the POS/Qammaris App. Capture website DB only. The export `tools/fragrance-quiz/export-public-snapshot.php` bootstraps the existing application, enforces MySQL READ ONLY for one transaction, selects only `Product::published()` rows (existing visibility boundary), and rolls back. No server files/accounts/records are created; stdout is gzip/base64, not encrypted. Keep capture local; never print its payload to logs/chat.

Provide `QAMMARIS_QUIZ_AUDIT_ROOT` as the approved current application root and `QAMMARIS_QUIZ_AUDIT_CASES_B64` as base64 of compact JSON:

```json
{"schema":"qammaris-preference-scenarios-v1","cases":[{"id":"N01","kind":"normal","legacy_answers":{"activity":"office","time":"morning","intensity":"soft","scent":"fresh","mood":"clean","gender":"all"}}]}
```

The example shape must contain all30 cases (edges need only ID/kind); send only ID/kind/legacy_answers, not full review document. This avoids Windows command-line limits. Validate approved legacy options before evaluation. Run PHP8.2+ with the script on stdin through SSH; decode gzip/base64 stdout to a UTF-8 local snapshot. Use strict exit checks, no command-string interpolation of untrusted/user values. The exporter includes no media paths/URLs, private SKU/UUID, users, customer requests or tokens. Current capture contains378 public rows/20 baselines; these counts are evidence, not hardcoded assumptions.

## Offline audit

From the repository, vendor dependencies installed, with a **new empty output directory**:

```powershell
& C:/xampp/php/php.exe tools/fragrance-quiz/audit.php --snapshot=C:/approved-local-path/snapshot.json --output=C:/approved-local-path/new-audit
```

Input ≤16MiB/5000 rows and exact schema; hash of products must match. The standalone audit does not bootstrap Laravel or connect to a DB/network. Outputs `catalog-audit.json` and UTF-8 BOM formula-safe `product-index.csv`. It refuses to overwrite and removes only outputs created by a failed invocation. CSV is a review index, **never an import/apply input**.

Retain private local source snapshot plus full offline report to reproduce that capture; source/catalog/dictionary/legacy-input hashes are in the receipt. `legacy_input_sha256` hashes compact captured legacy cases, not the expanded human-review scenarios. `scenario_file_sha256` independently identifies expanded fixtures. Update dictionary version/review provenance on edits and rerun into a new directory; do not silently rewrite previous acceptance.

Committed review packet is a public-field reduction: summary/hashes; products with deduplicated per-layer search groups and source fingerprints; vocabulary with original labels/counts/product refs; ambiguity flags; candidate public description paragraphs; old engine observed IDs; safe CSV. Original full descriptions/media keys are excluded. Receipt hashes its derived artifacts. Candidate description paragraphs matching sweetness/profile/performance terms are **not verified attributes**, and a paragraph without a keyword may still contain useful factual evidence. Whole raw source remains local; hashes do not provide cryptographic server authentication.

## Human reference and phase handoff

Owner/staff fill relevant/rejected product IDs, acceptable-empty, reviewer/date/revision in `docs/planning/fragrance-preference/scenarios.json` using the worksheet. The implementer may propose aliases/fixtures but must not fabricate human approval. Confirm unknown terms/placeholder effects/conflicts and identity across sizes; all identical-note groups are unverified. Do not infer sweetness/strength/long life from materials/concentration. Review has no catalog writeback.

Freeze the20 normal +10 boundary reference before calibration. N16–N20 are held-out labels: do not use them to tune. Record all Owner decisions and uncertainties; unknown attributes stay unknown. PREF-01 remains IN_REVIEW until this gate is met. PREF-02 is a distinct item, not automatically authorized by tool completion. No production release in PREF-01; previous About permission does not authorize quiz deployment.

## Checks and rollback

Run focused `FragranceNoteNormalizerTest`, `FragranceCatalogAuditTest` and existing `ProductDomainStateTest::test_fragrance_quiz_only_recommends_published_products`; Pint changed PHP, syntax/diff and regular CI. No browser/build repetitions for this audit-only code; CI still runs its usual frontend gate. Test inputs are synthetic local temp files, no staging actor/data.

Rollback this unpublished phase by reverting new offline tools/dictionary/docs/tests; public quiz has no new caller, DB schema/data/cache/flag is unchanged. Never purge source catalog/media or retained audit evidence as rollback. Later feature-flag rollback and profile-table procedures belong to PREF-02–04.
