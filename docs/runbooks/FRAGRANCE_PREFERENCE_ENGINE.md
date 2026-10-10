# PREF-02 — Profile, review and evaluation runbook

Development code only; no public cutover or production permission. [Status/evidence](../verification/pref-02/README.md), [contract](../planning/QAMMARIS_FRAGRANCE_PREFERENCE.md). Existing quiz stays live. Flag `FRAGRANCE_PREFERENCE_ENABLED` defaults false and the future adapter checks it; the public controller is not wired during this phase.

## Profile sources and rules

`FragranceNoteNormalizer` keeps explicit aliases, placeholders and raw/layer provenance. `resources/data/fragrance-profile-rules.json` maps conservative inferred families, exact description-family terms and four fingerprint-scoped source-review cases. No free typo matching, named-product special ranking, personal/age proxy, AI, popularity boost or external enrichment. Unknown terms are review issues, not guessed notes.

`ProfileBuilder` parses labelled factual statements for performance/level. Literal use recommendations and exact profile/character predicates can supply context/family evidence; arbitrary story/promotion words do not supply performance. Negation/uncertainty and contradictory statements remain unknown. Layers normalize/dedup before family scoring; repeating synonyms does not raise points. Full-family detection remains conservative avoid evidence even when explicit facts/review correct the aroma target. No free-from/allergy inference.

Fingerprint covers raw description/notes and parser/dictionary/rules/answer vocabulary code. Price/size/availability/publication/images remain live catalog data. Changed source/parser excludes old profile until rebuild; old source-bound overrides remain stored and marked stale, not silently applied. Numeric hours are catalog claims: an explicit `8 Jam+` stores min8/maxunknown, not a fabricated upper bound. Seharian support requires stated minimum≥8; few-hours minimum≥3. Vanilla/tonka alone does not prove sweetness level or gourmand dominance; EDP/Extrait never proves longevity.

## Preview and apply

Only use an approved development/staging DB and existing admin actor. Before any staging/production migration/write, obtain the phase-specific authorization and verified recovery path. The current task only ran SQLite in-memory tests and offline read-only source processing.

```powershell
& C:/xampp/php/php.exe artisan fragrance-profiles:build
```

Default previews only: bounded5000 public products, source/parser/old revision/override fingerprints, issues/unknowns and manifest fingerprint. Missing profile schema may still be previewed. `--apply --preview=<exact current fingerprint> --actor=<existing admin ID>` requires both additive tables and current existing admin role. Rechecks under product/profile locks, aborts stale preview, writes only recommendation tables and revision attribution in one transaction. Unexpected failure rolls back all writes. No product, offer, URL, image, account or permission mutation. Rerun preview after an apply/source/review change; a new current preview with unchanged sources yields zero writes/history entries. Replaying an old preview is rejected rather than forced.

`FragranceProfileStore::review(productId, expectedRevision, expectedSource, changes, evidence, existingAdmin)` is the guarded operation for the future admin interface (no new HTTP endpoint in PREF-02). Allowed changes: aroma_target, sweetness, projection, numeric longevity, context, verified identity key. Bounded source evidence required; authorization and optimistic source/parser/revision checks in transaction. Repeating identical review is no-op. Null can clear unsupported performance/identity. Context may be emptied. All claims remain attributed to the reviewer; never write an agent proposal as a human observation.

Identity keys are assigned only after explicitly verifying those product IDs describe the same fragrance across sizes/brands; equal notes alone never qualifies. A source/parser change also suspends identity correction. Detected notes remain avoid evidence after correcting target families; a disputed source needs specific review, not a silent remove-to-pass override. Existing pending alias/semantic/identity items remain reviewable in generated issues and PREF-01 packet.

## Ranking and explanation

Engine version `pref-02.1-provisional`; initial weights aroma50, sweetness15, projection10, longevity10, context10, gender5. Fixed maxima and denominator; any/unknown has no weight and missing product attributes earn zero. Within aroma, exact inferred note membership provides0.8 support versus1 for explicit catalog/review character evidence. Values are provisional internal points, never a user accuracy percentage. Explicit aromas contribute45 plus up to5 favorite support if a favorite is present; without favorite explicit max50. No explicit likes: eligible favorite supplies target; otherwise exploratory choices require matching answered context. A favorite Habis remains target evidence when filtering Ready. Catalog gender is a small preference and does not exclude Unisex.

Up to3 within exact budget, plus separate max1≤110% only if fewer3 main or strictly higher internal score than the third. Comparison uses integer hundredths of rupiah, not float rounding. Verified identities dedup across both slots; no other grouping. Ranking fit → known-attribute completeness → Ready → lower price → stableID. No score-zero/aroma-mismatch padding or bestseller fallback. Reasons cite only actually scored answers/evidence or current budget facts; limitations describe unknowns/compromises. Catalog adapters recheck current visible published single offers, price/status and source/parser fingerprint. Never reuse cached old product visibility or price as authority.

Canonical preference fields use fruit/wood/green_herbal, unknown projection and not_priority longevity. Explicit compatibility aliases fruity/woody/green and any for projection/longevity preserve the frozen PREF-01 scenario spelling; no free normalization. Reject unknown fields/options, missing eight core answers, >3 likes, nested/unknown values, non-whole budgets and like/avoid conflicts. Optional gender/favorite can be skipped.

## Offline evaluation and freeze

```powershell
& C:/xampp/php/php.exe tools/fragrance-quiz/evaluate.php --snapshot=C:/approved-local-path/snapshot.json --output=C:/approved-local-path/new-training.json
```

No Laravel/DB/network bootstrap. Public snapshot schema/catalog hash required;≤16MiB/5000, unique productIDs. Refuses existing output, checks complete write and cleans only newly created failed output. Holds five cases out by default. Compare version/fingerprint, proposed and actual human reference independently, legacy exact mapping and budget/avoid violations. Output profiles, evidence/unknowns, cases and metadata; source remains unchanged.

Record training engine fingerprint before held-out check. `--include-holdout --frozen-engine=<that fingerprint>` enforces identical code/parser/rules; mismatched fingerprint fails before output. Changing weights/rules/parser later requires a new version and fresh evaluation/reference acceptance; never tune against N16–N20. Source-derived agent-ID overlap is not human accuracy or tasting relevance. Human release targets stay unestablished until actual independent review; do not relabel proposals or pending edge fixture acceptance as passed quality gates.

Run narrow engine/profile/evaluator/normalizer/audit checks first; changed-file Pint; CI broader suite/build after stable code. No browser requirement for disconnected backend-only work. Later wizard/feedback/anonymous HTTP security tests are separate PREF-03. Both MySQL migration rehearsal and live flag cutover wait for the authorized release phase.

## Rollback and retention

Keep legacy controller/flag default disabled. If future cutover regresses, disable flag and return to legacy while retaining profile revisions and future feedback. Do not run migration down, purge profiles/revisions, rewrite catalog or clear source evidence as operational rollback. Unpublished source changes can be reverted without DB action. No production/staging schema was changed by PREF-02 development.
