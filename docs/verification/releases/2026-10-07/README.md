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

CI, main merge, production pipeline, existing watchdog replacement and post-release checks pending. Owner approval covers the prepared release; unresolved device limitations remain honest limitations, not passes. No production test product/user/customer order or bulk write planned. Authenticated admin smoke checks are read-only; WhatsApp messages are not sent.

## Recovery

Retain previous release and original watchdog script. If health fails, the pipeline restores previous code. Other regressions: use a reviewed revert/forward fix through main CI, or restore the verified previous current symlink and watchdog with explicit release recovery. Stop photo consumption during an older-code rollback; retain successful product/media/audit data. No database restore, product deletion, cursor reset, historical replay or stock mutation.

Next action is post-release observation/Owner acceptance; no payment gateway or further backlog implementation started.
