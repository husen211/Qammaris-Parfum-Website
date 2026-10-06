# P1-04 — Production cutover preflight

**2026-10-06 — IN_PROGRESS; production activation is not authorized.**

Owner requested the next phase after publishing the photographed staging catalog. Latest Owner direction explicitly waives preservation/migration and backup of legacy production data: “data di production itu gpenting dihpus juga gpapa, gprlu backup”. Launch uses the approved new catalog. Earlier requirements for a legacy database merge and final legacy backup are superseded for this release. This does not require deleting the old system: leave its database/files in place and activate a separately prepared target. No deletion, new database, credential change or public cutover has been executed. Earlier SSH permission covers staging only; production hosting inspection still awaits explicit scope under [ADR-001](../architecture/decisions/ADR-001-legacy-production-and-clean-redeployment.md).

## Verified release baseline

| Check | Actual result |
| --- | --- |
| Candidate source | `85ef088f4f9481c6f061141ee1d0a987ddf13832` |
| Existing CI | [37421516661](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37421516661), successful PHP/Laravel checks and Vite build for that exact commit |
| Staging runtime | PHP 8.2.33, Laravel 12.69.2, MySQL; 22 migrations recorded |
| Source comparison | 220 tracked application/configuration files; 205 byte-identical, 13 additional matches after CRLF/LF normalization, two staging Basic Auth `.htaccess` differences; no missing files or extra application paths |
| Static public files | All 22 present; 21 byte-identical, remaining difference is staging Basic Auth |
| Built assets | Eight staging manifest assets exist; seven match local bytes. The staging CSS filename is absent from the current local build. Browser rendering works; this is an artifact provenance gap, not proof of a CSS defect |
| Real launching catalog | 350 published products, each with one offer, a primary photo and a `qammaris_app` identity; zero readiness blockers |
| Retained drafts | 95, all without photos/descriptions |
| Staging fixture | One additional published AOERA integration fixture; excluded from launching manifest |
| Real launch media | 1,016 unique JPEG files / 350 primary photos / 35,974,303 bytes; zero missing files; server SHA-256 metadata collected |
| Latest API/runtime read | 06:20:46 UTC: unauthenticated feed 401, authenticated 200, checkpoint/next_seq 457, `has_more=false`, no sync error, stock/image queues empty |
| Minute operations | Scheduler/watchdog timestamps 43/44 seconds old at that read; 30-minute reconciliation defined in `routes/console.php` |
| Content/accounts | Staging has zero users, blog posts and store-info rows. Legacy accounts/content will not be copied automatically; Owner must have a working admin account in the new target before activation |

Staging Git HEAD is still `1867d83cb527f7573ce1046392860a83f64c8e65`, while guarded overlays match the candidate source as above. Git HEAD alone does not identify the deployed application. Do not use `git reset` as a filesystem rollback for this deployment. Freeze a coherent source/dependency/build manifest for the approved release; do not mix partial local assets with the staging bundle. The existing GitHub release workflow/environment is staging-only; it must not be pointed at production.

Evidence and browser checks: [P1-04 verification](../verification/p1-04/README.md). No new application build or broad test suite was run for this documentation/read-only task.

## Launch packet — preview only

Protected, ignored workspace location: `storage/app/private/p1-04-preflight/20261006/`.

- `launch-catalog-preview.json`: the 350 approved launching records and 95 retained drafts with relationships and before-state fingerprints. This is not an executable production import or database backup.
- Catalog preview SHA-256: `285aed1dd35ca32e56776c9306f73844514fa2c23a2f9f01bcd83a681db967e1`.
- `launch-media.json`: exact path/byte/MIME/primary/checksum metadata for the 1,016 launch photos. Manifest SHA-256: `e3df211b44675d9a7a3d7a4c8115b1f328edc2fedfdd3c54060bac94b6a22562`.
- Availability at capture: 242 available / 108 sold out. This is a release preview, not a replacement for refreshing the source feed at activation.
- Gender: 106 Pria / 59 Wanita / 185 Unisex, following Owner approval. Existing names/descriptions remain unchanged even if their wording differs from the approved gender.

No media was copied, moved or deleted. Refresh catalog fingerprints and checksum comparison before any eventual write. Exclude the fixture, fixture photo/taxonomy, private owner-review pages, sessions, cached configuration, queued job payloads, historical failed jobs and staging operator batch IDs from production activation. Keep the private approval/audit evidence; do not transplant its staging foreign keys into production.

## Production facts and the superseded merge plan

The public `/products` page displays **208 products**. This is a public pagination count, not a confirmed database count. Production PHP/MySQL versions, migration history, schema, product/variant IDs, SKU coverage, media inventory, accounts/roles, blog/store content and deployment directory are **Not confirmed**. Read-only SSH inspection awaits explicit Owner scope; do not infer them from staging or the old local snapshot.

Existing public URLs already demonstrate the need for a target-specific preview:

| Production page observed | Staging candidate | Consequence |
| --- | --- | --- |
| `/products/afnan-lynked-freedom`, Rp889,000 | ID144, `/products/afnan-lynked-freedom-edp-100ml`, Rp819,000 | Identity/size match Not confirmed; preserve the old URL and review the price conflict before updating an existing record |
| `/products/joe-winn-al-fajr`, Rp439,000 | ID170, `/products/maison-jw-al-fajr-extrait-100ml`, Rp439,000 | Brand/name alias and size match Not confirmed; no automatic name-only pairing |
| `/products/mpf-wicked-noir-eau-de-parfum`, Rp349,000 | ID354, same slug and displayed price | Production identity still requires inspection; matching slug does not authorize replacing its ID |

The comparison above remains historical evidence; it no longer blocks launching and does not authorize automatic pairing. Owner accepts launching the fresh catalog instead of preserving legacy product IDs/URLs/data. No legacy database export, migration inspection, variant consolidation, account/password-hash copy or full old-media inventory is needed for this plan.

Production hosting facts still required: correct website directory/document root, PHP/extensions, available database/storage layout, deployment entry point, cron/worker mechanism and HTTPS/environment status. Read only the minimum operational metadata; do not dump old database contents or secrets. Report secrets only as **terisi / tidak**. Permission for production inspection remains pending; existing access is staging-only.

## Updated target strategy — new catalog, no legacy backup

1. Prepare an approved separate target using the frozen candidate source and one complete build bundle. Fresh tables use existing migrations; no legacy migrations/data coercion is needed. Production database creation/settings and deployment target still require exact Owner-approved execution scope.
2. Transfer exactly the 350 real launching products plus 95 retained drafts from the refreshed private preview. Exclude the AOERA staging fixture and its media/taxonomy. Create target IDs and explicitly remap offers, images, taxonomy and provider identities; never blindly copy the staging database or seed synthetic accounts. Stage users/blog/store counts are zero, so their absence in the new target must be acknowledged rather than reported as preserved production content.
3. Keep approved new catalog slugs; old canonical URLs may stop resolving under Owner's waiver. Automatic old-to-new name-only redirects are not justified. Old URL recovery/mapping is optional separately approved work, not a launch gate.
4. Apply only validated scope with before-state conflict guards, idempotency, transaction boundaries and Owner/operator audit attribution. Current staging operators intentionally refuse production; do not relax those guards. The production-safe target transfer operation is not yet implemented or executed.
5. Copy the 1,016 approved launch photos through the Laravel disk using **copy → checksum/size verify → switch references → retain source**. Stop on differing destination checksum; do not overwrite. Retain staging source photos. The current public disk is sufficient for this release unless Owner approves a concrete storage change; the earlier R2 rehearsal covered 19 objects, not the full launch catalog.
6. Establish Owner's intended production admin access through protected configuration/handoff before launch, without sending credentials through chat or copying synthetic test accounts. No new account/password/permission changes are authorized by this preflight alone.
7. Prepare source snapshots/identity links, start a fresh checkpoint-zero feed reconciliation and drain through `has_more=false`. Do not copy staging queued payloads, failed jobs, sessions, cached env or operator IDs. Preserve approved website publication separately from source availability.

The approved target cohort is **350 public + 95 draft**, before future explicit catalog additions. Source availability at transfer must be fresh. No backup is created for the waived legacy data. Keep old database and application files untouched as the simplest reversible fallback; their continued presence is not a backup operation and does not delay launch. Permanent cleanup is unnecessary here and is not executed.

## Reviewable cutover sequence — do not execute yet

| Order | Work | Completion evidence / gate |
| --- | --- | --- |
| 1 | Authorized minimal production hosting inspection | Exact website target, runtime and deployment/database/worker paths; no old catalog dump or backup |
| 2 | Freeze target/release manifest | 350 public +95 draft, exact UUID/media/offer/taxonomy remapping, fixture excluded, complete source/build bundle and Owner admin access plan |
| 3 | Owner authorizes concrete activation scope | Separate target/database/env/runtime setup and public website activation; backend webhook destination switch separately explicit |
| 4 | Prepare target and catalog | Existing migrations into approved fresh database, audited guarded transfer, all1,016 media checksums verified; leave legacy database/files untouched |
| 5 | Prepare integration/runtime | Receiver/HMAC, database stock worker and minute scheduler/watchdog; no duplicate schedule:work; 30-minute reconciliation; protected env present and HTTPS session setting explicit |
| 6 | Refresh and verify | Fresh checkpoint-zero source feed drained, revisions monotonic; Owner admin login and catalog/detail/search/price/photo/inquiry/SEO checks; no fixture/private review exposure |
| 7 | Approved website activation | Serve approved target at domain; approved new catalog URLs; no DNS/other-site changes implied; clear only target application/sitemap caches |
| 8 | Separately approved backend webhook switch | Change intended backend destination only after production receiver/worker ready; keep staging isolated; verify signed delivery without exposing secrets |
| 9 | Observe or recover | Queue/cron/feed/HTTP/media/count checks; restore old deployment routing if needed without deleting either database or new writes/media |

Protected environment checklist names only: `APP_ENV`, `APP_DEBUG`, `APP_URL`, existing `APP_KEY`, database settings, session settings including `SESSION_SECURE_COOKIE`, cache/queue/filesystem settings, `QAMMARIS_APP_BASE_URL`, `QAMMARIS_APP_API_KEY`, `QAMMARIS_APP_WEBHOOK_SECRET`. Confirm actual config variable names before entry. Staging's default queue is sync, but its stock job explicitly selects the database connection; the production worker must consume `qammaris-app` on that connection. Never copy staging `.env` or rotate production credentials as part of preflight.

## Recovery

Owner explicitly waived legacy backup; do not make one under this phase. Leave the existing legacy directory/database untouched and record the current deployment routing before activation. If approved recovery is required, route back to the old application and its existing database rather than executing destructive down-migrations. Preserve the new target's writes/media/audit evidence for a forward fix. Restore the webhook destination only within separately authorized integration recovery scope. Exact switching commands and recovery duration are Not confirmed until hosting inspection; no backup/restore proof is claimed.

## Definition of Done

Updated P1-04 gates: minimal hosting baseline, frozen source/assets/catalog transfer manifest, working Owner admin access plan, approved separate target/runtime setup, reversible activation plan and concrete Owner cutover authorization. Legacy data matching and backup/restore are explicitly waived for this release. At this checkpoint staging/preflight evidence and the target plan are prepared; production target, account setup, transfer and activation have not happened. Continue this same item; do not start another phase automatically.
