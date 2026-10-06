# P8-06 — Curated descriptions and wave-one staging review

2026-10-06. Owner approves Shopee copy cleanup, clear audience evidence and staged launch. Exact 113 photographed structurally complete staging drafts; production backup deferred until immediately before separately approved cutover. No publication before Owner review.

## Source and factual checks

Unchanged basic export SHA256 `dc2774e82193a1ae8cbc4ce9487f804343a68ba4513cccd4b5dc0af3141682bf`. Exact existing Shopee IDs and unchanged media-baseline source names pair all 113. No fuzzy new identities. Original text, cleaned text, removal audit and audience evidence retained in private JSON. Actual cohort source already has zero emoji/READY STOCK/shipping/hashtags; the cleanup rules leave its product facts unchanged.

76 clear audiences; 34 unknown; 3 conflicting: 9 AM Dive (name Homme versus description pria/wanita), Armaf Bling (Gender MEN/Unisex), MPF Leather For Men (description Unisex). “Pria maupun wanita” counts as explicit Unisex; comparison/inspiration and bottle color/aroma are not audience evidence. All unclear values remain blank. 246 other drafts need category/offer corrections; the separate list includes size/price, not just concentration. None belong to this wave.

Fresh READ ONLY staging capture checks all 445 current draft fingerprints/blockers against the previous reviewed capture. 446 products, 519 images, 625 identities, 1 batch/452 rows, 0 human users, checkpoint 456; 113 cover files read through configured Laravel disk, detected JPEG/PNG/WebP MIME, bounded size and SHA verification. No source/env secrets or API bodies captured.

## Checks run before apply

7 Python curation regression checks passed: promo removal preserving facts, name/description conflicts, dual labels, both-audience phrases, comparison/inspiration exclusion, color/aroma exclusion and Unicode preservation. 22 focused maintenance/copy tests passed / 170 assertions initially. Full Laravel suite **242 passed / 1591 assertions**. Added replay-byte assertions then reran copy tests **8 passed / 33 assertions**. Pint on all 5 PHP files passed; diff whitespace check passed. No tests run against production; local tests use isolated SQLite.

## Staging application and private review

Pending verified overlay, exact persisted preview/apply/replay, protected-state comparison and browser acceptance. All products remain drafts until separately reviewed/approved. Record final batch/counts and immutable overlay SHA here after verification.

Private review tool uses actual website covers and escaped source copy. No public Blade/CSS/JS change; no API/credential/account/permission/database-schema/media mutation. A baseline of the new offline review was captured before compacting instructions for mobile; final browser checks pending. Review CSVs and captured data are ignored, not in Git.

## Recovery and next step

ADR-023 explains trusted CLI scope and audit attribution. Runbook `docs/runbooks/QAMMARIS_LAUNCH_COPY.md` gives preview/apply/recovery. Restore exact prior code without reverting draft fields; forward corrections use fresh reviewed maintenance. Next within P8-06: Owner review/corrections, then explicit staging publication approval. Production cutover is not started.
