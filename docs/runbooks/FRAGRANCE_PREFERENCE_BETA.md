# Preference beta — authorized trial release

Owner2026-10-10 explicitly authorizes trial release (“gua mau t4es gpapa rilis aja dlu”). Human accuracy and genuine touch acceptance remain pending. Current technical evidence: [PREF03](../verification/pref-03/README.md), [ADR039](../architecture/decisions/ADR-039-preference-website-beta.md). Apply normal main-only CI/deployment guards; do not weaken pending-migration refusal.

## Candidate and schema

Build an exact candidate using locked GitHub production package workflow; manual dispatch builds only. Rehearse on a private sibling candidate linked to existing staging env with isolated framework/log storage, no public staging routing or auth change. Validate staging/production DB connection separation and existing table fingerprints. Existing staging may have no admin; do not invent a persistent/test account or claim admin rehearsal if unavailable. MySQL anonymous result/feedback probes use a fully rolled-back transaction. SQLite admin/profile tests are not MySQL admin proof.

Before selected production DDL create server-private recovery point with restrictive permissions and verify dump readability/checksum; do not download/print credentials/data. Only run2026_10_10_000001_create_fragrance_profiles.php and2026_10_10_000002_create_fragrance_quiz_results.php. They add fragrance_profiles/fragrance_profile_revisions/fragrance_quiz_results/fragrance_quiz_feedback; no catalog/media columns or writes, down/refresh/reseed. Check replay no-op and current table fingerprints. Prepare exact current profile preview, review action counts/source/parser issues, apply via existing admin actor (automated source formation, not claimed human tasting), then fresh preview must be unchanged. Audit attribution affects new profile revision records only.

## Flag and release

Default FRAGRANCE_PREFERENCE_ENABLED=false retains old six-question test. Before activation verify new tables and current public profiles; no flag-only release. Normal GitHub main CI/release must succeed and server revision match. Enable=true in existing shared env, rebuild active config cache, no credential/permission edits. Do not replace the whole env or change unrelated flags. Inspect real website wizard→result→structured feedback, current actual media/prices/status, private/noindex headers, URL-alone denial, errors,320/390/1440 overflow. Native records used to test live are labelled operational beta observations; do not purge anonymous results or call generated responses independent sensory labels.

## Admin and retention

Existing admin opens /admin/fragrance-preference (dashboard link). Filter feedback by exact engine version/product ID, overall counts and original empty result count. Review individual product at /admin/fragrance-preference/profiles/ID. Preview does not mutate catalog; actor-bound apply rechecks fingerprint under locks. Source/revision changes block corrections; never copy an outdated override blindly. Corrections use bounded JSON attributes and8–2000-character evidence through the existing store; parser/source/code decisions and exact values in [engine runbook](FRAGRANCE_PREFERENCE_ENGINE.md). Offline evaluator remains CLI; no web remote-code execution route.

Local browser drafts expire24h. Cookie/result access lasts7days; anonymous answers/feedback/profile audit retained without automatic purge. No IP stored with results, raw cookie stored/logged, name/phone/location/free text, feedback weight-learning, percent accuracy, tester guarantee or stock reservation. Feedback evaluates relevance before smelling.

## Recovery

Disable FRAGRANCE_PREFERENCE_ENABLED and rebuild active config cache to restore legacy quiz. Keep new tables/profiles/results/feedback and existing catalog/media. No migration down. Retained previous release71a4c2e66c7405b48fd8afe3a77bfcf3a12bb213 is current read-only preflight recovery revision; verify again immediately before final switch. Code-only rollback follows standard deployment runbook; do not use a database dump to overwrite normally changing catalog. Investigate error counters and feedback; changed ranking needs new engine version/evaluation. Owner tests/independent quality review are next; V2 remains out of scope.
