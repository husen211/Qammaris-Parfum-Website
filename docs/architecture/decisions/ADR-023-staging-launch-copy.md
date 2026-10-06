# ADR-023 — Restricted staging launch copy

Status: Accepted — Owner instruction 2026-10-06; publication remains subject to Owner review.

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
