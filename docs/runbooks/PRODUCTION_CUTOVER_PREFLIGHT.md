# P1-04 — Production cutover preflight

**2026-10-06 — IN_PROGRESS; production activation is not authorized.**

Owner requested the next phase after publishing the photographed staging catalog. Latest Owner direction explicitly waives preservation/migration and backup of legacy production data: “data di production itu gpenting dihpus juga gpapa, gprlu backup”. Launch uses the approved new catalog. Earlier requirements for a legacy database merge and final legacy backup are superseded for this release. This does not require deleting the old system: leave its database/files in place and activate a separately prepared target. No deletion, new database, credential change or public cutover has been executed. Owner's subsequent “lanjutt gas” authorizes the pending minimal production hosting read-only inspection; that inspection is now completed, without reading the old database or changing the server. This lifts the inspection deferment in [ADR-001](../architecture/decisions/ADR-001-legacy-production-and-clean-redeployment.md) for this narrow scope only.

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

The public `/products` page displays **208 products**. This is a public pagination count, not a confirmed database count. Production CLI PHP and deployment paths are now confirmed below. Production web-SAPI/MySQL versions, migration history, database schema, product/variant IDs, SKU coverage, media inventory, accounts/roles and blog/store content are **Not confirmed** and do not require investigation for the Owner-approved fresh launch.

Existing public URLs already demonstrate the need for a target-specific preview:

| Production page observed | Staging candidate | Consequence |
| --- | --- | --- |
| `/products/afnan-lynked-freedom`, Rp889,000 | ID144, `/products/afnan-lynked-freedom-edp-100ml`, Rp819,000 | Identity/size match Not confirmed; preserve the old URL and review the price conflict before updating an existing record |
| `/products/joe-winn-al-fajr`, Rp439,000 | ID170, `/products/maison-jw-al-fajr-extrait-100ml`, Rp439,000 | Brand/name alias and size match Not confirmed; no automatic name-only pairing |
| `/products/mpf-wicked-noir-eau-de-parfum`, Rp349,000 | ID354, same slug and displayed price | Production identity still requires inspection; matching slug does not authorize replacing its ID |

The comparison above remains historical evidence; it no longer blocks launching and does not authorize automatic pairing. Owner accepts launching the fresh catalog instead of preserving legacy product IDs/URLs/data. No legacy database export, migration inspection, variant consolidation, account/password-hash copy or full old-media inventory is needed for this plan.

## Hosting inspection and frozen packet — 2026-10-06, 13:37–13:40 Asia/Bangkok

- Production document root: `/home/u429527638/domains/qammarisparfum.id/public_html`. Its `index.php` loads `../laravel_app/vendor/autoload.php` and `../laravel_app/bootstrap/app.php`.
- Existing application: `/home/u429527638/domains/qammarisparfum.id/laravel_app`; public `storage` symlink points to `../laravel_app/storage/app/public`.
- CLI PHP 8.2.33 with required extensions. Composer requirement PHP^8.2/Laravel^12; production lock has Laravel v12.39.0. No Composer installation, bootstrap or database read was performed there; installed runtime/framework version remains Not confirmed.
- In `laravel_app/.env`, existing app/database configuration reports terisi; Qammaris app API key and webhook secret report tidak. No `.env` is present in the public root; its earlier absence must not be mistaken for absent application configuration. Secret values never emitted or saved.
- PHP shell command functions are unavailable. A separately filtered OS crontab read finds zero entries with explicit website paths; this does **not** prove hPanel has no jobs. hPanel cron definitions and actual production worker processes remain Not confirmed. No cron/process changed.

Private packet: `storage/app/private/p1-04-preflight/20261006/release-85ef088/`. Public checksum/size evidence: [release manifest](../verification/p1-04/release-packet-manifest.json); [hosting metadata](../verification/p1-04/production-hosting-baseline.json).

| Part | Verified content |
| --- | --- |
| `application-source.tar` | 241 curated tracked source/static files from candidate85ef088; source verified after CRLF/LF normalization; no env/vendor/runtime/private reviews |
| `frontend-build.tar.gz` | Complete tested staging build manifest + eight declared assets; every SHA-256 and size matches server metadata, closing the local CSS provenance gap |
| `launch-photos.tar.gz` | All1,016 approved launch photos, exact byte/checksum match; no fixture media |
| `launch-catalog-preview.json` | Fresh13:40:46 local-time capture:350 public+95draft,390offers,795external identities including445 qammaris_app UUIDs,36 referenced brands/5categories; zero readiness/orphan/duplicate identity/slug/offer failures |
| `production-public-index.php.template` | Private deployment adapter pointing from a prepared public root to proposed `../releases/qammaris-85ef088`; original application code and both servers unchanged |

All95drafts still lack photos/descriptions. Compared with the preceding preview, only two view counts and their updated timestamps changed; no business data difference. Source IDs in the packet are references for target remapping, never direct production primary-key assignments. No sync-state/checkpoint, accounts, sessions, queues, failed jobs or protected env are included. The packet is a locally verified release input; no production-safe catalog writer has been deployed or run.

Proposed new application path: `/home/u429527638/domains/qammarisparfum.id/releases/qammaris-85ef088`. Proposed prepared public root: `/home/u429527638/domains/qammarisparfum.id/public_html_next`. Neither exists as a prepared target yet. The deployment adapter is a template, not approval to create/activate these paths. Preserve existing `laravel_app`, database and public root until separately approved activation. The exact rename/routing method must be checked during approved target preparation; no permission changes or DNS changes are implied.

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

Updated P1-04 gates: minimal hosting baseline and frozen source/assets/catalog input manifest are now complete. Remaining: guarded target transfer implementation/preview, concrete Owner admin/database/env/runtime setup scope, verified target/reversible activation, and Owner cutover authorization. Legacy data matching and backup/restore are explicitly waived for this release. Production target, account setup, transfer and activation have not happened. Continue this same item; do not start another phase automatically.

## P1-04 authorized production preparation — 2026-10-06

Owner authorizes a separate fresh production target, GitHub auto-deployment preparation and Owner admin admin@qammaris.com. Owner explicitly permits sending that new admin password in chat after successful account creation; API/webhook secrets remain confidential. Public cutover and backend webhook destination switch remain final concrete approval gates.

Created releases/qammaris-85ef088 and new runtime directories under the production domain, plus protected0600 env: production/debugfalse/HTTPS sessions/fresh APP_KEY and the same staging API credential pair read in server memory. Legacy files/database/routing/permissions untouched. Owner created u429527638_qam_launch in hPanel; row verified. Database credential installation, account creation and target transfer are still pending.

New catalog:bootstrap-launch accepts only the reviewed350public+95draft/1016photo cohort in the approved fresh MySQL target/releases path. Persists an audited preview, verifies UUID/offer/media ownership and checksums/MIME, remaps IDs and reuses existing offer/identity/media/publication operations. Refuses existing catalog, requires exact preview for apply, supports rollback rehearsal and idempotent replay. Does not copy users/checkpoints/queues/sessions/fixture1. Focused6tests/37assertions passed; PHP lint, Pint and whitespace passed. No schema/dependency/public UI changes. Production apply not yet run.

Production release workflow builds locked Composer/Vite and curated source artifact without env/media/runtime/database/accounts. Main green CI plus PRODUCTION_DEPLOY_ENABLED=true and server .production-active gate recurring deployment; feature builds cannot deploy. Planned shared protectedenv/storage + per-commit releases/current link retain prior code and runtime. Deploy rejects pending migrations and reverts code after failed /up. Workflow/SSH setup/actual build and deployment remain to verify. Adding workflow is not activation. Initial artifact commit supersedes the older85ef088 source packet once CI succeeds. Continue P1-04, not another backlog item.

P1-04 target preparation verified2026-10-06: GitHub artifact7632361408c72d43b0ec07b3c38d6640d918962a built successfully in run37427677886; CI37427681959 passed. Official artifact digest and inner archive SHA verified. Source/vendor/build installed to its separate release;22 existing migrations in new qam_launch database, no seeds/legacy writes. Audited preview1 validated445rows/1016photo manifests. Actual MariaDB11.8.9 rehearsal rolled back all business rows, then final apply350public+95draft/390offers/795identities/36brands/5categories; replay exact business-table digest unchanged. New Owner admin admin@qammaris.com created and hash verified; password only in session memory for Owner delivery, not documentation/files. Secure clipboard handoff explicitly authorized; credential read into memory and protected sharedenv only. Initial database-worker feed401without/200with key,452snapshots/checkpoint457/has_morefalse,350public/95draft/0hidden,242available/108soldout,queue0/failed0/noerror. Backend webhook still points staging. Public routing remains legacy.

Owner explicitly approved GitHub production environment and storage of existing deployment SSH key after automatic approval review rejected the earlier general authorization. Production secrets now terisi, branch policy main only; repository PRODUCTION_DEPLOY_ENABLED=false. No server auth/key/permission changes or public cutover. Current internal link points newrelease, persistent protectedenv/storage shared; two value-free worker/scheduler scripts prepared and bash syntax passed. hPanel cron page under production domain actually displays staging entries too: treat it as account-wide and preserve both. Add only distinct production commands, never remove/duplicate staging cron.

## Final target checkpoint — 2026-10-06

Current evidence: docs/verification/p1-04/PRODUCTION_TARGET_PREPARATION.md and production-cron-20261006.png. Target source3538c72 is GitHub-built/CI-green;350public+95draft,1016verifiedphotos,Owner admin created,initiallivefeed452snapshots/checkpoint457/errornull,onePHPworker/zeroqueue/failed/schedule:work. Two production minute cron entries saved exactly once, alongside untouched staging entries; both heartbeats07:28:02UTC. public_html_next points current/public but legacy remains served. Web-context kernel checks pass; a CLI-only footer500 was a verification-context artifact, no application fix. Public HTTPS/browser/static-media/adminlogin and source outbound production delivery remain unproved until activation.

PR2 is retargeted to main for a concrete full launch review; the sole add/add conflict with main's older staging workflow retains the complete staging-tested workflow. Main is not merged/changed. Production artifact packaging now includes explicit directory modes and recurring extraction preserves them; otherwise private deployment umask would make public assets unreadable. Existing legacy/staging permissions remain unchanged. Only newly created target traversal/public-photo modes still require final activation approval; env/config credentials stay0600 and private data stays private. Initial public switch, main promotion/auto-deploy enable and only Node backend WEBSITE_WEBHOOK_URL change remain pending. No new backlog item.
