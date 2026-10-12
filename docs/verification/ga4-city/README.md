# ANALYTICS-02 verification

Owner requested direct GA4 implementation for city statistics on 2026-10-12. This bounded change preserves existing Cloudflare, Search Console, orders, accounts, integrations and PWAs.

Local checks passed: 6 PHP analytics tests (81 assertions), including a valid synthetic private order URL; 67 Node tests including 5 GA4 consent/privacy regressions; Pint on changed PHP; production Vite build. Existing large About chunk and DaisyUI `@property` warnings remain unrelated.

Chrome browser checked at **390×844** and **1440×900**, with no horizontal overflow. Consent buttons measure 44px high, skip creates no Google script, preferences reopens with focus on Skip, Enter works, and the remembered refusal survives reload. Local preview uses a fresh isolated SQLite database and synthetic measurement ID; no local analytics visit is sent. Physical Android/iOS was not available.

Before/after captures:

| Viewport | Before | After |
|---|---|---|
| 390×844 | [before](ga4-before-mobile.png) | [after](ga4-after-mobile.png) |
| 1440×900 | [before](ga4-before-desktop.png) | [after](ga4-after-desktop.png) |

UX rationale: small optional choice in the existing charcoal/white style, equally accessible Allow/Skip buttons, no blocking modal and an always-available preference control on eligible pages.

Production is gated on official PR CI, official GitHub Actions release, safe-page/private-page checks, unchanged feature flags and fresh worker/scheduler heartbeats, plus receipt of a consenting public visit in GA4 Realtime. Runbook: [ANALYTICS](../../runbooks/ANALYTICS.md). Disable by clearing only `GA4_MEASUREMENT_ID` and refreshing configuration cache; no schema or media rollback.
