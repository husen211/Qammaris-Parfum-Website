# Homepage best sellers: hide price — 2026-10-07

Owner requests no price in homepage Produk Terlaris for now. The shared card accepts an optional `showPrice` flag, defaulting to true; only the homepage passes false. This keeps photos, names, size, availability, links and daily selection unchanged. Catalog and detail prices remain visible. No CSS redesign or new dependency.

Files: `resources/views/home/sections/best-seller.blade.php`, `resources/views/products/_catalog-card.blade.php`, existing `tests/Feature/HomeBestSellersTest.php` price expectation, BUSINESS_RULES and BACKLOG.

Checks actually run: HomeBestSellersTest and PublicCatalogCardTrustTest, 8 passing tests / 94 assertions; scoped Pint and git diff --check pass; Vite build passes with existing DaisyUI @property and large lanyard chunk warnings.

Chrome desktop 1440×900 and mobile viewport 390×844: homepage 6 cards, zero price elements, retained sizes/status and no horizontal document overflow. Native homepage card click opens the matching detail, showing Rp 442.000. Native breadcrumb click opens catalog, which retains 24 prices for 24 cards. This is mouse/viewport verification; actual iPhone Safari touch is Not confirmed.

Before/after screenshots: [desktop before](before-desktop.png), [desktop after](after-desktop.png), [mobile before](before-mobile.png), [mobile after](after-mobile.png). Preview used a disposable copied historical 350-public-product catalog with local-only best-seller flags; it is not evidence of current production merchandising. No production data, database, media, stock, API or environment change. Original local fixture/media retained; owned preview removed after verification.

Change remains in PR8, not deployed. Rollback: revert this follow-up commit; no migration or media recovery. Next recommended action: Owner review/release of PR8. No next backlog item started.
