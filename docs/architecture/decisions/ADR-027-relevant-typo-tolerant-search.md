# ADR-027 — Relevant typo-tolerant search

Date: 2026-10-07. Status: accepted for implementation; release pending review.

## Problem

`Product::scopeSearch` searched descriptions using broad LIKE clauses: `reverie` matched CHNO's prose, while `rverie` found nothing. Other admin/controller and client substring implementations behaved inconsistently. Import target search must help discovery without guessing identities or changing selections.

## Decision

Use one focused PHP `SearchMatcher` and equivalent client option matcher with shared fixtures. Keep existing Eloquent queries, controllers, Blade, pagination and authorization. Search product identity text (name, brand, active size), not descriptions. Require every query token, rank exact/partial before bounded alphabetic edit-distance matches, and keep numbers/SKU/UUID strict. Apply eligibility before matching. Explicit sort choices remain authoritative; default catalog search relevance precedes merchandising ties.

Laravel's existing LIKE scope cannot provide these typo/ranking rules. The helper introduces matching/scoring code, parity fixtures and a scoped metadata read; it does not introduce repositories, a generic search service, cache invalidation, schema indexes, external packages or infrastructure. With a catalog of hundreds, this complexity is preferable to maintaining conflicting search rules or deploying a search engine. SQL result restriction and bound CASE ordering retain database pagination.

## Consequences

Search cost grows with eligible metadata and query tokens. Query/text bounds limit per-item work, but are not a scalability guarantee; profile before significant growth. Client normalization covers verified Latin names, while PHP transliteration is broader. Search never auto-maps imports, changes product content, or bypasses actor/visibility scopes. Public description-only discovery is intentionally removed to fix the demonstrated false positive. No-search behavior and production identifiers/media remain unchanged.
