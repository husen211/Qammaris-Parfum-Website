# P7-12 — Cart and WhatsApp recipient checkout

Implemented for review, not deployed. Branch `codex/whatsapp-order-checkout` starts from main `7b86b00`; prior catalog/import/bestseller PRs are not included.

## Outcome and UX rationale

Available product → cart → required recipient form → WhatsApp order composer. Public terminology now uses Keranjang, Checkout and Pesanan. Available products no longer bypass recipient information with a stock-question link. Sold-out/OTW retain restock contact; unknown retains neutral availability contact. Cart items show current price/size/status with readable mobile quantity controls. Checkout uses 16px fields, visible labels/errors, native required checks, optional postcode/note and a separate item/subtotal summary. Shipping/payment continue in WhatsApp. No fake delivery/payment success is shown.

At GET/POST checkout, current published active offers are resolved from the database; positive price and available status are required. A session review fingerprint catches changed prices, quantities and product details. Price-change retry retains validated recipient input. Existing session price/name/image snapshots cannot override the database. WhatsApp redirect retains the cart for retries; pending feedback resets on history return, with a 15-second retry fallback if navigation does not complete.

## Files and impact

- `CartController`, `Cart/CheckoutRequest`, `InquiryWhatsApp`: current-data/order guard, bounded normalized recipient input and order message. Existing helper class name remains internal, avoiding unrelated caller churn.
- `routes/web.php`: GET `/cart/checkout` added; existing cart routes and web CSRF retained.
- `views/cart/index`, new `views/cart/checkout`, cart drawer Blade/JS, detail purchase action and CardNav cart label: related public flow only.
- Six existing feature test files updated for the explicitly approved semantics; checkout integrity coverage expanded.
- BUSINESS_RULES, MASTER_PLAN, ARCHITECTURE, BACKLOG and ADR-026 document the override and future payment plan.

No migration, package, catalog/price/stock/API/media/identity/URL mutation or permanent customer/order storage. No real customer data or message sent. Failed input may reside temporarily in Laravel session old input for correction. Checkout GET/WhatsApp redirect are private/no-store and no-referrer. Existing `StoreInfo` accessor always falls back to the canonical Qammaris WhatsApp number: invalid-contact guard was verified on the URL builder, not claimed as a currently reachable browser state.

## Automated checks actually run

- Focused 35 Laravel tests / 319 assertions passed.
- Full suite: 281 tests / 1906 assertions passed.
- After final validation-copy/retry changes: focused 14 tests / 145 assertions passed.
- 12 Node regression checks passed; Vite build passed. Existing DaisyUI `@property` and large about-lanyard chunk warnings remain.
- Scoped Pint and `git diff --check` passed.

Coverage includes missing/malformed/oversized recipient fields, untrusted escaped old input, phone normalization, invalid destination, stale price/quantity/review, empty/malformed cart, unpublished/inactive/unknown/sold-out product, zero price, current-data authority, status/OTW presentation, retained cart and no customer persistence on successful redirect.

## Browser evidence

Actual Chrome browser; viewport resizing with mouse/keyboard, not genuine touch emulation. Isolated local copy: 445 products, 350 published, zero users; original fixture/media read-only. Native detail Add created the cart; quantity 1→2 updated subtotal 158,000→316,000. Drawer loaded current data, Escape closed/restored cart-trigger focus. Native blank submit focused name; invalid phone server response preserved synthetic name/address and showed linked Indonesian validation error. Keyboard Tab advanced to address. Valid native submission produced a real application WhatsApp redirect, intercepted by the private preview router before external navigation. No request/message containing recipient data went to WhatsApp.

Current-review price change in the owned local fixture (158,000→160,000) blocked submission and rendered fresh 320,000 subtotal with retained synthetic input. Subsequent source-status change to Habis blocked ordering and returned to a disabled cart. Remove action reached empty state. No captured browser console errors/warnings.

| Surface | Viewport | Evidence |
| --- | --- | --- |
| Before cart layout | 1440×900 / 390×844 | [desktop](before-cart-desktop.png), [mobile](before-cart-mobile.png) |
| After cart | 1440×900 / 390×844 / 320×844 | [desktop](after-cart-desktop.png), [mobile](after-cart-mobile.png), [320](cart-320.png) |
| Checkout | 1440×900 / 390×844 / 768×900 | [desktop](after-checkout-desktop.png), [mobile](after-checkout-mobile.png), [tablet](checkout-tablet.png) |
| Validation | 1440×900 / 320×844 | [desktop](checkout-validation-desktop.png), [320](checkout-320-validation.png) |
| Drawer / price change | 320×844 | [drawer](cart-drawer-320.png), [changed price](checkout-price-change-320.png) |
| Habis / empty / redirect interception | desktop / mobile / tablet | [Habis](cart-sold-out-desktop.png), [empty](cart-empty-mobile.png), [local redirect](local-whatsapp-redirect-held.png) |

Measured cart/checkout widths never exceeded viewport at 320, 390, 768 and 1440. Input text is 16px. Before screenshots capture the prior cart layout before view edits; the footer notice had already changed during backend preparation. The first server harness configured its database after provider boot and caused local delays; moving its configuration before provider boot fixed the harness. This is not a production performance fix or measurement.

## Limits, release and recovery

- Actual WhatsApp composer/device handoff and message sending: **Not confirmed**. Application redirect/message payload verified by HTTP tests plus browser with local interception.
- Actual iPhone Safari/touch: **Not confirmed**, still requires pre-release verification. No release waiver inferred from earlier unrelated PRs.
- Numeric inventory/reservation, delivery fee calculation, payment and accepted-order history are not implemented. Boolean source availability cannot reserve a quantity. Future gateway steps are in ADR-026, not started.
- Large multi-product WhatsApp URLs have device/platform length limits; a many-item real-device composer check is still needed before release. Infrastructure proxy/APM body/header retention is **Not confirmed**; no application request-body logging was added.
- Existing source-unknown products remain neutral; they are not treated as available merely to enable ordering. Removed/unpublished offer corruption retains the existing whole-list review behavior.
- Local preview server/database/sessions/router were removed after verification; no existing local fixture/media/account was deleted.

Rollback: revert this bounded code change and rebuild cached views/assets; no schema/data rollback needed. Existing cart session structure is unchanged. Recommended next item: Owner review of this checkout, real-device WhatsApp/touch verification and separately approved release. Do not begin the gateway or other backlog phase automatically.
