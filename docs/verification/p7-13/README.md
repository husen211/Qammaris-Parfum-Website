# P7-13 — Destination skeleton navigation

Owner requested immediate destination-shaped feedback on page changes. Clicking a native link or submitting a valid native form now hides/inerts the old content and shows the target shape immediately; the browser still performs the original document request. There is no added navigation delay, HTML fetch/swap, forged URL/history, or SPA. This improves feedback, not server response time.

## Scope and files

- `resources/js/ui/navigation-skeleton-state.js`: same-origin HTML route classification and exclusions.
- `resources/js/ui/navigation-skeleton.js`: click/submit/reload feedback, live status, recovery, history reset.
- `resources/views/components/navigation-skeleton.blade.php` and `resources/css/navigation-skeleton.css`: static inert templates for public catalog, detail, cart, checkout, home, journal/article, quiz/result, login, and admin list/form/dashboard. Shapes contain no product/customer data. Uniform opacity pulse becomes static under reduced motion.
- Public/admin/login layouts opt in by marking existing content; the public navbar and admin navigation remain during pending requests. The real destination document still recreates its own layout.
- `resources/js/reactbits/card-nav/CardNav.jsx`: close expanded menu on actual navigation feedback.
- `resources/js/ui/navbar.js`: remove duplicate pending listener; existing scroll styling retained.
- `resources/js/ui/cart-page.js`: explicit successful reload event; mutations retain their existing feedback.
- `resources/views/admin/products/index.blade.php`: four GET filter controls use requestSubmit so valid native submissions emit the submit event.
- `resources/js/app.js` / `resources/css/app.css`: load the shared enhancement.
- `tests/js/navigation-skeleton-state.test.mjs`: regression coverage for destination types and native-navigation exclusions.
- Backlog, business rules and architecture updated for this UI decision.

## Results

- Full Laravel suite: **288 tests / 2001 assertions passed**.
- Final targeted catalog/detail/checkout/admin-context suite after adding journal/quiz classification: **29 tests / 244 assertions passed**.
- Node suite: **21 tests passed**, including four navigation boundary regressions.
- Vite production build, scoped dirty Pint and git whitespace check passed. Existing DaisyUI `@property` and large about/lanyard bundle warnings remain; unrelated to this task.
- Real Chrome browser: 320x740, 375x812, 390x844, 768x1024 and 1440x900. Destination overlays measured without horizontal overflow. Local read-only catalog source has 445 products / 350 published, normal pagination.
- Actual successful document navigation: catalog -> detail -> in-site Kembali, browser Back, cart, checkout, synthetic local admin login, admin list and publication filter. Catalog page=2 returns to the selected Fermo Frag Emily card at scrollY=952, with focus restored, no pending marker and hidden loader.
- Search submits the native search query and receives real results; mobile filter submission closes its dialog before showing the catalog skeleton. A 375px mobile menu click closes the React menu immediately (aria-expanded=false) and shows the destination catalog. Native keyboard Enter opens the cart link. Add-to-cart Ajax retains button feedback and does not invoke the page skeleton.
- Invalid login and empty checkout submission leave the normal form visible without skeleton. No recipient data or WhatsApp message was sent.
- Held navigation: after 12 seconds, recovery button appears. Clicking Kembali restores visible source content, original busy/inert attributes, focus and scroll. It does not automatically retry a POST.
- No browser console errors observed in the checked flow.

## Screenshot method and evidence

Before screenshots were taken before application edits. The owned local fixture also simulated an 8-second response delay. Browser tooling could not reliably capture intermediate frames while that response was pending; those stale captures were discarded. For deterministic screenshots and recovery tests, the fixture temporarily returned HTTP204 for one selected HTML destination, leaving the actual click/submit handler pending. No browser DOM was edited to create the skeleton. Real successful HTTP navigation and history were tested separately with the fixture override removed.

| Evidence | Viewport |
| --- | --- |
| [Before catalog desktop](before-desktop.jpg) / [mobile](before-mobile.jpg) | 1440x900 / 390x844 |
| [Catalog loading desktop](catalog-loading-desktop.jpg) / [mobile](catalog-loading-mobile.jpg) | 1440x900 / 390x844 |
| [Detail loading desktop](product-loading-desktop.jpg) / [mobile](product-loading-mobile.jpg) | 1440x900 / 390x844 |
| [Detail narrow](product-loading-320.jpg) / [tablet](product-loading-768.jpg) | 320x740 / 768x1024 |
| [Cart loading](cart-loading-mobile.jpg) / [checkout loading](checkout-loading-mobile.jpg) | 390x844 |
| [Admin list desktop](admin-list-loading-desktop.jpg) / [mobile](admin-list-loading-mobile.jpg) | 1440x900 / 390x844 |
| [Admin form loading](admin-form-loading-desktop.jpg) | 1440x900 |
| [Slow-navigation recovery](slow-recovery-mobile.jpg) | 375x812 |

## Limits, safety and release

No schema, existing catalog, price, availability, API, media, credentials, production deployment or package changes. Preview used an owned SQLite copy and synthetic local account/session/cart only; temporary account, server and fixture are removed after verification. Existing source fixture/media retained.

Actual iPhone Safari/touch emulation and screen-reader announcement behavior: **Not confirmed**. The current browser only provides viewport/mouse/keyboard testing. Reduced-motion support is implemented in CSS; a device reduced-motion session was not exercised. Native browser Back with a cached document may return instantly without a skeleton; pageshow clears any saved loader. First direct page loads and browser-controlled reload/error screens are native, before this enhancement can execute. When a POST has reached the server, cancelling browser loading cannot undo its business mutation; recovery never resubmits it automatically.

P7-13 depends on PR10's direct cart and recipient-checkout UI. Review this as a separate dependent PR. No production merge/deployment performed. Recommended next item is Owner review, actual touch/device verification and separately authorized release of the dependent UI changes, not a new feature.

Rollback: revert the P7-13 commit and rebuild frontend assets; no migration or data/media restore is required.
