# P8-07 — Owner-approved Shopee pairs and manual candidate review

2026-10-06. Scope is Owner-requested staging supplemental photos/descriptions by app-team mapping evidence; no automatic publication or production changes.

## Actual results

| Measure | Before | After |
| --- | ---: | ---: |
| Connected website products | 446 | 446 |
| Drafts / published test fixture | 445 / 1 | 445 / 1 |
| Drafts with website-stored primary photos | 179 | **297** |
| Drafts without photos | 266 | **148** |
| Drafts with curated descriptions | 113 | **297** |
| Active photo records, including fixture | 519 | **861** |
| External identities | 625 | **743** |
| Active offers | 316 | 316 |
| Human users | 0 | 0 |
| Feed checkpoint | 456 | 456 |

**118** new Shopee identities, **184** blank descriptions filled, **342** new actual stored images. Existing 179 draft photo pairs are contained in the approved set and agree with the app team's evidence. No previously existing description/media is overwritten. All 297 draft targets have exact same source-description text as the curated basic export; publication readiness remains independent, with original 76 clear audiences retained.

The 298 approved source rows include the already published AOERA MAJESTIC 50 ML integration fixture (website ID1, app UUID `00360de8-31bd-4982-bd73-ddaaba2d9658`, Majoo SKU `AOERAMAJESTIC50ML`, Shopee code `53616980299`). It is held unchanged, not demoted or edited through a draft-only operation. Accordingly **297 drafts applied**, not 298. The fixture still has its synthetic image/copy; it is not a newly photo-paired launch draft.

## Sources and matching

Owner CSV SHA256 `357ab1e01fbfcb3e21d5ae45f3a35f84ad2d3aab0fa48af5f822e34bbd63fb52`; exact 371 rows: 9 `sku`, 289 `kuat`, 26 `perlu_cek`, 30 `ambigu`, 17 `tidak_ketemu`. Media export `mass_update_media_info_1853666049_20261005201835.xlsx` and basic export `mass_update_basic_info_1853666049_20261006100429.xlsx` stay read-only. All 371 provider IDs and source names agree across files. Source hashes, original mapping/source data and curated descriptions remain in private ignored artifacts. No export instruction is treated as authorization.

Every approved Majoo SKU and all **102** candidate references exist in the current 452-snapshot feed. **Missing SKU list: empty (0)**; approved SKUs/targets are unique, visible and mapped, except protected fixture. Matching preserves exact string identifiers; no case-folding, partial/truncated/name fallback. One supplied candidate has a newline in its name, retained by parser rather than dropped. The original stricter name/concentration matcher is not reapplied to Owner-approved `sku`/`kuat` evidence.

56 manual rows remain unchanged, with all supplied candidates and 56 verified real source covers in [private Owner review](https://staging.qammarisparfum.id/owner-review/p8-07-pairing/). All source covers are downloaded/verified/embedded; no Shopee hotlinks. 17 unmatched source names are downloadable from the review. The remaining 148 website photo gaps are not automatically treated as 75 unique missing Shopee products: candidate choices can collide or resolve to already paired UUIDs. 35 candidate references are already occupied; two rows have only occupied candidates (Des Tentations For Men, Khadlaj Island Dreams). Owner may propose correction choices with an explicit conflict flag, but no provider rebinding occurs. Additional manual apply requires fresh guards and Owner direction.

## Code and staged apply

Implementation overlay `5580deb246a4af09911b6a7ae70466d43d3a48bc`; contract correction `f199ccf5cc175dceb76f69a9cfc976e7c2e5cd75`; [CI 37408533984](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37408533984) passed. Baseline server Git remains 1867d83 with prior P8-04/P8-06 overlays. Four allowlisted PHP files deployed from immutable Git bytes and one constant fixed with exact old/new hash verification. Existing modes and env/config/Basic Auth/frontend manifest hashes unchanged; code originals retained privately. No migration/install/cache rebuild/auth/credential/permission change or extra database backup.

MySQL initial preview rejected contract string longer than existing varchar(20). The transaction left catalog and existing 2 batches/565 rows unchanged. Shortened contract `qammaris-pairs-v1` and explicit width regression check resolve it without changing schema. Persisted **batch 3 / 371 rows / 297 valid / 74 held** independently compared against every source proposal, resolved UUID/website ID and fresh capture before apply. Held rows are 56 manual + 17 unmatched + 1 fixture. Apply/queue reuse existing identity, audit, safe acquisition and attachment primitives. Replay has identical product/identity/batch/row digest. No human account fabricated.

Full 446-product before/after comparison allows only 184 description/timestamp changes; every product ID, other attribute, offer and original identity/image record retained. 118 new identity records have exact approved provider IDs. 342 new images belong to exact paired targets, successful cover before additional images, at most 3 active photos per product. Every new file checksum/bytes/detected MIME/dimensions matches persisted acquisition outcome. Every original 519 file byte checksum retained. Review covers do not count as product media. All relevant image jobs completed, temporary worker exited.

Health **03:32:14 UTC**: source without key 401/with key 200; next_seq 456, has_more=false; no stored sync error; stock/image queues 0. Scheduler and watchdog heartbeats 03:32:01 UTC (13 seconds old), one actual PHP stock worker, no schedule:work/image/review downloader processes. Initial process count included flock wrapper; executable/cwd filter confirms one actual worker. No app settings/frontend/database or production webhook URL changed. Secret values never printed/copied/logged/committed.

## Review and verification

Review is a standalone snapshot behind existing staging Basic Auth, not a new admin account/API or published-product bypass. Directory/index/CSS/JS/unmatched CSV all anonymous401; existing authenticated Owner Chrome renders. Initial new review directory was created with private artifact modes and returned403; retained unchanged and superseded by new readable public artifact creation, without chmod or permission changes to any existing path. Raw inputs/captures remain private. No existing P8-06 review file changed.

Chrome exact 1440x900 and 390x844 CSS viewports checked. 56 rows/102 options, no preselection, horizontal overflow, broken loaded covers, external image URLs or console errors. Search by SKU returns exact row; filters 26/30, selected subset, empty/reset/clear, retained choice on filter reset, keyboard disclosure/focus and description visibility checked. Real one-choice JSON downloaded to Owner's Downloads with correct SKU/UUID/website ID/fingerprint/provenance; browser event waiter timed out, but actual file/timestamp/content prove download. Own generated test file retained privately then removed from Downloads with exact content/timestamp/hash guards. Duplicate selected UUIDs show corrective error and do not export. All test choices cleared before handoff; Owner decisions have not been made/applied.

Screenshots: [desktop before](review-desktop-before.jpg), [mobile before](review-mobile-before.jpg), [desktop review](review-desktop-after.jpg), [mobile review](review-mobile-after.jpg). Baseline is existing P8-06 Owner artifact, not an unrelated public-site redesign. Native select/details plus explicit export reduce repeated manual lookup while preserving human decisions. Temporary viewport override reset; final review tab left for Owner.

Tests actually run: full local Laravel **251 passed/1644 assertions**, focused after contract correction **9 passed/53 assertions**, initial pairing+draft suite 21/124; CI on corrected commit **251 passed/1646 assertions**, frontend build passed. **6 Python** regression checks: newline/leading-zero candidates, malformed candidate rejection, exact provenance/copy cleanup/source retention, changed SKU without name fallback, duplicate source rejection and safe HTML/conflict/no-preselection output. JS syntax, Pint on 5 PHP files and Git whitespace checks passed. Local Laravel tests use isolated SQLite/fake HTTP/storage, never production. MySQL-specific contract width issue and staging integrity verification are separately recorded above. Rebuilt four-file review is byte-identical.

## Files, recovery and next Owner action

Changed PHP: `app/Actions/Products/PairQammarisShopeeDrafts.php`, `app/Console/Commands/PairQammarisShopeeDrafts.php`, existing `QueueProductImportImages.php` and `AcquireProductImportRowImages.php`. Coverage: `tests/Feature/QammarisShopeePairingTest.php`; tools: `prepare_shopee_pairs.py`, `build_shopee_pair_review.py`, `test_shopee_pairs.py`. Documentation: business rules, current architecture, ADR-024, backlog, pairing runbook and this evidence/screenshots. No Blade/build/config/schema/package changes.

Recovery: disable bounded pairing command/restore exact retained PHP originals; keep successful media/audit/copy, use fresh reviewed forward corrections. Code rollback does not revert data. Never restore staging over production. Production backup remains immediately before a separately approved cutover.

P8-07 is IN_REVIEW. Next Owner action is choose/review56pairs and resolve occupied corrections; then separately approve any factual/audience completion and exact staging publication subset. No next implementation item, production cutover or automatic publication started. P8-03 deferred real OTW/timing/runtime gates remain deferred rather than claimed passed.
