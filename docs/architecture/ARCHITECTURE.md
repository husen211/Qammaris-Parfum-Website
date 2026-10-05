# Current application architecture

## Qammaris app consumer (P8-01, 2026-10-05)

```text
Internal app -> signed POST /integrations/qammaris-app/webhook
            -> raw-body HMAC + bounded validation + throttle
            -> database queue qammaris-app -> 202

Webhook job / 30-minute scheduled reconciliation
            -> SyncQammarisAppFeed -> read-only HTTPS changes feed
            -> validate page -> lock/check local checkpoint
            -> cache latest allowlisted UUID snapshots
            -> existing qammaris_app identity mapping
            -> ApplyQammarisAppAvailability
            -> product availability/hidden/ETA + before/after audit
            -> commit page checkpoint atomically

Public catalog/inquiry -> Eloquent published scope / isPubliclyVisible
                      -> exclude upstream hidden tombstones
                      -> connected availability without expiry
                      -> CatalogAvailability source-aware labels
                      -> Blade cards/detail + fresh inquiry resolution
```

Unmapped source records stay in `qammaris_app_products`; no matching, draft creation or publication is inferred. Explicit CLI mapping previews IDs and reuses existing identity invariants, then replays cached state. Feed price/name/taxonomy are review snapshots, not catalog writes. Product IDs, URLs, offers and media remain website-owned. Human availability edits are rejected for connected products; provider CSV cannot overwrite the source metadata.

The webhook is stateless, outside session/CSRF web routes, and uses HMAC instead. Credentials stay in config/env. Receiver acknowledgement means a database job exists, not that synchronization already completed. Worker/scheduler/secrets and deployed URL are not activated or verified by this code change.

P8-02 centralizes availability labels in `CatalogAvailability`, shared by Blade cards/detail, `CartController` and `InquiryWhatsApp`. Inquiry rows are resolved from current product metadata on every request; session labels are not trusted. App-only inquiries use reservation-neutral notices; legacy/mixed lists retain their confirmation notice. The drawer loads this notice from the existing JSON endpoint and resets to neutral during loading/error/empty states. Connected details hide checked timestamps; legacy freshness is unchanged. No UI redesign or new database fields are required by P8-02.

ADR-021 defines the integration. `CURRENT_STATE.md` remains the historical discovery baseline. Public copy/restock UI is implemented locally in P8-02; activation remains unverified. The restricted website mutation API and Shopee media import remain separate future items.
