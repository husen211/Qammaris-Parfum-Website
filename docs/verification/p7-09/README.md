# P7-09 — Catalog pointer feedback regression

Status: IN_REVIEW, local patch verified; not deployed. Active surface: public product cards and the detail Kembali link. Owner supplied screenshots with local8001 page2 showing an intrusive gold panel and a retained black outline. No P8-09 work started.

## Diagnosis and change

`resources/css/app.css` globally paints active/pending anchors with gold background and an inset border, including full-height product cards. New scoped overrides remove that background/shadow only on `[data-catalog-product]` and `[data-catalog-return]`, using gold name/text feedback without changing geometry, photography or ordinary navigation. Other button/menu feedback stays unchanged.

`resources/js/ui/catalog-navigation.js` previously called `focus()` on the prior card after every return, including mouse returns. Actual Chrome pointer entry/Kembali produced focus-visible=true and a black solid outline. A stored keyboard flag now restores focus only for keyboard/assistive activation, including keyboard Kembali after pointer entry. Pointer Kembali overwrites earlier keyboard intent. Browser Back retains the departure intent. Scroll, sidebar/brand scroll, query/page matching and native links remain. Real keyboard focus-visible styling is preserved, not globally removed.

Changed code: the two files above and `tests/js/catalog-return-focus.test.mjs`. Documentation: BACKLOG current item and this evidence. Existing prior-release evidence was carried into the branch as a documentation-only commit; it is not a second release.

## Actual verification

- All15Node tests passed, including three new actual-module regressions for pointer return/history, keyboard/assistive focus, switching keyboard to pointer, legacy records and unrelated query/page contexts.
- Narrow Laravel catalog discovery/card-trust/hardening:13passed/100assertions. No PHP application code changed. Full PHP suite was not rerun locally for this patch.
- Final Vite build passed: `app-KGEUC8GU.css`, `app-IGlbRLj1.js`; whitespace check passed. Existing DaisyUI@property and large about-lanyard chunk warnings remain unrelated.
- Chrome CSS viewports1440x900/390x844 against the retained isolated350-published-product reference, normal pagination24/page. Before pointer return: card focused/focus-visible/solid outline. After pointer Kembali and browser Back: no forced focus/outline, transparent card background and no shadow; page2 and nonzero172px desktop position restored. Keyboard Enter entry/Kembali: prior card focused with solid focus-visible outline. Mobile pointer return has no forced focus/outline/background/shadow and no horizontal overflow. Desktop also has no horizontal overflow.
- Actual compiled stylesheet read in the browser confirms scoped active/pending overrides (`background-color:transparent; box-shadow:none`) and text-only gold feedback. A held/pending transient screenshot was not obtained; do not present a static screenshot as proof of that transient state. The visible before gold-panel evidence is the Owner's supplied screenshot.
- Final fresh local-page console-error observation returned[]; browser Tab/Enter and pointer links reach the correct Hawas detail. A rebuild briefly removed the local manifest while another local request was in flight; that request displayed a local manifest error. Completed build + reload restored it. No production request, deployment or configuration change involved. Artificial local detail latency caused CDP command timeouts; fresh page state was read and ordinary navigation verified afterward.

Screenshots are unedited browser captures. Default representative viewport captures are retained; extension raster dimensions can differ from CSS viewport and are not touch-device evidence.

| Evidence | File |
| --- | --- |
| Before mouse return | [desktop1440](before-return-1440.jpg), [mobile390](before-return-390.jpg) |
| After pointer return / browser Back | [desktop1440](after-return-1440.jpg), [mobile390](after-return-390.jpg) |
| Keyboard focus retained | [desktop1440](keyboard-return-1440.jpg) |
| Detail Kembali presentation | [desktop1440](after-detail-1440.jpg) |

## Impact, limitations and recovery

No schema/migration/package change, product content/price/availability/identity/media change, API operation or production/staging mutation. Only public GET view counters can change in the isolated UI fixture. No temporary account or business test record created. A temporary local-only read-only latency router/server was used for verification and removed/stopped afterward; the pre-existing8001 preview remains available.

Actual iPhone Safari/touch is **Not confirmed**. The earlier waiver applied only to PR4/PR5. This new patch is not deployed; future release must satisfy the repository's release approval/touch gate or receive an explicit Owner exception. No unrelated redesign or global removal of focus accessibility.

Rollback: revert these frontend changes and rebuild; no database/media restore or checkpoint adjustment. Recommended next action is review/release of this focused regression fix. P8-09 supplemental Shopee import stays next in the roadmap, not started here.

![After pointer return — no retained card outline](after-return-1440.jpg)
