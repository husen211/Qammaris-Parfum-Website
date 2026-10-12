# ORD-02 — local smoke test r4.2 with Qammaris App

Date: 2026-10-09. Run `smoke-r42-1`.

This is a smoke test of the real code on an isolated local instance. It is not a hosted E2E sign-off. Baseline for comparison: [r4.1 smoke](SMOKE_LOCAL.md).

| Item | Value |
|---|---|
| Website | commit `693e6f9`: r4.2 baseline (`b0e1f5d5…cb88`) plus the `websiteIssue` fixture |
| App | commit `1477b96` (branch `dev/september-2026`). OpenAPI r4.2 copied byte-identical. |
| Database | MariaDB 11.8.9, separate database `qam_e2e_r42`. The r4.1 evidence database `qam_e2e` was not touched. |
| Owner allowlist | `e2e000000000000000000001` only, read from a local file outside the repository. "E2E Owner Dua" deliberately left out. |
| Switches | V2, API, webhook and Website issues on; task links off |

Preflight before the run: OK. The allowlist PASS (1 Owner), all 7 fixtures PASS, and 7/7 orders were readable.

## Result

**App harness: 25 PASS / 0 FAIL / 1 SKIP.** The skipped check is the tunnel preflight, which does not apply locally.

| Order | Website revision | App revision | Queue |
|---|---|---|---|
| QAM-0001 local | 8 | 8 | in_delivery |
| QAM-0002 intercity | 9 | 9 | in_delivery |
| QAM-0003 customerCourier | 8 | 8 | done |
| QAM-0004 pickup | 6 | 6 | done |
| QAM-0005 issue | 3 | 3 | has_issue |
| QAM-0006 costs | 11 | 11 | preparing |
| QAM-0007 websiteIssue | 2 | 2 | has_issue |

**Proof waiver (r4.2):**
- `exp_ae9f8c25…` reached `paid` with `proof=waived`. It carries decision `wvr_4eb7bf47651445f8afb69b1d32165ebc`, `by = e2e000000000000000000001`, and the decision ID matches on the App and the Website.
- A waiver attempt by "E2E Owner Dua", who is not on the allowlist, got **403**. The App stopped automatically. Cost `exp_8f6177f2…` stayed `awaiting_proof` with no waiver.

**Website issue:** QAM-0007 is served with `opened_by=null` and `opened_by_source=website`. The App displays it.

**Webhooks:**
- The App received 38. The Website delivered 38, 0 failed, at most 3 attempts.
- 9 more were still pending. These were the creation events queued before the App receiver ran. The App had already synced those orders through `GET /orders`.
- The Website instance was stopped after the run, so these retries do not reach the App's UAT receiver.

**Transport mode** (`X-Qammaris-Delivery`), as recorded on App events: 35 `live`, 3 `retry`.

**Errors:** none in the Website log during the run.

Raw Website report: [smoke-r42-1.json](smoke-r42-1.json).
