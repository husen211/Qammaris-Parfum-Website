# ADR-038 — Isolated provisional profile engine before public cutover

Date2026-10-10. Status: accepted implementation boundary for PREF-02, provisional ranking not quality accepted. Owner explicitly continued the next step after delegating reference preparation. [ADR-037](ADR-037-fragrance-preference-review-first.md) remains the quality/release authority.

Build the approved backend phase using separate profile/revision tables, pure parser/engine, admin-scoped source-bound corrections, transactional preview/apply and an offline evaluator. Do not wait idle for missing human labels to implement reversible code, but do not mark PREF-01 DONE or PREF-02 quality accepted. Do not move agent labels into Owner review. Public controller/UI/routes/results/feedback stay out of this item.

Per-family explicit catalog or attributed review statements have priority over exact-note inference; inference contributes0.8 internal aroma support to the fixed50 maximum. Promotion/concentration/material presence cannot create performance or sweetness levels. This provisional factor is documented and fingerprinted; no calibration/empirical confidence is claimed. Source/parser changes suspend dependent overrides and verified identity until reviewed. Detected notes remain strict conservative avoid evidence after target corrections.

Hold-out access is opt-in after recording the identical engine fingerprint. Reports keep agent-proposal overlap, human relevance and technical boundary tests separate. Limited overlap and missing level data remain review limitations;18/20 and16/20 are not asserted. No production schema writes/cutover; additive migration tested only in local in-memory SQLite. Phase-specific staging/production authorization still required. Next phase is not automatic.
