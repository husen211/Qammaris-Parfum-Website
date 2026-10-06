# ADR-025 — Recurring app drafts and automatic source prices

Status: Accepted for P8-08 implementation; deployment pending.
Date: 2026-10-06.

## Problem and evidence

`SyncQammarisAppFeed` previously stored unlinked snapshots and applied stock only. `PrepareQammarisAppDrafts` was a launch CLI operation, while admin product entry/import still required operators to find and create app records manually. Source-price proposals were never automatically applied to existing connected offers. Owner now requests recurring automatic drafts and explicitly approves automatic existing-price updates.

## Decision

Keep the existing validated cursor feed, webhook wakeup, queue, reconciliation, UUID mapping and Laravel admin boundary. The worker uses the existing draft operation for visible new UUIDs and applies source prices alongside availability. Do not introduce a new repository, API, event bus or import table. Existing checkpoint -> source -> product transaction ordering covers catalog/identity/audit/checkpoint; a failed creation rolls everything back. Equal/older revisions remain no-ops.

Reuse `PrepareQammarisAppDrafts` creation rather than duplicating the launch rules. Before creation, conservative legacy name/SKU candidates are held; explicit size/concentration are parsed only when supported by the existing parser. No automatic publication, guessed audience, description, photo, source-department category or SKU-as-identity.

`SyncQammarisAppPrice` validates positive integer source prices within the existing database range. `SyncSingleOffer`, shared by forms/imports, also enforces the cached connected source price, protecting it against stale or crafted admin saves. Invalid/missing/hidden source retains the last valid price, and incomplete new drafts require review. Existing offer IDs/size/SKU/local quantity remain unchanged by feed updates. `base_price` remains the offer mirror, with the existing incomplete-draft exception; an invalid comparison price is cleared. Name, slug, publication and media are not overwritten on connected products. Availability/price before-after information uses existing app audit rows.

Admin GET inbox reads cached snapshots only, never fetches or mutates source data. Auth/admin middleware and CSRF protect writes. Manual sync queues the existing job. Historical unlinked snapshots require a human-bound immutable preview, accepted confirmation, source/catalog revalidation and idempotent apply. Existing null-actor CLI batches remain compatible; a human cannot apply another actor's batch.

## Tradeoffs and boundaries

Source prices are now authoritative for connected products; operators correct prices in the internal app. This intentionally supersedes the earlier price-proposal-only and stock-worker-only parts of ADR-021/022. Unconnected manual products keep their existing price editing. Publication remains separate. Conservative duplicate holds may need operator UUID mapping; this item exposes them but does not add a new rebinding UI.

The inbox paginates 25 cached rows in memory after loading the current few-hundred-product catalog. New UUID creation uses the existing catalog matching query; no speculative large-scale pipeline. No schema, package, credential or media migration. Raw Shopee XLSX upload/description curation is a subsequent backlog item; this item clarifies the existing curated CSV path and manual editor storage.

## Rollout and recovery

Review branch/CI and real browser evidence before deployment. No checkpoint reset or historical mass price replay is part of release. Newer source revisions apply prices; preexisting unlinked snapshots use preview/apply. Production historical price mismatches are not confirmed locally. Reverting code stops recurring draft/price behavior but retains new draft/identity/audit data and checkpoints. Do not delete newly created records, reset checkpoints or discard media as a code rollback.
