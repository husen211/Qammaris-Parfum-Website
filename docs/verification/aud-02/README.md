# AUD-02 — Current repository context consolidation

Date: 2026-10-07. Documentation implementation complete; branch/PR review remains. Owner explicitly approved AUD-02 only. Source baseline `6956667` (AUD-01 branch); no next implementation or deployment is started.

## Outcome and authority

Current read path: [AGENTS](../../../AGENTS.md) → [BUSINESS_RULES](../../product/BUSINESS_RULES.md) → [ARCHITECTURE](../../architecture/ARCHITECTURE.md) → [BACKLOG](../../planning/BACKLOG.md) → relevant ADR/runbook/source/tests. [README](../../../README.md) owns setup/check commands and document responsibilities; [MASTER_PLAN](../../architecture/MASTER_PLAN.md) owns program goals/dependencies/closure.

Resolved proven contradictions using already accepted decisions:

| Previous context | Current interpretation / authority |
|---|---|
| Price remains a proposal; feed only stores unlinked snapshots | ADR-025: new visible UUID drafts and automatic valid connected source prices; guarded historical preview remains separate |
| Inquiry-only cart, no recipient/checkout | ADR-028: direct cart, required recipient fields, current quote guard, WhatsApp composer; no inventory reservation/payment claim |
| Future required R2/auto-deploy not active | Current persistent public product disk and GitHub release observed in dated cutover/P9 proof; no new storage or pipeline changes |
| All release/pending notes read as current | Compact current board and evidence index; historical snapshots retain full original wording and scoped launch permissions |
| Two decisions numbered ADR-026 | Shopee stays canonical ADR-026; checkout is ADR-028, with old filename/headings retained as compatibility alias |

The UI skill's domain summary and dated public UX baseline now point to current rules. This does not apply a redesign or change touch/keyboard/approval requirements. Older ADR-004/020/021/022 retain original bodies with scoped supersession notes; ADR-025/027 headings now reflect their recorded releases. No accepted behavior was newly chosen.

## History and link verification

The four complete prior context documents plus original checkout decision are preserved in [history](../../history/2026-10-07-context/README.md). [Manifest](history-manifest.json) records origin commit, original working-file SHA-256, line counts and exact Markdown rebases. Original-byte hashes capture the pre-edit files; source-commit comparisons normalize Windows newlines and reverse recorded link rebases.

Checks actually run locally:

Machine-readable results: [checks.json](checks.json). The initial check found the not-yet-created verification README link and an ADR-heading scanner that did not accept historical `ADR 001/002` spacing. The report was created and the scanner accepts both preserved heading styles; final checks below passed. These were documentation/checker issues, not application failures.

- Each of five archived bodies compared against `git show 6956667:<source>` after documented newline/link normalization; all match. No historical decision/evidence paragraphs removed.
- Local Markdown targets and referenced heading anchors checked across every modified/new Markdown document, including archive/index/alias. External URLs are retained; no network link-health claim.
- Repository-wide inbound anchor search for the four rewritten context paths found no existing anchored references. Checkout alias preserves original section headings and points to canonical sections; dated verification/audit links to old filename are retained.
- Canonical ADR headings checked: IDs 001–028 unique; compatibility alias excluded from canonical count. New references use the descriptive canonical filename.
- Tracked diff and explicitly staged file list checked for documentation-only scope; `git diff --check`/staged whitespace checked. Existing unrelated `tools/__pycache__/` is not changed or staged.
- Current summaries checked against relevant source entry points and accepted ADRs; stale inquiry/price/storage/deploy text remains only as clearly labeled history or superseded context.

Local application tests/build/browser were **not rerun** for documentation-only work. AUD-01's earlier 339 Laravel/31 Node results are historical evidence, not AUD-02 results. PR CI may run its existing PHP/frontend suites; its actual outcome is reported on the PR, without changing any workflow.

## Impact, limitations, and recovery

Files changed: AGENTS/README; business rules, architecture, master plan, backlog; ADR supersession/release headers plus checkout alias/canonical/index; dated history/manifest; public UX baseline status, UI skill summary and deployment runbook current/history notice. Application code, schema/migrations, product/import/customer records, media, env, credentials, permissions and infrastructure are untouched. No database/media backup or remote action occurred.

No UI change: browser viewports/screenshots are not applicable to AUD-02. No production facts were freshly fetched; production release/count/worker claims reference dated evidence. Remaining P9 genuine-touch/native-upload/new-event/populated-checkout acceptance is still [IN_REVIEW](../p9-01/README.md), and the original program is not closed. The docs do not authorize bulk publication, data replay or release.

Rollback is a documentation commit revert; no data migration/recovery is required. If any downstream work references canonical ADR-028, preserve its alias/index or forward-fix those references rather than breaking them. Main merge can trigger the already configured production release; do not merge/deploy without separate Owner approval. This branch is stacked on the existing documentation audit branch, not a production change.

Recommended next item: **AUD-03**, narrowly extracting Shopee row work/complete evaluation and clarifying stale-media versus download retry, after Owner direction. No code refactor or next phase is started.
