# Architecture decision index

Use [current business rules](../../product/BUSINESS_RULES.md) and [current architecture](../ARCHITECTURE.md) for what applies today. ADRs retain accepted reasoning/tradeoffs and dated rollout context; rejected alternatives and historical pending remarks are not automatic current instructions.

Important supersession: ADR-025 updates connected recurring drafts/prices from ADR-021/022; ADR-028 updates the inquiry-only journey in ADR-020; ADR-027 defines current relevant search. Earlier R2 rehearsal decisions do not require moving current production media. Later Owner-approved launch exceptions are scoped to their exact historic datasets.

| Canonical ID | Decision |
|---|---|
| ADR-001 | [Legacy Production and Clean Redeployment](ADR-001-legacy-production-and-clean-redeployment.md) |
| ADR-002 | [Kontrak Produk Katalog](ADR-002-kontrak-produk-katalog.md) |
| ADR-003 | [Publication dan Availability sebagai State Terpisah](ADR-003-publication-availability-transition.md) |
| ADR-004 | [Draft Minimal dan Single Offer sebagai Price Authority](ADR-004-drafts-and-single-offer-price-authority.md) |
| ADR-005 | [Boundary Identitas Produk Eksternal](ADR-005-external-product-identity-boundary.md) |
| ADR-006 | [Boundary Storage Media Produk](ADR-006-product-media-storage-boundary.md) |
| ADR-007 | [Lifecycle Attach dan Primary Image Produk](ADR-007-product-media-primary-lifecycle.md) |
| ADR-008 | [Copy-Verify R2 sebelum Cutover Media](ADR-008-r2-copy-verify-before-cutover.md) |
| ADR-009 | [Endpoint Delivery R2 Staging dan Production](ADR-009-r2-staging-delivery-endpoint.md) |
| ADR-010 | [Soft Archive dan Urutan Media Produk](ADR-010-product-media-archive-and-ordering.md) |
| ADR-011 | [Canonical Product CSV dan Preview Read-only](ADR-011-canonical-product-csv-preview-boundary.md) |
| ADR-012 | [Persistent Immutable Import Preview Batches](ADR-012-persistent-immutable-import-preview-batches.md) |
| ADR-013 | [Transactional Product Import Draft Apply](ADR-013-transactional-product-import-draft-apply.md) |
| ADR-014 | [Safe Imported Image Acquisition](ADR-014-safe-imported-image-acquisition.md) |
| ADR-015 | [Manual Protected Product Import Resolution](ADR-015-manual-protected-import-resolution.md) |
| ADR-016 | [Import Batch Audit CSV Report](ADR-016-import-batch-audit-csv-report.md) |
| ADR-017 | [Read-only Catalog Maintenance Snapshot](ADR-017-read-only-catalog-maintenance-snapshot.md) |
| ADR-018 | [Internal-ID Bulk Maintenance Preview](ADR-018-internal-id-maintenance-preview.md) |
| ADR-019 | [Transactional Bulk Maintenance Apply](ADR-019-transactional-maintenance-apply.md) |
| ADR-020 | [Public Catalog State and Inquiry Boundary](ADR-020-public-catalog-state-and-inquiry-boundary.md) |
| ADR-021 | [Qammaris App availability integration](ADR-021-qammaris-app-availability-integration.md) |
| ADR-022 | [Feed-authoritative launch drafts](ADR-022-feed-authoritative-launch-drafts.md) |
| ADR-023 | [Restricted staging launch copy](ADR-023-staging-launch-copy.md) |
| ADR-024 | [Owner-approved Shopee SKU pairing on connected drafts](ADR-024-owner-shopee-sku-pairs.md) |
| ADR-025 | [Recurring app drafts and automatic source prices](ADR-025-recurring-app-catalog-admin-workflow.md) |
| ADR-026 | [Impor konten Shopee berulang melalui admin](ADR-026-recurring-shopee-content-import.md) |
| ADR-027 | [Relevant typo-tolerant search](ADR-027-relevant-typo-tolerant-search.md) |
| ADR-028 | [Recipient checkout and WhatsApp ordering](ADR-028-whatsapp-order-checkout.md) |
| ADR-029 | [Shared admin editor save and Shopee guards](ADR-029-shared-product-editor-save.md) |
| ADR-030 | [Whole rupiah and current-catalog cart totals](ADR-030-whole-rupiah-and-current-cart-totals.md) |
| ADR-031 | [Narrow admin product change history](ADR-031-admin-product-change-history.md) |
| ADR-032 | [Blog write foundation](ADR-032-blog-write-foundation.md) |
| ADR-033 | [Blog editorial CMS and transient preview](ADR-033-blog-editorial-cms.md) |
| ADR-034 | [Public Journal search, rendering and SEO](ADR-034-public-journal-search-seo.md) |
| ADR-035 | [Journal owned media and complete components](ADR-035-journal-media-and-components.md) |
| ADR-036 | [Journal scoped draft automation](ADR-036-journal-draft-automation.md) |
| ADR-037 | [Source-derived fragrance profiles and human reference first](ADR-037-fragrance-preference-review-first.md) |
| ADR-038 | [Isolated provisional preference engine](ADR-038-provisional-preference-engine.md) |
| ADR-039 | [Explicit Owner-authorized preference beta](ADR-039-preference-website-beta.md) |
| ADR-040 | [Adaptive preference questions with factual evidence](ADR-040-adaptive-preference-questions.md) |
| ADR-041 | [Private Order API v1 implementation](ADR-041-order-api-v1-implementation.md) |
| ADR-042 | [Order API contract r4.2](ADR-042-order-api-r4-2-draft.md) |
| ADR-043 | [Operational UX simplification for online orders](ADR-043-order-operational-ux.md) |
| ADR-044 | [Website cart checkout saves a guest order](ADR-044-website-checkout-orders.md) |
| ADR-045 | [Payment before fulfillment and the order stepper](ADR-045-payment-gate-and-order-stepper.md) |
| ADR-046 | [Guest order history, paperbag charge, direct WhatsApp and App task links](ADR-046-order-ux-polish-history-paperbag.md) |
| ADR-047 | [Installable public website (Qammaris Perfumes PWA)](ADR-047-public-website-pwa.md) |
| ADR-048 | [Online order links, tracking, and staff task links](ADR-048-online-order-links-and-tracking.md) (aliases: [ADR-034](ADR-034-online-order-links-and-tracking.md), [ADR-037](ADR-037-online-order-links-and-tracking.md) filenames) |
| ADR-049 | [Admin roles, abilities, and user management](ADR-049-admin-roles-and-user-management.md) (aliases: [ADR-035](ADR-035-admin-roles-and-user-management.md), [ADR-038](ADR-038-admin-roles-and-user-management.md) filenames) |
| ADR-050 | [Qammaris Admin PWA](ADR-050-admin-pwa.md) (alias: [ADR-039 filename](ADR-039-admin-pwa.md)) |
| ADR-051 | [Order state cutover and payment/refund ledger](ADR-051-order-cutover-and-money-ledger.md) (alias: [ADR-040 filename](ADR-040-order-cutover-and-money-ledger.md)) |
| ADR-052 | [Courier cost on the Website, automatic approved reimbursement in the App](ADR-052-website-courier-cost-and-auto-reimbursement.md) |
| ADR-053 | [Web Push for new website orders (admin)](ADR-053-admin-web-push.md) |
| ADR-054 | [Walkable 360 store tour](ADR-054-store-virtual-tour.md) |

Checkout formerly shared ADR-026 with Shopee. The [old checkout filename](ADR-026-whatsapp-order-checkout.md) remains a compatibility alias to ADR-028; ADR-026 uniquely means recurring Shopee enrichment. Retained [original checkout](../../history/2026-10-07-context/ADR-026-whatsapp-order-checkout.md) preserves provenance. ADR-032–040 belong to the Journal and fragrance preference on main. The order line first used 034/035, then 037–040; for the release candidate (2026-10-11) those order decisions became ADR-048–051, and every old order filename stays as an alias. Use canonical ID + descriptive filename in new references; do not delete aliases or silently renumber historical evidence.
