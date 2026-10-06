# ADR-022 — Feed-authoritative launch drafts

Status: Accepted — explicit Owner direction 2026-10-06. Staging only; production cutover remains separate.

## Context

452 app snapshots are synchronized; 446 are visible. The old 180-product local backup is a reference for preserving old IDs/slugs/media, not a current launch catalog. Source names omit size/concentration for many rows. Owner authorizes draft creation and owns the 371-product Shopee media export.

## Decision

Keep the stock worker unchanged: webhook/reconciliation only cache snapshots and update already-mapped availability. A separate explicit CLI preview/apply prepares one draft per visible UUID. Reuse import batch/row audit tables under `qammaris-drafts-v1`, identity mapping, single-offer and availability operations. No migration, invented admin account, generic repository or new dependency.

Preview locks checkpoint then snapshots, records immutable source/hash/revision, issues and media candidates. Apply validates catalog/offer/media state and every source/payload before any write; one transaction, repeat apply no-op. Existing UUID mappings are retained; possible unmapped current website matches block creation pending review. Unique provider constraints remain authoritative.

New draft initial name/brand/price come from app. Missing brand may be created from explicit source text; concentration taxonomy only from explicit name. No department category or guessed size/audience/description. Only valid integral ml and price create the existing single offer; missing size keeps source price as draft base price and review data, not an invented offer. SKU remains null to avoid truncated/duplicate Majoo exports. source=app always requires review. Never auto-publish or mutate existing website prices/slugs/media.

Shopee matching is conservative: unique exact normalized name/brand/size, with no explicit concentration conflict. Similarity and missing sizes produce manual candidates only. One Shopee ID cannot auto-own multiple UUIDs. Map provider shopee for approved strong new drafts; download cover plus first two additional slots through existing host allowlist, HTTPS/no redirects, byte limit, detected MIME and dimension checks. Cover must succeed before additions; reruns retain successful objects. No hotlinks or published image replacement.

## Tradeoffs

Existing import audit tables avoid unnecessary schema and preserve file/retry controls, but this CLI contract is deliberately isolated from human CSV admin routes. Owner-authorized machine runs have null human actor IDs plus an explicit contract/source label and machine availability audit; do not fabricate user accounts. A future operational UI can expose these persisted rows if separately approved.

Parsing/matching favors review over guesses. Preparation completeness is not launch readiness. Before production creation, inspect the actual existing production catalog and resolve mappings to preserve old IDs/URLs/media. Never copy staging DB wholesale over production.

## Staging release constraint

Existing full release workflow rewrites Basic Auth and chmods environment/cache. Current Owner scope forbids permission changes. Use an immutable, allowlisted PHP-only staging overlay with protected original-file/database backup and per-file SHA verification, retaining the baseline Git checkout, env/auth/assets and persistent stock worker. Record both baseline Git SHA and overlay SHA. Do not claim server Git HEAD is the overlay SHA. Future full production release must include this commit through the separately approved normal pipeline.

## Rollback

Retain source checkpoint, identities, draft/media records and batch audit. Prefer forward fix. PHP-only rollback restores the exact two prior image-operation files and disables/removes new commands only after checking queued image jobs; do not delete drafts/media or reset source checkpoint. Database restore/data cleanup requires separate approval.
