# ADR-020 — Public Catalog State and Inquiry Boundary

Status: Accepted — 2026-09-17

## Context

Katalog existing mempunyai form GET untuk search, kategori, brand, serta sort, tetapi pagination dan detail tidak mempertahankan state. Mobile dan desktop juga dapat menampilkan sort berbeda. Detail memakai stock variant sebagai `In Stock`, sementara domain Qammaris menetapkan availability terpisah, tidak real-time, dan mempunyai freshness window 36 jam. Outcome customer adalah inquiry melalui WhatsApp, bukan checkout atau reservasi stok.

## Decision

1. URL GET adalah source of truth discovery publik. Parameter allowlisted adalah `search`, `brand[]`, `category`, `gender`, `price_min`, `price_max`, `availability`, `sort`, dan `page`.
2. Validation/normalization state dilakukan server-side dan dipakai bersama oleh query, control desktop/mobile, pagination, serta context detail.
3. Pagination mempertahankan seluruh state valid dan mengganti hanya `page`. Perubahan search/filter/sort menghapus `page`.
4. Detail membawa context katalog tervalidasi dan menyediakan kembali-ke-hasil tanpa menerima arbitrary return URL. Canonical product URL tetap tanpa query.
5. Availability publik hanya memakai effective availability product. Stock variant dan quantity snapshot tidak boleh menghasilkan klaim `In Stock`.
6. Sold-out tetap published/discoverable. Action berubah menjadi inquiry restock; `unknown` menjadi konfirmasi stok; fresh `available` tetap diberi bahasa non-reservasi.
7. Satu product mempunyai satu offer customer-facing. Ukuran dan harga ditampilkan sebagai informasi, bukan multi-variant selector.
8. Cart customer-facing berevolusi menjadi daftar inquiry. Route/session internal boleh dipertahankan sementara untuk migrasi incremental, tetapi copy tidak boleh mengklaim transaksi pembayaran atau reservasi.
9. Implementasi tetap Laravel/Blade/GET forms dengan progressive enhancement. Tidak ada framework atau dependency UI baru.

## Consequences

- URL hasil dapat dibagikan, direload, dipaginasi, serta dikembalikan dari detail secara konsisten.
- Satu validation boundary mencegah drift antara mobile, desktop, query, dan link.
- Customer tidak menerima klaim stok yang lebih kuat daripada data yang dimiliki Qammaris.
- URL detail dengan context membutuhkan canonical tag tanpa query untuk menghindari duplicate indexing.
- Legacy published rows tanpa offer/primary image menjadi data-quality state eksplisit dan harus direkonsiliasi sebelum cutover; UI tidak boleh menebak datanya.

## Alternatives rejected

- Menyimpan filter hanya di JavaScript/local storage: tidak shareable, gagal tanpa JavaScript, dan mudah drift dari query server.
- Arbitrary `return_to` URL: membuka risiko open redirect dan context yang tidak tervalidasi.
- Menggunakan variant stock sebagai availability: bertentangan dengan freshness/source semantics dan memberi kesan live inventory.
- Mengubah langsung menjadi SPA atau commerce checkout: tidak diperlukan untuk journey katalog/inquiry dan memperluas risiko tanpa alasan produk.

## Rollback dan forward fix

- ADR ini belum mengubah runtime. Sebelum implementasi, rollback cukup menghapus dokumen kontrak dan ADR.
- Setelah item Phase 7 berjalan, perubahan query/state harus di-forward-fix dengan mempertahankan kompatibilitas URL existing; jangan menghapus parameter publik tanpa redirect atau normalization terukur.
- Perubahan copy/action availability dapat di-rollback independen dari schema karena memakai domain state yang sudah tersedia.
