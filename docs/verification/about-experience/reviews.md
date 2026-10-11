# ABOUT-01 — Owner-supplied Google Maps reviews

2026-10-08 follow-up. Owner supplied eight Google Maps screenshots after the original About implementation. All images were visually read in the conversation. Six complete relevant reviews selected; no invented wording, author, stars or hidden continuation. This updates About PR36, not a new feature or production write.

| Author as displayed | Stars | Owner source |
|---|---|---|
| Owen Bryant Owen Bryant | 5 | Photo1, first review |
| Dede Persib | 5 | Photo1, second review |
| Derry Qlay | 5 | Photo2, first review |
| Ria Rizki | 5 | Photo4, first review |
| Muhammad Zaidan Mufid | 5 | Photo4, second customer review, below Owner reply |
| Ferdiansyah Cahyadi | 5 | Photo6, second customer review |

Source files are Owner attachments under `C:/Users/ASUS/.codex/codex-remote-attachments/01a07160-5b86-7363-bd13-197672666902/4D25DD94-EEC0-4C69-8E2C-61E4BD4630F3/` (`1-Photo-1.jpg` through `8-Photo-8.jpg`). They are retained at their original location; screenshots, reviewer photos and profile metadata are not copied into public assets/Git. Public text/stars/name are transcribed in `config/store_about.php` and escaped through the existing Blade review component, adapted from the retrieved 21st component.

Keep original informal wording and duplicated Owen display name. No inferred review dates from relative “months ago”; screenshot capture date is not independently established. Hidden “more” text is not used. Duplicate/price-specific review examples are not selected. Owner business replies are not customer testimonials. Every card links to the supplied Google Maps business review URL; individual review permalinks are not available. Rating4,9 remains the separate dated Maps observation, not calculated from these six selected five-star reviews. No aggregateRating/review structured data added.

## Layout and checks actually run

Intro/rating remain a two-column header on desktop. Attributed quote cards form three columns at1440, two at768, one at320/375/390. No carousel, hidden content, new dependency, auto animation or fake profile avatar. Quote footer preserves name/source and accessible five-star label. Source link remains native new-tab with noopener/noreferrer and keyboard focus.

- About regression:3passed/131assertions. Initial Pint detected line-ending/operator-spacing issues; fixed and final Pint passed.
- Vite build passed; About CSS11.10KB uncompressed; existing DaisyUI/property and unrelated3D chunk warnings remain.
- Installed Chrome before390/1440:zero review cards; after320/375/390/768/1440:exactly six names and six five-star accessible labels,HTTP200,no horizontal overflow or JavaScript errors. Source link/focus checks passed. Browser configured genuine touch(maxTouchPoints1,hoverfalse), but this follow-up does not claim a newly exercised tap/swipe or physical Safari; original About touch proof remains separate.
- [Before results](reviews-before-browser.json), [after results](reviews-after-browser.json). Before/after screenshots under [screenshots](screenshots/): `reviews-before-390.jpg`, `reviews-before-1440.jpg`, `reviews-after-390.jpg`, `reviews-after-1440.jpg`; both after images visually inspected. The long mobile section screenshot can include the unchanged fixed navbar over a middle part of the screenshot; this is not a new review overlay.

Changed config, About Blade/CSS, existing About regression and affected architecture/backlog/provenance/evidence. No schema or production database/article/order/media change; no new image assets. Temporary local SQLite environment has no account/customer fixtures and will be removed after checks. No production merge/release authorized by the review screenshot message. Code-only forward fix/rollback retains all original photos and persistent media. Next: explicit release approval already requested for PR34–37; do not start another feature.
