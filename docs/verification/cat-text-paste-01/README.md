# CAT-TEXT-PASTE-01 and CAT-COMPARE-PRICE-01 verification

Date: 2026-11-02. Branch `fix-keep-pasted-photos` (bundled with CAT-MEDIA-PASTE-02 at the Owner's request to merge together). Local only (SQLite, `php artisan serve` + Vite); nothing ran against production.

## CAT-TEXT-PASTE-01 — pasted Shopee text fills notes, gender and size

`resources/js/ui/product-text-paste.js` (parser `parseProductText`, panel `admin/products/_text_paste_panel`). Pasting into "Deskripsi" (or "Pisahkan dari deskripsi") shows "Ditemukan dari teks"; "Isi ke kolom" fills only after the click; "Kembalikan teks asli" restores the previous description, notes, gender and size. Nothing is saved until "Simpan".

| Case (1440 / 390) | Result |
|---|---|
| Shopee text with emoji bullets, "READY STOCK", separator, "Deskripsi Produk:", `Top/Middle/Base Notes : …`, `Gender : Unisex`, `Ukuran : 50ml`, shipping line, hashtags | Top Bergamot, Pink Pepper · Middle Rose, Jasmine · Base Vanilla, Musk, Amber · Unisex · 50 ml · 11 lines removed; description keeps the name line, the prose and "Longevity: 6-8 jam" |
| Bare "Top Notes:" with bullet lines, "Heart Notes: …", "untuk pria" (390) | Apple, Cinnamon, Lavender · Orange Blossom, Lily of the Valley · Vanilla, Tonka Bean, Amber · Pria · 100 ml ignored because the size field was already filled |
| "Kembalikan teks asli" | original description back, notes/gender/size back to empty |
| "Simpan draft" after applying | stored gender Unisex, notes `{top:[Bergamot, Pink Pepper], middle:[Rose, Jasmine], base:[Vanilla, Musk, Amber]}`, 50 ml, cleaned description |

Rules: a size is taken only when the text names exactly one ml value and the size field is empty; gender only from explicit words (unisex / pria / wanita and English equivalents), "pria … wanita" → Unisex; all-caps or all-lowercase notes are title-cased, mixed case kept; duplicates dropped.

## CAT-COMPARE-PRICE-01 — compare-at price options

`resources/js/ui/compare-price-options.js` (partial `admin/products/_compare_price_options`). Options +30/+50/+70% of the selling price, rounded up to Rp 10.000 below Rp 200.000 and Rp 50.000 from there; one click fills the field, the chosen option is marked, the field stays optional and editable, and options follow the selling price as it changes. Rp 409.000 → Rp 550.000 (+34%), Rp 650.000 (+59%), Rp 700.000 (+71%); a stored "409000.00" is read correctly; the saved compare-at price was 550000.

## Screenshots

| Check | 390 | 1440 |
|---|---|---|
| Before: description, compare-at and notes | — | [before-edit-1440](before-edit-1440.jpg) |
| After: "Ditemukan dari teks" panel | [after-found-panel-and-compare-390](after-found-panel-and-compare-390.jpg) | [after-found-panel-1440](after-found-panel-1440.jpg) |
| After: fields filled, compare options | — | [after-applied-and-compare-1440](after-applied-and-compare-1440.jpg) |

## Tests

- `tests/js/product-text-paste.test.mjs` (6) and `tests/js/compare-price-options.test.mjs` (4).
- `AdminProductImageTransactionTest::test_add_and_edit_pages_offer_text_split_and_compare_price_options`.

Synthetic data (one draft, a temporary admin) was removed afterwards.
