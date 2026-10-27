# ADR-054 — Walkable 360 store tour

Status: accepted for review (TOUR-01, Owner 2026-10-27). Not deployed.

## Context

The Owner captured the Palu store with Teleport 360 on an iPhone and asked for a virtual tour on the website that customers can walk through, not only look around in. The captures had stitching errors (swirled ceiling lights, broken arches and cabinets, the photographer's feet and reflection). They were retouched outside the repository; three are used (Pintu Masuk, Tengah, Depan Kasir); the Owner dropped Dalam Tengah after the first review. The Owner approved a standalone prototype (Pannellum) before this item.

After the second review the Owner asked for no page of its own: the tour lives on the store location page and on the homepage above Qammaris Journal, visitors "enter" it, and the camera starts at "0,5" (the iPhone ultra-wide) so nobody has to zoom out.

## Decision

- **One Blade component, `<x-virtual-tour>`**, on `/store/location` (after the intro) and the homepage (between "How it goes" and Qammaris Journal). It renders a cover (a 150° strip of the entrance panorama that pans slowly while on screen), a "Masuk" button and a native `<dialog>`. The tour data comes from `App\Support\StoreVirtualTour` (config `config/store_virtual_tour.php`). The first-pass page `/store/virtual-tour` was removed before release; the footer links `/store/location#virtual-tour`.
- **Enter = full-screen dialog.** `showModal()` gives the top layer, focus containment and Escape; the page behind stops scrolling. A history entry is pushed on open so the phone back button leaves the tour instead of the page. While the panorama loads, a square 100° render of the start view pushes forward ("walking in"); the viewer then picks the zoom that matches that photo exactly as drawn and opens up to 0,5×.
- **Load on demand.** `virtual-tour.js` (2 KB) only wires the cover; the viewer (`virtual-tour/viewer.js`), three.js and the panoramas load after "Masuk" (warmed up on mouse hover). Closing disposes the renderer, textures and WebGL context.
- **Viewer on the existing `three` dependency.** The panorama is drawn on an inside-out sphere; walk arrows, area buttons and controls are DOM. The prototype's Pannellum is not added: the stack already ships three.js (homepage 3D), so a second 3D/360 package is not justified (AGENTS.md).
- **Camera zoom like a phone.** 0,5× is the widest view: 115° horizontal on landscape screens, limited to 128° vertical on tall phones (about 87° horizontal on a 390×844 phone). 1× doubles the focal length; pinch, wheel and +/- go up to 3×. Two round buttons (0,5× and 1×) show the live value like a phone camera. A walk steps in to at least 1× and returns to the visitor's zoom.
- **Panoramas are static WebP site assets** in `public/images/virtual-tour/`: 4096 and 6144 px equirectangular WebP plus a WebP preview per area, the cover strip and the intro render (3.2 MB). WebP is required: the Hostinger CDN resizes JPEG/PNG on the fly (1600 px wide for desktops, 800 px for phones, measured on production after PR #129) but passes WebP through unchanged; a JPEG panorama is blurry in the viewer. They are curated content changed by code review, not user uploads, so they do not use the product media disk. The 6144 px file only when `MAX_TEXTURE_SIZE ≥ 8192`, the window is at least 1024 px wide and Data Saver is off. Mipmaps are off; only the current area and its direct neighbours stay loaded.
- **Walk links in config**: arrow yaw/pitch and the yaw faced on arrival, from relative camera poses estimated from feature matches between the panoramas (essential matrix on bearing vectors), not from a measured floor plan. A floor plan was tried and removed at the Owner's request.
- **Fallbacks.** No WebGL or no `<dialog>`: "Masuk" shows the three preview photos under the cover. No JavaScript: the previews show and the button is hidden. Load failures show "Coba lagi"; a lost WebGL context offers a reload.

## Consequences

- New or reshot areas need retouched panoramas, re-estimated links and a code change; there is no admin editor. Without a tripod and with the camera closer than ~1.5 m to shelves, Teleport stitching errors return.
- Arrow positions are estimates on the floor; where furniture covers the floor, an arrow sits on that furniture.
- 0,5× on a tall phone is a strong wide angle; straight lines stretch toward the top and bottom edges, as on the iPhone ultra-wide.
- Real-device touch on iPhone Safari, the Android back button and low-end Android GPUs are unverified until checked on hardware.
