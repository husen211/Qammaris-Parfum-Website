# ADR-030 — Whole rupiah and current-catalog cart totals

Date: 2026-10-07. Decision: Owner approved whole rupiah as the final AUD-06 rule, superseding the earlier two-decimal reply during the same task. Implementation on the AUD-06 review branch; no production release or data migration.

## Concrete problem

AUD-F08: cart mutation JSON totals used stale session prices even though pages/checkout resolved the current catalog. Browser consumers currently ignore these totals, but the public numeric response could disagree after a source price update.

AUD-F09: existing decimal columns/validation permitted fractional prices. A stored 100.90 displayed as 101, while cart/WhatsApp truncated it to 100. A 100.90 → 100.91 change also produced the same checkout fingerprint after integer casting. This is reproducible with synthetic data; affected production records are Not confirmed.

## Decision and boundaries

- New manual selling/comparison prices and canonical/maintenance CSV prices must be positive whole rupiah within the existing storage range. `100`, `100.0`, and `100.00` represent the same permitted whole value. Reject nonzero fractions/scientific notation/formatted currency. Source feed remains its existing positive-integer contract.
- Reuse one small `Rupiah::WHOLE_PRICE_PATTERN` at input boundaries and the shared manual offer operation. No currency package, generic Money framework, new service layer, schema migration, or blanket rounding.
- Keep decimal(10,2), IDs, slugs, media and existing values. Read-only historical local snapshots had no fractional base/comparison/offer prices. Current production remains unmeasured. If a legacy fraction exists, show its exact value and an editor correction notice; preserve exact cart/WhatsApp arithmetic until explicit correction. This does not auto-unpublish products or authorize correcting data.
- Integer hundredths represent existing decimal amounts during multiplication/sums; decimal strings represent calculated amounts internally and in the checkout fingerprint. Whole prices format as `Rp 175.000`, not `Rp 175.000,00`. Hypothetical legacy fractions format truthfully as `Rp 100,90`.
- Cart mutation totals use the same current-catalog resolver as pages/checkout. Keep existing numeric JSON fields for compatibility, cast only at output, and return null if all remaining items cannot be resolved. Formatting and server quote verification remain authoritative; clients never calculate the checkout amount.
- Preserve quantity, availability/publication, current-source-price, parent ownership, recipient privacy and retry gates. An old session quote may request one fresh checkout review after release; no session wipe is needed.

## Tradeoff and recovery

The focused helper avoids four inconsistent input rules and multiple formatters. Integer conversion adds a small explicit boundary to avoid floating-point arithmetic and silently rounded legacy data. Two decimal representations remain in storage/internal computation for compatibility, rather than forcing an unsafe whole-column migration.

Existing un-applied fractional CSV proposals cannot bypass the shared manual-price guard; transaction rollback/failure recovery asks for a new preview. Already applied audit rows remain historical and are not rewritten. Code-only revert needs no schema rollback, but reinstates the known fractional truncation/quote bug. Prefer a narrow forward fix; any deployment/revert or legacy-price correction remains separately authorized.

Verification, consumer trace, screenshots and limitations: [AUD-06](../../verification/aud-06/README.md).
