# UX-02 — catalog, quiz, navbar and homepage refinements (2026-10-12)

- Branch: `modernization/ux-02-catalog-home`, built from `main` `c12b1be`.
- Checked in Chrome at 390 and 1440. The local instance used synthetic data (40 products, 16 best sellers, Rp180–780 ribu). The "before" screenshots come from production.

| # | Change | Evidence |
|---|---|---|
| 1A | Price fields format thousands while typing (`200.000`, `1.000.000`) and keep the caret position. The submitted URL stays `price_min=200000`. The server also accepts `200.000` and `Rp 1.000.000`, for example without JS. | `tests/js/price-input.test.mjs`, `PublicCatalogDiscoveryTest::test_price_filter_accepts_thousands_separators`. Chrome: typed values shown formatted, URL `?price_min=200000&price_max=1000000` |
| 1B | Price filter stacked: Harga minimum above Harga maksimum, wider fields with an `Rp` prefix, 16px text on touch screens (no iOS zoom), more spacing | [before 390](before-price-dropdown-390.png), [after 390](after-price-dropdown-390.png), [after 1440](after-price-dropdown-desktop-1440.png) |
| 2 | Quiz budget: "Rp1 juta ke atas" removed; the highest choice is Rp500 ribu–1 juta. Other choices, custom amount and "tidak dibatasi" unchanged | `FragrancePreferenceHttpTest`, [before](before-quiz-budget-390.png) / [after](after-quiz-budget-390.png) |
| 3 | Pesanan Saya icon: Lucide `clipboard-list` (order list) instead of the receipt with a dollar sign; distinct from the cart bag | [before](before-navbar-390.png) / [after](after-navbar-390.png) |
| 4 | Default "Terlaris" order: best sellers come first, as before, but in a daily price-varied order instead of newest first (`App\Support\BestSellerArrangement`) | `BestSellerArrangementTest`, `PublicCatalogDiscoveryTest::test_default_best_sellers_put_affordable_picks_first_without_sorting_by_price`. Chrome first 12: 275, 289, 229, 620, 215, 299, 189, 345, 265, 450, 245, 375 ribu (production before: 424–669 ribu) |
| 5 | Distributor Resmi shows every active brand with published products, logos first. The marquee was replaced by a carousel that rests, then moves one brand | `HomeBestSellersTest::test_rail_ends_with…`. Chrome: still at 1.5 s, moved one brand (114 px / 171 px) by 4 s. [before](before-brands-1440.png), [after 1440](after-brands-frame1-1440.png) → [next frame](after-brands-frame2-1440.png), [after 390](after-brands-frame1-390.png) |
| 6 | Produk Terlaris shows 10 products. The rail ends with a "Lihat Katalog Lengkap" card; the button under the section stays | [before](before-bestseller-390.png), [after end 390](after-bestseller-end-390.png), [after end 1440](after-bestseller-end-1440.png) |

How the default order (4) works:
- Every published best seller is split into thirds by its lowest active price (low, mid, high).
- Each third is shuffled per Palu calendar day. The order is stable within the day and different the next day, even when no new product is added.
- The thirds are interleaved low, mid, low, high, low, mid, and so on.
- Products without a price come last; ordinary products follow with their previous order (newest first).
- A search keeps its relevance order, and the other sorts and filters are unchanged.

Logos (5):
- `tools/build_brand_logos.py` turns each supplied logo into one dark mark on transparency, trimmed and sized by visual area. The result is saved as `public/images/brands/<brand slug>.webp`.
- Logos supplied: Afnan, Ahmed Al Maghribi, Armaf, Emper, Frag. World, Khadlaj, Mykonos, Osma, Project1945, Rayhaan, Reef Perfume, Riffs, Zimaya By Afnan.
- Brands on sale without a logo show their name in the previous text style. In production on 2026-10-11 that was 22 brands, including Bali Surfer, French Avenue and Rasasi.
- Adding a logo file named after the slug is enough to show it; no code change is needed.

The carousel never runs when the user prefers reduced motion (the brands wrap into a static list). It also stops while hovered or focused, off screen, or in a hidden tab. Without JS, the row scrolls.

**UX rationale:**
- Readable prices and a stacked range make the filter usable on phones.
- A varied, affordable-first opening avoids the impression that every perfume is expensive, without hiding premium ones or sorting by price.
- A resting carousel reads as calm and deliberate, unlike a constant marquee.
- The end card turns active browsing into a catalog visit.
- No new dependency, font or colour was added.
