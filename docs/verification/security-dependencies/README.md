# SEC-DEP-01/02 — Bounded security dependency patches

2026-10-08. Owner requested finishing remaining work; About implementation is IN_REVIEW. This separately scoped follow-up resolves the previously recorded npm/Composer dependency findings. Branch `codex/security-dependency-patches` is stacked on `codex/about-experience-store`; its PR must not include unrelated orders. Production release permission is pending.

## Changes and evidence

Only three package versions changed: shell-quote 1.9.0 → 1.11.0; source-map-js 1.2.1 → 1.2.2; league/commonmark 2.10.1 → 2.10.3. npm package-lock and Composer lock retain all unrelated versions. `concurrently` 9.2.4 pins the vulnerable shell-quote exactly; even the current concurrently 10.0.5 pins it. A parent-scoped npm override selects the compatible patched shell-quote rather than a major concurrently upgrade, new direct package, or unrestricted audit fix. Remove the override only when the parent itself selects a verified patched dependency.

Maintainer advisories: [shell-quote](https://github.com/advisories/GHSA-pqg4-j6r4-53mv), [source-map-js](https://github.com/advisories/GHSA-68fv-2mgg-jv7q), [CommonMark raw HTML](https://github.com/thephpleague/commonmark/security/advisories/GHSA-97jj-33gv-5xf9), [CommonMark tables](https://github.com/thephpleague/commonmark/security/advisories/GHSA-3q6v-r5mr-hxv8). npm packages are development/build dependencies; CommonMark is transitive Laravel runtime dependency. Journal stores sanitized HTML; no app Markdown conversion path was found. No actual exploitation is claimed.

Files: `package.json`, `package-lock.json`, `composer.lock`, this evidence and `docs/planning/BACKLOG.md`. No schema, credentials, permissions, database records, catalog, order, article content, or persistent upload media changed. Installed dependencies/build artifacts are local ignored outputs.

## Actual checks

- Clean `npm ci --ignore-scripts` succeeded; audit reports zero vulnerabilities. [JSON](npm-audit.json).
- Composer targeted minimal update succeeded; locked audit reports no advisories or abandoned packages. Strict metadata validation passed. [JSON](composer-audit.json).
- Laravel: 469 passed, 3662 assertions, two existing conditional skips. Node's repository test command: 31 passed, zero failures. An initial `npm test` attempt failed because this repository has no such script; it was corrected to the documented/CI `node --test tests/js/*.test.mjs` and rerun successfully.
- Two safe `node --version` commands through concurrently both completed with exit 0 after the override.
- Vite production build and git diff whitespace check passed. Existing DaisyUI `@property` and large 3D chunk warnings remain; About does not load that 3D module.
- Post-build installed Chrome smoke at 390 and 1440 px: HTTP 200, nine gallery photos, no page overflow, no JS errors, no Instagram iframe before customer action. [Results](browser-smoke.json). No new UI was introduced; the full About five-width and genuine touch screenshots are in [ABOUT-01](../about-experience/README.md).

Audits are dated package-database checks, not proof of absence of all possible security defects. Physical Safari and production verification are not claimed. PR37 CI37753448048 passed both PHP/Laravel and Node/Vite jobs. Production release remains pending. [PR37](https://github.com/husen211/Qammaris-Parfum-Website/pull/37), [CI](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37753448048). No new test framework or implementation-mirroring tests added.

## Release / recovery

Normal GitHub CI and separately authorized production release. No migrations. After release verify exact revision and public About/blog/location responses. App rollback can use the previous release without rolling back data; reintroducing vulnerable packages should be temporary only, with a reviewed forward patch preferred. Keep media and machine token configuration unchanged. Next item: Owner About review / verified testimonial excerpts and explicit production release, without automatically starting another feature.
