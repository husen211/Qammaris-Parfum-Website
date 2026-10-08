# Qammaris Journal — approved production release, 2026-10-08

The Owner requested the blog release and explicitly excluded order work. Separate replies authorized transaction-only staging fixtures with full rollback and the five additive staging/production migrations after staging passed, with a private recovery point immediately before production DDL. These approvals do not activate production machine credentials or rewrite old articles.

## Delivery and scope

- Reviewed application head: `51fd5c22d05c405ea217a0d4b59f34aae0fc2d7e`.
- Consolidated [PR31](https://github.com/husen211/Qammaris-Parfum-Website/pull/31) includes BLOG-01–05; main was an ancestor of that head. No order/cart/checkout file changes. Order PR27 and32 remained open and untouched.
- Manual [candidate build37724385033](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37724385033) passed, **deploy skipped**, as required by the workflow's event/main guard. Archive SHA-256 `95fdf45a5ab9a565adfb9e074f38553acca889df8022391fae8318d81202bc62` matched locally and on both servers.
- Merge: `3c8d35f8aa33ad8c3ce614ce7e7ed01497c30e3f`.
- Main [CI37725513037](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37725513037) and [production release37725566737](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37725566737) passed. Server revision/current link and deployment log independently confirmed activation at **04:03:49 UTC**.
- PR26 was recognized merged; superseded BLOG02–04 PR28/29/30 were closed without deleting branches. Order PRs were not merged/closed.

Code/assets ship through GitHub. Protected shared env/storage and the existing pending-migration guard remain. No File Manager, hosting permission, human credential, product price/stock/catalog mapping or source-system change.

## MySQL and media rehearsal

Actual CLI PHP8.2.33/MariaDB11.8.9 supported GD/WebP, persistent public media and file cache. Candidate directories were private siblings outside public routing, linked to each environment's existing protected env/public media, with isolated framework/log/cache storage. Staging/production were checked independently; no database name or credentials were exported.

Only these migrations ran, each followed by a no-op replay:

1. `2026_10_07_000002_add_blog_write_safety.php`
2. `2026_10_07_000003_add_blog_editorial_cms.php`
3. `2026_10_07_000004_add_blog_search_metadata.php`
4. `2026_10_07_000005_add_journal_media_and_relations.php`
5. `2026_10_08_000001_add_blog_automation.php`

[Staging DDL](stage-migrations.json), [transaction rehearsal](stage-rehearsal.json), [production DDL](prod-migrations.json). Original article columns/IDs/slugs/contents/authors/image references and user IDs were compared **on the server**, as were seven unrelated catalog tables; all matched. DDL runtime was375ms staging and395ms production in aggregate. No down/fresh/reseed/backfill or mass data update occurred.

Staging fixtures tested the actual HTTP kernel/API and shared admin actions inside an outer MySQL transaction: hashed Bearer authentication, sanitized owned draft, idempotent create/upload replay, stale409, foreign403, forbidden publication422, expired401, GD/WebP variants, human no-op/stable slug/revision/publish/archive/restore and both audit actor types. Records/accounts/tokens were rolled back and generated original/variant files removed. Auto-increment gaps may remain; no ID was reassigned. This is **not** a persistent staging browser account, production admin-write test, or simultaneous MySQL worker load test.

[Recovery receipt](recovery.json): full SQL gzip snapshot of23tables,1,132,371compressed bytes, private server storage with0600 mode and verified complete footer. Contents never downloaded, committed or printed. Gzip verification is not a restore rehearsal; restoration requires an approved isolated target. Originals/shared media retained, not moved/purged.

## Verification results

| Check | Observed result |
|---|---|
| Laravel regression / GD | 468tests /3564assertions passed |
| JavaScript regression / Vite build | 31tests and production build passed |
| Pint / Composer strict validation | Passed on application head |
| Production `/up`, `/blog`, `/products`, `/login` | HTTP200 |
| Anonymous admin blog | HTTP302 to login |
| Machine API without activation | HTTP503 `automation_disabled`, expected |
| Legacy articles | All3HTTP200; image requests rendered successfully |
| Browser errors / overflow | None at320/375/390/768/1440 |
| Article metadata | Canonical plus valid JSON parsing for BlogPosting/BreadcrumbList on all3articles |
| Genuine Chromium touch |390px, hasTouch/maxTouchPoints1, hover:false; one tap navigated and gesture scroll moved |
| Native file input + upload | Local authenticated390px touch form submitted once, stored/rendered one media image, no broken image/overflow/JS error |
| Runtime | One actual PHP queue worker on exact current release; scheduler/watchdog heartbeats; zero Laravel ERROR entries since activation at observed check |
| Persistent data |453products /1131product-image rows /3articles; no pending migrations |
| Production automation | OFF; zero machine actors, no tokens issued |

[Before browser](before-browser.json), [after browser](after-browser.json), [local native-input upload](local-upload.json), [runtime](runtime.json), [production aggregate preflight](prod-before.json)/[after](prod-after.json). Screenshot evidence in [screenshots](screenshots/). The connected CUA runtime failed technical initialization; independent headless installed Chrome was used. An extra broad rerun hit a network timeout after the first successful full public check; this was not an application exception.

[Live controls](interactions.json): search `royal` and typo `royall` each returned the one relevant article; unrelated query returned the empty state. Review category returned one article, mobile TOC opened on one tap, and sitemap200 contained all three article URLs. No JavaScript errors. The Node request transport timed out for sitemap; the actual browser then loaded it successfully. Network retries did not alter application code or production data.

Bounded server-origin GET timings after release: Journal first/warm TTFB68/50ms, catalog55ms and sitemap44ms, all200. These four samples do not measure customer mobile network, LCP, throughput or sustained load; no broad performance claim is inferred.

Physical iPhone Safari is untested; touch emulation fulfills the approved alternative. Native file-input selection uses Playwright's file input interface, not the operating-system picker. Local fixture DB/admin/media/sessions, image and server were removed after testing. No production upload/test article/account or agent credential was created.

## Remaining review and recovery

- Existing3articles lack explicit alt metadata; current title fallback keeps their images usable. One article contains literal price/stock prose. Owner review is needed; no automatic rewrite/unpublish or author replacement.
- Production machine-credential activation remains a separate step. API code/schema being installed does not grant agent access.
- Existing SEC-DEP-01 npm and SEC-DEP-02 commonmark advisories remain open; dependency audits are **not clean**. No speculative dependency update was bundled; Journal consumes sanitized HTML, not Markdown conversion.
- External Google validator/Search Console/indexing, production native upload, sustained/concurrent MySQL load and human Owner editorial walkthrough remain unconfirmed. This release does not close P9/order work.
- Retain additive schema, audit/replay rows and originals/variants during recovery. A pre-Journal rollback can re-expose archived articles or strip new components during edits; use a compatible forward fix, or an explicitly reviewed rollback that retains visibility/media semantics. Keep the API OFF for containment; do not migrate down populated tables or delete shared media.

[Release runbook](../../runbooks/JOURNAL_RELEASE.md), [automation operations](../../runbooks/JOURNAL_AUTOMATION.md), [program board](../../planning/BACKLOG.md).
