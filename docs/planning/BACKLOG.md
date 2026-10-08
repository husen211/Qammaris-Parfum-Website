# Qammaris — backlog current

Updated 2026-10-08, approved Journal production release; audit scope retained. This board is the entry point; detailed prior scope/acceptance/results remain in the [historical backlog](../history/2026-10-07-context/BACKLOG.md), [audit](../audits/2026-10-07-maintainability.md), ADRs and linked verification. Historical “next/unstarted/pending” paragraphs are relative to their date. Do not implement them again or treat them as new authorization. Current rules: [BUSINESS_RULES](../product/BUSINESS_RULES.md); executable flow: [ARCHITECTURE](../architecture/ARCHITECTURE.md).

**Release update:** Owner authorized review/consolidation, staging/MySQL migration and production release, plus a temporary staging actor inside a fully rolled-back test transaction. AUD-01–07 are complete within their approved scopes and included in merged PR24 / production `9325df4`. [Release proof](../verification/audit-release/README.md) supersedes implementation-phase pending-release/MySQL-unconfirmed statements retained below and in dated verification. P9 acceptance limits remain open; this is not 100% closure of the original program.

## Status and working limit

BACKLOG = proposed/not approved for execution; READY = accepted bounded scope ready; IN_PROGRESS = active work; IN_REVIEW = complete within stated scope, review/limits still open; BLOCKED = named external prerequisite; DONE = accepted criteria and stated verification completed. One approved implementation item at a time. A released feature may have separate IN_REVIEW device/runtime acceptance; do not infer either code unreleased or all tests passed from the label alone.

## Active separate program — Qammaris Journal

Owner approved the [Journal plan](QAMMARIS_JOURNAL.md), BLOG-01–05 implementation, transaction-only staging fixtures, five additive staging/production migrations after staging passed, and a blog-only production release. Order work is excluded. [BLOG-06 release evidence](../verification/blog-06/README.md) supersedes earlier phase “not deployed/staging unconfirmed” statements; it does not close P9. Owner subsequently authorized production API activation and local credential setup2026-10-08; [activation evidence](../verification/journal-activation/README.md).

| ID | Status | Current scope / next prerequisite |
|---|---|---|
| BLOG-01 | DONE | Safe shared writes, revisions, retained images, archive/restore and audit installed via PR31; selected MySQL DDL/replay and staging transaction passed. [Implementation](../verification/blog-01/README.md) |
| BLOG-02 | DONE | CMS fields, Tiptap visual/HTML editor, taxonomy/tags, authenticated preview and scheduling installed. Legacy IDs/URLs/content/authors preserved. [Implementation](../verification/blog-02/README.md) |
| BLOG-03 | DONE | Public responsive Journal/search/TOC/share, metadata/schema and scheduled sitemap boundaries installed; live three article URLs/schema and five widths checked. [Implementation](../verification/blog-03/README.md) |
| BLOG-04 | DONE | Owned responsive/cropped media and full editorial components/relations installed; GD/WebP staging and local native-file-input browser upload passed. [Implementation](../verification/blog-04/README.md) |
| BLOG-05 | DONE | Scoped draft-only API/schema/admin assignment installed; production gate enabled by separate Owner approval2026-10-08; one local machine actor/token. Staging auth/replay/revision/ownership/upload guards passed. Local activation/read access verified; cloud-only secret provisioning is not configured. [Implementation](../verification/blog-05/README.md) |
| BLOG-06 | IN_REVIEW | Approved production release completed via PR31 /3c8d35f; CI/release/MySQL/runtime/public browser/touch passed. Owner editorial walkthrough, three legacy alt fields and one prose price/stock article review remain; physical Safari/production upload/external SEO and sustained load unconfirmed. [Release](../verification/blog-06/README.md), [runbook](../runbooks/JOURNAL_RELEASE.md) |

BLOG-01 acceptance implemented: failed file/DB/audit write keeps current article/image; committed image survives cache outage; stale editor/archive/restore fails; no-op/views preserve editorial time/revision; legacy ID/slug/author/content/image formats survive additive migration and replay; archive excluded from public listing/category/detail/sitemap and restored as draft. No historical body rewrite, purge, API, package install, public redesign or production mutation. 403 Laravel tests/2804 assertions and31 JS tests passed; browser widths320/375/390/768/1440 checked with screenshot/keyboard/two-tab conflict evidence. Full check/cleanup/limitations in evidence.

## Original catalog program board

Unrelated finding SEC-DEP-01 (BACKLOG, not authorized for automatic package upgrades): npm audit reports concurrently9.2.4/shell-quote1.9.0 (critical) and source-map-js1.2.1 (high). Versions are identical in cb0a1c3 and BLOG-02; not introduced by Tiptap. Assess/update in a separately scoped dependency task, then repeat audit before release.

Unrelated finding SEC-DEP-02 (BACKLOG): BLOG-04 locked Composer audit reports two existing league/commonmark advisories, medium GHSA-97jj-33gv-5xf9 and high GHSA-3q6v-r5mr-hxv8. BLOG-04 does not change either lockfile; Journal content uses the existing HTML sanitizer, not Markdown conversion. Assess affected application paths and package update separately; dependency audits are not clean.

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
| AUD | Maintainability/context | DONE | AUD-01–07 reviewed and released through PR24; targeted MySQL migration/runtime rehearsal passed; P9 acceptance is separate |

Statuses are not a fabricated progress percentage. P1/P8/P9 are not marked complete merely because the current code is live. [MASTER_PLAN](../architecture/MASTER_PLAN.md) defines closure and future-program boundaries.

## FIX-SUPREMACY — Correct Pink Shopee content assigned to Purple — DONE

Owner approval 2026-10-07: explicitly correct the observed production Pink/Purple mismatch. Exact scope: Shopee42131600634 previously assigned to Purple879, belongs to Pink686. One transaction reassigns that Shopee identity only, completes Pink with the verified100ml/Wanita/EDP copy and three checksum-verified independent photo copies, publishes Pink, returns Purple to draft and soft-archives its incorrect Pink photos. Original application UUIDs, source prices/status, product IDs and slugs retained; no source-system/database/schema/credential/deployment changes.

Production audit batch8 stores two before/after rows; private preview/receipt retain recovery evidence and original media files. Shared importer rebind guards remain unchanged. Local53tests/264assertions passed; production preview/rehearsal rollback and verify passed; public Pink200/Purple404 and all three Pink gallery images loaded. Original applied batch6 remains historical evidence, not rewritten. Details, limits and scoped recovery: [correction verification](../verification/supremacy-pink/README.md). This correction does not start AUD-03 or close P9.

## AUD-07 — Narrow admin product change history — DONE

Owner execution approval: “gas aud 07”. Separate scope/retention answer: “Setuju tabel audit, simpan tanpa hapus otomatis”. Scope AUD-F11: one additive table, explicit admin actor on product create/edit/archive/restore and gallery primary/move/archive, safe before/after metadata/hashes in the business transaction. No UI, credentials/permissions, API/framework, backfill, purge, live migration or deployment.

Acceptance implemented:
- Changed allowlisted groups only; no-op/validation/authorization/foreign child/readiness failure does not commit history. Audit failure rolls back mutation; editor compensation retains old media and removes only newly stored files.
- Authenticated account ID comes from the session, never request actor fields. Description/notes/SKU/image keys hash-only; request bodies, URLs/files, customer/user credential data excluded. Existing feed/import evidence remains separate; shared accounts cannot distinguish physical human from automation.
- Historical IDs retained without FK cascade/nulling; no automatic deletion or invented old actors. No universal history/restore/tamper-proof claim or new history page.
- Baseline43tests/217assertions; new14tests/110assertions; intermediate110targeted tests/695assertions; final382Laravel tests/2666assertions and31Node tests passed. Strict Composer metadata and changed-PHP Pint passed; no UI/browser test claimed.
- Selected migration/replay on an isolated historical SQLite copy445products: all21original non-migration table fingerprints unchanged, audit table empty, migration recorded once. Subsequently, staging/MySQL migration/editor/audit/no-op passed inside a fully rolled-back transaction; targeted production DDL/replay passed with nine existing table fingerprints unchanged. Concurrent load/long-term growth remain Not confirmed. Production deployment pending-migration guard stayed intact.

Files, safe diagnostic steps, schema/data/media impact and retained-table rollback: [AUD-07 verification](../verification/aud-07/README.md), [ADR-031](../architecture/decisions/ADR-031-admin-product-change-history.md), [runbook](../runbooks/ADMIN_PRODUCT_AUDIT.md). Reviewed/released through PR24: [proof](../verification/audit-release/README.md). AUD-07 is the last item of this bounded audit queue. Recommended next is separately scoped P9 acceptance, not a new refactor or automatic next phase.

## AUD-06 — Whole rupiah and current-catalog cart totals — DONE

Owner execution approval: “gas aud 06”. Final money decision: “gajadi pake bilangan bulat aja … gausa desimal”, superseding the earlier decimal reply. Scope: AUD-F08/F09 only. Whole new prices in editor/CSV/shared manual offer operation, truthful legacy price handling, exact cart/WhatsApp calculation and current-catalog mutation totals. No schema/package/data rewrite, source contract change, production mutation or deployment.

Acceptance implemented:
- New selling/comparison prices reject nonzero fractions; database decimal zeroes remain accepted. Whole amounts have no decimal suffix. Hypothetical legacy fractions remain unchanged, visible accurately and flagged in the editor; no implicit rounding/unpublishing.
- Add/update/remove totals resolve all remaining current offers, never session prices; unresolved totals are null, `/cart/data` retains 409. Numeric JSON compatibility and quote/availability/publication/recipient guards remain.
- Read-only local historical snapshots:180 and445 products, zero fractional base/comparison/offer prices. Production was not measured during implementation; release preflight subsequently checked411 offers with zero fractions. Local default MySQL was not used.
- Final Laravel368tests/2556assertions,31Node tests, changed-PHP Pint, strict Composer metadata, Vite build and diff checks passed. Browser390×844/1440×900 money flows and320px longer amounts checked; a detected320px cart overflow is fixed. Genuine Safari/touch and live WA handoff not claimed; complete matched desktop-before screenshots were not captured.

Changed modules, consumer trace, screenshots, temporary-fixture cleanup, limits and code-only recovery: [AUD-06 verification](../verification/aud-06/README.md), [ADR-030](../architecture/decisions/ADR-030-whole-rupiah-and-current-cart-totals.md). Reviewed/released through PR24: [proof](../verification/audit-release/README.md). Device/WA limits remain under P9.

## AUD-05 — Editor save/validation and explicit dependencies — DONE

Owner execution approval: “lanjutt” after AUD-04 recommended AUD-05. Scope: AUD-F04/F05/F06 backend cleanup only. Shared `SaveProductEditor` for create/update transaction/media/publication; shared stable request rules/messages/comparison check with explicit create/update exceptions; Shopee guard/collaborator injection. No Blade redesign, schema/package/business-rule change, production mutation or deployment.

Acceptance implemented:
- HTTP redirects/input/errors/context stay in controllers; source-price protection, parent-owned offer, stable IDs/SKU/slugs, manual availability lock and publication/readiness guards remain.
- Failed attachment/publication restores DB state and compensates only newly stored files. Shopee actor/payload/media guards, checkpoint/source/product locks and dispatch timing remain intact; contracts stay separate.
- Baseline68tests/425assertions; expanded94tests/616assertions; full Laravel354tests/2467assertions,31Node tests, targeted Pint, strict Composer metadata, Vite build and diff checks passed.
- No UI changes or browser check claimed. SQLite does not verify live MySQL contention/webhook timing; existing UI/device acceptance limits remain. Existing build warnings and Blade duplication are documented, not fixed by this item.

Evidence/changed files/limits/code-only rollback: [AUD-05 verification](../verification/aud-05/README.md), [ADR-029](../architecture/decisions/ADR-029-shared-product-editor-save.md). Reviewed/released through PR24: [proof](../verification/audit-release/README.md). Broader acceptance remains under P9.

## AUD-04 — Shared related-product cards — DONE

Owner execution approval: “lanjut aud 04”. Scope: public detail related-product cards only, resolving AUD-F07. Reuse `_catalog-card` with h3 headings, visible active-offer price and lazy media; delete the obsolete sole-consumer `_card`. Existing same-brand eligibility/order/limit, detail query context, source availability, gallery and routes are unchanged. No schema, catalog/media mutation, dependency or deployment.

Acceptance implemented:
- Square contained media, existing whitespace balancing and local error fallback now apply to related cards. One accessible native link covers image/name; keyboard focus and existing click feedback are reused without new hover/motion logic.
- Laravel30 targeted tests/304 assertions and31Node tests passed; targeted Pint, Vite build and diff checks passed. Red regression on the old card confirmed missing h3 and image-recovery hooks.
- Real Chrome local350-product catalog: before/after390×844 and1440×900; additional320/375/768 widths without horizontal overflow. Mouse image click, keyboard Tab/Enter, native history back and related-link query context verified. Final Kembali click/control cleanup were interrupted by browser detachment; genuine touch/Safari and failed-image runtime were not confirmed.

Evidence, screenshots, limits and code-only rollback: [AUD-04 verification](../verification/aud-04/README.md). Reviewed/released through PR24, with production390/1440px detail/link checks: [proof](../verification/audit-release/README.md). Genuine device acceptance remains under P9.

## AUD-03 — Shopee review summaries and media recovery — DONE

Owner execution approval: “Berikutnya AUD-03”. Scope: one shared read-only row summary for counts, filters and rendering; batch readiness evaluation; clear download retry versus protected media review. No import framework, dependency, migration, automatic publication, production mutation or deployment.

Acceptance implemented:
- `ShopeeContentReview` replaces duplicated controller/Blade calculations. `EvaluateProductPublicationReadiness::forReview` batches slug checks and evaluates each distinct target once; apply/publish still use fresh `handle` validation.
- Failed downloads offer retry/manual upload; protected target/media/capacity outcomes require editor review or a new export pair. New Shopee blocked outcomes carry additive reason codes; legacy outcomes remain readable without rewriting historical rows. Recheck of pending content does not reset applied-media baselines.
- Same375-row local fixture:408 →25 review queries,375 →1 slug checks;6 work/369 complete unchanged. This is a SQLite read/render measurement, not a production latency claim.
- Laravel345tests/2384assertions and31Node tests passed; targeted Pint, Vite build and diff checks passed. Real Chrome review at390×844 and1440×900 covered375 rows, recovery, pagination, size-choice search, ready/queued/empty/disabled states. Existing build warnings and genuine touch/live upload limits remain explicit.

Evidence/screenshots/cleanup/rollback: [AUD-03 verification](../verification/aud-03/README.md). Reviewed/released through PR24: [proof](../verification/audit-release/README.md). Applied-media holds retain their guards; release is not an overwrite or re-import instruction.

## AUD-02 — Consolidate current repository context — DONE

Owner execution approval: “oke gas aud-02”. Scope: documentation only across AGENTS, README, rules, architecture, plan and board; safe current/history navigation and unique ADR references. No application/data/schema/media/config/credential/deployment work or next refactor.

Dependencies: AUD-01 source-referenced findings and already accepted Owner rules, particularly automatic connected pricing/drafts (ADR-025), Shopee recovery (ADR-026), search (ADR-027), recipient checkout (ADR-028).

Acceptance:
- Fresh session can follow AGENTS → rules → architecture → active board → relevant ADR/runbook/source without reading every historic phase.
- Clearly identify UUID draft creation, automatic valid prices/source status, manual/Shopee persistent media, human publication and recipient WhatsApp ordering.
- Retain dated launch approvals and decision/evidence text; validate moved relative links and alias old checkout ADR filename after separating its duplicate number.
- Preserve P9 unknowns; no invented completion, business rule, code or infrastructure change.
- Define one document owner per context purpose and the future-feature update convention, rather than creating competing context files.

Outcome: current documents reconciled; four complete pre-consolidation snapshots retained with source hashes/provenance; original checkout ADR-026 becomes compatibility alias to canonical ADR-028; ADR-026 Shopee and ADR-027 search retain their IDs. Earlier scope decisions are explicitly marked partially superseded, not erased. Historical product counts/one-off launch permissions are separated from recurring rules. Documentation-only diff/link/history checks and limits: [AUD-02 verification](../verification/aud-02/README.md). Reviewed/included in merged PR24: [release proof](../verification/audit-release/README.md).

## AUD-01 — Repository maintainability audit — DONE

Investigation complete, no refactor. [Report](../audits/2026-10-07-maintainability.md) records 14 findings, no established Critical finding, actual code boundaries, data/runtime unknowns and bounded follow-ups. AUD-02 approval accepts the documentation follow-up, not all proposed code/business changes. Historical Laravel339/2306assertions and31Node baseline plus related-card browser390/1440 screenshots belong to AUD-01 evidence; they are not new tests run by AUD-02.

## P9-01 — Bounded production acceptance — IN_REVIEW

[P9-01 evidence](../verification/p9-01/README.md): last observed PR15/0dc6924; health/feed authentication, current public readiness/local file existence, cached-source price/status parity, scheduled reconciliation and one worker passed within the read-only scope. The Owner's real batch apply/stored photos were observed independently; agent did not apply it. These are dated observations, not immutable counts.

Remaining acceptance limits:
- Physical Safari/genuine touch single-tap and scroll not tested.
- Native file chooser/upload path blocked by existing extension permission; permission was not changed. Persisted Owner import/CDN outcomes are confirmed, native upload path is not.
- New source-event timing/replay/outage in current production not exercised; existing source tests/staging event evidence do not replace it.
- Populated production recipient checkout/WA handoff not submitted; empty cart/navigation passed. No fabricated customer/order.
- Two protected media-baseline holds preserve published photos; retry alone cannot reconcile stale baseline. AUD-03 improves local recovery guidance; production resolution is still unconfirmed and is not permission to overwrite.

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

## Work remaining after AUD-07

The bounded AUD-01–07 queue is reviewed, released and complete within documented scopes. There is no approved AUD-08.

1. Review/consolidation, staging/MySQL rehearsal, targeted production migration and GitHub release are complete: [proof](../verification/audit-release/README.md). The pending-migration guard was retained; no product data/media rewrite occurred.
2. Close or explicitly scope-waive outstanding P9/device/upload/new-source-event/populated-checkout/protected-media and Owner acceptance limits documented above. Local audit tests do not satisfy them.

Payment gateway, machine-write website API, cloud migration and arbitrary normalization are not active backlog items or launch prerequisites. New requests require a concrete approved scope. Measured query/compatibility-route/name cleanup remains later work only after benchmark/consumer evidence (AUD-F10/F13/F14), not a new approved phase. Unrelated audit findings stay in the [register](../audits/2026-10-07-maintainability.md).
