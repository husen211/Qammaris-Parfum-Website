# Store opening hours correction — 2026-10-08

Owner confirmed the Palu shop opens Saturday through Thursday, 09:00–21:00, and closes Friday. The location page now says **Jam Operasional**, **Sabtu–Kamis**, **09.00–21.00 WITA**, **Jumat / Tutup**. WITA replaces the incorrect WIB label for Palu.

Only static Blade copy and affected business/backlog documentation change. Existing layout, contact/map links, national-holiday notice and WhatsApp copy remain. No database, media, prices, stock, orders, API, dependency, or credential change.

Verification:
- Inspected actual production `/store/location` (HTTP 200) before editing its displayed schedule.
- Chrome 390×900 and 1440×900: before/after screenshots, zero horizontal overflow, zero page JavaScript errors.
- After screenshots use a local browser response preview with exactly the five Blade text substitutions, not an assertion of production deployment.
- `php artisan view:cache` passed; `git diff --check` passed. No new tests or package/build change for static copy.
- Machine text assertion initially compared CSS-uppercase innerText; the inspection script was corrected to read textContent and passed. No application workaround was introduced.

[Browser results](browser.json), [mobile before](before-390.jpg)/[after](after-390.jpg), [desktop before](before-1440.jpg)/[after](after-1440.jpg).

Release status: ready for review; not deployed. Release requires explicit production approval. Rollback is a source-copy revert, with no database or media recovery step. Next work remains Owner Journal acceptance; do not activate API credentials or start order work as a consequence of this correction.
