# HOME-NEW-ARRIVAL — "Baru Tiba" verification (2026-11-17)

Branch `new-arrival-beranda`, card #202. Local only; nothing deployed.

## Setup

- Local SQLite catalog; Laravel on `127.0.0.1:8011` with `APP_URL` overridden for that process (port 8010 was held by another local project), Vite dev server.
- Local products had no `published_at`. For the check only, products 72, 73, 75, 77, 78 and 86 (photo, price, not sold out) got recent dates; all six were set back to `null` afterwards (0 products with `published_at`, as before). No other data was created.
- "Before" equals `main`: with no qualifying product the section is not rendered.

## Screenshots

| State | 375 (mobile) | 1440 (desktop) |
|---|---|---|
| Before | [before-mobile-375.jpg](before-mobile-375.jpg) | [before-desktop-1440.jpg](before-desktop-1440.jpg) |
| After | [after-mobile-375.jpg](after-mobile-375.jpg) | [after-desktop-1440.jpg](after-desktop-1440.jpg) |
| Hover on row 03 | | [after-desktop-hover-row-03.jpg](after-desktop-hover-row-03.jpg) |
| Only 2 qualifying products: section hidden | [after-mobile-375-fewer-than-3-hidden.jpg](after-mobile-375-fewer-than-3-hidden.jpg) | |

Captured with headless Chrome (fixed navbar hidden for the capture).

## What was checked (in-app browser and headless Chrome)

- Section sits between the brand row and Produk Terlaris; no horizontal overflow at 375 or 1440; no price anywhere in it.
- Autoplay every 4 s: number, "Baru · 16 Nov" label, brand, name, size, stage link and active row change together; progress line restarts.
- Desktop (mouse): hovering row 01 while 04 showed switched the stage at once and held it (progress paused at 0 for 5 s); leaving the section resumed from 01 with a fresh line and moved to 02 after 4 s.
- Keyboard: Tab from "Lihat semua produk baru" through stage, pause button and rows; focusing a row switches and holds; focus ring visible (1.6px solid black).
- Pause button: label changes "Jeda pergantian produk" ↔ "Putar pergantian produk"; paused stays put for 5 s, play resumes.
- Off screen: progress paused while scrolled to the top, resumed when back.
- Mobile (touch emulation, fine pointer off): swipe left = next, right = previous, vertical drag ignored, the click after a swipe does not open the link.
- `prefers-reduced-motion: reduce` (headless emulation): no autoplay, no animations, pause button hidden, instant switch on focus and hover.
- Clicking a row opens that product page.
- Fewer than 3 qualifying products: no section, Produk Terlaris unchanged.

## UX rationale

Produk Terlaris is a white, uniform horizontal rail. Baru Tiba uses a cream band, one large product at a time and a numbered list, so the two sections read as different things: "what is new" versus "what sells". No price keeps the section about discovery, like Terlaris on the homepage. Product photos stay uncropped (`object-contain`); on mobile the number and date sit in a band above the photo so they never cover the bottle.

## Not verified

- Physical iPhone Safari / Android touch (only emulation); real touch tap-to-open on a phone.
- No-JS rendering was checked from the server HTML (first product shown, other layers `opacity-0`, pause button `hidden`, rows are plain links), not as a screenshot.
- The "New Arrival" eyebrow (`brand-gold` on cream) is below 4.5:1 for small text, as with the existing "Pilihan Eksklusif" eyebrow; the gold list numbers are decorative (`aria-hidden`), the list order comes from `<ol>`.
- Production data: whether at least 3 products qualify depends on real `published_at` dates after release.
