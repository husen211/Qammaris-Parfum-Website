# P7-12 — direct cart navigation and add feedback

2026-10-07. Existing PR10 follow-up, in review; no production release.

## Result and rationale

Clicking the header basket opens `/cart` directly with the product list. The extra drawer, its markup and loader are removed. Adding an available product stays on detail: immediate loading/disabled CTA, then confirmed added text and a 650ms image flight into the basket. Failed server mutations cannot animate success or update the count. The React header consumes the confirmed count, including browser-history refresh; it no longer depends on a missing DOM badge ID. Quantity is bounded 1–99 with 44px controls, tabular numbers, subtle digit motion and pending locks. Cart update failure rolls back the displayed quantity and enables retry; successful updates reload current totals. Checkout, CSRF, source availability, price rules, URLs and session shape remain unchanged.

Active UI surface: product purchase action, header basket and cart list, following `skills/qammaris-ui-review/SKILL.md`. Charcoal/cream tokens, contained photography and current catalog layout retained. No hover movement/scale, no entrance stagger and no touchstart/pointerdown navigation or hold-repeat. Reduced motion uses immediate textual success without flight/digit motion. No new dependencies or full-page React rewrite.

## Requested references and attribution

All four component sources were retrieved through the connected 21st MCP; they are adapted to existing Laravel/Blade/vanilla JavaScript, not installed:

- [motiondotdev / motion-add-to-basket](https://21st.dev/community/components/s/cart?preview=%2F%40motiondotdev%2Fcomponents%2Fmotion-add-to-basket): quadratic flight, shrinking image and short basket arrival feedback. Preserve the original product image; fly a copy. On mobile, when the main photo is offscreen, the copy starts beside the tapped button. No hover scale or disruptive basket bounce.
- [bidyut10 / add-to-cart-button](https://21st.dev/community/components/s/cart?preview=%2F%40bidyut10%2Fcomponents%2Fadd-to-cart-button): stationary tactile shadow and idle/loading/added states. Replace the demo's fake success timer with the real server response; only reset the already-confirmed success after 1.6 seconds.
- [arihantcodes_1f7b8c4d / quantity-stepper](https://21st.dev/community/components/s/cart?preview=%2F%40arihantcodes_1f7b8c4d%2Fcomponents%2Fquantity-stepper): bounded decrement/value/increment composition, digit feedback and disabled limits. Click/keyboard activation instead of pointer-down repetition to preserve mobile scrolling.
- [beratberkayg / product-card-1](https://21st.dev/community/components/s/cart?preview=%2F%40beratberkayg%2Fcomponents%2Fproduct-card-1): readable product/photo/size/current-price/action hierarchy in the existing cart rows; no invented ratings, shipping claims, wishlists or catalog redesign.

New bag SVG retrieved from the Lucide family on [21st Icons](https://21st.dev/community/icons/lucide). No icon package added. Full Lucide/Feather license and attribution retained in `docs/licenses/lucide.txt`.

## Checks actually run

- Before editing: native detail Add opened the drawer; no header count update. Existing source and actual browser inspected.
- Focused Laravel: 12 tests, 126 assertions pass.
- Full Laravel: 288 tests, 2001 assertions pass, including price/review/availability/recipient checkout guards.
- Node: 17 tests pass. New cases cover real response/CSRF/quantity, rejected/expired/offline responses, duplicate-click lock/retry, quadratic endpoint/arc/fade, and reduced-motion no-animation behavior.
- Vite build and scoped Pint pass; whitespace check passes. Existing DaisyUI `@property` optimization warning and unrelated large lanyard bundle warning remain.
- Chrome with actual viewport widths 320, 390, 768 and 1440; no horizontal overflow on detail/cart, 56px add CTA and 44px quantity controls. These are viewport/mouse/keyboard checks, not genuine touch emulation.
- Native add: confirmed added phase and one active flight overlay; count 1→2. Later add quantity 2: count 3→5. Overlay removed and button returned to idle. Native cart link opened `/cart` with no dialog.
- Cart quantity 2→3: subtotal 538,000→807,000. Local harness forced one 503 update: quantity rolled back to 3, retry enabled; removing the failure flag allowed 3→4. No production service or database used for fault injection.
- Local fixture offer temporarily set to zero after detail was loaded: add returned 422, no flight, unchanged count, idle retry/error state. Restored original price in the owned copy.
- Back to a restored detail page after cart decrement: header refreshed to current count and button idle. Long SAFF & CO product name, keyboard Enter Add, min disabled, remove-to-empty and checkout link/required recipient form verified.

## Evidence

Screenshots are static states; transient flight/success behavior was verified through native AX and live DOM/animation presence rather than asserting a cached screenshot captured the 650ms flight.

| State | Evidence |
| --- | --- |
| Before detail, desktop | [before](before-detail-desktop.jpg) |
| Earlier pre-change cart baseline, mobile | [before](../before-cart-mobile.png) |
| Detail after, 320 / 390 / 768 / 1440 | [320](after-detail-320.jpg), [390](after-detail-390.jpg), [768](after-detail-768.jpg), [1440](after-detail-1440.jpg) |
| Cart after, 320 / 390 / 768 / 1440 | [320](after-cart-320.jpg), [390](after-cart-390.jpg), [768](after-cart-768.jpg), [1440](after-cart-1440.jpg) |
| Long name, mobile | [cart](after-cart-long-name-mobile.jpg) |
| Two-item final desktop / transient DOM observation | [cart](after-cart-desktop-final.jpg), [confirmed added/flight](live-motion-observation.json) |
| Update failure / rejected add / empty | [retry](quantity-retry-desktop.jpg), [rejected](rejected-add-desktop.jpg), [empty](empty-cart-mobile.jpg) |

## Impact, limits and recovery

No schema, production data, media, app API, credentials, deployment or package changes. Only owned temporary fixture/database/session/server used; original local fixture/media untouched. Actual iPhone Safari/touch, reduced-motion OS setting in browser, and real WhatsApp composer/send: **Not confirmed**. Reduced-motion skip is verified in JavaScript tests. No new stock reservation, payment or persistent order model. Network acknowledgement cannot itself establish order acceptance; existing checkout rereads current catalog.

Rollback: revert this UI follow-up and rebuild assets/views; existing sessions/data remain compatible. Updated documents: business rules, architecture, ADR-026, backlog and this report. Recommended next action for P7-12: Owner review plus genuine touch/WhatsApp-device verification, then a separately approved production release. No next phase started.
