# P7-10 follow-up — compact homepage photos and restored illustrations

Owner requested smaller best-seller photos and repair of the four missing homepage illustrations. This extends only the homepage surface of the existing P7-10/PR8; no production deployment was performed.

## Diagnosis and change

- Read-only production checks returned HTTP404 for `storage/images/animation/testparfumanimation.png` and `step1.png` through `step3.png`. Browser images had natural width0.
- All four originals still exist locally under ignored `storage/app/public/images/animation/`. The Git release packager includes tracked public source, excluding runtime storage. These static illustrations consequently did not accompany the source release; deletion of the production originals is not confirmed.
- Copied those originals byte-for-byte into tracked `public/images/illustrations/` and updated the three homepage sections. SHA256 source/copy equality was verified; original files and product uploads are retained. No art generation or conversion.
- Best-seller cards are196px on small phones,224px from640px,260px from1024px, with existing square contain/framing and fallback behavior. The visible next card indicates the existing horizontal rail; it is not document overflow.
- Repaired invalid desktop grid `1.05fr,0.95fr` to two space-separated tracks. Removed the absolute oversized floating quiz image; it now fits a240/288/340px bounded frame. Added intrinsic dimensions and guide image width bounds, retaining lazy loading and existing brand tokens.

## Checks actually run for this follow-up

- PHPUnit `HomeIllustrationAssetsTest|HomeBestSellersTest`: **5 tests /81 assertions passed**. New coverage requires the four homepage URLs to resolve to valid PNG files in the public source with matching intrinsic dimensions, so a clean CI checkout can detect missing release assets.
- Scoped Pint, Vite production build and `git diff --check`: passed. Existing DaisyUI `@property` and large about-lanyard chunk warnings remain.
- Four new local illustration URLs returned HTTP200. Browser confirmed all four loaded with their original dimensions; six best-seller photos loaded from actual product paths, not placeholders, with contain fit.
- Chrome CSS viewports320x900,375x900,390x844,768x900 and1440x900: no document horizontal overflow; observed card widths196/224/260px. Quiz stacks on phones/tablet and uses two tracks on desktop.
- Native desktop slider click and keyboard Enter advance the rail; endpoint buttons disable correctly. Native Royal Blend Nero link opens its corresponding detail; Back returns to the homepage. No error/warn console entries captured at final observation.
- Physical iPhone/Safari/touch is **Not confirmed**. These browser checks used viewport resizing, mouse and keyboard. Production serving of the new files remains unverified until an approved deployment.

## Screenshots

| Surface | Before | After desktop | After phone |
|---|---|---|---|
| Best sellers | [Production before](../before-home-1440.jpg) | [1440](after-best-1440.png) | [390](after-best-390.png) |
| Quiz | [Missing illustration](before-quiz-1440.png) | [1440](after-quiz-1440.png) | [390](after-quiz-390.png) |
| Shopping guide | [Missing illustrations](before-guide-1440.png) | [1440](after-guide-1440.png) | [390](after-guide-390.png) |

Before quiz/guide images use the unchanged missing-asset paths in the local preview, corroborated by actual production404 checks and Owner screenshots. Earlier production best-seller captures are retained in the parent evidence directory. After images use a disposable copy of the historical350-published catalog with30 synthetic best-seller flags and zero users; this is layout evidence, not current production merchandising. An inherited local media-disk setting initially produced placeholders in the harness; it was corrected only in the disposable router before saving the final photo captures.

## Impact and recovery

Three homepage Blade sections, four static PNG assets, one regression test and P7-10 evidence/backlog changed. No product/database/schema/price/stock/API/account/env/dependency changes. Original storage files and uploaded product photos are unchanged. The task-owned server and copied fixture are removed after checks.

Rollback: revert the frontend/asset commit and rebuild the release; no database restore is needed. The old illustration paths will again be broken unless independently provisioned. Next action: Owner review of PR8 and separately authorized release, without starting another backlog item.
