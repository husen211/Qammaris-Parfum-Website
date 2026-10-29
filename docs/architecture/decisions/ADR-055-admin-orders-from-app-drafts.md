# ADR-055 — Admin orders from App drafts; late App price fills the draft offer

Status: accepted (ORD-12, Owner 2026-10-29, card #134). Stage 1 of the Owner's product simplification.

## Context

The Owner's product flow starts in Qammaris App: a new product is added while OTW, received without a price, priced in Majoo, and the Majoo export imported back into the App. The Website creates a draft for every visible App product, but:

- Admin "Buat pesanan" only searched published products (ORD-01 rule). A product already in stock and priced in the App, but without a photo on the Website (example: BSP Black Edition 50ml, Rp 189.000), could not be ordered; the Owner had to complete and publish it first.
- A draft created while the App product had no price got no offer. When the price arrived later, `SyncQammarisAppPrice` only flagged `review_missing_offer`, so the draft stayed unorderable and showed under "Periksa harga". Production on 2026-10-29: 47 of the 58 "Periksa harga" rows were exactly this (example: Inception 100ml, Rp 425.000).

## Decision

- **Admin orders accept App drafts.** `Product::scopeOrderableByAdmin()` = published + active, or draft linked to a `qammaris_app` identity; never hidden in the App, never archived, never a manual draft without an App link. Used by the admin product search and `CreateOnlineOrder::handle()`. The offer still needs a positive price; the price is the App price and becomes the order snapshot as before. Ordering never publishes the draft.
- **Customers are unchanged.** Catalog, cart, website checkout, product links (WAIT-02) and product pages keep `published()`.
- **Search names what is missing.** App drafts that match but have no priced offer are returned as `incomplete` with "Ukuran belum diisi" or "Harga belum ada di aplikasi" and, for users with `catalog.manage`, a link to the editor.
- **Late App price fills the offer automatically.** `FillAppDraftOffer::fill()` runs from `SyncQammarisAppPrice` when a draft with no offer at all gets a valid App price and its size is written in the App name (or the Website name) by the existing `ImportedProductName::size()` rule. It creates the single offer through `SyncSingleOffer`; published, archived, hidden products and drafts that already have any offer are not touched. Names without a size keep the existing review state.
- **Drafts already waiting are filled once by the Owner.** The feed only replays newer revisions, so earlier ones never re-run. The App products page lists them (size + App price) with one button; `applyWaiting()` re-checks each previewed draft under the snapshot→product lock order and audits it as the Owner (`product_updated`). No automatic bulk replay.

## Consequences

- A customer link or App order can name a product that has no public page yet; the customer link shows the placeholder image and never links to the product page.
- Size parsing stays conservative (integer ml, one size, no set/bundle/refill); "90 M", testers and names without ml still need a size typed in the editor.
- The admin search can show up to 5 incomplete drafts below the orderable results.
