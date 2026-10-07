# Qammaris — backlog current

Updated 2026-10-07, AUD-02. This board is the entry point; detailed prior scope/acceptance/results remain in the [historical backlog](../history/2026-10-07-context/BACKLOG.md), [audit](../audits/2026-10-07-maintainability.md), ADRs and linked verification. Historical “next/unstarted/pending” paragraphs are relative to their date. Do not implement them again or treat them as new authorization. Current rules: [BUSINESS_RULES](../product/BUSINESS_RULES.md); executable flow: [ARCHITECTURE](../architecture/ARCHITECTURE.md).

## Status and working limit

BACKLOG = proposed/not approved for execution; READY = accepted bounded scope ready; IN_PROGRESS = active work; IN_REVIEW = complete within stated scope, review/limits still open; BLOCKED = named external prerequisite; DONE = accepted criteria and stated verification completed. One approved implementation item at a time. A released feature may have separate IN_REVIEW device/runtime acceptance; do not infer either code unreleased or all tests passed from the label alone.

## Program board

| ID | Phase | Status retained | Current meaning / evidence |
|---|---|---|---|
| P0 | Discovery/decisions | DONE | Historical baseline and accepted product rules; not fresh production counts |
| P1 | Foundation/staging/Git | IN_PROGRESS | P1-01–03 complete; P1-04 cutover released, acceptance IN_REVIEW ([proof](../verification/p1-04/PRODUCTION_CUTOVER.md)) |
| P2 | Tests/catalog safety | DONE | Existing regression/ownership/validation safeguards retained |
| P3 | Product foundation | DONE | Stable IDs/slugs, one offer, publication/availability boundaries |
| P4 | Media | DONE | Disk abstraction/lifecycle + earlier R2 rehearsal; current persistent public disk, no new R2 move |
| P5 | Admin | DONE | Catalog/editor/taxonomy/media workflows available |
| P6 | Bulk/audit | DONE | CSV, snapshot/maintenance and transactional guarded apply |
| P7 | Public UX | DONE | Catalog/search/cart/recipient WhatsApp flow released; device gates tracked under P9 |
| P8 | App/integration/import | IN_PROGRESS | Feed/automatic drafts/prices/Shopee released; older acceptance consolidated for review with P9, no website write API claimed |
| P9 | Acceptance/observation | IN_PROGRESS | P9-01 IN_REVIEW; exact remaining limits below |
| AUD | Maintainability/context | IN_PROGRESS | AUD-01 report IN_REVIEW; AUD-02 docs implemented, IN_REVIEW; code follow-ups not started |

Statuses are not a fabricated progress percentage. P1/P8/P9 are not marked complete merely because the current code is live. [MASTER_PLAN](../architecture/MASTER_PLAN.md) defines closure and future-program boundaries.

## AUD-02 — Consolidate current repository context — IN_REVIEW

Owner execution approval: “oke gas aud-02”. Scope: documentation only across AGENTS, README, rules, architecture, plan and board; safe current/history navigation and unique ADR references. No application/data/schema/media/config/credential/deployment work or next refactor.

Dependencies: AUD-01 source-referenced findings and already accepted Owner rules, particularly automatic connected pricing/drafts (ADR-025), Shopee recovery (ADR-026), search (ADR-027), recipient checkout (ADR-028).

Acceptance:
- Fresh session can follow AGENTS → rules → architecture → active board → relevant ADR/runbook/source without reading every historic phase.
- Clearly identify UUID draft creation, automatic valid prices/source status, manual/Shopee persistent media, human publication and recipient WhatsApp ordering.
- Retain dated launch approvals and decision/evidence text; validate moved relative links and alias old checkout ADR filename after separating its duplicate number.
- Preserve P9 unknowns; no invented completion, business rule, code or infrastructure change.
- Define one document owner per context purpose and the future-feature update convention, rather than creating competing context files.

Outcome: current documents reconciled; four complete pre-consolidation snapshots retained with source hashes/provenance; original checkout ADR-026 becomes compatibility alias to canonical ADR-028; ADR-026 Shopee and ADR-027 search retain their IDs. Earlier scope decisions are explicitly marked partially superseded, not erased. Historical product counts/one-off launch permissions are separated from recurring rules. Documentation-only diff/link/history checks and limits: [AUD-02 verification](../verification/aud-02/README.md). Branch review remains; no main merge/deployment. Recommended next is AUD-03 only after Owner direction.

## AUD-01 — Repository maintainability audit — IN_REVIEW

Investigation complete, no refactor. [Report](../audits/2026-10-07-maintainability.md) records 14 findings, no established Critical finding, actual code boundaries, data/runtime unknowns and bounded follow-ups. AUD-02 approval accepts the documentation follow-up, not all proposed code/business changes. Historical Laravel339/2306assertions and31Node baseline plus related-card browser390/1440 screenshots belong to AUD-01 evidence; they are not new tests run by AUD-02.

## P9-01 — Bounded production acceptance — IN_REVIEW

[P9-01 evidence](../verification/p9-01/README.md): last observed PR15/0dc6924; health/feed authentication, current public readiness/local file existence, cached-source price/status parity, scheduled reconciliation and one worker passed within the read-only scope. The Owner's real batch apply/stored photos were observed independently; agent did not apply it. These are dated observations, not immutable counts.

Remaining acceptance limits:
- Physical Safari/genuine touch single-tap and scroll not tested.
- Native file chooser/upload path blocked by existing extension permission; permission was not changed. Persisted Owner import/CDN outcomes are confirmed, native upload path is not.
- New source-event timing/replay/outage in current production not exercised; existing source tests/staging event evidence do not replace it.
- Populated production recipient checkout/WA handoff not submitted; empty cart/navigation passed. No fabricated customer/order.
- Two protected media-baseline holds preserve published photos; retry alone cannot reconcile stale baseline. Current recovery copy is an AUD-03 candidate, not permission to overwrite.

Scope remains verification only unless separately approved. Owner advanced to the audit without closing these gates. P1-04 and older P8 staging/launch IN_REVIEW records remain in history; review remaining scope against these latest observations rather than re-running old launch scripts or resetting checkpoints.

## Released feature evidence index

| Area | Current status | Relevant source of proof |
|---|---|---|
| P1-04 production activation/GitHub release | Released, acceptance IN_REVIEW | [Cutover](../verification/p1-04/PRODUCTION_CUTOVER.md), [P9](../verification/p9-01/README.md) |
| P8-01–03 webhook/feed/status activation | Released, current event gate under P9 | [Integration runbook](../runbooks/QAMMARIS_APP_INTEGRATION.md), [P9](../verification/p9-01/README.md) |
| P8-04–07 historical launch drafts/copy/pairing | Executed bounded launch steps; old reviews retained | [Historical backlog](../history/2026-10-07-context/BACKLOG.md); ADR-022–024, not recurring write authorization |
| P8-08 automatic drafts/source pricing | Released PR4/5; earlier device waiver scoped | [Implementation](../verification/p8-08/README.md), [P9 price parity](../verification/p9-01/README.md) |
| P7-10 daily best sellers | Released; homepage price hidden later | [P7-10](../verification/p7-10/README.md), current rules |
| P7-11 dropdown filters | Released PR13 | [P7-11](../verification/p7-11/README.md), [combined release](../verification/releases/2026-10-07/README.md) |
| P7-12 cart/recipient checkout/direct cart motion | Released PR13; device/send limits retained | [Checkout](../verification/p7-12/README.md), [motion](../verification/p7-12/cart-motion/README.md), ADR-028 |
| P7-13 destination skeleton navigation | Released PR13 | [P7-13](../verification/p7-13/README.md), combined release |
| P8-09 recurring Shopee XLSX enrichment | Released PR13 | [P8-09](../verification/p8-09/admin-import/README.md), [runbook](../runbooks/SHOPEE_ADMIN_IMPORT.md) |
| P8-09a exact21 Owner supplement | Completed exact approved batch | [Owner21](../verification/p8-09/OWNER_21_PRODUCTION.md); not global import/publication permission |
| P7-15 relevant typo search | Released PR14 | [P7-15](../verification/p7-15/README.md), ADR-027 |
| P8-10 size/recovery/work-only Shopee review | Released PR15 | [P8-10](../verification/p8-10/README.md), ADR-026 |

Previous detailed items/acceptance remain in [history](../history/2026-10-07-context/BACKLOG.md); older counts, pending release remarks and one-off exceptions have not been discarded. Release proof confirms code availability; remaining device/live workflows are not silently accepted.

## Proposed next items — not started

| Item | Problem / bounded direction | Dependency | Priority / scope |
|---|---|---|---|
| AUD-03 | Centralize Shopee row work/complete summaries outside Blade; distinguish stale-media review from download retry; preserve actor/content-v2/size/no-op/partial/replay behavior | AUD-02 review + Owner execution direction; AUD-F03/F12 | Medium / M |
| AUD-04 | Reuse current catalog card for related products; preserve labels/links/contained assets/keyboard/touch limits | AUD-02; AUD-F07 | Medium / S |
| AUD-05 | Reduce editor/request duplication with explicit dependencies; preserve source price, child ownership, transactions/media cleanup and publication | AUD-02; AUD-F04–06 | Medium / M |
| AUD-06 | Decide money unit/rounding; trace fractional legacy JSON/session totals before any validation/data change | Owner business decision + read-only data evidence; AUD-F08/09 | Medium / M |
| AUD-07 | Narrow human actor audit/diagnostics only if needed; no generic audit/event framework | AUD-05 + retention/scopes decision; AUD-F11 | Medium / M |

AUD-03 proposed acceptance: bounded summary evaluation/query measurement, clear stale-media recovery, unchanged work/complete counts and guarded import behavior; tests for actor/size/partial/no-op/retry plus real375-row mobile/desktop review. No migration/new package, implicit production writes or deployment. Work cannot begin just because this row is next.

Payment gateway, machine-write website API, cloud migration and arbitrary normalization are not active backlog items or launch prerequisites. New requests require a concrete approved scope. Measured query/compatibility-route/name cleanup remains later work only after benchmark/consumer evidence (AUD-F10/F13/F14), not a new approved phase. Unrelated audit findings stay in the [register](../audits/2026-10-07-maintainability.md).
