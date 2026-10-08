# UI-21ST-01 — FAQ, SVG icons, About gallery and timeline

2026-10-08. Owner requested the exact 21st FAQ Chat Accordion and proper icons, then added Fluid Expanding Grid and Growth Story Timeline. Implementation complete; production release requires separate approval. Order work excluded.

## Source and adaptation

| Source | Retrieval | Adaptation |
|---|---|---|
| [anshuman008 / FAQ Chat Accordion](https://21st.dev/@anshuman008/components/faq-chat-accordion) | 21st MCP demo1517, component273, paid successful retrieval | Same question/answer chat bubbles, plus/minus and single collapsible answer. Shared Blade `x-faq`, native `details name`; ivory/gold question, charcoal answer. No sample emojis, fabricated timestamp, React/Radix/Motion installation or delayed answer animation. |
| [0xUrvish / Fluid Expanding Grid](https://21st.dev/@0xUrvish/components/fluid-expanding-grid) | 21st MCP demo10467, component7168 | Explicit click expands one desktop/tablet item; every one of the nine photos remains available. Native image links/lightbox retained. One column and one tap to full photo on phones. `contain` replaces source `cover`/offset/zoom. No source four-item layout truncation or hover resizing. |
| [shadcnspace / Growth Story Timeline](https://21st.dev/@shadcnspace/components/timeline-01) | 21st MCP demo28273, component14660; missing utility read from [first-party registry](https://shadcnspace.com/r/timeline-01.json) linked by 21st | Alternating desktop story/photo rows with a center line; one column and side line on phones. Content always visible, static milestones without source scroll-entry delays or new motion dependency. |
| [21st Lucide icon catalog](https://21st.dev/community/icons/lucide) | Actual rendered SVGs retrieved from catalog search in Chrome | Ten static SVGs, currentColor, fixed viewBox, decorative `aria-hidden`, text/accessible button names retained. [Catalog IDs](21st-icons.json); [Lucide/Feather licence](../../licenses/lucide.txt). |

All three component pages list MIT. Sources/authors credited in adapted Blade files and here; no demo photographs, quotes or private credentials copied. Icon paths come from 21st, not an emoji font or a newly installed package.

## Scope and behavior

All currently implemented public FAQs are About and Journal structured FAQ, including its shared admin preview. Both use the same component. Admin disclosure panels, TOC, product filters and quiz questions are not FAQs and retain their existing behavior. UI arrows/stars/video/close/gallery/repeater controls replaced in About, public Journal, Journal editor, product photo controls and quiz result. Stored article content, quotes and user data were not mass-rewritten; checkout/order flow was not edited.

Owner confirmed timeline begins in2024 and store opened February2026. Intermediate entries identify the Palu idea and store preparation without inventing exact months. Existing photos illustrate the journey; the first milestone image is a current-store illustration, not a dated2024 archive.

No migrations, production data/API write, credential change, new dependencies, new media files or original-photo changes. Existing WebP images and URLs reused. Rollback: revert this code commit/release; DB/media do not need rollback.

## Bounded verification

- Final targeted Laravel run: **15 passed /263 assertions**: StoreAboutTest, JournalPublicTest, BlogContentSafetyTest, fixed trusted component renderer and shared preview cases. Initial fixture lacked APP_KEY/.env; corrected to synthetic temporary local environment, then passed without warnings. Temporary environment removed.
- Final Vite build passed. Existing DaisyUI `@property` and unrelated3D chunk size warnings remain; no new package/build error. Targeted Pint and Blade compilation passed; diff whitespace check passed.
- Real installed Chrome **390 and1440**: About and local synthetic Journal article FAQ tap/click, Enter/Space, single open/collapse, no overflow/page errors. **320** native FAQ works without JavaScript. [FAQ results](after.json).
- About **390 genuine touch /1440 desktop**: all nine photos loaded and `contain`, click expansion/collapse, single-tap dialog, next photo, Escape and focus return; no horizontal overflow/page errors. [Gallery results](after-gallery.json). Lazy images were explicitly scrolled into view before image-fit assertions; unvisited lazy images were not treated as failures.
- Before FAQ screenshots use the unchanged local baseline at main e7b52a6. Before gallery/timeline/review screenshots use read-only live About (same released implementation); after screenshots are local. Screenshot capture disables smooth scroll only to stabilize capture, not application behavior.

Screenshots: [FAQ before mobile](screenshots/before-about-390.png), [FAQ after mobile](screenshots/after-about-390.png), [Journal after mobile](screenshots/after-journal-390.png), [timeline mobile](screenshots/after-timeline-390.png), [timeline desktop](screenshots/after-timeline-1440.png), [gallery mobile](screenshots/after-gallery-390.png), [expanded gallery desktop](screenshots/after-gallery-expanded-1440.png), [SVG rating mobile](screenshots/after-icons-390.png). Complete before/after390/1440 pairs are in `screenshots/`.

Physical iPhone Safari not exercised. Older browsers without exclusive `details name` may allow multiple FAQs open; each native answer remains usable. New code is not deployed by this verification; CI and Owner release review are next. Historical P9/BLOG acceptance limits unchanged.
