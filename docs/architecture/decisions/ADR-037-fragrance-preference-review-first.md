# ADR-037 — Source-derived fragrance profiles with human reference first

Date: 2026-10-09. Status: accepted **program decision**, implementation PREF-01 only; no new engine installed. [Plan](../../planning/QAMMARIS_FRAGRANCE_PREFERENCE.md), [audit](../../verification/pref-01/README.md).

## Context

Owner wants a useful, explainable V1 rather than a quick MVP and cannot refill all catalog forms. Public378-product snapshot has descriptions/notes/prices, but25 placeholder products, unrecognized labels and limited performance evidence. Six-question keyword matching cannot represent budget/avoids; complete fields do not validate sensory accuracy.

## Decision

Separate derived recommendation profiles from operational catalog, with parser/engine versions, source fingerprints, per-attribute provenance, unknowns and reviewed overrides/revisions. Explicit normalizations only; unknown opaque names not guessed. Source changes invalidate dependent assessments. Catalog price/status/publication/media remain authoritative. No external AI/new package/automatic internet enrichment/catalog rewrite.

Establish30 Owner/staff-labelled cases before calibration, five held out; rules/acceptance defined in approved plan. No fabricated labels, unvalidated accuracy percentage or automatic learning from relevance feedback. Gender is light and Unisex eligible; strict detected-avoid and budget boundary cannot be sacrificed for aggregate relevance. Merge sizes only by confirmed fragrance identity, never equal notes.

PREF-01 provides operator read-only capture, offline audit/dictionary proposal, ambiguity evidence, legacy comparison observations and pending human worksheet. PREF-02/03/04 remain separate items. Feature flag and additive tables preserve legacy fallback; deploy requires distinct post-acceptance permission.

## Alternatives and consequences

Reject rewriting all product forms (Owner workload), external AI/free typo inference (untraceable conclusions), personality/age proxies, note-equals-performance rules, and recommendations padded by popularity. Exact audit search groups may group modifiers for discovery but do not assert physical ingredients are interchangeable. More source text does not imply more verified facts. Human review remains a real prerequisite; tools cannot prove perfume preference relevance without labels. Future corrections change only profile tables, not catalogs. Retained anonymous feedback is relevance-only and requires explicit future version evaluation before changing ranking.
