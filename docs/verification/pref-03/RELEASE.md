# PREF-03 beta release — 2026-10-10

Owner explicitly requested release for testing before independent quality acceptance. PR49 merged into main as `1a33c707830f88d845cf433849cf329345890e50`; main CI38022143812 and guarded production release38022187426 passed. Server current revision matches and only FRAGRANCE_PREFERENCE_ENABLED was enabled. This is a labelled provisional beta, not final PREF-04 acceptance.

## Verification

Feature CI38021306551 passed534 PHP tests/4068 assertions,31 frontend tests and Vite build. Manual build-only candidate09ebf2f package checksum744ef1bfefdd1694bb5501b628aaec645196037dcb3d6a3ddfbfa0f50d8f4ddd verified locally/server-side. Source is the same application content as the main merge. No extra packages or external AI.

Private staging MySQL rehearsal: selected two additive migrations installed, replay no-op, eligible source-backed result plus feedback JSON round trip and replay passed, every synthetic row rolled back. No existing staging admin, so no MySQL admin apply claimed and no account created. Temporary local SQLite account/database removed and local server stopped.

Before production DDL, restrictive server-private complete database dump verified by size/completion marker/SHA256. Exact profile and result migrations add four tables. Production preview381 current published products; source-derived apply381 changes, second apply0. Products, offers, images, brands, categories and user IDs/roles fingerprints unchanged. No catalog/media/account rewrite. Recovery receipts/dumps stay private on server, no credentials or raw rows copied into Git.

Live full wizard→result→feedback passed. The operational probe selected300k/office/AC/citrus/light sweetness/close projection/no longevity priority, skipping optional gender/favorite. Three current in-budget products displayed; original persistent catalog photos loaded with contain; current prices/Ready visible. Feedback overall/per-product persisted across refresh. Edit restored300k answer. One anonymous operational result and one feedback record retained under the agreed retention policy; these are automation observations, not human acceptance or sensory labels. Operational result ID SHA256: 19d031996a3cc969f0108e75405b4383b750a594aeeb365b2a280aac950bbe55. Exclude this probe from independent acceptance scoring.

Health and wizard200; result URL without owning browser404. Wizard and denied result: private/no-store/max-age0/noindex/nofollow. No application console errors observed. Requested390/320/1440 responsive widths: client/scroll widths375/305/1425 respectively, no horizontal overflow. Windows in-app scrollbar accounts for15px. Actual browser resize/mouse/keyboard proof only; genuine touch and physical Safari remain unconfirmed.

[Live mobile wizard](live-wizard-390.jpg), [live mobile results](live-result-390.jpg), [live desktop results](live-result-1440.jpg). Desktop screenshot after repaint inspected; real source photos fit cleanly. Initial resize screenshot was superseded because it captured an incomplete repaint.

## Operational fixes and recovery

Private staging bootstrap initially lacked bootstrap/cache; directory created, normal production deploy script already creates it. Initial first-product fixture had no aroma evidence; rejected as intended, transaction rolled back and a genuinely eligible existing fixture selected. No app code changes to weaken rules. First deploy extraction exceeded hosting quota; only this task's reproducible private staging/production trees and incomplete failed extraction were removed after verifying packages/recovery receipts/current release. Candidate logs retained beside private backups; current/shared media/DB and prior usable release untouched. Failed official deploy job rerun passed. Flag activation followed by a fresh config:cache process, then cached enabled=true verified.

Rollback: disable FRAGRANCE_PREFERENCE_ENABLED in shared env and rebuild active config cache; legacy six-question quiz returns. Keep four tables/history and persistent catalog/media. Previous usable code71a4c2e retained. Do not migration-down or restore database over live catalog changes.

## Remaining quality work

381 profiles,380 have detected aroma targets. Counts of review issues are occurrences, not products:787 unassigned note family,4 source-conflict review,36 placeholder,22 unparsed fact,33 unknown note,9 missing notes,3 conflicting fact. Unknown/ambiguous attributes remain unknown; independent source/identity/sensory review still needed.18/20 and16/20 normal-profile targets not claimed. Native browser/physical-touch acceptance pending. Next active work is Owner trial feedback and targeted source/quality review within PREF-03; no automatic PREF-04/V2 start.

Implementation files and boundaries: [technical snapshot](README.md), [beta runbook](../../runbooks/FRAGRANCE_PREFERENCE_BETA.md), ADR039. New controllers/request/middleware/services/models, two additive migrations, question/rule/profile data, Blade wizard/result/admin and scoped JS/CSS; existing catalog/legacy engine preserved.
