# BLOG-02 — CMS editorial verification

2026-10-07. Review branch `modernization/blog-02-editor`, isolated from concurrent order work. Depends on BLOG-01 (`cb0a1c3`, [PR26](https://github.com/husen211/Qammaris-Parfum-Website/pull/26)); no staging/production migration or deployment performed.

## Outcome and scope

Dedicated Tiptap visual/HTML editor; persistent Konten/Media/SEO/Relasi/Publikasi sections; draft defaults; new-publication readiness; managed category/tag activation; authenticated transient desktop/mobile preview using the public article renderer. Existing author/slug/content/media are preserved. Old published articles with missing new image metadata remain visible and show a review notice. No article backfill or public Journal redesign.

Product markers discard source HTML/price/status and resolve eligible current catalog names/links at render time. Complete live price/status cards, relations, responsive media and richer components remain BLOG-04. API/SEO rollout remains later scope.

## Executed checks

| Check | Result |
|---|---|
| Full Laravel suite | 415 tests, 2,887 assertions passed |
| Blog regression filter | 40 tests, 254 assertions passed |
| New editorial CMS tests (included above) | 12 tests, 83 assertions passed |
| JavaScript suite | 31 tests passed |
| Production build | Passed, 721 modules; editor 444.18 kB / 140.72 kB gzip |
| Pint dirty check / Composer strict validation / git diff check | Passed |

Regression coverage includes incomplete/private drafts, publication and scheduling, legacy edits, stable category URLs and additive schema, inactive taxonomy, tag clearing/no-op/audit rollback, preview authorization/no-write/noindex, sanitizer table spans and product-marker trust boundaries. PHP tests run locally with SQLite, not MySQL runtime or DDL/concurrency verification.

An extra regression rerun after removing the browser fixture environment failed with MissingAppKeyException (test bootstrap lacked its synthetic APP_KEY). Repeated using `.env.example` plus a synthetic key, as CI prepares its environment: all 40 blog tests / 254 assertions passed. That temporary environment was removed afterward; no application fix was needed.

Initial GitHub run [37632543650](https://github.com/husen211/Qammaris-Parfum-Website/actions/runs/37632543650) passed Node/build but failed four new CMS tests because the PHP job has no Vite manifest. The test class now uses `withoutVite()` like existing blog tests. Build correctness remains covered separately by the actual browser/build checks; application asset loading is unchanged.

After that fix, temporarily moved the local manifest aside and repeated the full Laravel suite: 415 tests / 2,887 assertions passed (36.95 seconds). Restored the manifest and removed the synthetic environment afterward. Review: [draft PR28](https://github.com/husen211/Qammaris-Parfum-Website/pull/28), based on BLOG-01; latest CI is available on that PR.

`npm audit` reports three pre-existing entries: concurrently 9.2.4 and shell-quote 1.9.0 (critical; same underlying shell-quote advisory), source-map-js 1.2.1 (high). Their installed versions are unchanged from the base lockfile. Recorded as SEC-DEP-01; no automatic dependency upgrades. Existing DaisyUI CSS optimizer and large 3D chunk warnings remain. New Tiptap dependencies are pinned to 3.31.4 and loaded only by the admin editor entry.

## Browser evidence

Real Chrome browser viewport checks: 320, 375, 390, 768 and 1440 px. At each width, measured document scrollWidth equals viewport width; the rich editor stays within its container. These are mouse/viewport checks, not touch emulation.

Observed visual typing, HTML/visual round-trip with H2/H3, strong text, quote, lists, merged table, link and separator; mode changes retained the source. Draft saved with keyboard Enter and reopened with exact source and separate introduction. Publication rejected missing image/alt while retaining inputs. Preview displayed article content and suppressed share/view count. Taxonomy tag added then deactivated. Blank image draft preview uses the placeholder; the old synthetic fixture's absent hero asset was not a production media test.

Native Chrome upload could not complete: the extension's file URL access was disabled. The chooser attempt stalled, then returned that error; Escape recovered the form. No permission settings changed. Server-side upload, verification and failure compensation tests passed, but native upload UX remains unverified. Genuine iPhone Safari/touch is also unverified. Local fixture contained no eligible catalog products, so product selection rendering is covered by server tests rather than a populated browser dropdown.

| Screenshot | Evidence |
|---|---|
| [Before desktop](before-desktop.jpg) / [before mobile](before-mobile.jpg) | Existing HTML form |
| [After desktop](after-desktop.jpg) | Grouped CMS form |
| [Visual editor desktop](editor-desktop.jpg) | Rich text editing |
| [390 px](after-mobile.jpg) / [320 px](editor-320.jpg) | Narrow editor layout |
| [Preview desktop](preview-desktop.jpg) / [preview mobile](preview-mobile.jpg) | Shared article renderer |
| [Validation](validation-desktop.jpg) | Retained form and field errors |
| [Taxonomy](taxonomy-desktop.jpg) | Reversible tag activation |

Temporary local server stopped; test tab closed and viewport reset. Synthetic local `.env`, fixture script, SQLite database (including test account/articles/tags), and its test session were removed. No production articles, accounts, prices, stock or media changed.

## Files and documentation

Core writes: `app/Actions/Blog/SaveBlogPost.php`, `RecordBlogPostChange.php`; shared `BlogEditorialRules`, `RenderBlogContent`, sanitizer; BlogPost/BlogCategory/BlogTag models; admin/public controllers and requests; routes. Additive schema: `2026_10_07_000003_add_blog_editorial_cms.php`.

UI: shared `admin/blog-posts/_editor`, create/edit/index/taxonomy/preview views, `components/journal-field`, public `blog/show` preview/metadata integration, dedicated `blog-editor.js` and CSS, Vite entry and package lock. Regression tests: `tests/Feature/BlogEditorialCmsTest.php`.

Updated [backlog](../../planning/BACKLOG.md), [business rules](../../product/BUSINESS_RULES.md), [master plan](../../architecture/MASTER_PLAN.md), [architecture](../../architecture/ARCHITECTURE.md), [Journal roadmap](../../planning/QAMMARIS_JOURNAL.md), [ADR-033](../../architecture/decisions/ADR-033-blog-editorial-cms.md) and [editor runbook](../../runbooks/BLOG_EDITOR.md).

## Acceptance and recovery limits

Staging MySQL migration/runtime, native upload and touch acceptance remain before release. Feature branch does not deploy; staging/production approval is separate. Retain additive columns/tables, audit and old media. Use a compatible rollback/forward fix that understands managed category_id; pre-Journal code can mislabel custom categories. Do not migrate down populated tables. Audit hashes are not full article version backups.

Recommended next item: BLOG-03 public Journal/SEO, after Owner selects it. It has not started.
