# P7-14 — Public footer refinement

Implemented 2026-10-07. Owner subsequently explicitly authorizes production deployment (“deploy gas”) after the review outcome and iPhone limitation were reported. Footer patch isolated onto main; cart/skeleton dependencies are excluded. Release verification pending.

## Scope and design

Reference: [shadcnblockscom Footer 7](https://21st.dev/@shadcnblockscom/components/footer-7), retrieved through the connected 21st MCP (demo2223) and its rendered preview. Adapted its brand / grouped navigation / divided copyright hierarchy to existing Blade and Qammaris charcoal/cream/gold tokens. No React/shadcn package or reference image copied.

Desktop uses a brand block followed by Jelajahi, Koleksi and Bantuan & belanja. Mobile places navigation in two columns and shopping links underneath. Link targets are at least44px high; long labels wrap, absent categories/channels are omitted. Existing routes, category IDs, channel configuration and social artwork remain. Keyboard focus stays visible; mouse-only hover and touch feedback change color without moving or resizing links. No placeholder privacy/terms routes.

Files: shared `resources/views/components/footer.blade.php`, scoped `resources/css/footer.css`, its import in `resources/css/app.css`, the existing catalog-media assertion in `tests/Feature/PublicCatalogHardeningTest.php`, BACKLOG and this evidence directory. No backend, API, database, product data, media, credential, environment or dependency changes.

## Checks actually run

- Focused public/catalog/cart checks:39 tests,290 assertions passed.
- Initial full Laravel run:287 passed,1 failed because an existing whole-page lazy-image count now included the footer logo. Scoped this test to actual `catalog-media__image` tags, preserving the product loading requirement. Focused regression:3 tests,34 assertions passed. Final full suite:288 tests,2001 assertions passed.
- Node suite:21 tests passed.
- Vite production build and `pint --test --dirty`:passed. Existing DaisyUI property / large lanyard-bundle warnings remain unrelated.
- Git whitespace check passed.

## Browser evidence

Real Chrome at320×740,375×812,390×844,768×1024 and1440×900. Document widths stayed within the viewport (305/360/375/753/1425px respectively). Default footer measured about1064px before and757px after at390px, about405px after at1440px. Footer links measured at least44px high. Actual native category click opened `/products?category=2`; Keranjang and Tentang Qammaris opened their correct routes. The shared navigation loader cleared. Keyboard Tab showed a visible focus outline. No console errors observed in this exercised flow.

Synthetic view-only optional-state checks covered long category labels, extra marketplace/social channels and empty categories. No external shopping/WhatsApp action or message sent. Synthetic channel links were example URLs for layout checking only; actual default category IDs were used to verify routing.

| Evidence | State |
| --- | --- |
| [Before desktop](before-desktop.jpg) | Old footer,1440px |
| [After desktop](after-desktop.jpg) | New footer,1440px |
| [Before mobile](before-mobile.jpg) | Old footer lower section,390px; height exceeded viewport |
| [After mobile](after-mobile.jpg) | New shared footer lower section on cart page,390px |
| [Keyboard focus](keyboard-focus-mobile.jpg) | Complete new footer with visible gold keyboard outline,390px |
| [Long names / channels](long-names-and-channels-320.jpg) | Synthetic long label and optional channels,320px |
| [Tablet channels](channels-tablet.jpg) | Three navigation groups,768px |
| [Empty collections](empty-collections-mobile.jpg) | Category section absent,390px |

Screenshots are viewport captures and include the existing sticky header; the gold outline in keyboard/optional-state captures is intentional keyboard focus, not a persistent touch hover.

## Limits, cleanup and rollback

Actual iPhone Safari/touch, screen-reader and reduced-motion device run:Not confirmed. CSS contains reduced-motion support and hover capability guards; viewport checking does not substitute for those device checks. Production appearance is not claimed.

Used an isolated local copy of the catalog (445 products,350 published) and read-only existing preview media. Owned PHP preview process and disposable `p7-14-preview` router/database/session files removed after verification; original preview data/media retained. Browser viewport restored and owned tabs closed. No production resources touched.

Rollback:revert the footer commit and rebuild assets through the normal deployment path. No migration/data restore required. Next backlog action is review/device verification and separately approved release, not a new implementation phase.
