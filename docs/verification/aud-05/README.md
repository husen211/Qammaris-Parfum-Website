# AUD-05 — Shared editor save, validation and explicit Shopee dependencies

**Subsequent release:** PR24 is merged and production `9325df4` activated; [MySQL/runtime/release proof](../audit-release/README.md). The dated implementation evidence and its remaining verification limits below are retained; pre-release status is superseded.

Date: 2026-10-07. Owner direction: “lanjutt” after AUD-04 recommended AUD-05. Status: IN_REVIEW; local implementation/verification complete, branch/release acceptance pending. No production deployment or AUD-06 execution.

## Outcome and changed files

`AdminProductController` now keeps HTTP results/list context and calls [SaveProductEditor](../../../app/Actions/Products/SaveProductEditor.php) for both create/update. The operation shares the explicit payload, notes, offer/media/publish sequence, database transaction and new-file rollback cleanup. Obsolete conversation comments were removed. Existing offer, attachment, storage and publication operations retain their behavior.

[ProductEditorRequest](../../../app/Http/Requests/Admin/ProductEditorRequest.php) shares stable rules/messages/comparison validation; [store](../../../app/Http/Requests/Admin/ProductStoreRequest.php) and [update](../../../app/Http/Requests/Admin/ProductUpdateRequest.php) retain their distinct completeness, taxonomy, SKU/parent ownership and image/availability checks. Input normalization still uses checkbox presence; the operation never mass-assigns the whole request.

[ShopeeContentGuard](../../../app/Services/ShopeeContentGuard.php) extracts existing actor/contract/payload assertions for [ApplyShopeeContent](../../../app/Actions/Products/ApplyShopeeContent.php), [QueueProductImportImages](../../../app/Actions/Products/QueueProductImportImages.php) and [ShopeeContentImageTarget](../../../app/Services/ShopeeContentImageTarget.php). Existing write collaborators and the [controller](../../../app/Http/Controllers/Admin/AdminShopeeContentController.php) image queue are injected. No universal validator, import contract/version change or lock/dispatch reordering. The queue's separate launch-pair environment guard is intentionally untouched.

Regressions changed in [AdminProductValidationTest](../../../tests/Feature/AdminProductValidationTest.php), [AdminProductImageTransactionTest](../../../tests/Feature/AdminProductImageTransactionTest.php) and [QammarisAppCatalogWorkflowTest](../../../tests/Feature/QammarisAppCatalogWorkflowTest.php): note/blank-comparison/checkbox normalization, rejection of another product's offer, ignoring non-editor identity/publication fields, connected-price preservation through HTTP, and publication failure after upload on create/update. Failure restores DB fields/offer/media metadata, returns publication blockers and cleans only new files, retaining existing files.

## Checks actually run

- Before edits: `php artisan test --compact --filter='AdminProduct(Validation|ImageTransaction|Publication|CatalogContext|Availability)|ShopeeContentImportTest'`: **68 passed / 425 assertions**. Same subset after initial extraction: **68 passed / 425 assertions**.
- Expanded targeted subset including `QammarisAppCatalogWorkflowTest|ProductDraftAndOfferTest`, after correcting new test fixtures to the existing admin ID routes/error keys: **94 passed / 616 assertions**.
- Full `php artisan test --compact` after Pint: **354 passed / 2467 assertions**. Includes feed, source-price/availability, publication, rollback/storage, child/actor/tamper protection, Shopee media recovery and legacy CSV/launch/maintenance contracts.
- `node --test tests/js/*.test.mjs`: **31 passed**, zero failures.
- `npm run build`: **passed**. Existing DaisyUI `@property` optimizer and large 3D chunk warnings remain unrelated.
- Composer strict metadata validation with explicit installed PHP and Composer PHAR: **passed**. No install/update.
- Targeted Pint for all 13 changed/new PHP files and `git diff --check`: **passed**.

No Blade/CSS/JS or UI behavior changes. No browser viewports/screenshots were run for this backend-only task; HTTP/render assertions are not presented as device evidence. Existing AUD-04/P9 device/browser limits remain open. Local SQLite tests do not prove MySQL lock behavior under concurrent production workers or a live source webhook; existing lock order is preserved, not newly benchmarked.

## Data, media and recovery

No application schema, source data, prices/status/publication, persistent product media, production credentials/accounts or server configuration were changed. PHPUnit uses its isolated in-memory SQLite and fake disks; all new records/media are synthetic test fixtures. Vite regenerated ignored local build artifacts only. Pre-existing unrelated `tools/__pycache__/` remains untouched.

The save operation still relies on server-boundary validation and existing product guards. File IO remains synchronous inside the editor DB transaction as before; the extraction does not claim a fully atomic filesystem. Failed cleanup is reported using the retained implementation; no generalized audit/event system was added. The shared request adds one explicit inheritance level, not a form framework. Existing Blade duplication was deliberately left alone to avoid UI scope expansion.

Rollback: revert this code/docs commit through the normal reviewed process; no migrations, media cleanup, historical imports, checkpoint resets or data restore are needed. A separately approved deployment must restart long-running workers through the existing runbook. Next recommendation: **AUD-06**, starting with a money/rounding decision and read-only evidence; not started.

## Documentation

Updated `docs/planning/BACKLOG.md`, `docs/architecture/ARCHITECTURE.md`, [ADR-029](../../architecture/decisions/ADR-029-shared-product-editor-save.md) and its index. Business rules/runbooks are unchanged because no business decision or release procedure changed.
