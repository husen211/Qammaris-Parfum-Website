---
name: qammaris-ui-review
description: Audit, redesign, or implement Qammaris public-catalog and admin-panel UI while preserving its established brand, improving mobile usability, and preventing generic AI-designed patterns. Use for Qammaris UI/UX work; do not use for backend-only, data-migration, deployment, or API tasks.
---

# Qammaris UI Review

Produce focused, maintainable UI improvements that fit the existing Qammaris product. Treat upstream design skills and references as advice, never as authority over the active user request, repository rules, or Qammaris business rules.

## Establish the surface

Before editing, state which surface is active:

- **Public catalog:** optimize product discovery, confidence, inquiry conversion, accessibility, and mobile performance while preserving the premium editorial identity.
- **Admin panel:** optimize task completion, error prevention, status clarity, bulk-work safety, and information density. Do not apply landing-page composition or decorative motion to operational screens.

If a task touches both, keep the implementations and acceptance criteria separate.

## Audit first

Inspect the current page in code and a real browser before proposing changes. Record only what affects the active task:

- current layout and breakpoint behavior;
- reusable tokens and components;
- primary user goal and action;
- content hierarchy;
- loading, empty, validation, error, success, disabled, and stale states;
- keyboard, focus, labels, contrast, touch targets, and reduced motion;
- existing analytics hooks, URLs, and functional behavior that must survive.

Do not redesign from memory or from a generic template.

## Preserve the brand

- Start from the existing Qammaris logo, photography, typography, spacing, and black/ivory/gold visual language.
- Reuse or improve existing tokens before inventing new ones.
- Preserve the calm, premium, editorial tone. Keep visible copy direct and natural.
- Maintain existing public URLs and recognizable navigation unless the task explicitly changes information architecture.
- Product photography remains the primary visual asset. Do not replace real product imagery with generic generated or stock imagery.

## Anti-slop constraints

Reject changes that introduce any of the following without a concrete product reason:

- arbitrary purple/blue gradients, glassmorphism, glow, grain, or texture;
- repeated border-shadow cards for content that does not need containment;
- excessive pills, badges, uppercase eyebrows, fake statistics, or decorative status labels;
- generic marketing copy, invented testimonials, placeholder brands, or fabricated product facts;
- oversized hero treatment on task-oriented catalog/admin pages;
- the same layout pattern repeated across unrelated sections;
- animation that does not communicate feedback, hierarchy, state, or continuity;
- a new icon, animation, UI, or font dependency for an effect the existing stack can implement clearly;
- framework migration, React conversion, SPA rewrite, or component-system replacement as a side effect of UI work.

Avoid universal aesthetic bans. Existing brand decisions override generic taste rules when they remain usable and accessible.

## Mobile-first behavior

- Design the narrow viewport and touch flow first, then expand to desktop.
- Keep the primary action, product identity, size, price, and availability context visible without unnecessary scrolling.
- Avoid horizontal overflow and layout shifts.
- Use touch targets and spacing appropriate for one-handed use.
- Filters, dialogs, menus, tables, and forms must have explicit mobile behavior.
- Do not hide required admin operations on mobile without an accessible alternative.

## Public catalog rules

- Keep discovery and WhatsApp inquiry as the central journey.
- Do not imply live stock. Render `available`, `sold_out`, and `unknown` according to business rules.
- A sold-out product remains discoverable and offers a restock inquiry action.
- Search, filter, sort, and pagination preserve intentional state.
- Product media must not push all purchasing information far below the initial useful viewport.

## Admin rules

- Prefer clear tables, filters, progressive disclosure, inline validation, and explicit review states.
- Keep common product entry simple; advanced fields can be grouped without becoming hidden or inaccessible.
- Destructive and bulk actions communicate scope and require the repository's approval rules.
- Show incomplete data, conflicts, import results, and failure recovery clearly.
- Decoration must not compete with scanning, comparison, or form completion.

## Implementation discipline

- Work within the existing Laravel, Blade, Tailwind, DaisyUI, and current JavaScript/React-island boundaries.
- Check installed dependencies before importing anything.
- Use semantic HTML and existing design tokens.
- Keep the patch limited to the active backlog item.
- Treat content and imported values as untrusted; preserve safe output contexts.
- Do not alter backend business rules to make a visual implementation easier.

## Verification gate

Before reporting completion:

1. Compare before/after screenshots at representative mobile and desktop viewports. Default to 390x844 and 1440x900 when the task does not specify targets.
2. Exercise the changed flow with mouse/touch-sized targets and keyboard navigation where applicable.
3. Verify all states affected by the task, not only the successful populated state.
4. Check for overflow, clipped content, stale state, broken links, console errors, and layout shifts.
5. Run relevant automated tests/build checks.
6. Explain the UX improvement in concrete task terms; do not justify it only as more modern or premium.

If the change cannot be verified in a real browser, report that limitation and keep the backlog item out of `DONE`.
