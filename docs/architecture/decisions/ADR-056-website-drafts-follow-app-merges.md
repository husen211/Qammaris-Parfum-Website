# ADR-056 — Website drafts follow App merges

Status: accepted (CAT-APP-MERGE-01, Owner 2026-10-29, card #138). Stage 2 of the Owner's product simplification. Released PR #139 (`e055083`, 2026-10-29).

## Context

A new product is first added in Qammaris App while OTW (`source=app`). When the Majoo export is imported, the App matches it by name and merges the OTW product into the Majoo product: the OTW UUID becomes inactive with `merged_into` = the Majoo UUID (hidden tombstone). The Website created a draft for the OTW UUID and, independently, a second draft for the Majoo UUID. The OTW draft stayed behind as a hidden duplicate the Owner had to find and archive. On production on 2026-10-29, 10 of the 15 "Disembunyikan aplikasi" rows were such OTW tombstones; their 9 drafts were archived by hand (#477, #502, #639, #894–#897, #919, #934). Archived products still appeared in the default admin Products list (490 shown for 481 live/draft products), which made the cleanup look unfinished.

ADR-005/ADR-025 forbid silently rebinding a provider identity and keep hidden tombstones with their mapping.

## Decision

- **A draft follows the App's explicit merge.** When a mapped draft receives a snapshot with `merged_into` and the target UUID is not linked to any Website product, its `qammaris_app` identity row moves to the target UUID (`FollowQammarisAppMerge`). If the target snapshot is already stored, its availability and price apply at once (and `FillAppDraftOffer` creates the offer when the size is in the name); otherwise they apply when it arrives. Product ID, slug, name, content and orders stay. The move is audited in `qammaris_app_changes` (`after.merge = followed_merge`). This is the only automatic rebind, and only for an App-declared merge.
- **A leftover draft is retired when the target already has its own product.** If the target UUID is already linked, the merged draft is archived only when nothing was built on it: never published, no image, no order line (`archived_merged_duplicate`). Otherwise it stays as it is for the Owner (`merged_review`; the existing "Arsipkan draft" button applies).
- **Published and archived products are unchanged.** A published product whose UUID is merged keeps its mapping and becomes hidden, as before (ADR-025 tombstone rule).
- **Archived products leave the default admin list.** `/admin/products` without a status filter lists published and draft ("Tayang dan draft"); archived products are under "Diarsipkan" and can still be restored.
- **No hard delete.** Archive stays the removal path; permanent deletion of never-published drafts needs a separate Owner decision with a backup.

## Consequences

- No more duplicate draft per OTW → Majoo merge going forward; the moved draft keeps the OTW working name (the feed never renames existing products), so the Owner may rename it in the editor.
- A wrong merge in the App moves the Website draft too; correcting it means fixing the App and, if needed, re-linking by hand.
- The 9 drafts archived before this change stay archived and are now out of the default list.
