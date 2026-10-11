# ABOUT-04 — source-faithful split hero

Owner requested replacing Hero 10 with the supplied HeroSection/hero-section-2 and explicitly matching animation/flow, 2026-10-09. Paid MCP retrieved [ravikatiyar162/hero-section-2](https://21st.dev/@ravikatiyar162/components/hero-section-2), demo 5260/component 3839, matching the attached source and preview. [Receipt](source-receipt.json) records source/demo hashes and motion timing. No token or full request payload. Pasted framework installation/stock-image instructions are reference material, not authorization to rewrite Laravel or replace real photography.

## Source adaptation

Blade structure follows logo/slogan, bold left-aligned title/highlight, 80px divider, subtitle, text CTA, three contact details and right diagonal media panel. Desktop 60/40, tablet 50/50, phone stacked; contacts stack where narrow columns would impair reading. Existing Qammaris logo, Inter, cream/charcoal/gold, real address, WhatsApp and website; one previously selected storefront photograph. The actual 21st component's globe/phone/map-pin SVGs are added to the fixed trusted icon partial; no emoji. The image uses contain/native ratio rather than cover zoom; the intentional source diagonal clip still trims its outside edge. Gallery images unchanged.

Source motion is ported to native Web Animations in about-hero.js, imported only by About: 20px upward/opacity item reveal, 500ms easeOut, 150ms stagger and 200ms delayChildren. Nested container timing preserved: brand 200ms, contacts 500ms, title 550ms, divider 700ms, subtitle 850ms, CTA 1000ms; section/main opacity container fades retained. Photo independently opens from a collapsed right-edge polygon to polygon(25% 0,100% 0,100% 100%,0% 100%) in 1200ms. 121 sampled frames use the mathematical circOut curve, sqrt(1-(t-1)^2), rather than replacing it with a generic slide/fade. Reduced-motion and pagehide cancellation settle to readable static markup; no-JS fully readable. No pin, GSAP/Framer dependency or React migration.

Files: resources/views/store/about.blade.php, resources/css/about.css, resources/js/about.js, new resources/js/about-hero.js, resources/views/components/icon.blade.php, existing StoreAboutTest. No DB/schema/media upload or deletion, credentials, package/lockfile, blog/order changes. All existing image files retained; other About content/FAQ/gallery/reviews/hours/URL unchanged.

## Verification

Existing 3 About tests passed with 96 assertions; changed PHP formatting fixed; Blade compiled/cleared; Vite build and git whitespace checks passed. About bundle 6.80kB / 2.59kB gzip. Existing DaisyUI@property and unrelated 3D large-chunk warnings remain.

Actual Chrome at 320/390x844 genuine touch, 768/1440x900: [results](results.json) verify nine recorded animations, each source duration/delay/from/to, contact SVG content, one store image, contain rendering, no overflow/page errors and single-tap native location CTA. No-JS/reduced-motion have no hero animation and readable content. Settled captures after all animations finish verified no lingering animation/hidden subtitle or overflow. [Before390](before-390.png), [before1440](before-1440.png), [after390](after-390.png), [after1440](after-1440.png), [full mobile composition](full-390.png). Final desktop/full-mobile screenshots visually inspected. Controlled sampled timeline frames [100ms](motion-100.png)/[600ms](motion-600.png)/[1500ms](motion-1500.png) supplement the separate actual [motion recording](hero-motion-1440.webm); sampled frames are intentionally paused test captures. Physical Safari and external Instagram playback remain unconfirmed.

Synthetic local SQLite preview contains no accounts or real records and is removed before commit. Existing shared SVG cases retained. No repetitive full local suite; normal required GitHub CI still applies.

## Release and recovery

Ongoing About correction remains within Owner's prior publication authorization. Concrete PR/main CI/automatic production gates apply; at implementation snapshot this precedes merge, and actual deployment/live results follow in chat. Owner visual review remains open, distinct from code deployment. Code-only rollback to prior main 7e5ac42adff396facc960055f4abd4e065e811f4 retains DB/media/env; no cleanup/migration needed. Current architecture, business rules and backlog updated. Next Owner visual review; no other backlog phase started.
