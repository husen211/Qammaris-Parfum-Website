# ORD-02e verification — Private Order API v1

Date: 2026-10-09. Branch `modernization/ord-02-online-order-redesign` (PR #32, draft).

**Not deployed.** No production data was read or changed. Decision: [ADR-041](../../architecture/decisions/ADR-041-order-api-v1-implementation.md). Operations: [runbook](../../runbooks/ORDER_API_STAGING.md).

## Commits

| Commit | Slice |
|---|---|
| `eb9af4b` | A: config, HMAC middleware (current + previous secret), envelope and error mapping, compiled OpenAPI validator, read endpoints (list with opaque cursor, detail, private QR download) |
| `bf5b19a` | B: every mutation endpoint, Idempotency-Key (7 days), `expected_revision`, atomic claims, exact `packed_items`, J&T request/QR/pickup with atomic handover, `funding[]` costs with unique `expense_ref`, audit fields (source, key, X-Request-Id) |
| `84cbf4b` | C: webhook outbox with a fresh signature per attempt, 24 h give-up, **Integrasi App** status page and resend, group task link behind a flag, synthetic fixture command |
| `6b7a28e` | D: MariaDB-safe locking (READ COMMITTED, locking claim reads, deadlock retry); fixes to unreleased migrations found on MariaDB; MySQL-only concurrency and migration rehearsal tests |
| `224a9e2` + the docs/evidence commit | E: `details.task`/`details.holder` on `403 action_not_allowed` (agreed with the App), Super Admin claim release, Website issue guard while the API is on, fixture output keyed by scenario, docs, browser evidence |

## Automated tests

| Run | Result |
|---|---|
| Full suite, SQLite (default) | **509 passed**, 6 skipped. The skipped tests are the MariaDB-only rehearsal and concurrency tests. |
| Full suite, isolated MariaDB 10.4.32 (`qam_test`) | **513 passed, 2 failed.** The 2 failures are pre-existing Shopee tests that assume SQLite; see below. This includes the MariaDB-only rehearsal and concurrency tests. |
| `node --test tests/js/*.test.mjs` | 42 passed |
| `npm run build` | built |
| Pint | passed |

ORD-02e coverage (feature tests):
- **Read:**
  - authentication codes in contract order;
  - rotation (previous secret accepted until cleared);
  - the published HMAC vector verifies on the server;
  - rate limit;
  - detail/list match the OpenAPI schemas and minimise recipient data;
  - legacy and unknown orders → 404.
- **Write:**
  - every OpenAPI operation has a route;
  - `task_already_claimed` takes precedence over `revision_conflict`;
  - claim gating per action, with `details`;
  - exact packing at the latest revision;
  - courier → handover → delivery;
  - J&T request → multipart QR (`file`, `expected_revision`, `actor`) → private download → pickup as atomic handover;
  - issues (resolving someone else's issue is owner-only);
  - costs: funding sum, proof/waiver, `source_version`, `expense_already_linked`;
  - an open reimbursement does not block completion;
  - idempotency: replay, key reuse, stored 4xx, expiry;
  - audit: actor, key and X-Request-Id are stored;
  - shape, body size, unknown order.
- **Outbox:**
  - one signed minimal event per revision;
  - each retry re-signs the same bytes with a new timestamp, including beyond 300 s;
  - give-up after 24 h;
  - errors are stored without URLs or secrets;
  - failed-event view and resend (Super Admin only).
- **Task link:** opens the Admin PWA when the flag is off and the App when it is on; no session or cookie; the V2 group message never carries a bearer link.
- **Claims UI:** Staff Order sees the holders but cannot release; a Super Admin needs a reason; a stale revision gets a conflict notice; a release is audited as `claim_released`.
- **Issue guard:** while the API is on, a Website issue is refused and `GET /orders` stays valid; with the API off, the issue is accepted.
- **Fixtures:**
  - refuses production and needs an active Super Admin;
  - six scenarios keyed `local`, `intercity`, `customerCourier`, `pickup`, `issue`, `costs`, each matching the App preflight;
  - a re-run reuses clean fixtures;
  - `--reset` cancels earlier fixtures (it does not delete them).

### OpenAPI conformity

- Every JSON response in the API tests is validated against the compiled schema (`resources/order-api/openapi-v1.json`, strict: unknown properties fail).
- The compiled copy is checked against the YAML.
- The YAML hash is locked to the r4.1 baseline (`1.0.0-rc.4.1`, unchanged by ORD-02e).
- The claim rules and `details` are an r4.1 clarification in the Markdown (§8.1); no schema change.

## MariaDB 10.4.32 (isolated)

Owner decision: use the local MariaDB 10.4, in a separate temporary instance.

Setup:
- `mysqld` with its own datadir under `%TEMP%`, bound to 127.0.0.1:3399;
- databases `qam_test` and `qam_migrate`;
- a random root password kept only in a local file outside the repository and never displayed.

The XAMPP default instance was not touched.

| Test | Result |
|---|---|
| `OrderMigrationsMariaDbTest`: migrate fresh → roll back the 8 ORD-02 migrations → seed ORD-01 rows → migrate (backfill checked) → no column with implicit `ON UPDATE` → roll back again (every ORD-01 row/value identical) → migrate again | **pass** |
| `OrderApiConcurrencyTest`, real separate PHP processes released at the same millisecond: 6 simultaneous claims | exactly 1 holder; 5 × `task_already_claimed` |
| 6 identical requests, one Idempotency-Key | applied once; 5 replays with the same body |
| 6 packs with the same `expected_revision` | 1 winner; 5 × `revision_conflict` |
| One `expense_ref` raced onto two orders | 1 link; the other gets `409 expense_already_linked` |
| Two Super Admins deactivating each other | one stays active |

Defects found on MariaDB and fixed. SQLite showed none of these:

1. **Stale snapshot under REPEATABLE READ.** Concurrent claims returned 404 or wrong results. Fix: READ COMMITTED for API write transactions, locking reads for claims, and the order lookup moved before the transaction.
2. **Gap-lock deadlock on the `expense_ref` race (500).** Fix: READ COMMITTED plus retry on 1213/1205, up to 3 attempts.
3. **`Invalid default value` and implicit `ON UPDATE CURRENT_TIMESTAMP`** with `explicit_defaults_for_timestamp=OFF`. `customer_link_expires_at` was reset on every update. Fix: unreleased NOT NULL timestamps declare `useCurrent()`, and the test asserts no `on update` column.
4. **Row size too large (8126 bytes, utf8mb4).** Fix: long free-text columns (`address`, `location_url`, notes, `refund_reason`) are TEXT.
5. **A second rollback failed with row size** because MariaDB's instant DROP COLUMN keeps the dropped columns as hidden metadata. Fix: `down()` rebuilds `online_orders` (`ALTER TABLE … FORCE`, keeps data) before dropping columns.

Full suite on MariaDB: every ORD-01/ORD-02 test passes (513 passed). Two pre-existing, unrelated Shopee import tests fail on MySQL only, because they assume SQLite behaviour: double-quoted identifiers in a logged query, and an import batch ID of 1 after a rolled-back transaction. They are recorded in the backlog and not changed here. `OnlineOrderTest` had the same ID assumption and was made ID-independent.

**Limits:**
- 10.4 is not the production version.
- Production uses MariaDB 11.8.9. That is recorded in [audit-release](../audit-release/README.md) and [p1-04](../p1-04/PRODUCTION_TARGET_PREPARATION.md); it was not re-checked live from here, because this session has no production access.
- Before staging sign-off, run the same two test files on a database matching the production engine and version (Owner decision 3). 11.8 defaults to `explicit_defaults_for_timestamp=ON`, so fix 3 is defensive there.

## MariaDB 11.8.9 (production version, isolated) — follow-up 2026-10-09

The official `mariadb-11.8.9-winx64.zip` was checked against the release's `sha256sums.txt` and run as a separate process bound to 127.0.0.1, with its own datadir and empty scratch databases. Its server defaults match production: `explicit_defaults_for_timestamp=ON`, strict mode, `REPEATABLE-READ`, utf8mb4.

| Test | Result |
|---|---|
| `OrderMigrationsMariaDbTest`: migrate → roll back 8 → ORD-01 data → migrate (backfill) → roll back (data identical) → migrate | **pass** |
| `OrderApiConcurrencyTest` (5 races) | **pass**, run 3 times in a row |
| Full suite on 11.8.9 | **518 passed**, 2 failed (the known Shopee test assumptions, TEST-MYSQL-01) |
| Full suite on 11.8.9 after the Shopee test fix | **522 passed, 0 failed** |

One run showed a transient failure in `ProductDetailJsonLdTest`. It happened while `bootstrap/app.php` was being edited mid-run, and the test passed on rerun and in the final full run.

Production's own version was not re-checked live: this session has no official production access. The staging deploy key is not used for production.

Local smoke test with Qammaris App: [SMOKE_LOCAL.md](SMOKE_LOCAL.md). Round 3: 23/0/1, 50/50 webhooks, revisions match.

## Browser (real headless Chrome, synthetic data, temporary SQLite)

The run used:
- API and webhook switched on with throwaway local values;
- the webhook pointed at a closed local port, so delivery fails on purpose;
- one V2 order held in the App by two synthetic App users.

Raw results: [browser-checks.json](browser-checks.json). No JS exceptions. Horizontal overflow was 0 at every capture.

| Check | Viewport | Evidence |
|---|---|---|
| Staff Order sees **Dipegang di Qammaris App** (Packing · Ikrar, Pesan kurir · Andi), with no release form. The issue form is replaced by the App-only note. | 390 | [claims-staff-390](claims-staff-390.jpg) |
| Super Admin sees **Lepas klaim**. The summary takes keyboard focus. An empty reason is blocked (`required`). | 390 | [claims-owner-390](claims-owner-390.jpg) |
| Release with a reason → in-section notice "Klaim dilepas.", and the other claim remains | 390 | [claims-released-390](claims-released-390.jpg) |
| Order page with the claim block on desktop | 1440 | [order-claims-1440](order-claims-1440.jpg) |
| **Integrasi App**: switches, configured or empty (no secret values; checked that no secret text appears), counts, failed event, retrying event | 320 / 390 / 1440 | [320](integrations-320.jpg), [390](integrations-390.jpg), [1440](integrations-1440.jpg) |
| **Kirim ulang** → "dikirim ulang (event dan isi sama, tanda tangan baru)", and the failed list empties | 390 | [after resend](integrations-after-resend-390.jpg) |
| Staff Order opening **Integrasi App** | 390 | 403 |

Full-page mobile captures show the fixed bottom navigation where the viewport ended (capture artifact, as in ORD-02d).

Before: the V2 order page had no claim information ([ORD-02d detail](../ord-02d/detail-active-390.jpg)), and **Integrasi App** is new in ORD-02e.

UX rationale:
- The claim block appears only while a task is held, so it adds nothing to the normal flow.
- Release is a two-step action (summary, then a reason), so a stuck claim can be freed without accidental taps.
- The issue note explains where to record issues instead of showing a form that would fail.

Not verified: real touch devices and Safari; a real App, tunnel or staging (they need the Owner).

## Staging and fixture readiness

- Ready in code:
  - fixtures (`qammaris:order-api:fixtures`);
  - webhook URL switchable to a tunnel;
  - failed view and resend;
  - the kill switches;
  - the runbook.
- The App agent confirmed:
  - the claim mapping;
  - the multipart QR fields;
  - 7-day replay;
  - the X-Request-Id handling;
  - the fixture keys and preflight.
- Needs the Owner:
  - a staging deploy;
  - per-direction staging credentials set directly in env;
  - the per-session tunnel URL;
  - approval of contract r4.2 (`Issue.opened_by` nullable).

## Open issues

1. **r4.2 `Issue.opened_by`.** Needs Owner approval. Until then, Website issue creation is blocked while the API is on. An order with an earlier Website-opened issue would make API reads fail, so check before switching on.
2. Production-version database (MariaDB 11.8) rehearsal is still to do before staging sign-off.
3. Older, already-deployed migrations were not audited for the TIMESTAMP default pattern. 11.8 with `explicit_defaults_for_timestamp=ON` is not affected; listed for awareness.
4. The ORD-01 migration fixes (`useCurrent`, TEXT columns) exist only on this branch. PR #27's copy of that migration does not have them. If ORD-01 is ever released separately, port the fixes first.
