# Repository Instructions for Coding Agents

## Mission

Improve the Qammaris Laravel catalog and admin panel incrementally while preserving production data, URLs, media, and the established public visual identity.

The user's explicit instructions take precedence over this file. If another skill or instruction conflicts with the active task, explain the conflict instead of silently changing scope.

## Required context

Before making changes, read:

1. `docs/product/BUSINESS_RULES.md`
2. `docs/architecture/MASTER_PLAN.md`
3. `docs/planning/BACKLOG.md`
4. the active backlog item and relevant ADRs

For Qammaris UI/UX review, redesign, or implementation, also read and apply `skills/qammaris-ui-review/SKILL.md`. Apply it only to the active UI surface; it must not expand a backend or data task into a redesign.

Do not assume production facts that are marked unknown.

## Scope discipline

- Work on one approved backlog item only.
- Do not begin the next phase automatically.
- The owner has pre-approved routine, reversible cleanup and implementation inside Qammaris Website without another clarification, including archive-over-delete safety changes, formatting, tests, documentation, commits, and pushes. This does not authorize production deployment, destructive data work, credential/permission changes, cross-project work, or material business-rule decisions.
- The owner has also pre-approved temporary local-only test accounts and test records needed for verification when they use synthetic credentials, are scoped to Qammaris Website, and are removed in the same task. This does not authorize changing an existing human account, real credential, persistent access, staging/production identity, or permission model.
- Record unrelated findings in the backlog; do not fix them unless they block the active item.
- Prefer the smallest change that meets the acceptance criteria.
- Avoid speculative abstractions, generic repositories over Eloquent, microservices, SPA rewrites, event buses, and new dependencies without a demonstrated requirement.

## Architecture rules

- Keep Laravel, Blade, Eloquent, MySQL, and the existing public URL shape.
- Admin forms, imports, and future API endpoints must reuse the same tested product operations.
- Keep HTTP concerns in controllers/requests and business mutations in focused application operations when reuse is justified.
- Use Laravel filesystem disks; never hardcode a production storage provider into domain logic.
- Keep credentials and environment-specific values out of Git.
- Do not expose raw database access to automation.

## Data safety

- Never delete, truncate, reseed, replace, or mass-update production data unless the task explicitly authorizes the exact operation and a verified backup/rollback path exists.
- Preserve product IDs, variant IDs, existing slugs, password hashes, and media until an approved migration says otherwise.
- Do not rewrite historical migrations to change an existing deployment. Use additive migrations.
- Do not infer deletion from a missing import row.
- Bulk writes require preview, validation, idempotency, conflict handling, and audit attribution.
- File migration uses copy -> verify -> switch -> retain -> separately approved cleanup.
- Never log credentials, tokens, complete request bodies, or unnecessary customer data.

## Product rules

- One catalog page currently represents one product, one size, and one price.
- Different sizes are normally separate catalog products.
- SKU is optional for human entry; internal IDs remain stable.
- Publication and availability are separate concerns.
- The website does not promise live inventory.
- Sold-out products remain discoverable unless separately archived.
- Cart/WhatsApp is an inquiry flow and does not reserve stock.

## UI/UX rules

- Design mobile-first and verify both mobile and desktop.
- Preserve Qammaris's premium editorial identity and existing visual tokens.
- Avoid generic AI aesthetics: arbitrary gradients, glass panels, excessive cards/pills, decorative dashboards, invented metrics, and verbose promotional copy.
- Do not redesign unrelated pages during a focused feature task.
- Define loading, empty, validation, error, success, disabled, and retry states.
- Prioritize visible price, size, availability context, and primary action.
- Maintain keyboard accessibility, labels, focus behavior, contrast, and reduced-motion support.
- UI changes require before/after screenshots at agreed viewports and a short UX rationale.

## Code quality

- Follow existing project conventions unless the active item deliberately changes them.
- Validate at the server boundary even when the UI validates.
- Scope child IDs to their owning parent.
- Treat imported and stored text as untrusted at every output context.
- Coordinate database and file operations explicitly; check storage failures.
- Add comments only when they explain non-obvious intent.
- Do not add packages for behavior already available in Laravel or the current stack.

## Testing and verification

- Inspect the existing behavior before editing.
- Add meaningful regression coverage for business-critical or previously unsafe behavior.
- Run the narrowest relevant tests first, then the agreed broader checks.
- UI work must be verified in a real browser on mobile and desktop viewports.
- A successful build alone is not proof that the feature works.
- Do not report tests as passed unless they actually ran.

## Documentation updates

Update only the documents affected by the task:

- `docs/planning/BACKLOG.md` for status.
- `docs/product/BUSINESS_RULES.md` for approved business decisions.
- `docs/architecture/ARCHITECTURE.md` for current architecture.
- `docs/architecture/decisions/ADR-*.md` for significant decisions and tradeoffs.
- `docs/runbooks/` for deployment, backup, restore, migration, or operational procedures.

Git commits and pull requests are the detailed code history. Do not duplicate every code change in `AGENTS.md`.

## Final report contract

Every implementation response must include:

1. outcome;
2. files changed;
3. database/data/media impact;
4. tests and checks actually run, with results;
5. browser viewports checked and screenshots for UI work;
6. known limitations or remaining risks;
7. rollback or forward-fix notes when relevant;
8. documentation updated;
9. recommended next backlog item, without starting it.

Stop and request owner direction before destructive production actions, public deployment/cutover, broad data rewrites, credential/permission changes, or a business-rule choice that materially changes the product.
