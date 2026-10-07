# Combined pending release — 2026-10-07

Owner authorizes footer deployment, then expands scope explicitly: “yg belum deploy deploy aja smua”. Release includes prepared PR6 catalog click/mobile feedback, PR7 recurring Shopee XLSX admin enrichment, PR9 dropdown filters, PR10 recipient WhatsApp checkout/direct cart/motion, PR11 destination skeletons and PR12 footer. No next feature phase, schema migration, catalog import/replay/publication, credential or media-provider change is authorized or executed by this release.

Previous production rollback revision: `ae86fc13274998dd6e50de439d5dfd014762cd37`. Existing shared env/database/media remain. Pipeline packages locked code/assets and switches the current symlink after migration-status and health guards.

## Integration decisions and verification

Combine prepared branches on `codex/production-pending-release`, preserving original commits. Resolve overlapping catalog markup by retaining new dropdowns and independently scrolling mobile fields/fixed actions, plus current DEFAULT_SORT/Terlaris dahulu; explicit latest remains selectable. Keep brief catalog click feedback alongside destination loading, preserving native navigation and pointer/keyboard return distinctions. Documentation conflicts retain historical decisions for each distinct feature.

Automatic approval review rejected the first combined conflict-resolution/commit command as insufficiently verified; it did not run. Conflicts were subsequently inspected and explicitly resolved, with narrow checks before each merge commit. Initial integrated Node run caught removal of the existing native click listener; restored the existing brief feedback listener and reran its9 boundary tests successfully.

Final combined local suite:322 Laravel tests/2158 assertions and29 Node regressions passed. Vite build, scoped Pint and whitespace passed. No migration or lockfile change. Narrow catalog20/192, import32/135, checkout/catalog38/327 and admin/catalog/cart133/1080 checks passed during reconciliation.

Required queue readiness: production had database retry_after90 and no env override, equal to image job timeout90. Set default retry_after120 and update the existing single production watchdog to consume qammaris-app first, then product-import-images, with timeout90. Regression confirms reservation outlives image job timeout. No new cron or worker credential.

Read-only production preflight: revision matches previous deployed commit;448 products/1080 image records, jobs0/failed0; ZIP/XMLReader/SimpleXML loaded. First CDN probe found no source URL in old import rows; a bounded original Owner-export image probe then downloaded a valid640×640JPEG/25974bytes through the normal host/TLS/MIME/size-checked downloader. Exact temporary file removed; no product attachment/import. One successful photo is not a guarantee for every Shopee URL.

Combined local Chrome390: mobile dropdown opens, expanded brand list keeps header/actions within viewport (panel101..844, actions767..844), doc width375 within390. Actual iPhone/touch, screen-reader and full native Shopee file chooser remain Not confirmed. Initial native mobile apply observation was interrupted by browser tool timeout; final production verification must independently recheck this path. Historical feature-level screenshots/checks remain in their separate verification directories.

## Release checkpoint

Released via [PR13](https://github.com/husen211/Qammaris-Parfum-Website/pull/13), merge revision `d7a511504068720a31be510d16588e12b39a9f06`. PR CI37551410842, main CI37551550947 and [production pipeline37551594819](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37551594819) succeeded. Server `release-revision.txt` matches. Prepared PR6/7/9/10/11/12 are merged; no open PRs remained after this release. This supersedes the preceding pending deployment statements and the feature documents' historical unreleased statements.

Existing runtime watchdog replaced atomically from the reviewed release, retaining its previous script/mode and using the same lock/cron. Queue restart completed; actual production worker PID3957934 consumes `qammaris-app,product-import-images` with timeout90, database retry_after120, lock held. The other observed stock-only worker belongs to the existing staging scope and was untouched. Existing scheduler and worker heartbeat files both advanced to00:27:01UTC; no new cron. Integration checkpoint467, last_synced_at00:30:03UTC, last_error null; jobs0/failed0. Read-only counts remain448products/1080image records. No migration, catalog apply/publication/import/replay, media mutation or credential change.

Sequential HTTPS smoke checks: `/up`, `/`, `/products`, `/cart`, `/login` return200. Empty `/cart/checkout` redirects to `/cart`; unauthenticated `/admin/shopee-imports` redirects to `/login` (final200). Both build assets return200 with correct CSS/JS MIME, one existing product photo200image/jpeg. Production CSS is `app-D685iIAR.css`, JS `app-BUbMbDa1.js`. Public documents include the skeleton shell and new footer.

Actual Chrome production navigation: mobile filter -> Brand -> Afnan -> Tampilkan hasil returns `brand[]=42`,17results; default Terlaris dahulu remains selected. Header cart link opens `/cart` directly, initially empty. Product Supremacy In Heaven opens with3photos/current availability/price; add1 returns confirmed success; cart shows1item/current subtotal; checkout displays required name/phone/address and optional postcode/note. No recipient fields entered, no checkout submission or WhatsApp request/message. Removed exactly the temporary session cart item and verified the original empty cart. No product/order/customer record was created.

Browser limitations: viewport overrides requested390x844 and1440x900, but read-only browser measurements reported520px and1920px respectively; do not claim exact production390/1440 visual proof. Mobile control behavior above was observed through the browser accessibility tree. Screenshot capture repeatedly timed out, so no new production screenshot is claimed; historical local before/after captures are available in the feature verification folders, including [footer desktop](../../p7-14/after-desktop.jpg) and [footer mobile](../../p7-14/after-mobile.jpg). Fresh admin import tab reached login: authenticated production upload/preview and native file chooser remain Not confirmed; no password was requested/reset. Actual iPhone/touch, screen-reader and real WhatsApp send remain Not confirmed. Owner's release instruction did not turn these limitations into test passes.

Local isolated combined preview process/router/database/session files removed; original reference data/media retained. Temporary browser viewport override reset. Final follow-up changes are release evidence/runbook/status documentation only, without a second code deployment.

## Recovery

Retain previous release and original watchdog script. If health fails, the pipeline restores previous code. Other regressions: use a reviewed revert/forward fix through main CI, or restore the verified previous current symlink and watchdog with explicit release recovery. Stop photo consumption during an older-code rollback; retain successful product/media/audit data. No database restore, product deletion, cursor reset, historical replay or stock mutation.

Next action is post-release observation/Owner acceptance; no payment gateway or further backlog implementation started.
