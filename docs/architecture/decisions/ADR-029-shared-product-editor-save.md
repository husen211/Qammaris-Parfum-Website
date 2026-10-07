# ADR-029 — Shared admin editor save and Shopee guards

Date: 2026-10-07. Status: accepted for AUD-05 implementation; release pending review.

## Concrete problem

`AdminProductController::store/update` duplicated field assembly, note parsing, offer/media orchestration, transactions, publication and compensating file cleanup. Two HTTP requests also duplicated stable field limits/messages and comparison-price validation. Adding a save step in only one path could leave uploaded files or diverge create/edit behavior. `ShopeeContentImageTarget` and the image queue resolved `ApplyShopeeContent` merely to validate proposals/batch ownership; write and controller methods hid actual collaborators behind `app()`.

## Decision and tradeoff

Keep Laravel Form Requests, Eloquent, the existing product operations and native redirects. Both admin writes invoke `SaveProductEditor` with validated editor data, validated uploads, an explicit publish flag and an optional existing product. Its explicit field allowlist, transaction and compensating cleanup are shared; `SyncSingleOffer`, `AttachProductImage` and `PublishProduct` remain the authorities. Controller-specific success/error messages, input recovery and return context stay in the controller. A controller-private helper would still tie the save operation to HTTP and duplicate orchestration for another entry point; this one operation has two real callers today, not a speculative API.

`ProductEditorRequest` shares stable validation/messages/comparison checks; the concrete store/update requests retain their deliberate differences: draft/publish defaults, current inactive taxonomy on ordinary edit, parent-owned offer/SKU rules, app availability protection and current-image publication/limit checks. Checkbox presence is explicitly normalized using the existing convention. No generic form builder, DTO, repository or validation framework. This adds one inheritance level and one save collaborator to trace, in exchange for a single owner of the duplicated transaction/cleanup sequence.

`ShopeeContentGuard` owns the unchanged actor/contract and payload assertions shared by content writes, image queue and image target. The target no longer resolves the write orchestrator. Existing identity/offer/publication collaborators and controller image queues are injected. The shared guard is specific to the real Shopee contract, not a universal import validator; CSV, launch pairing and maintenance contracts stay distinct.

## Preserved behavior and limits

- Create starts as unknown/draft; existing publication defaults and explicit publish gates remain. Update does not accept crafted publication/slug/base-price fields.
- Source-connected prices and manual availability lock checks retain their existing operations/ordering. Existing product/offer IDs, SKU, slugs and media are preserved on ordinary edit.
- File storage stays inside the editor transaction as before; only newly stored paths are compensated after rollback. Existing files are never cleanup candidates. Cleanup failure is reported without replacing the original error. This is not an atomic database/filesystem guarantee or a new concurrency protocol.
- Shopee checkpoint/source/product lock ordering, payload hashes, proposal fingerprints, actor attribution, stale holds, retry behavior and job dispatch timing stay unchanged. No source row/media baseline reset or new mapping rule.
- Blade layouts differ intentionally between new and connected/existing products. No UI extraction/redesign here; visible form duplication remains a possible later task only with concrete need and browser evidence.
- No schema/package/business-rule/data/media migration, new API, production operation or automatic next phase.

## Verification and recovery

See [AUD-05 verification](../../verification/aud-05/README.md). Code rollback reverts this bounded commit; it requires no data restore, file deletion or checkpoint rewind. Deployment/restarting long-running workers follows the existing separately authorized release procedure.
