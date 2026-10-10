# Multistep UI — released 10 October 2026

Owner explicitly requested “rilis” after reviewing PR53 and the reported genuine-touch limitation. [PR53](https://github.com/husen211/Qammaris-Parfum-Website/pull/53) is merged and live at `c8e653016ae5d3cfa5f6df2702e33d99deda5a1c`. This remains the labelled PREF-03 beta, IN_REVIEW; no sensory acceptance, 80–90% claim, PREF-04 or V2 is inferred. [Sanitized receipt](release-receipt.json).

## Delivery and verification

Candidate [38031662307](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/38031662307) built the exact reviewed head; production deploy skipped. The official artifact ZIP digest and inner package checksum matched before upload and on the private staging candidate. Blade compiled, the wizard rendered HTTP200 with six stages against the distinct staging MySQL connection, and all measured database row counts stayed unchanged. Session/cache were array-isolated, no staging actor or result/feedback was created, and public staging routing/auth was not changed. The CLI checker initially skipped the footer view composer; using actual web context corrected that operator-check artifact without changing application code.

Main [CI38032624116](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/38032624116) passed 542 PHP tests /4112 assertions, 38 Node tests and Vite. [Release38032666438](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/38032666438) build/deploy succeeded and server revision/current link independently matched; activation was recorded at06:57:43UTC. Existing pending-migration, main/event, latest-revision and activation guards stayed intact. No migrations, profile rebuild/apply, flag change, credential change or database/catalog/media/account write occurred. Before/after counts were identical, including381 profiles/762 revisions/11 results/3 feedback. No production quiz submission or synthetic quality feedback was added by this UI smoke.

Live health/quiz were HTTP200 with six stage controls, private/no-store and noindex quiz headers. At06:58:13UTC, no ERROR/CRITICAL/ALERT/EMERGENCY entries were observed in the one Laravel log since06:55:36UTC. Browser console had no captured errors. These are bounded release checks, not a guarantee about future errors.

## Hosting quota recovery

The first private extraction failed with Disk quota exceeded; production remained on the healthy preceding release. Only that incomplete extraction was removed after validating its exact path and retained package checksum. An inactive private Journal rehearsal vendor tree was compressed to `vendor-recovery-ui53.tar.gz` under its existing private candidate directory. Its gzip integrity,12485 entries and SHA256 were verified before removing only the recoverable vendor extraction. Current routing, staging routing, process/cron references were checked; its source/runtime/logs and every active/previous release, backup, env and media were retained. To recover that vendor, verify the receipt checksum and extract the archive within that same private rehearsal directory; it is not a production rollback operation.

The UI staging app was also removed after it passed, retaining its verified package and diagnostic logs. This reclaimed enough temporary quota capacity for the official main deployment. No retained-release pruning or broad retention policy was introduced. Storage limits can recur on later deployments and need a separate scoped retention decision.

## Live browser and recovery

Actual390×844/1440×900 viewports (375/1425 content width due scrollbar), plus320×844 (305 content width) had no horizontal overflow. Existing draft answers were preserved. Keyboard Enter moved Budget→Pemakaian, previous-stage click returned to Budget, and transition locks cleared. No new result/feedback submit was performed; the complete local wizard/result/feedback proof and earlier beta production smoke remain separate evidence. Hidden-tab resizing initially did not apply, so those1265px observations are not labelled mobile. Only visible-tab measured screenshots are linked below. Temporary tabs closed and viewport reset.

- [Before390](live-before-390.png) / [after390](live-after-390.png)
- [Before1440](live-before-1440.png) / [after1440](live-after-1440.png)
- [After320](live-after-320.png)

Mouse/keyboard and viewport resizing are not genuine touch/iPhone Safari proof. That device check and independent recommendation quality remain pending and are not marked DONE. The previously healthy code release `ec3999367c8f94c70ad1ef60f4ce4b9486754a1f` remains available. Code-only rollback restores the old UI while retaining all profiles/results/feedback; no schema or parser rebuild is required. Flag false remains the separate full legacy-quiz fallback. Do not restore a database dump over newer customer records for a UI rollback.

Current documentation is recorded on a follow-up docs branch; it is not auto-merged because even docs-only main merges trigger production delivery. Next remains Owner/staff review of the labelled beta and targeted ambiguous recommendations within PREF-03, without starting the next phase.
