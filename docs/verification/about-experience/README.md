# ABOUT-01 — Qammaris experience-store About

2026-10-08; implementation branch`codex/about-experience-store`. Owner approved content/UI and supplied nine images/two LinkedIn posts/Reel/Maps/component. Reread current AGENTS/rules/architecture/backlog and applied repository Qammaris UI skill. Automatic approval review rejected merging pending PR34/35 because specific production release authorization was not established; requested explicit approval, no bypass.

## Change and rationale

Replace generic repeated cards, empty gallery slots and permanent3D loading/broken fallback with a readable personal story, named journey, real photos and clear tester/staff/discussion experience. Cream/charcoal/gold, existing font/nav/footer/URL. Nine photos displayed in context and gallery, responsive WebP lightbox, complete poster/drawing compositions. Reduced page weight: no About3D/chunk/model requests. Native anchors/details/image fallback, keyboard/focus and touch-only active colors. Conditional JS2.48KB /CSS10.77KB build(uncompressed, observed final build), no new dependencies.

`StoreController`, AboutBlade, `config/store_about*`, rating/review partials, pageCSS/JS andViteentries; regression`StoreAboutTest`. Catalog/API/order/database/schema untouched. Original source images retained;27curated static variants,total1,255,034bytes. Media are site assets deployed via Git, not a change to product/blog upload storage.

## Source integrity and limits

[Source notes](source-notes.md): LinkedIn first-party story actually read in installedChrome; no exact opening/founding dates or invented milestones. Owner claims all products have testers/staff recommendations/discussion/service. Mapsbusiness4,9 observed8October2026; review text/count blocked in limited view, so no testimonial quote copied until verified. Card adapted from actual21stMCP8993`demoCode`; paid retrieval succeeded. Reels/embed returned200; opt-in video and permanent source fallback.

Google Maps itself currently lists23:00closure; website follows Owner09:00–21:00WITA/Fridayclosed. Google profile update is a separate Owner action. PhysicalSafari/SEOindexing and continuous rating synchronization unconfirmed. Native Windows sandbox remains broken; bounded approved execution/browser helpers used, no permission-policy change.

## Checks

Initial tests failed from missing temporary localAPP_KEY and incomplete syntheticStoreInfo fixture; prepared isolatedSQLiteenvironment and supplied required phone. Corrected tests passed11tests/225assertions(About+Journal). Broad Laravel469passed/3662assertions,2existingconditional skips; JavaScript31passed. Initial Pint detected format issues, fixed; final changed-PHP Pint passed. Vitebuild passed(existingDaisyUI@property/large3Dchunk warnings; About does not load3D). No production test account/customer/order.

Browser baseline actualproduction390/1440; screenshots under[screenshots](screenshots/). Local revised320/375/390/768/1440 passedHTTP200/oneH1/ninegalleryitems/nooverflow/nobrokenphotos/noJSerrors/no3Drequests. Gallerymouse/keyboardnext/Escape/focusreturn and FAQ passed. ActualInstagramembed/accountplayer rendered after opt-in; fallback alwaysvisible. GenuineChromiumtouch(maxTouchPoints1,hoverfalse) gesture0→360px plus one-tapgallery/FAQ/location navigation passed. [Browser results](local-browser.json), [touch](local-touch.json). Hero390/1440 screenshots visually inspected; gallery2columns on mobile keeps the page manageable. Instagram screenshot can include the existing fixed navbar when scrolled underneath it; no extra overlay introduced. Initial browser image checker incorrectly counted the hidden unsourced dialog image as broken; corrected to check sourced images only. No application failure inferred. CDPsynthesizeScrollGesture did not move the page in this runtime; realtouchStart/move/end events did, and were used for finalproof.

## Release and recovery

Implementation not yet merged/deployed. Production approval pending. NormalGitHubCI/release, no new migrations. Code rollback may remove About only; keep curated photo variants and source files. Token/APIenv/actor and persistent product/blog media must remain unchanged. Do not include Claude's order work.

Remaining separate work: reviewed PR34/35 release, source-verifiable legacy Journal alt/price prose, P9limits and SEC-DEP01/02 patching. Owner's reported successful testing is recorded without converting untested gates into pass claims.

## Review source follow-up

Owner supplied eight Google Maps screenshots after the initial implementation. Six complete attributed five-star reviews now render in a responsive grid. [Provenance, changed files, fresh checks and limitations](reviews.md) supersede the initial empty-review source prerequisite; production authorization remains pending. Earlier full-suite/browser numbers above are dated initial implementation evidence, not newly rerun tests for this content follow-up.

## Approved production release follow-up

Owner explicitly approved PR34–37; final mainfb7ed28 CI/build/deploy and live About/location/Journal/API/runtime checks passed. [Release proof](../about-release/README.md) supersedes initial pending-release statements above; physical Safari and unrelated acceptance limits remain explicit.
