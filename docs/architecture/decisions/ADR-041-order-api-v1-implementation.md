# ADR-041 — Private Order API v1 implementation (ORD-02e)

Status: accepted for review (ORD-02e, 2026-10-09). Not deployed. Contract: [API v1](../../integrations/QAMMARIS_ORDER_API_V1.md), OpenAPI `1.0.0-rc.4.1` (frozen baseline; payloads and behaviour follow it exactly).

## Context

Qammaris App staff work V2 orders from the App: claim a task, pack, request a courier or J&T pickup, upload the J&T QR, hand over, report issues and costs. The Website stays the source of truth for orders. Both systems must survive retries, concurrent staff, and an App outage.

## Decision

1. **One set of operations.** Every API write calls the same `OnlineOrderFulfillment` operation as the Admin PWA. The actor is an `OrderActor::app(...)` that carries the App user, idempotency key, X-Request-Id and source `app`, and each change writes one event with those audit fields. API routes resolve V2 orders only. Legacy rows return 404.
2. **Authentication.** The `AuthenticateOrderApi` middleware enforces, in order:
   - the integration flag and a configured secret (503);
   - body size (413);
   - client ID (401 `unknown_client`);
   - a timestamp within ±300 s;
   - HMAC-SHA256 over `ts\nMETHOD\npath_with_query\nsha256(body)` against the current or previous secret. The previous secret enables rotation.
   - a per-client rate limit (429 + Retry-After).
   No session, cookie or user account is involved.
3. **Idempotency.** `integration_idempotency_keys` stores `(client, key)` for 7 days:
   - The key is reserved inside the same transaction as the effect.
   - A concurrent duplicate waits on the unique index, then replays the stored response (`X-Qammaris-Idempotent-Replay: true`).
   - A different payload under the same key returns `idempotency_key_reused`.
   - 4xx answers are stored; 5xx answers are not, so the caller may retry.
4. **Concurrency.**
   - Write transactions on MySQL/MariaDB run at READ COMMITTED.
   - The order row is locked with `lockForUpdate`, and claim reads are locking reads.
   - Deadlocks and lock wait timeouts (1213/1205) retry up to 3 times.
   - `expected_revision` is checked after the lock. `task_already_claimed` takes precedence over `revision_conflict`.
   - Claims are unique per (order, task). For App actors, `preparation` needs the preparation claim; courier requests, J&T and QR need `courier_booking`; handover and J&T picked-up need `handover`. Website users are not claim-gated.
5. **Costs.** A `PUT /costs/{expense_ref}` upserts by `expense_ref`, which is unique across all orders: the same reference on another order returns `expense_already_linked`. `funding[]` must sum to the amount, and `source_version` is monotonic.
6. **J&T QR.** The QR is uploaded with a signed multipart PUT (`file`, `expected_revision`, `actor`) and stored on the private `local` disk, never public. It is downloaded only through the signed API.
7. **Webhook outbox.** A change to an order writes one `integration_outbox` row with a fixed raw body. Delivery runs every minute through the scheduler (`qammaris:orders:deliver-webhooks`):
   - backoff schedule: 0, 1 m, 5 m, 15 m, 1 h, 6 h;
   - give up after 24 h;
   - every attempt gets a fresh timestamp and signature over the signing path `/api/integrations/website/orders/events` (K-A/K-B).
   Failed deliveries appear under **Integrasi App** (Super Admin), with a resend button. The App can always rebuild its state with `GET /orders`, so the webhook is only a hint.
8. **Responses.** Every response uses the contract envelope with `X-Qammaris-Request-Id`. Tests validate JSON bodies against the compiled OpenAPI schemas (`resources/order-api/openapi-v1.json`). A sync test locks that schema to the frozen YAML hash.
9. **Group link.** `GET /admin/orders/{public_id}/task` redirects to the Admin PWA order page. When `QAMMARIS_ORDER_APP_TASK_LINKS=true`, it redirects instead to `https://qammarisapp.com/orders/<order_id>`. Neither link carries a bearer token, and the Admin PWA stays the fallback.
10. **Synthetic fixtures.** `qammaris:order-api:fixtures` creates six V2 scenarios using draft products of brand `E2E Sintetis` and a `[e2e-fixture:…]` marker. It refuses to run in production and requires an active Super Admin `--actor` for audit. Each run reuses clean fixtures. `--reset` cancels earlier open fixtures (it never deletes them) and creates six fresh ones.

## Consequences

- If the App is down, orders keep working in the Admin PWA, webhooks queue, and the App catches up with `GET /orders` plus a resend.
- Issues opened from the Website cannot be serialised yet: `Issue.opened_by` requires an App user.
  - The App agent technically accepts r4.2 (`opened_by: ActorRef | null` plus `opened_by_source`), but it is a schema change and needs Owner approval.
  - Until then, while `QAMMARIS_ORDER_API_ENABLED=true`, Website users cannot open V2 issues: the operation refuses, and the page explains this. Existing issues can still be resolved.
  - Turn the API on only when no V2 order holds a Website-opened issue. Such an order would make API reads fail.
- Claim gating per action is recorded as an r4.1 clarification in the contract (§8.1), with no schema change. A missing or foreign claim returns `403 action_not_allowed`, with `details.task` and, when someone else holds it, `details.holder`.
- The V2 order page shows App claims while any exist. A Super Admin can release one with a reason, which is audited as `claim_released`. This is the recovery path for a stuck claim.
- MariaDB facts found by the isolated tests:
  - With `explicit_defaults_for_timestamp=OFF`, MariaDB gives the first NOT NULL TIMESTAMP column `ON UPDATE CURRENT_TIMESTAMP`, so all unreleased NOT NULL timestamps declare `useCurrent()`.
  - utf8mb4 rows hit the 8126-byte limit, so long free-text columns are TEXT.
  - Instant DROP COLUMN leaves hidden columns behind, so `down()` rebuilds `online_orders` before dropping columns.
- SQLite results do not represent production concurrency; `OrderApiConcurrencyTest` and `OrderMigrationsMariaDbTest` run only on MySQL/MariaDB.
