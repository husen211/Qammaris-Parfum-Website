# ORD-02e — local smoke test with Qammaris App (r4.1)

Date: 2026-10-09.

This is a **smoke test** of the real Website code running on an isolated local instance. It is **not** the E2E sign-off: the Owner and the App agree that sign-off needs hosted staging and a tunnel. Owner approval for this local variant was given through the App agent. No production resource was used.

## Environment

| Item | Value |
|---|---|
| Website commit | `a2f1f7f` for rounds 2–3. Round 1 ran on `f98efd0`; it found the QR bug that `a2f1f7f` fixes. |
| App commits | round 1 `f04b56d`, round 2 `930a729`, round 3 `38fe8c5` (branch `dev/september-2026`) |
| Contract | r4.1, OpenAPI `1.0.0-rc.4.1` |
| Database | MariaDB **11.8.9**, the production version. Official Windows zip, SHA-256 checked against `sha256sums.txt`. Separate process bound to 127.0.0.1 with its own datadir and random root password. Scratch database holds synthetic data only. |
| Instance | `http://127.0.0.1:8130`. Not reachable from the internet. |
| Settings | `APP_ENV=staging`, fresh `APP_KEY`, sessions/cache/queue on the database, a real `queue:work` and `schedule:work`, `MAIL_MAILER=log`, catalog sync off. |
| Flags | `ORDERS_V2_ENABLED`, `QAMMARIS_ORDER_API_ENABLED` and `QAMMARIS_ORDER_WEBHOOK_ENABLED` on. `QAMMARIS_ORDER_APP_TASK_LINKS` off. |
| Secrets | Random per direction, in a local file outside both repositories, readable only by the Windows user. The App was given the path, never the values. |
| Webhook target | the App's local receiver, `http://127.0.0.1:8000/api/integrations/website/orders/events` |

**Preflight** (`qammaris:order-api:preflight`) was OK before every round. Every check passed, and all six fixtures matched the App's preflight rules.

**Website-side signed HTTP smoke** (my own external client):
- the list and all six fixture details validate against OpenAPI;
- bad signature → `401 invalid_signature`;
- unknown order → `404 order_not_found`;
- responses carry `X-Robots-Tag: noindex, nofollow`.

## Results

| Round | App harness | Website webhooks (round) | Notes |
|---|---|---|---|
| 1 | 15 PASS / 8 FAIL / 1 SKIP | 36/36 delivered, 0 failed | QR upload failed (**Website bug**, fixed below). Cost 403s explained below. Two App bugs: a key reused with a changed payload, and resends hitting 429. |
| 2 | 21 PASS / 2 FAIL / 1 SKIP | 49/49 delivered, 0 failed | Both failures were App harness bugs. Webhooks during an App outage were refused once, then accepted on retry with a new timestamp and signature. |
| 3 | **23 PASS / 0 FAIL / 1 SKIP** | **50/50** delivered, 0 failed | The skipped check is the tunnel preflight, which does not apply locally. The App received 50 unique event IDs. |

**Overall:** 135/135 webhooks delivered, 0 failed, at most 3 attempts. 34 were delivered on a retry: early queueing in round 1, plus the App's outage windows.

**Round 3: final revisions in the Website database, compared with the App's projection**

| Order | Website | App | Result |
|---|---|---|---|
| local QAM-0013 | 8 | 8 | match |
| intercity QAM-0014 | 9 | 9 | match |
| customerCourier QAM-0015 | 8 | 8 | match |
| pickup QAM-0016 | 6 | 6 | match |
| issue QAM-0017 | 3 | 3 | match |
| costs QAM-0018 | 10 | not given | — |

**What the round-3 records show**
- Every App event in the Website database carries the App's `X-Request-Id`.
- Rejected actions left no event and no revision bump: a lost claim, a wrong packing quantity, and a handover without a claim.
- The Website logged no error during rounds 1–3.

Raw Website-side reports (synthetic E2E names only): [round 1](smoke-local-round1.json), [round 2](smoke-local-round2.json), [round 3](smoke-local-round3.json).

App coverage:
- sync of the six fixtures and data minimisation;
- concurrent claims;
- packing 422 and ready;
- local courier with handover; customer-booked courier;
- pickup;
- an issue raising `has_issue`;
- J&T pickup requested → multipart QR → picked up without a tracking number;
- mixed-funding costs with reimbursement through to `paid`;
- replays after a dropped connection, a lost reply, or a 503;
- real webhooks, including while the App is down;
- legacy modules;
- a secret scan.

## Defects found

1. **Website, fixed in `a2f1f7f`: signed multipart QR upload always failed (`401 invalid_signature`).**
   - Cause: on PHP 8.4, Symfony 7.4 parses multipart PUT/PATCH bodies with `request_parse_body()`, which consumes `php://input`. The HMAC was therefore checked against an empty body.
   - Feature tests inject the body directly, so they could not catch it.
   - Production runs PHP 8.2, so it is not affected today, but it would be after an upgrade.
   - Fix: `public/index.php` captures signed Order API multipart PUT/PATCH requests from the untouched raw body (`RawBodyRequest`); every other request is unchanged.
   - Verified over real HTTP with three variants: standard boundary 200, quoted boundary 200, actor sent as bracket fields 422.
   - Regression test: `tests/Unit/RawBodyRequestTest.php`.
2. **App, fixed in App `930a729`:**
   - one idempotency key was reused with a changed cost payload (`409 idempotency_key_reused`);
   - automatic cost resends hit the 120/min rate limit (`429`).

## Open contract points (Owner decision)

1. **Who may send a proof waiver.**
   - The Website answers `403 action_not_allowed` ("Pengecualian bukti hanya dari Owner.") when a cost PUT that adds or changes a waiver comes from a non-owner App actor.
   - r4.1 §4 lists only two owner-only actions, so this rule is stricter than the contract text.
   - The App now sends waiver changes with the Owner actor, so the smoke passes either way.
   - Removing the check was blocked by this session's safety policy, so it is unchanged.
   - Owner chooses one of:
     - keep the rule and write it into the contract as a clarification; or
     - relax it so any App actor can relay `waiver.by`.
2. **`approved` + `proof=waived` + `waiver=null`.** The Website answers `422 validation_failed` (request schema), while §8.5 says `422 proof_required`. Both mean "missing proof". Align the contract or the code in the next contract revision.

## Not covered

- Hosted staging and a tunnel; this is the Owner's sign-off gate.
- Real devices.
- A production read-only database check: no official access from this session.
