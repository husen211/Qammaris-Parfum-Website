# BLOG-05 — draft automation verification

2026-10-08. Review branch `codex/blog-05-api`, dependent on BLOG-04 `eae6d79`. Local implementation verified; no production deployment/activation, real tokens, catalog changes or historical content rewrite. BLOG-06 remains separate, not started.

## What changed

- Separate Sanctum machine actor/hash/scopes/90-day token lifecycle; default API gate OFF, bearer only, no human cookies/feed credentials. CLI can create/issue/revoke/disable; production create/issue requires an explicit activation flag in addition to separate Owner approval.
- Versioned owned-draft list/detail/create/PATCH/media and active taxonomy/public catalog lookup. Forbidden/unknown publication/actor fields fail with field errors, incomplete drafts stay private. PATCH preserves omitted data and revision conflicts do not overwrite.
- Shared admin/API writes and transactional machine/admin audit. Fresh actor/ownership/state checks after locks; bounded deadlock retry. Agent image changes require owned upload markers, no arbitrary URL downloads/foreign media. Existing omitted legacy content remains intact.
- Unique hashed actor/scope idempotency reservation completed inside write transaction; identical retries do not duplicate, changed payload/pending returns409. Current ownership/state protects replay. Completion/audit failure rolls back attachments/files, record and revision.
- Separate admin assignment page with accessible native select, 44px targets, no duplicate success message, revision/audit and explicit revoke/reassign. No editor content is saved by assignment. Existing publication/pending-migration/deployment guards remain.

## Automated evidence

| Check | Result |
|---|---|
| Full Laravel/PHPUnit, PHP8.2.12 + GD | **468 tests,3564 assertions passed** |
| Node regression suite | **31 tests passed** |
| Vite production build | Passed; existing DaisyUI @property and large about-lanyard chunk warnings retained |
| Pint changed PHP files | Passed |
| Composer strict validation | Passed |
| Dependency delta | Only Sanctum4.3.3 added to Composer lock; no existing package update, npm lock unchanged |
| Composer locked audit | Existing commonmark medium GHSA-97jj-33gv-5xf9 and high GHSA-3q6v-r5mr-hxv8 remain SEC-DEP-02 |
| npm audit | Existing3findings: concurrently/shell-quote critical, source-map-js high remain SEC-DEP-01 |

Audits are **not clean** and are not claimed as passing. No speculative upgrade bundled into this feature. Focused API/assignment tests cover actual hashed Bearer tokens, missing/invalid/nonexpiring/age-expired/expiry/revoked/inactive tokens, scopes, human-session isolation, ownership/public/scheduled/archive/reassignment, denied publication/taxonomy/catalog writes, field/nested rules, sanitized content/owned markers, PATCH preservation/stale revisions, key replay/pending/conflict, file MIME, foreign hero/media and rollback on audit/replay completion failure. Additive migration replay compares original article columns exactly and leaves existing owner null. Rate checks prove60actor/min across tokens,10media/min and120IP/min pre-auth failures; the pre-auth test caught and corrected Laravel middleware-priority reordering.

## Real local HTTP concurrency

[http-checks.json](http-checks.json): two independent PHP workers at localhost8007/8008 share an isolated SQLite fixture. Five simultaneous same-key create pairs each committed exactly one draft, returned201plus200or409, and replayed200. Same-key multipart upload committed one media record/file and replayed200; simultaneous PATCHat one revision returned200/409. Non-JSON Accept still returned JSON401.

The first rehearsal exposed SQLite transaction contention; three bounded shared DB transaction attempts resolved it. Final HTTP evidence contains no500. This is a local multi-worker proof, **not MySQL staging acceptance**, distributed cache validation or a production token test.

## Browser evidence

The connected CUA browser runtime failed to initialize twice. An independent headless installed Chrome session on the synthetic localhost fixture was used instead; no existing personal browser tab/session was modified.

[browser-checks.json](browser-checks.json) and screenshots show:

- Before editor390/1440 and after editor/link1440.
- Assignment page320/375/390/768/1440, no horizontal overflow; select/save44px.
- Native select/Tab/Enter keyboard submission, success and two-tab stale-revision feedback.
- Genuine Chromium mobile context `isMobile=true`, `hasTouch=true`, maxTouchPoints>0, hover:false. CDP touch gesture scrolls the admin scroll container while keeping editor route; one link tap navigates, one save tap submits. No JavaScript page errors recorded.

Physical iPhone Safari remains untested. This evidence covers the changed admin workflow; it does not close earlier public Journal/P9 device gates. Native browser file chooser was not exercised; real HTTP multipart uploads are covered.

## Cleanup and boundaries

Synthetic localhost env/database/admin/actors/tokens/articles/media/session files and helper scripts are removed after verification; both PHP workers stopped. No staging/production account/token/database/migration/media/price/stock changed. Public source/IDs/slugs/authors and original media remain. Feature branch/PR only, no merge/main release. Activation/runbook staging MySQL/GD/cache and Owner review remain BLOG-06; no credentials are activated by installing Sanctum or committing migrations.

[ADR-036](../../architecture/decisions/ADR-036-journal-draft-automation.md), [API contract](../../api/JOURNAL_AUTOMATION.md), [operator guide](../../runbooks/JOURNAL_AUTOMATION.md), [agent guide](../../runbooks/JOURNAL_AGENT_GUIDE.md).
