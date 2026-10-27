# ADR-054 — Walkable 360 store tour

Status: accepted for review (TOUR-01, Owner 2026-10-27). Not deployed.

## Context

The Owner captured the Palu store with Teleport 360 on an iPhone and asked for a virtual tour on the website that customers can walk through, not only look around in. The captures had stitching errors (swirled ceiling lights, broken arches and cabinets, the photographer's feet and reflection). They were retouched outside the repository; four of seven captures were good enough (Pintu Masuk, Tengah, Dalam Tengah, Depan Kasir). The Owner approved a standalone prototype (Pannellum) before this item.

## Decision

- **Viewer on the existing `three` dependency.** `resources/js/virtual-tour.js` draws the panorama on an inside-out sphere and positions walk arrows, the floor plan cone and the area buttons in plain DOM. The prototype's Pannellum is not added: the stack already ships three.js (homepage 3D), so a second 3D/360 package is not justified (AGENTS.md). Page-only Vite entries (`virtual-tour.css/js`); three is shared through Vite's common chunk.
- **Panoramas are static site assets** in `public/images/virtual-tour/`, like the hero media and brand marks: 4096 and 6144 px equirectangular JPEGs plus a 1200×675 WebP preview per area (8.5 MB total). They are curated content changed by code review, not user uploads, so they do not use the product media disk.
- **Size choice in the browser.** The 6144 px file only when `MAX_TEXTURE_SIZE ≥ 8192`, the window is at least 1024 px wide and Data Saver is off; otherwise 4096 px (every WebGL device we target supports 4096). Mipmaps are off to keep GPU memory near 50 MB per phone panorama; only the current area and its direct neighbours stay loaded.
- **Tour data in config** (`config/store_virtual_tour.php`): per area the arrival yaw, floor-plan position and heading, and walk links (arrow yaw/pitch, yaw to face on arrival). The directions come from relative camera poses estimated from feature matches between the four panoramas (essential matrix on bearing vectors), not from a measured floor plan. A walk turns toward the arrow, zooms in, cross-fades to the next panorama rotated so the walking direction carries over, and zooms back out.
- **Fallbacks.** No WebGL or a GPU below 4096 px textures, and no JavaScript, show the four preview photos instead. Load failures show "Coba lagi"; a lost WebGL context offers a reload.
- **Interaction rules.** Drag/one-finger to look, two-finger pinch to zoom, arrow keys and +/- on the focused viewer. A plain mouse wheel scrolls the page; zoom needs Ctrl (trackpad pinch). The viewer blocks touch scrolling only inside its own box.

## Consequences

- New or reshot areas need retouched panoramas, re-estimated links and a code change; there is no admin editor. Without a tripod and with the camera closer than ~1.5 m to shelves, Teleport stitching errors return.
- Arrow positions are estimates on the floor; where furniture covers the floor, an arrow sits on that furniture.
- The floor plan is schematic (spot positions inside a rectangle), not a measured layout.
- Real-device touch on iPhone Safari and low-end Android GPUs is unverified until checked on hardware.
