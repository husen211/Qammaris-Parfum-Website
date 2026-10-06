# ADR-023 — Restricted staging launch copy

Status: Accepted — Owner instructions 2026-10-06; exact 122-product staging publication subsequently approved.

## Problem and decision

113 photographed feed drafts have only description/audience blockers. Owner authorizes cleaned Shopee copy and clear audience evidence, but staging has zero human Laravel users. Creating a fake admin to call maintenance would misattribute audit and expand access without authorization.

Reuse `ProductMaintenancePreviewer`, recorder and transactional apply instead. A small `launch-copy-v1` CLI contract permits description/gender only on visible, nonactive, connected Shopee drafts whose other publication requirements are met. It refuses production. The operator uses the already authorized staging SSH session; null human actor IDs, contract version and `owner-authorized-cli-` source label distinguish this operation from human administration. No credential, role, route, schema or public API is added.

Preview retains immutable source/catalog/row hashes and before snapshots. Apply rechecks payload, current catalog, row timestamp/fingerprint, mapped providers, draft visibility and publication prerequisites under existing product locks. Replay returns the persisted result. Failures preserve transactional behavior. Human maintenance routes keep their own contract; machine apply refuses a human maintenance batch. No publication, source availability, offer, slug, image or identity mutation is allowed.

The offline curation tool pairs existing Shopee IDs and unchanged media-baseline names. It records original text, cleanup removals and exact audience evidence. Comparison/inspiration and aroma/bottle color do not establish audience; missing/conflicting evidence remains blank. A private offline HTML review shows verified existing website covers and exact proposed copy, with separate correction CSVs. It has no publication operation.

The same five-file review can be served on staging behind its existing HTTP Basic Auth after confirming anonymous 401 and an authenticated Owner browser render. No protection/account is created or changed. Raw sources/captures/maintenance input stay private; the review is a snapshot, with no product mutation or published-scope bypass.

## Tradeoff

The existing Laravel operations already supply the needed transaction/audit/concurrency behavior. A separate bounded contract and nullable machine actor add a few checks, avoiding a second mutation implementation, generic write API, new account or new audit schema. This is a trusted operator tool, not a substitute for future scoped API authorization. Attribution identifies the authorized CLI operation, not a specific human identity; preserve the operational deployment record and Owner approval alongside batch ID.

## Production and recovery

All 113 remain drafts until Owner reviews the concrete package; unclear audience still blocks publication. The 266 image-less drafts are untouched. Production backup is deferred until immediately before separately approved cutover. Staging data must never replace the production database wholesale.

Code rollback restores the exact prior recorder/apply files and disables the new command, retaining audit and curated data. It does not undo applied fields. Forward corrections require fresh guarded preview; before snapshots are evidence, not permission for automatic restore.

## Approved continuation after pairing

Owner requests continuation of photographed launch products while leaving all image-less drafts alone. The original113 cohort is historical; a fresh structurally complete175 cohort is derived from current exact provider mappings and actual Laravel readiness. Reuse the same unchanged CLI/application guards for46 clear blank-audience updates; do not loosen structural or publication protection. The supplemental read-only preparation tool records original Shopee copy/evidence, current guards and exact publication-candidate IDs. Other taxonomy/offer facts and ambiguous audience stay in review. No new mutation layer/contract, role, account or deployment is justified. Owner approval of a concrete current subset still precedes publication.

## Approved publication of the concrete subset

Owner's subsequent explicit approval covers exactly 122 reviewed staging IDs in CSV SHA256 `c9eda30849a7e439fdcdeb6be4cd958e6f5f71a146da81b0550b652d35b48013`. Existing `PublishProduct` is sufficient for each mutation; the admin HTTP flow requires a human account while staging has none. A fixed one-off SSH operator script supplies the existing operation with immutable preview, identity/source/media/readiness guards, ordered locks and one transaction, recording nullable-actor before/after audit as `launch-publish-v1`. It is deliberately bound to this exact list and staging database rather than creating a reusable bulk-write API, synthetic admin or another application layer. The separate audit contract prevents copy/maintenance tools from interpreting publication rows as field edits. The real publisher and audit writes were rehearsed with complete rollback, including failure on the last row, before approved apply; replay is a no-op.

This adds only an operator script and existing-table audit, not a new product mutation abstraction, schema, account or web route. Keep the original lists, sources and audit private. Production remains separately approved; staging is never copied wholesale into production. Recovery requires an explicitly scoped forward unpublish/correction using before snapshots; removing the script or rolling back code does not undo publication.

## Five explicitly approved factual corrections

Owner subsequently approves four named perfumes represented by five existing records29/34/35/36/350, including two BaliCliff1 sizes. Existing launch-copy contract cannot alter category or offers; retain that restriction. Existing Eloquent field writes, `SyncSingleOffer` for exactly two missing offers and `PublishProduct` suffice, composed in a fixed staging operator `staging_publish_owner_corrections.php`. Exact immutable CSV/hash/IDs, expected before blockers, original price/identity/source guards, persisted preview, atomic rehearsal/apply and replay give reviewable safety without a generic mutation API, new service layer, synthetic admin or relaxed application authorization. New existing-table audit contract `owner-correct-v1` distinguishes this approved correction/publication from the earlier122 publication-only batch. Cost: a one-off bounded operator retained with its private input; it is not generalized or adapted for production. Batch7 before/after supports a later explicitly approved guarded forward correction, never automatic rollback of newer stock.

## Owner-approved49-row enrichment wave

A further concrete49-row list is approved from the supplied175-row research JSON. Preserve earlier immutable operator scopes. Separate fixed staging tool `staging_publish_enrichment_wave.php` composes the same existing Eloquent/SyncSingleOffer/PublishProduct operations and audit contract owner-enrich-v1(batch8), retaining supplied-source hash and explicit Owner approval. Allows approvedWanita plus existingEDP/EDT/Extrait for the exact input; no guessing, brand aliases, defaultUnisex, category-conflict override or taxonomy creation. Supplied verified/input labels are provenance rather than independent research certification. This costs a retained one-off tool/input; avoid broadening machine maintenance rights or creating a genericwriteAPI. Existing contracts/auth/application deployment remain untouched.
