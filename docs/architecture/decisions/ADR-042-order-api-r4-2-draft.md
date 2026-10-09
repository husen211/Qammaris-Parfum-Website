# ADR-042 — Order API contract r4.2

Status: **accepted** (2026-10-09). The App agent signed off on draft `ff3532c` after corrections K-1–K-4, and r4.2 is now the API v1 baseline on the ORD-02 branch. Not deployed. The file name keeps `-draft` so existing links still work.

Owner decisions of 2026-10-09 followed the r4.1 local smoke test ([SMOKE_LOCAL](../../verification/ord-02e/SMOKE_LOCAL.md)). Contract: [API v1](../../integrations/QAMMARIS_ORDER_API_V1.md) §8.5, §8.6, §6.3 and §10.

## Context

The r4.1 smoke test showed three gaps:

- Issues opened in the Website could not be represented, because `Issue.opened_by` requires an App user.
- The Website treated "who sends the cost" as "who approved the waiver". A legitimate resend by the App backend was therefore refused, while any App owner account could still claim a new waiver.
- Error codes did not match the contract text.

## Decision

1. **Issue source.** `opened_by` is `ActorRef | null`, and `opened_by_source` is `app` or `website`. Issues opened in the Website are sent with `opened_by=null`. The admin account, name and time stay in the Website's own audit. Once the App reads the new field, `QAMMARIS_ORDER_API_WEBSITE_ISSUES=true` lets the Admin PWA open issues while the API is on.
2. **Waiver authorization.** Three identities are kept apart:
   - **Approver:** `waiver.by`, `decision_id` and `at`.
   - **Request actor:** the person whose action produced this cost version.
   - **Transport:** the HMAC client, `X-Request-Id`, and `X-Qammaris-Delivery` (`live`, `retry` or `resync`), stored on the event.

   A new or changed decision needs a `decision_id`. It is accepted only if `actor.app_role=owner`, `actor.app_user_id=waiver.by.app_user_id`, and that ID is on the Website allowlist `QAMMARIS_APP_OWNER_IDS`. The allowlist is server configuration and is never read from the payload. An identical resend of a stored decision is accepted from any App actor. Waivers stored before r4.2 are served with `decision_id: null`.
3. **Error codes.**
   - `422 validation_failed` for structure: waiver missing or malformed, or `decision_id` missing on a new decision.
   - `422 proof_required` when a valid payload does not meet the approval requirement. This rule moved from the schema into business validation.
   - `403 action_not_allowed` for an unauthorized new or changed waiver.
4. **Robust reads.** An order that cannot be serialised is left out of `GET /orders` and listed in `unavailable`. `GET /orders/{id}` answers `500 serialization_failed`. A warning is logged with only the order ID and the failure location.

## Consequences

- The waiver rule is **stricter** for new decisions than r4.1's actor-is-owner check: it adds the ID match, the allowlist and the `decision_id`. Resends are explicitly allowed.
- An empty allowlist fails closed: no new waiver is accepted, and the preflight warns.
- New migration `2026_10_09_500001` adds `online_order_events.delivery`. It is additive; its rollback rebuilds the table first.
- Removing a waiver (proof attached later, cancelled, rejected or void cost) is not a decision and is accepted from any App actor (App review K-1).
- `X-Qammaris-Delivery` is audit only: it is not signed and never affects authorization or idempotency (K-3).
- Rollout follows contract §14 with no compatibility shim (K-4). Website r4.1 rejects `decision_id`, so the App and the Website switch to r4.2 together in the test environment. The smoke test is then repeated against the r4.1 baseline. Production follows only after separate Owner approval.
