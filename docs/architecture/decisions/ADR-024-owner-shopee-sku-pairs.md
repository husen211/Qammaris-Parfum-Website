# ADR-024 — Owner-approved Shopee SKU pairing on connected drafts

Status: Accepted — Owner instruction 2026-10-06; staging only.

The original name/concentration matcher paired 179 drafts. Owner supplied reviewed app-team matching evidence for 298 source rows, plus 56 candidate-review rows and 17 unmatched rows. Re-running the old stricter matcher would discard that approved evidence. Resolve the supplied exact Majoo SKU against current synchronized snapshots, then retain the UUID as the stable website identity. Missing, duplicate, hidden, unmapped, conflicting and published targets remain protected.

Existing draft creation deliberately skips already mapped UUIDs; ordinary Shopee import would create new drafts for previously unseen Shopee IDs. Neither operation safely adds supplemental media/copy to these existing UUID drafts. A focused staging-only pairing operation therefore records a separate `qammaris-pairs-v1` preview/apply batch, reusing identity mapping, product snapshots, payload hashes and the existing safe image queue/downloader. No schema, account, route, generic repository or dependency is added.

The immutable preview captures source and catalog hashes, exact resolved target and source-file provenance. Apply rechecks unique SKU ownership, payload/catalog/source changes under the feed checkpoint lock before any write. Only blank descriptions are filled and missing Shopee identities attached. Existing copy/media, website names/prices/taxonomy/audience/URLs and publication are retained. A replay returns the existing audit result. Image candidates are queued only for targets with no active media, with draft/visible UUID/Shopee ownership checked again by the worker. Cover success precedes additional photos; retries retain successful outcomes.

The 56-row private review displays supplied candidates without preselection and exports choices for a subsequent fresh guarded preview. It does not grant publication or silently apply ambiguous matches. It remains behind existing staging review protection; no credential/permission changes. Source images for the review are downloaded and verified, never hotlinked.

Tradeoff: a small bounded operation is needed because creation and human maintenance have different mutation scopes. Reusing their identity/media/audit primitives avoids duplicate download/storage logic. CLI audit identifies the Owner-authorized operation, not a fabricated human account. This is not a public machine write API.

Recovery: disable the new command and restore the allowlisted PHP overlay if necessary. Retain batch provenance, original code and stored files; data corrections need a fresh guarded preview. Never restore staging wholesale over production. Production backup/cutover remains separately approved.
