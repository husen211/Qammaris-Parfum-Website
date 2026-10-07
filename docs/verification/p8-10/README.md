# P8-10 — Shopee import recovery and focused review

Status: released to production on 2026-10-07 after Owner approval. Implementation and release evidence are separated below. No production import POST, apply, publication, schema, credential or media write occurred during this verification.

## Evidence before changing code

Read-only production inspection of Owner batch6: **375 rows,368 matched,7 review,0 applied**. Four matched rows no longer matched the old target hash: Reverie Aqua, Royal Blend, Sharaf Blend and Rasayel Shagaf. The old hash includes product/offer updated_at, price, stock, availability and bestseller alongside content/media. The previous baseline values are not retained in that hash, so the particular changed field for each of these four rows is **Not confirmed**. `ApplyShopeeContent` threw on the first stale row inside the whole-batch transaction.

The seven review source names were Silk Noir100ml, Emper Melina100ml, Dicium Azure50ml, SAFF Iliad35ml, Dicium Liora50ml, Project1945 Bamboe Roencing150ml and Fragrance World Liberty Intense100ml. They are not automatically approved by this fix. Silk Noir's selected website offer75ml was observed in Owner screenshot/native production read. Owner explicitly approved a per-row incorrect-source-size confirmation, retaining website size/price. Do not infer that all seven have an incorrect Shopee size.

Native production admin session/batch was observed without mutation. Before screenshots: [desktop](before-desktop.png), [mobile](before-mobile.png). Photos/source descriptions/credentials were not dumped.

## What changed

- Dedicated content-v2 target hash excludes independent price/stock/timestamp/bestseller updates, protecting actual identity, offer size, content, publication/hidden and media. The global catalog hash for other operations is unchanged.
- Existing actor-owned batches use explicit **Periksa ulang perubahan**, including legacy proposals. It refreshes unfinished audit/proposals from the retained immutable source; no products/photos/publication change until separate apply. Applied and completed no-op rows are not bulk refreshed.
- Apply prevalidates all payload hashes. Recoverable target conflicts hold individual rows; valid rows continue. Tampering still aborts/rolls back. No-op rows do not save products/bind identities/download photos.
- Manual size mismatch retains the chosen candidate for an actionable per-row comparison. Only explicit matching-size confirmation makes it applicable; different/multiple sizes, occupied/hidden identities remain guarded. The wrong-size description is not imported, including an attempted replacement. Original source and confirmation actor/sizes remain in review metadata.
- Default review shows products needing work; complete published products without proposals are available under **Sudah lengkap**/**Semua hasil**. Pending count covers proposed changes, not all recognized rows. Separate publication/readiness gates remain.
- Shopee photo enqueue holds only media-conflicted rows, retaining baseline/attach guards and continuing safe rows. Other import contracts are untouched. Search-option filtering preserves option metadata needed for the size comparison; filter/search/page return context survives choice/editor navigation.

## Checks actually run

- Shopee feature suite: **35 tests,193 assertions passed**, including real375-row XLSX, legacy recovery, no-op/default filters, per-row confirmation/revocation, preserved size/price/copy, app price/stock changes, human-copy conflict/partial apply, image conflict isolation, actor/child guards, validation, tamper rejection, photo retry/publication/idempotency and editor return context.
- Full Laravel suite: **339 tests,2306 assertions passed**; final relevant suite rerun after recovery/UI adjustments. Existing CSRF/admin route stack retained; new refresh request uses the same actor authorization/accepted-confirm and6/min throttle.
- Node suite: **31 tests passed**. Vite production build passed; existing DaisyUI @property optimizer and separate3D about-page chunk-size warnings remain, with no package or bundle-boundary change in this task.
- Scoped Pint and `git diff --check` passed.

## Native browser verification

Chrome used a separate loopback server/SQLite database with **375 synthetic products/rows**, synthetic temporary admin, isolated sessions/media/views and no production/local catalog mutation. Initial fixture contained368complete published products and7needing work, one legacy proposal and one human content edit after preview. This fixture count is not a new production audit count.

1. Default view showed7work/368complete; complete and all filters retained368/375results and normal25-row pagination. Complete page2 preserved its filter and showed26–50of368.
2. Searched the target dropdown for Silk Noir, selected75ml, and observed the100/75comparison. Option size metadata survived search filtering. Submitting without confirmation retained the candidate and showed **Konfirmasi ukuran**, with the comparison expanded. Confirming and resubmitting proposed one photo while retaining75ml/current price and suppressing description replacement.
3. Actual native local Apply processed5products/queued5photo jobs and held2rows. Native recheck removed the outdated description proposal rather than overwriting the human edit. After separate apply,2more products queued. Final local states:368skipped-no-changes/7updated,7jobs; human description unchanged, Silk75ml/description still blank. Queued jobs were isolated test data; real CDN/worker completion is not claimed from this browser test. Automated fake downloader/attach tests passed separately.
4.320,375,390,768and1440px widths measured no horizontal overflow: mainScroll==mainClient throughout; row action44px minimum. Native keyboard Tab from source search focused filter with visible2px black ring. Empty search/filter state retained selected filter and showed recovery instructions. No browser console errors captured.
5. The temporary server, SQLite, synthetic account, sessions, copied media and preview scripts were removed after verification; original p7-07 reference and unrelated `tools/__pycache__` retained. Browser overrides reset and task tabs closed.

After screenshots: [desktop overview](after-desktop.png), [mobile comparison](after-mobile.png), size confirmation [320](size-confirm-320.png)/[375](size-confirm-375.png)/[390](size-confirm-390.png)/[768](size-confirm-768.png)/[desktop](size-confirm-1440.png), [partial apply](partial-apply-desktop.png), [complete filter](complete-filter-desktop.png), [empty state](empty-desktop.png). Viewport screenshots were used because full-page capture cannot represent the existing fixed-height scrolling admin main correctly.

## Production activation and recovery

Release requires Owner approval through the existing GitHub pipeline; do not auto-merge/deploy this branch. No migration/import/publication is needed to activate the code. After release, Owner opens the existing375-row history, chooses **Periksa ulang perubahan**, reviews actual remaining work, confirms incorrect source sizes only where appropriate, then explicitly applies. No need to upload those files again merely to recover an old preview. Wrong-size description completion remains in the editor; nothing is automatically published.

Actual new-code production/MySQL runtime, Owner's live apply, real CDN downloads, updated production work counts and physical iPhone Safari/touch are **Not confirmed**. Local mouse/viewport checks are not touch testing. The code remains in review. Code-only rollback retains all source/audit/media/data; old code cannot apply content-v2 proposals using its broader hash, so prefer forward fix/recheck. Never delete batch rows/media or reset the feed checkpoint for rollback. Re-upload a current pair if source text/photos themselves have changed; recheck uses the source already stored in this batch.

The preceding review/release notes describe the pre-release checkpoint and are superseded by the release evidence below.

## Owner-approved production release — 2026-10-07

Owner approves the concrete P8-10 release with “oke setuju gas”. [PR15](https://github.com/husen211/Qammaris-Parfum-Website/pull/15) merged as `0dc6924479ae108177c467a380b3978b1f89c70b`; main CI37565895505 and [production run37565946279](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37565946279) succeeded. The server revision matches; deployment timestamp03:17:25UTC /10:17:25Asia/Bangkok. The existing pipeline installed code/assets, retained shared env/storage, refused pending migrations, checked `/up`, and restarted the existing worker. No manual File Manager/config/permission change or migration was performed.

Read-only production metadata before and after:448products,371published/77draft,1080image records; batch6still375rows/0applied/status previewed. Jobs0/failed0, checkpoint467, last sync03:00:05UTC/errorabsent. Scheduler heartbeat03:18:01UTC and watchdog03:18:02UTC; one PHP database worker consumes qammaris-app and product-import-images, retry_after120/timeout90. `/up` and `/products` each HTTP200; the new refresh route is active. This is a runtime/metadata check, not a fresh upstream event or photo-download test.

Existing authenticated Owner Chrome session checked only GET navigation and unsubmitted controls. Batch6default is37work/338complete, not the synthetic7/368 fixture. Existing complete products with additional photo proposals legitimately remain work. Complete filter returns338with25-row pagination. Source search silk + review filter finds Silk Noir. Searching the product selector and choosing website75ml shows source100ml/website75ml, an unchecked explicit confirmation and disabled mismatched-description replacement. No choose/refresh/apply/publish POST was sent, and the selected value was discarded on returning to the batch overview.

Production viewport1440×900 and390×844: main client width equals scroll width (1154/1154 and360/360), no horizontal overflow; native keyboard focus reaches the size checkbox and Perbarui pilihan. Captured console errors:0. Screenshots: [desktop overview](production-desktop.jpg), [size desktop](production-size-desktop.jpg), [size mobile](production-size-mobile.jpg). Temporary viewport reset; Owner's existing tab remains on batch6.

Production GET/MySQL/runtime and current work counts are now confirmed. Production native upload, refresh/apply writes, real new CDN acquisitions and physical iPhone Safari/touch remain **Not confirmed**. For the existing batch, Owner first clicks **Periksa ulang perubahan**, reviews remaining proposals, then separately confirms incorrect sizes only where appropriate and applies. No automatic publication; no re-upload needed solely to recover the old preview.

Recovery: retain previous `669de1130093ad0391e9beeee7f068c40555ac6a` and all data/source/audit/media. Prefer forward fix/recheck; old code's broader target hash cannot safely apply content-v2 proposals. Do not delete batches/media or reset the source checkpoint. Documentation follow-up does not request a second production deployment.

Next backlog item: bounded P9 Owner/device/live-admin acceptance, then the separately approved evidence-first clean-code/documentation audit. Neither started in this release.
