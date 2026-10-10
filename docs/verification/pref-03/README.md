# PREF-03 — usable website beta

Owner2026-10-10: “gua mau t4es gpapa rilis aja dlu”. Explicit approval is for a usable trial release before independent human-quality acceptance. This advances the necessary website adapter, not a claim that PREF-01/02 quality targets passed. One active item: PREF-03, with its technical staging/release preparation. No offline QR/gift/AI/catalog rewrite.

Eight core and two skippable screens, editable summary, immediate options, inactive fieldsets hidden/inert, Indonesian labels, 24h local draft (with storage failure disclosure). Public GET/POST URLs retained; flag switches index/submit and new result routes. Result UUID and encrypted HttpOnly same-site seven-day browser cookie; HMAC only in result storage, URL alone insufficient. Private/no-store/noindex/no-referrer headers include rate/validation/error responses downstream of browser middleware. Cookie binding executes before limiter. CSRF stays in web middleware.10 submits/20 feedback per minute per browser,30/60 additional IP limits. No PII/free text or automatic retention purge.

Results preserve question/engine versions, anonymous answers, internal score/evidence/fingerprints and original selections. Current public offer/image/size/status reread; hidden/draft names/photos never rendered from old snapshots. Changed catalog banner makes original reasons/date limitations explicit; recomputation creates a new result and retains old feedback. Ready filtering of existing results requires no new answers. Empty/sparse/error/retry/expired states; no percentage accuracy. Feedback overall and optional original-product rating/reasons, validated and transactionally updated under result row lock. No feedback auto-learning. Admin report/version/product filters, preview/apply and source/revision guarded profile correction reuse existing admin and store operation; no accounts/permission change.

Current observed release: [2026-10-10 beta deployment and live evidence](RELEASE.md). Below is the dated pre-deployment snapshot; its pending statements are historical.

## Verification before deployment

-64 focused fragrance tests /406 assertions passed (11 new HTTP/security cases). Exact browser/IP/feedback limits, secure cookie, ownership, seven-day access with record retention, current price/draft hiding, replay, malformed/conflicting answers, legacy rollback and result/feedback insertion failure rollback actually tested using in-memory SQLite.
-Changed PHP Pint and whitespace checks passed. Vite build passed; existing DaisyUI@property and About3D large-chunk warnings unchanged. Required GitHub CI and MySQL/release proof are pending at this snapshot.
-Actual in-app browser, requested390×844/1440×900 and320 overflow check. Desktop/mobile draft refresh, back/edit, aroma contradiction, favorite search, optional skip, summary, full POST→results, feedback and feedback refresh passed. No application console errors observed. Active fieldsets count1; others hidden/inert.320 scrollWidth=clientWidth305;390=375 (Windows in-app scrollbar/window trim). Images use contain and loaded; local fixture deliberately uses existing placeholder, not copied production photos. Real production photos still require live check.
-Keyboard traversal leaves inactive panels out of focus. These are browser resizing/mouse/keyboard checks, **not genuine touch or physical Safari**. No touch-emulation API is exposed in this in-app browser. Owner-facing beta does not silently convert this limitation into passed device acceptance.

## Evidence and boundaries

[Before mobile](before-390.jpg), [before desktop](before-1440.jpg), [wizard mobile](wizard-390.jpg), [wizard desktop](wizard-1440.jpg), [results mobile](result-390.jpg), [results desktop](result-1440.jpg). Browser screens reviewed; task-focused form preserves cream/charcoal/gold and moves budget/avoid constraints into explicit answers. No decorative hero, staggered choice appearance, new package/framework or media replacement.

New code: preference controller/request/browser middleware/results service/models/questions; additive result/feedback migration; Blade wizard/results/error/admin screens; scoped JS/CSS; existing quiz delegate/routes/provider/middleware priority/dashboard link. Legacy engine and original views retained. Previous profile/revision migration also needed at release; no catalog form columns or operations changed.

Independent relevance18/20 and16/20 targets, semantic/source/identity reviews and physical touch acceptance remain pending. Source sparse/unknown attributes and known soft compromises remain visible. Beta is provisional; no sensory-accuracy guarantee. Runbook documents recovery. Next: Owner tests real recommendations and gives concrete feedback; no V2 automatic start.
