# Qammaris — master plan current

Konsolidasi 2026-10-07 (AUD-02). [BACKLOG](../planning/BACKLOG.md) adalah board/status item; [BUSINESS_RULES](../product/BUSINESS_RULES.md) adalah aturan; [ARCHITECTURE](ARCHITECTURE.md) adalah flow executable. [Plan sebelum konsolidasi](../history/2026-10-07-context/MASTER_PLAN.md) mempertahankan tahap awal, keputusan cutover dan evidence. Pending/angka di sana tidak boleh dianggap status current.

## Tujuan dan prinsip tetap

Website Laravel untuk discovery dan pemesanan WhatsApp, admin sederhana, bulk enrichment aman dan integrasi aplikasi internal yang terbatas. Pertahankan Blade/Eloquent/MySQL, identity/slug/media, visual Qammaris dan shared product operations. Satu item approved pada satu waktu; jangan refactor karena ukuran file atau pola dianggap best practice. Production bukan tempat eksperimen.

Majoo tetap sistem operasional. Qammaris App mengirim UUID/status/harga, website mengelola publication/konten/media; tidak menggantikan POS atau mendapat stok numerik. Tidak ada SPA rewrite, generic repository, microservice, event bus, autonomous AI atau payment gateway pada program current.

## Separate approved preference program

[V1 preference plan](../planning/QAMMARIS_FRAGRANCE_PREFERENCE.md) approved2026-10-09. PREF-01 audit/tooling/scenario packet is IN_REVIEW pending actual Owner/staff labels and semantic/reference freeze. Owner explicitly continued PREF-02 implementation2026-10-10; separate profile/engine code and offline evaluation are IN_REVIEW with independent calibration/quality gates pending ([evidence](../verification/pref-02/README.md)). PREF-03 website/feedback/admin and PREF-04 acceptance/release remain unstarted; no phase advances automatically. Preserve existing catalog/site/legacy quiz and operational integrations. Previous blog/About release scopes do not authorize this deployment; post-acceptance quiz release needs separate permission. This program does not close original P9 or initiate offline-store V2.

## Hasil current dan dependency

```text
Foundation/test/product/media operations (existing)
    -> feed HMAC/cursor + automatic UUID drafts/source prices (released)
    -> human Shopee/manual enrichment + readiness publication (released)
    -> catalog/search/direct cart/recipient WhatsApp checkout (released)
    -> bounded production acceptance P9 (IN_REVIEW, limits explicit)
    -> AUD-01 evidence-first audit (DONE)
    -> AUD-02 current-context consolidation (DONE)
    -> AUD-03–07 bounded cleanup (DONE; approved release PR24)
    -> remaining P9 acceptance (not started by audit release)
```

Advancing to the audit does not mark P9 limits passed. AUD-02 is not a release or authorization to execute the next refactor. AUD-01–07 were reviewed/consolidated into PR24 and released after Owner-approved staging/MySQL rehearsal and targeted production migration: [release proof](../verification/audit-release/README.md). Original P9 acceptance gates remain; these audit tasks are not newly invented launch blockers. No AUD-08 or later program is started.

Already implemented: one-product/one-offer foundation; archive-over-delete/media lifecycle; CSV/bulk snapshot-preview-apply audit; app feed/worker/reconciliation; recurring Shopee XLSX/content recovery; catalog GET state/typo search; daily homepage best sellers without prices; recipient checkout/native loading feedback. Evidence is linked per item, not copied as counts here.

## Architecture and data boundary

- Keep stable website IDs/variant IDs/slugs and external UUID identities; different sizes normally separate products. Publication is human/readiness-checked, independent of source availability.
- Connected availability has no expiry/outage downgrade. Valid newer app prices update automatically through shared offer operations; no proposed-price approval loop for connected recurring products.
- Persistent public product storage remains current; Laravel disk abstraction/R2 rehearsal stays, but no cloud migration is required.
- Human admin forms/imports and any future API reuse the same validated operations. Feed client is a consumer; a website write API has not been built.
- Bulk changes need preview/idempotency/conflict/audit. Destructive work and data/media migrations require exact approved scope plus verified recovery. Additive migrations, copy-verify-switch-retain for files; no broad checkpoint rewind/reseed.
- Owner's no-legacy-backup/no-legacy-migration waiver was scoped to the 2026-10-06 fresh launch. It is historical evidence, not blanket permission to discard current production data.

## Deployment and closure gates

GitHub code/asset delivery is active according to dated [cutover proof](../verification/p1-04/PRODUCTION_CUTOVER.md) and [latest P9 observation](../verification/p9-01/README.md). Per-commit releases preserve private shared env/storage/DB. Main CI + enable/activation/revision/migration guards gate release. Main merge can deploy docs-only; separate Owner release approval is required. Do not change secrets/permissions/sites just to complete documentation.

The original program is **not 100% closed**. Outstanding evidence/acceptance:

1. Genuine iPhone Safari/touch single-tap/scroll behavior; resized mouse viewports are not a substitute.
2. Native browser file upload path under existing extension permissions; real persisted Owner batch/photos are confirmed, the chooser/upload path itself is not.
3. A controlled new production source event: timing/replay/outage behavior beyond already recorded integration tests/staging proof and cached-source parity.
4. Populated production recipient checkout/WhatsApp handoff; current read-only P9 checked empty-cart/navigation, not a submitted live customer order.
5. Owner acceptance of P1/P8/P9 scope and remaining limits; restore/recovery claims must be tied to their concrete local/staging evidence, not inferred for current production.

[P9-01](../verification/p9-01/README.md) contains the exact evidence and exclusions. Only a separately approved verification task or explicit scoped waiver can close these gates; documentary cleanup does not satisfy them. No additional production testing/writes are authorized here.

## Documentation as ongoing context

Use the README read path. Existing current documents have distinct owners; historical snapshots, ADR decision text and dated verification retain provenance. Future features update only affected rules/boundaries/operational steps plus item status/evidence. Audit proposals remain proposals until approved. Every report distinguishes source proof, runtime observations and Not confirmed.

## Qammaris Journal — separate approved program

Owner approved [the Journal plan](../planning/QAMMARIS_JOURNAL.md), BLOG-01–05 implementation and the blog-only release. PR31 installed foundation/CMS/public Journal/SEO/media/components/draft API code in production on2026-10-08 after authorized MySQL staging rehearsal and targeted production migrations. [Release evidence](../verification/blog-06/README.md) and [board](../planning/BACKLOG.md) distinguish delivered code from remaining Owner/editorial/device/credential review. Order PR27/32 are explicitly excluded.

Preserve `/blog/...`, HTML, IDs/authors/content/media. Tiptap vanilla is admin-only; Sanctum is implemented with separate machine identity/ownership, strict draft-only abilities and no catalog writes. Production machine API/local actor and token were subsequently enabled in the separately approved2026-10-08 [activation operation](../verification/journal-activation/README.md). Cloud-only secret provisioning is not configured. Original P9 remains open. This completed release is not permission for later automatic deployment, new credential grants or article rewrites.

## Other later programs, not started

Website machine-write API requires revocable/scoped actor identity, field allowlists, shared tested operations, idempotency/conflict handling and audit; automation never gets unrestricted MySQL access. Payment gateway requires Owner decisions on acceptance/shipping/quantity, persistent order/line/payment lifecycle, signed/idempotent provider notifications and sandbox testing. These are separately scoped programs, not prerequisites invented for the existing WhatsApp order flow. [ADR-028](decisions/ADR-028-whatsapp-order-checkout.md) records the bounded future payment considerations.
