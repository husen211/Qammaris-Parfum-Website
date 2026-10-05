# Qammaris App integration — activation and launch gates

Prepared 2026-10-05 from repository code/configuration. This is a reviewable activation plan, not deployment authorization or a claim that the API is connected.

**Activation update, 2026-10-05:** staging feed is connected after reusing the existing protected pair through authorized staging SSH and backend hPanel. Backend restarted on the same `921569b4` commit; source **401 without key / 200 with key**. Database worker drained **452** snapshots to checkpoint **452**, has_more=false; six hidden tombstones retained. Existing minute crons execute, temporary schedule:work stopped, duplicate/older ingress signals are cache no-ops. One Owner-selected AOERA staging mapping (product 1) passed actual automatic sold-out/revert, revision 453/454 in approximately 10/13 seconds by stored timestamps. Latest revision/checkpoint 456; authenticated desktop/mobile available-state browser checks and 27 rolled-back MySQL state checks passed. The inherited Hostinger cron lock was repaired; both minute heartbeats advance with one worker. Exact source ingress/browser-transition latency and remaining real-source scenarios remain P8-03 gates; [latest evidence](../verification/p8-03/runtime-follow-up.md). Actual half-hour reconciliation passed at **16:00 UTC**, worker success **16:00:04**, queue empty. See [current evidence](../verification/p8-03/README.md) and [operational handoff](QAMMARIS_APP_OWNER_ENV_HANDOFF.md). Preparation observations below are historical and do not override this update. Production remains separately approved.

## Progress denominator

The program board has 10 phases (P0–P9); 7 are marked DONE: P0 and P2–P7. **70% is phase-count progress only**, not percentage of effort or production readiness. P1 still has production backup/cutover preflight outstanding; P8 is in progress; P9 has not started. P8-01 backend and P8-02 presentation are deployed to staging; actual feed/webhook availability cycle and reconciliation are proven. P8-03 is IN_REVIEW with explicitly deferred/remaining runtime gates, while P8-04 is preparing the Owner-approved feed launch catalog. Hosting access and staging credentials are now confirmed. There is still no defensible launch-date/remaining-hours estimate before the reviewed launch set, its media/data gaps and production preflight are known.

Three remaining launch stages below are delivery gates spanning P1/P8/P9, not three new architecture projects. The machine mutation API, AI integration and source price review UI remain separate future work. Owner explicitly added feed-authoritative drafts and Shopee media to P8-04 on 2026-10-06; this overrides the earlier optional-catalog scope.

## Historical pre-activation evidence

| Gate | Evidence | Status |
| --- | --- | --- |
| Laravel receiver | `route:list --path=integrations -v`: POST webhook, throttle 120/min | Verified locally |
| Reconciliation definition | `schedule:list`: `*/30 * * * *` queues `qammaris-app:sync` | Verified definition; deployed cron not confirmed |
| Worker implementation | Database queue, 60s timeout, five tries, 10s/60s/300s/900s backoff; database retry_after 90s | Code inspected; persistent hosting worker not confirmed |
| Credentials | Runtime check emitted presence booleans only: API key false, HMAC secret false, client configured false | Local connection disabled; remote configuration not confirmed |
| Regression | 24 integration/presentation tests / 186 assertions passed on this preparation; full suite 222 / 1487 passed during P8-02 | Isolated SQLite, synthetic credentials and fake HTTP; no live connection proof |
| Staging topology | `HOSTINGER_GITHUB_DEPLOYMENT.md` records separate staging domain/MySQL and Basic Auth on 2026-09-15 | Historical evidence; current remote state not confirmed |
| Webhook ingress | Staging workflow adds unconditional `Require valid-user` Basic Auth | Potential staging delivery blocker before Laravel; no exemption approved/applied |
| Public HEAD checks | Attempts against staging root and webhook path failed at transport with a socket exception, no HTTP response obtained | Not confirmed; do not infer 401, route deployment, outage, or source API failure |
| Release pipeline | Manual hPanel source deployment + `staging-release.yml` with exact committed `expected_revision`; workflow builds assets, migrates staging, optimizes | Defined; does not install/restart this worker or schedule cron |
| Release revision | P8 changes currently in working tree | Reviewed immutable release SHA / current remote CI still required |
| Catalog preview | Loopback preview uses 2026-09-14 public-table snapshot: 180 products, 64 offers, 19 image records, no users | UI preview only; production counts/mappings/completeness not confirmed |

## Stage 1 — Real API connection on staging (P8-03)

Candidate receiver URL, pending Owner confirmation:

`https://staging.qammarisparfum.id/integrations/qammaris-app/webhook`

The upstream source URL must be confirmed against the deployed internal app; the Laravel default is configuration, not connectivity evidence. Owner places API key and secret in protected environment configuration, never chat/Git/logs. Credentials must match both sides; no employee JWT or new human permission is needed.

Activation order:

1. Obtain explicit approval for **staging only**, its exact receiver URL, deployment/migration, dedicated worker/scheduler and any webhook ingress permission change. Production remains outside this approval.
2. Inspect actual staging release/environment/schema, backup/restore, PHP/runtime, clocks, separate database/storage, access and available process-manager capability. Do not treat old staging evidence as current. A 30-minute sync or minute-cron queue drain is not a substitute for the requested prompt webhook processing. If persistent execution is unavailable, bring the concrete hosting constraint to Owner before choosing another runtime.
3. Prepare/review the complete release diff, record an immutable commit SHA and green CI, confirm the publishing branch and auto-deploy settings before a push that could deploy. Take a staging backup. Deploy that SHA through the existing exact-revision pipeline; additive migrations only, no seed or replacement with the SQLite preview.
4. Owner installs credentials securely in both environments and selects the app's staging receiver target. Confirm environment isolation; do not redirect operational delivery from an existing production target without an explicit plan.
5. Resolve Basic Auth ingress before testing. A proposed option is an **exact webhook-path-only** exception from human staging Basic Auth, with Laravel HMAC still mandatory and all review pages protected. This is a separate security/access decision, not an automatic `.htaccess` edit. Verify Apache rewrite behavior and the actual URL on the server, and ensure subsequent staging releases preserve the approved behavior. Alternative upstream support for a staging Basic Auth header is not confirmed; no internal-app changes are made by this website task.
6. Start the dedicated supervised database worker documented in `QAMMARIS_APP_INTEGRATION.md`, verify crash/restart recovery and code/config refresh, and verify the once/minute scheduler with the 30-minute reconciliation task. Do not share this worker's timeout/queue configuration blindly with image-import jobs.
7. Use an explicitly approved synthetic staging product/UUID pair; preview the pair before mapping. Send source changes through the real app and measure actual source-change → webhook acceptance → worker commit → public label latency. The app's approximately seven-second dispatch estimate is not the website's verified SLA.

Required staging acceptance matrix:

| Scenario | Required result / evidence |
| --- | --- |
| Valid signal | 202 after durable enqueue, then consumed job, advanced checkpoint/source revision and machine audit; 202 alone does not pass |
| Authentication | Invalid/missing HMAC rejected by Laravel; human staging pages remain protected; no auth bypass or secrets in output |
| Available / sold out / inbound | Same state reaches mapped website product; no expiry/checked-time prompt for connected products |
| OTW / unknown | OTW remains Habis · Restok segera; unknown neutral; no inferred quantity |
| Duplicate / older / revert | No duplicate mutation or revision regression; newer revert applies and audits |
| Hidden / merge | Public scope/detail/inquiry exclude hidden without deleting product/media or reassigning identity |
| Missed webhook | Actual scheduled reconciliation restores the missed change; checkpoint/revision correct |
| Source outage / malformed page | Product state retained, current page checkpoint unchanged, safe error/recovery, no partial page writes |
| Multiple pages / concurrent jobs | MySQL page transaction/checkpoint serialization works, continuation drains, no skipped changes |
| Worker restart | Pending work survives and consumes after supervised restart; old code/config does not remain in memory |
| Non-stock fields | IDs, URLs, publication, offers/prices, taxonomy and media unchanged |

Stage 1 passes only when these runtime outcomes and the approved configuration/release SHA are recorded. Tests with fake HTTP do not complete this gate.

## Stage 2 — Feed launch drafts and media review (P8-04)

Owner approved current feed snapshots as launch authority (452 total / 446 visible), new drafts only, explicit name-derived size/concentration, and Owner-owned Shopee cover + first two additional downloads. Old 180-product backup is a read-only preservation matching reference. Source=app requires review. See [draft runbook](QAMMARIS_APP_LAUNCH_DRAFTS.md) and ADR-022.

A separate preview/apply CLI creates only unmapped visible drafts with immutable audit and source/catalog stale guards. Stock worker does not create/publish or alter prices. Existing mapped website ID/slug/price/media remain; ambiguous existing catalog candidates block automatic creation. Initial source price applies only to new drafts. Missing data is never invented.

Stage preparation passes when all eligible staging UUIDs have one record, draft/no-publish and preservation/repeat safeguards hold, strong photos are acquired or explicitly reported failed, and manual photo/data exceptions are reviewable. This is not approval to publish incomplete drafts or replace production DB.

Before production, resolve actual production UUID pairings and old URL/media preservation, choose reviewed launch subset, complete required catalog facts/photos and publish via readiness validation, with P1-04 backup/restore proof. P8-03 rare-state live tests deferred by Owner remain visible in the release risk matrix.

## Stage 3 — Production hardening, release and observation (P9-01)

1. Final regression/CI and browser acceptance on the deployed candidate: mobile/desktop catalog, search/filter, real media, detail, inquiry, admin/auth, hidden/sold-out behavior, sitemap/URLs. Confirm production configuration, indexing policy, HTTPS, debug off, worker/scheduler, log hygiene and failure recovery; do not reuse staging Basic Auth or synthetic users.
2. Record production code/schema/data/media baseline, verified backup/restore, reviewed release SHA, migration timing and rollback/forward-fix plan. Never replace production with the local preview or delete the legacy release/media.
3. Show the actual staging evidence and launch/mapping scope to Owner for **separate production cutover approval**. Proposed final receiver: `https://qammarisparfum.id/integrations/qammaris-app/webhook`, pending confirmed production target.
4. Execute the approved additive deployment/configuration/mapping sequence. Switch the source delivery URL only within that plan; supervise queue drain, checkpoint, source revisions, media/URL health and reconciling missed deliveries.
5. Observe actual operational changes/retries and at least one real scheduled reconciliation after cutover, recording latency, failures and recovery. Preserve source snapshots/audit/checkpoint through forward fixes. No automatic destructive rollback or cleanup.

Pass: Production actually consumes signed changes and renders correct mapped states; reconciliation and recovery proven, URLs/media preserved, release evidence and Owner acceptance recorded. Optional later automation/outgoing APIs are not required to call this stock integration launch complete.

## Approval / handoff boundaries

- Ready to review: existing P8 diff, this runbook, candidate staging URL, exact-path ingress proposal, existing additive migration and dedicated worker/schedule commands.
- Owner decisions required: staging activation and ingress permission, protected credential installation, selected mapping/launch set, then production cutover approval after staging evidence exists.
- This preparation did not change application code, `.env`, database, media, hosting permissions, worker process or deployed URL. It did not access the internal-app repository or send operational source changes.
- Preparation rollback: revert this documentation/backlog entry only. Runtime rollback after approved activation follows `QAMMARIS_APP_INTEGRATION.md`; retain integration state rather than dropping tables/resetting checkpoints.
