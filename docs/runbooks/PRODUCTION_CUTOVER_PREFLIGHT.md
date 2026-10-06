# P1-04 — Production cutover preflight

**2026-10-06 — IN_PROGRESS; production activation is not authorized.**

Owner requested the next phase after publishing the photographed staging catalog. This document prepares a reviewable release; it does not authorize production mutations. Earlier SSH permission covers staging only. Production SSH read-only inspection has been requested separately because [ADR-001](../architecture/decisions/ADR-001-legacy-production-and-clean-redeployment.md) deferred that inspection. The final production backup remains scheduled immediately before an explicitly approved cutover, not during this preflight.

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
| Content/accounts | Staging has zero users, blog posts and store-info rows. These must not overwrite production content/accounts |

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

## Production facts still needed

The public `/products` page displays **208 products**. This is a public pagination count, not a confirmed database count. Production PHP/MySQL versions, migration history, schema, product/variant IDs, SKU coverage, media inventory, accounts/roles, blog/store content and deployment directory are **Not confirmed**. Read-only SSH inspection awaits explicit Owner scope; do not infer them from staging or the old local snapshot.

Existing public URLs already demonstrate the need for a target-specific preview:

| Production page observed | Staging candidate | Consequence |
| --- | --- | --- |
| `/products/afnan-lynked-freedom`, Rp889,000 | ID144, `/products/afnan-lynked-freedom-edp-100ml`, Rp819,000 | Identity/size match Not confirmed; preserve the old URL and review the price conflict before updating an existing record |
| `/products/joe-winn-al-fajr`, Rp439,000 | ID170, `/products/maison-jw-al-fajr-extrait-100ml`, Rp439,000 | Brand/name alias and size match Not confirmed; no automatic name-only pairing |
| `/products/mpf-wicked-noir-eau-de-parfum`, Rp349,000 | ID354, same slug and displayed price | Production identity still requires inspection; matching slug does not authorize replacing its ID |

The read-only baseline should return counts, types, constraints, migration/version information, public URL/media manifests and collision reports. Secret configuration is reported only as **terisi / tidak**, never values. Do not print password hashes, environment files, job bodies or customer data.

Concrete migration gates:

1. Detect multiple `product_variants` per product before `2026_09_16_000002_make_product_drafts_and_single_offers_explicit.php` creates its unique product constraint. Staging has none; production is Not confirmed. Do not delete variants to force migration success.
2. Check existing slugs, external identity uniqueness, orphan relationships, field lengths/nulls and money types against the additive migrations. Do not rewrite historical migrations or coerce incompatible data silently.
3. Preview the publication backfill in the preceding migration: legacy active products can become published. The approved 350-product staging set does not authorize publishing every legacy record.
4. Match reviewed UUIDs to legacy IDs using available identity evidence, SKU plus size, and explicit ambiguous choices. Never force a truncated/nonunique SKU or inferred brand alias.
5. Review name/price/category/gender/description/media conflicts separately. Preserve existing unmatched records and URLs pending Owner direction; do not infer removal from missing feed rows.

## Proposed data strategy, pending the baseline

Prefer preparing a separate approved target from the final legacy database backup, so real product/variant IDs, password hashes, blog/store content and existing relationships remain intact. Apply reviewed additive migrations there, then apply an exact, audited catalog merge preview. This target has not been created and its actual schema compatibility is Not confirmed.

For matched products, retain their production IDs and canonical slugs; `Product::getSlugOptions()` already disables regeneration on update. Preserve existing variants/media until a reviewed correction explicitly changes them. New records receive target-generated IDs above the existing sequence; remap every child relationship from the reviewed identity map. Never copy staging IDs over production IDs. A deliberate canonical URL change requires an approved redirect.

Apply writes only with validated scope, before-state conflict guards, idempotency, transaction boundaries and Owner/operator audit attribution. Current staging operators intentionally refuse production. Do not relax those guards or assume a generic CSV command already supports this merge. The actual merge operation and exact price/unmatched-product decisions follow the production baseline and Owner-reviewed preview.

Copy launch photos through the current Laravel disk using **copy → checksum/size verify → switch references → retain originals**. Check destination path collisions first; a differing checksum must stop, not overwrite. Preserve production blog/media files and current storage URLs. Keep the public disk for this release unless Owner approves a concrete storage change; the earlier R2 rehearsal covered 19 objects, not this entire launching catalog.

The final visible product count depends on legacy reconciliation. Do not promise 350 total production records until unmatched legacy visibility and mappings are approved.

## Reviewable cutover sequence — do not execute yet

| Order | Work | Completion evidence / gate |
| --- | --- | --- |
| 1 | Authorized production read-only baseline | Actual hosting/schema/data/media/account counts and duplicate/collision reports; no mutations |
| 2 | Exact target preview and release freeze | Owner reviews ID/UUID/slug mapping, conflicting prices/copy, unmatched legacy policy, migrations, complete source/build manifest and target layout |
| 3 | Owner authorizes the concrete production cutover scope | Includes approved writes, target, downtime/window, media handling, recovery and separately authorized backend webhook switch; do not request vague blanket approval |
| 4 | Final production backup immediately before cutover | Database and required legacy files/media downloaded to protected storage, checksums recorded, isolated restore checked; environment secrets remain in protected environment/vault, outside Git/chat |
| 5 | Prepare the approved target and catalog | Reviewed additive migrations, guarded audited merge and media copy/verify; retain legacy release/database/media for recovery |
| 6 | Prepare integration and runtime | Production receiver/HMAC, database queue worker and minute scheduler/watchdog; no duplicate schedule:work; 30-minute reconciliation; protected env present, HTTPS cookie setting explicit |
| 7 | Refresh source status | Start a fresh consumer at checkpoint zero, drain the safe feed through `has_more=false` after identity mapping, verify monotonically applied revisions. Do not copy staging checkpoint/queues or stale availability over newer source data |
| 8 | Verify the approved target | Owner's existing admin account still works; blog/store content retained; old sampled URLs, catalog/detail/price/photo/stock/inquiry, assets and sitemap verified; no test fixture/private review exposure |
| 9 | Approved website activation | Keep the existing public URL shape. No DNS/other-site changes are implied. Clear only target application caches including the one-hour sitemap cache |
| 10 | Separately approved backend webhook URL switch | Change only the intended backend destination to production after its receiver/worker is ready; keep staging isolated; verify feed and signed delivery without exposing secrets |
| 11 | Observe or recover | Queue/cron/feed/HTTP/media/error checks and exact product counts; preserve old system and all newly written data/media through the agreed recovery window |

Protected environment checklist names only: `APP_ENV`, `APP_DEBUG`, `APP_URL`, existing `APP_KEY`, database settings, session settings including `SESSION_SECURE_COOKIE`, cache/queue/filesystem settings, `QAMMARIS_APP_BASE_URL`, `QAMMARIS_APP_API_KEY`, `QAMMARIS_APP_WEBHOOK_SECRET`. Confirm actual config variable names before entry. Staging's default queue is sync, but its stock job explicitly selects the database connection; the production worker must consume `qammaris-app` on that connection. Never copy staging `.env` or rotate production credentials as part of preflight.

## Recovery

Retain the legacy application, its compatible database, media and protected environment as a complete recovery unit. On approved rollback, restore traffic to that unit rather than running destructive down-migrations against populated new tables. Revert the webhook destination only within the authorized integration recovery scope. Preserve new target writes, media and audit evidence for a reviewed forward fix/reconciliation; do not overwrite them with an old backup. Actual recovery commands, paths, maximum downtime and restore duration remain Not confirmed until the production baseline and isolated restore.

## Definition of Done

P1-04 is complete only after the actual production baseline and reviewed mapping, coherent release artifacts, latest backup plus isolated restore evidence, approved activation scope and recovery path are available. At this checkpoint only staging/preflight evidence and the proposed sequence are prepared. Production deployment, backup/restore and webhook switch have not happened. Continue this same item when Owner grants the missing read-only scope; do not begin another phase automatically.
