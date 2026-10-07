# BLOG-04 — media/components verification

Date:2026-10-07 UTC /2026-10-08 local. Status: IN_REVIEW, not deployed. Branch modernization/blog-04-media depends on BLOG-03 commit5e92f50, then BLOG-02/01. Owner selected the next blog item; no order work changed.

Delivery: [draft PR30](https://github.com/husen211/Qammaris-Parfum-Website/pull/30), base modernization/blog-03-journal, implementation commit84e5346. Both [push CI](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37656786108) and [PR CI](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37656877368) passed PHP and Node jobs; Linux log confirms439 tests/3134 assertions including JournalComponentsTest, with GD enabled. Follow-up delivery documentation does not change code; latest checks are on the PR.

## Outcome and impact

Owned media with metadata/original checksums, bounded WebP srcset and reviewed focal crops; image/gallery/callout/CTA/YouTube/inline article components; ordered products/articles/FAQ/references. Current catalog cards read image/name/price/size/availability, hiding private targets and retaining sold-out products. Public and preview renderer are shared. Native gallery/FAQ controls and editor repeaters are responsive/keyboard accessible; no new dependency or public URL change.

Migration000005 adds blog_media plus five nullable blog_posts columns only. Legacy IDs/slugs/body/author/image references remain; no historical backfill, data rewrite, catalog mutation, staging/production migration, credentials or deployment. New originals/variants reside on the configured Laravel disk, default public/blog. Replacements/recrop/archive retain old files; failed mutations compensate new files only.

## Checks actually run

- JournalComponentsTest:16 tests/141 assertions passed with GD in the PHPUnit process. Covers actual bounded derivative dimensions/checksums, unavailable-processing fallback, partial write cleanup, upload/recrop DB/audit failure, stale upload/no-op, admin/parent ownership, foreign featured/body media, feature archive guard, canonical markers/injection/HTTPS, ordered structured data/clear/self-ID normalization, live price/status/private targets/no catalog writes, private no-write preview, memory/original-integrity limits, recrop retention/alt sync, per-form error retention/explicit conflict reload, additive migration preservation/replay and empty legacy-list no-op.
- Full Laravel:439 tests/3134 assertions passed,31.83s,106MB. Command: `C:/xampp/php/php.exe -d extension=gd vendor/bin/phpunit`. An earlier artisan invocation did not propagate GD to its child and skipped GD-specific coverage; the direct PHPUnit runs above supersede that result.
- JavaScript: `node --test tests/js/*.test.mjs`,31 passed/0 failed. `npm test` has no configured script; rerun with the repository's CI command passed. New Tiptap/component interaction checked in the real browser, not claimed as additional automated JS coverage.
- Vite:724 modules built. Editor449.81kB/142.46gzip; public Journal2.24kB/1.01gzip. Existing DaisyUI @property and unrelated large about-page3D chunk warnings remain.
- Pint dirty formatted; final Pint test/git diff --check, Composer strict validation and221 local documentation links passed. Dependency lockfiles unchanged. npm audit still reports3 existing advisories (SEC-DEP-01); Composer locked audit reports2 league/commonmark advisories (SEC-DEP-02). Audit is not clean; no unsolicited package upgrade.
- Migration applied/replayed only in disposable SQLite. GD/WebP checked locally with per-process extension flag; no hosting configuration changed. Local fixture/runtime cleanup recorded below.
- Local image-processing benchmark,3 passes each:1080×1080 JPEG→6 variants median494.54ms/max550.59ms, original226627bytes/aggregate variants317470bytes;800×500 PNG→5 variants median256.11ms/max259.96ms, original411738bytes/aggregate variants99634bytes. PHP-reported peak50MB (not all native GD memory). Temporary benchmark variants were discarded and originals retained. This measures two local sources, not hosting throughput/production-scale performance or a guaranteed bandwidth reduction for every photo.

Cleanup completed: task-created loopback PHP server stopped; disposable SQLite (including synthetic admin/articles/product), local .env, fixture/benchmark scripts,19 fixture media files/retained generations and one session removed. Browser tab closed and viewport reset. Dependency tooling/build assets remain ignored. Shared checkout, staging and production were not touched.

## Browser observations and screenshots

Chrome extension with mouse/keyboard/viewport only. Public article and media form checked at320/375/390/768/1440px; page scrollWidth did not exceed clientWidth. Public scrollbar clients305/360/375/753/1425; admin form viewport clients320/375/390/768/1440. JPEG square hero and landscape PNG gallery use bounded sources; selected16:9 crop atx75/y25 saved natively and persisted after reload. Small-source warning, original link, retained variants and featured archive instruction visible.

Gallery next click/ArrowRight scrolled to256px within343px frame/599px content; ArrowLeft returned0 and button disabled state updated. FAQ opened natively. Product card has long name,100ml,Rp479.000,Habis and keyboard link without page overflow. Browser console recorded no errors/warnings in the final public check.

Editor inserted a callout, toggled HTML→Visual preserving gallery/product/article/CTA markers, added/reordered FAQ rows (field names reindexed), and saved via native form; “Artikel disimpan” confirmed. Reload retained nodes/order. Authenticated preview showed the same components and owned hero; mobile iframe client/scroll375/375 and noindex. Preview writes/privacy are also server-tested. Media cropless/empty/error paths have explicit copy, and retry/conflict preserve the failed form rather than copy it into unrelated rows.

Screenshots use only local synthetic articles/account/product and existing local artwork. The old baseline has no owned-media/components UI; no real customer data. Header captures and body/component captures show different scroll positions deliberately.

| Surface | Before | After |
|---|---|---|
| Public article | [1440](screenshots/before-article-1440.jpg) | [320](screenshots/after-article-320.jpg), [375](screenshots/after-article-375.jpg), [390](screenshots/after-article-390.jpg), [768](screenshots/after-article-768.jpg), [1440](screenshots/after-article-1440.jpg) |
| Admin editor | [390](screenshots/before-editor-390.jpg) | [Components390](screenshots/after-editor-components-390.jpg), [Ordered FAQ1440](screenshots/after-editor-relations-1440.jpg) |
| Media/focal crop | New surface | [320](screenshots/after-media-320.jpg), [375](screenshots/after-media-375.jpg), [390](screenshots/after-media-390.jpg), [768](screenshots/after-media-768.jpg), [1440](screenshots/after-media-1440.jpg), [Crop390](screenshots/after-crop-preview-390.jpg) |
| Public interaction | New components | [Gallery](screenshots/after-gallery-390.jpg), [Keyboard](screenshots/after-gallery-keyboard-390.jpg), [FAQ](screenshots/after-faq-390.jpg), [Product](screenshots/after-product-card-390.jpg) |
| Shared preview | Existing preview retained | [Desktop](screenshots/after-preview-1440.jpg), [Mobile frame](screenshots/after-preview-mobile.jpg) |

UX rationale: dedicated media management keeps writing focused, presents image rights/quality/crop before publication and makes revision reload explicit. Owned selectors avoid HTML/file-path entry; small ordered repeaters replace opaque JSON. Native gallery/FAQ preserve usable content when JavaScript is absent, and catalog cards share familiar pricing/status hierarchy.

## Limits, recovery and documentation

Still unconfirmed: native browser file chooser upload (prior extension file-access limitation; no permission change), genuine iPhone/touch, staging MySQL/runtime/GD/memory/storage rehearsal and Owner acceptance. CLI/server multipart tests are not native-upload proof. YouTube allowlist/output is tested; actual external playback not verified. Preview sandbox intentionally blocks script/video; public controls work. EXIF auto-rotation is not implemented; review/rotate photos before upload. No Lighthouse/Core Web Vitals or production-scale media/performance claim. Dependency advisories require separate assessment before release.

Keep new tables/JSON/markers/media/history on compatible application rollback; populated down or pre-marker editor saves can lose content. Prefer forward fix and separately approved retention cleanup. Updated backlog/rules/master plan/architecture/Journal roadmap, ADR-035/index and editor/media runbooks. Next recommended item BLOG-05 draft-only agent API, only after Owner selects it; not started.
