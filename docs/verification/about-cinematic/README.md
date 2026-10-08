# ABOUT-02 — Cinematic About and unique photography

2026-10-08. Owner explicitly requested another About redesign, the actual jahed source and at least six additional21st components. Implementation and bounded local verification complete; Owner subsequently explicitly approved publication of PR43. Production release and live checks complete: [release proof](RELEASE.md). PR41 approval applied only to the earlier FAQ/copy correction.

Outcome: ten new source-backed adaptations; full [source/behavior/photo mapping](sources.md), [retrieval hashes](component-sources.json). Hero/source depth transitions bounded to480px pin on sufficiently tall/wide desktop; mobile/tablet/small-height/reduced-motion use the fully readable static layout. Native actions remain available. GSAP is requested only when desktop motion qualifies, not on mobile/reduced motion. About JS6.08kB/2.32kBgzip; desktop-motion114.46kB/45.40kBgzip; About CSS16.96kB/4.06kBgzip. These are build sizes, not a measured network/LCP guarantee.

Seven unique visible store/build image compositions, each appears once. Both tester posters leave visible About; all original/derivative files retained. No data/schema/product/order/blog/API/credential changes. Existing dates/reviews/hours/SEO/URL and FAQ400ms animation retained. Code-only rollback to prior release; no DB/media rollback required.

## Checks actually run

- StoreAboutTest:3passed/82assertions. Existing cases updated for four curated gallery photos and one occurrence per composition; poster omission, opt-in iframe, real source content, hours, schema/canonical and escaped stored address pass. Lower assertion count reflects fewer repeated gallery derivatives, not removed cases.
- Vite build passed. Existing DaisyUI@property and unrelated3D-chunk warnings remain. Changed PHP Pint, Blade view:cache/view:clear and git whitespace checks passed. No new dependency or lockfile change.
- Real installed Chrome320/390 genuine touch,768tablet-width and1440desktop. All changed filter/lightbox/video/native-navigation/FAQ flows passed; no overflow/page errors. One tap opens the actual catalog route, no customer/order submission. [Results](results.json).
- Filtered lightbox1/1 disables both directions; Rancangan3navigates only its photos, keyboard arrows/Escape/focus restoration pass. Filter clears expanded layout. Photos contain their full composition.
- Video: no iframe before action; exact allowed Instagram URL, portrait dialog, no autoplay grant, Escape/button close removes iframe immediately; reopening passes. External playback was intercepted with an explicit local transport fixture; actual Instagram availability/playback is not claimed.
- FAQ retains actual400ms scripted height/opacity. Reduced-motion and390no-JS native open with no scripted panel animation. Native CSS color feedback is not misreported as a panel animation.
- Desktop hero transform changes expansion/pullback and only one480px pin. Desktop→mobile→desktop and runtime reduced-motion remove/recreate/revert motion correctly; no mobile motion chunk request. A negative-depth photo occlusion and queued video-close race were found and corrected before final checks.
- Local preview is a fresh synthetic SQLite database with no account or real data. Own temporary environment/database/server removed after verification. Physical iPhoneSafari, live redesigned page, indexing and external Reel playback remain unconfirmed.

## Visual review

Before screenshots read-only production26576a1; after screenshots isolated local implementation. Visually inspected mobile/desktop hero, store steps, gallery, Reel and reviews.

[Before hero390](before-hero-390.png) · [after hero390](after-hero-390.png) · [before hero1440](before-hero-1440.png) · [cinematic card1440](after-cinematic-240-1440.png) · [motion video](cinematic-desktop.webm)

[Gallery390](after-gallery-390.png) · [experience1440](after-pengalaman-1440.png) · [Reel390](after-reel-390.png) · [reviews1440](after-ulasan-1440.png) · [FAQ390](after-faq-390.png)

The targeted browser run initially asserted all animations were absent in no-JS mode; native CSS color feedback legitimately remains. Corrected the assertion to absence of scripted panel animation and completed only remaining fallback/responsive checks, preserving the passed four-width results. No unnecessary full local suite repetition.

Owner subsequently approved PR43, normal GitHub release and live checks passed; [proof](RELEASE.md). Next: Owner visual review of the released About. No next implementation phase, order work or global acceptance closure.
