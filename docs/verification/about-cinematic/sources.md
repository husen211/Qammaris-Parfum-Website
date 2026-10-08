# Actual21st source retrieval and ports

Owner requested jahed CinematicHero, then at least six more components beyond the first four. **Ten new components are retrieved and used**: the original four plus six additions below. Existing FAQ/rating/timeline/fluid-grid/SVG components are retained and excluded from this ten count. VideoPlayer613 was evaluated then rejected because its local-video playback controls cannot control an Instagram embed; it is not counted.

|#|Source|Demo ID|Where used|
|---|---|---|---|
|1|[Cinematic landing Hero](https://21st.dev/@jahed/components/cinematic-landing-hero)|11494|Cinematic hero|
|2|[Feature Section](https://21st.dev/@ravikatiyar162/components/feature-section-1)|8706|Experience section|
|3|[Gallery Grid with Lightbox](https://21st.dev/@moumensoliman/components/gallery-grid-block-shadcnui)|10596|Gallery filters and lightbox|
|4|[Scroll Spy](https://21st.dev/@ddoemonn/components/scroll-spy)|23554|Section navigation|
|5|[Scroll Progress](https://21st.dev/@cnippet-dev/components/scroll-progress)|18715|Page progress|
|6|[Steps](https://21st.dev/@anubra266/components/steps)|6087|Trying perfume steps|
|7|[testimonial](https://21st.dev/@uilayout.contact/components/testimonial)|6268|Review composition|
|8|[Call to Action](https://21st.dev/@felipemenezes098/components/cta-01)|18475|Catalog CTA|
|9|[Button](https://21st.dev/@originui/components/button)|143|About buttons|
|10|[Hero Video Dialog](https://21st.dev/@dillionverma/components/hero-video-dialog)|1107|Portrait Reel dialog|

Each full source and usage demo was read through the authenticated paid MCP, not inferred from a preview. [Receipt](component-sources.json) records original source/demo hashes and implemented file/behavior. Jahed source matches the Owner-pasted cinematic-hero.tsx. Retain source authors/links; no claim of a particular licence absent from the retrieved bundle. Do not replace the authorised source access with a generated lookalike.

These are deliberate Blade/vanilla ports of the retrieved structure and behavior for the existing Laravel site. They do not install10React islands or migrate the site to React. Existing GSAP handles the cinematic source. Native dialogs, CSS and requestAnimationFrame handle the rest; no package/lockfile change. Component-specific changes:

**Cinematic landing Hero — 11494:** GSAP scroll timeline, depth/photo entrance, expansion then pullback; bounded480px desktop pin. Real store image instead of a demo phone/accountability app.

**Feature Section — 8706:** Centered title/subtitle, icon-or-number plus feature copy grid, final helpful contact CTA; three real store services.

**Gallery Grid with Lightbox — 10596:** Data-derived categories, aria-pressed filter buttons, selected image dialog and previous/next. Navigation is corrected to respect the active filter.

**Scroll Spy — 23554:** rAF measurement with page-progress-dependent check line, selected location and sliding indicator; native fragment/history preserved.

**Scroll Progress — 18715:** ScrollY/document range drives an origin-left scaleX progress rail. Updates share the nav rAF; no inaccurate reading-time claims.

**Steps — 6087:** Numbered circular indicators, connecting separators and ordered steps; static visit guidance, not a misleading interactive form.

**testimonial — 6268:** Three columns of stacked quote/identity cards, contrasting middle column and alternating outer accent cards. Six verified existing Google reviews; no demo names/photos or delayed reveal.

**Call to Action — 18475:** Centered balanced title/description and real catalog link inside a contained section; CSS text-wrap replaces the additional balancing dependency.

**Button — 143:** Rounded button variants, color feedback, focus-visible and disabled states, nonshrinking/decorative SVG. Native anchors,48px targets and guarded hover.

**Hero Video Dialog — 1107:** Actual thumbnail, centered play control, open/close modal and iframe. Native dialog supplies focus/keyboard; iframe unmounts on close and supports reopening. Portrait layout and explicit Instagram opt-in.

All mockup metrics, stock/demo photos, blue gradients, application downloads, infinite/staggered reveals and hover movement are excluded. Colors are Qammaris cream/charcoal/gold; visible content/controls are immediately available. Existing21st Lucide SVGs remain, not platform emoji. Named sections and buttons have real purposes rather than new empty decorative sections.

## Unique photo placement

- shelves: cinematic hero only.
- visitors: actual interior atmosphere / Reel thumbnail only.
- facade: visit/address only.
- construction/design-board/shelf-plan/floor-plan: construction/design gallery only.
- paper-test/skin-test: removed from visible About. Originals and derivatives remain in the repository/server; no file deletion.
- Timeline: four text steps; no current-store image presented as dated2024 photography and no image repeated elsewhere.

Gallery has four full compositions and filters Semua/Pembangunan/Rancangan. Images use contain, no zoom; mobile uses one column and full-photo dialog. No historical date/copy/review/rating/hour/address/URL or backend rule is invented or changed.
