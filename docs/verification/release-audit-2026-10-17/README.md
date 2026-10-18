# Pre-production audit — ORD-08 / ORD-09 / App Daftar Tunggu request (2026-10-17)

Scope:
- Website `modernization/ord-08-website-courier-cost`, 11 commits ahead of `origin/main` `535d5f3` and 0 behind;
- App `feat/ord-08-website-courier-cost`, 6 commits ahead of `origin/main` `3df3461` and 0 behind; local only.

Local, synthetic data only; nothing deployed. Release steps: [RELEASE_ORD_08_09](../../runbooks/RELEASE_ORD_08_09.md).

## Findings and fixes

| Severity | Finding | Action |
|---|---|---|
| Important | The production deploy script refuses pending migrations; this release has 3 (additive). | Release runbook uses the proven "migrate first from the verified package, then merge" procedure (RC §5a). |
| Important | "Uang siapa" validation could call Qammaris App (up to 5 s) while holding the order row lock; every order page called the App, with no back-off. | Validation uses the list the page just refreshed. Page read: 3 s timeout, 60 s back-off after a failure. Regression test added. |
| Important | After "Tandai Lunas" / "Konfirmasi packing", the step collapses and its success message was never shown. | The success message now appears with the existing fallback banner. Test added; browser-verified. |
| Important | Security: `symfony/http-foundation` CVE-2026-108082 (ReDoS via the Cache-Control header, public site). | Patch 7.4.18 → 7.4.21, lock-only; `composer audit` clean. |
| Minor | Push subscriptions accepted any https endpoint (server-side requests to arbitrary hosts). | Limited to Google, Mozilla, Apple and Microsoft push services; test added. |
| Minor | Product price fields relied on JavaScript to remove the thousands separators. | Server accepts `150.000` on the product form; CSV imports stay strict. Test added. |
| Minor | App Daftar Tunggu: a request typed without the size never got a suggestion. | All request words in the product name → suggestion (never auto-link); test added. |
| Minor | The MariaDB-only migration rehearsal assumed ORD-04 was the newest migration (it fails on this branch, but CI uses SQLite so CI misses it). | The rehearsal now includes the 3 new migrations and counts its rollback steps. Passes on MariaDB 11.8.9. |
| Minor | App: comma-operator returns in `verifyWebsiteRequest`; one test type error (`tsc` over tests). | Rewritten, same behaviour; fixed. |

## Not changed (recorded)

- App frontend `npm audit`: 6 high in build-only tooling (`tailwindcss` → `braces`); no fix available; not in the static build. Pre-existing on App `main`, and makes the App `npm run check` audit step fail.
- App frontend test `RaisedPhaseB` "POST /reimbursements sekali" exceeds the 15 s timeout in a full run on this machine. It passes alone and fails identically on an export of App `origin/main`, so it is pre-existing and unrelated.

## Evidence

- **Website SQLite (CI equivalent):** 779 tests OK, 8 skipped (MariaDB-only, GD, and the VAPID key command on Windows); JS 71/71; Pint clean on all changed PHP; `npm run build` OK; `composer audit` and `npm audit --omit=dev` clean.
- **Website MariaDB 11.8.9 (isolated instance, port 3398):**
  - order/push/RBAC/migration subset: 212/214, then 2/2 after the rehearsal fix;
  - full suite: **779 OK**, 1 skipped (VAPID key command on Windows).
- **App:** backend 1267/1267; `tsc` backend + frontend clean; ESLint clean; backend build OK; frontend build OK (with a placeholder `VITE_BACKEND_URL`, which production builds require); frontend 1388/1389 (the pre-existing timeout above); waitlist 14/14.
- **Browser** (Website 8011 + App 8000/5173, 390 px and 1440 px), as Super Admin and Staff Order:
  - ongkir typed as 10.000;
  - "Tandai Lunas" and "Konfirmasi packing" show their success messages;
  - the Super Admin has no packing action;
  - "Uang siapa" = live App employees, including a renamed one;
  - booking with an employee, then "Sudah diserahkan ke kurir" 5 s later on the same open page → success, no "Pesanan berubah";
  - the App created an Approved reimbursement;
  - Owner GoPay booking reads "uang Owner (GoPay)";
  - the App order page is view-only with "Buka di Website";
  - push: test and real website-checkout notifications displayed (ORD-09 evidence).
- **Cleanup:**
  - every synthetic account, order and push subscription was removed by restoring the local DB backup taken just before each run;
  - App records were deleted by order ID;
  - temporary DB backups and the MariaDB instance were removed;
  - the Owner's own local push subscription was kept.

## Final release preparation (same day)

- **New read-only `php artisan qammaris:release-check`.** It prints one table after the deploy: migrations, App integration, staff link target, the App's active employees, approving Owner vs `QAMMARIS_APP_OWNER_IDS`, VAPID keys, admin PWA and queue.
  - The App employees endpoint now also returns that approver.
  - Run for real against the local App: all PASS, plus the expected local WARN for `QUEUE_CONNECTION=sync`.
- **Existing production costs.** `OrderCostMigrationTest` creates an App-reported cost *before* the 3 new migrations. The row becomes `origin=app`, its values are unchanged, and the Order API still validates. Passes on SQLite and MariaDB 11.8.9, together with the rehearsal and the release check (5/5).
- **Version compatibility** (old App ↔ new Website, both directions), checked in code: safe for data. Order: App first, then the Website in the same session.
- **Runbook corrected.**
  - VAPID keys are generated after the deploy, because the command ships with the release.
  - `config:cache` + `queue:restart` after the push settings.
  - Rollback = revert the merge commit.
- **Final runs:** Website 782 OK (8 skipped), JS 71, Pint clean; App backend 1267 OK, `tsc` + ESLint clean.
