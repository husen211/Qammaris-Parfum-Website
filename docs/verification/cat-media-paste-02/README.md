# CAT-MEDIA-PASTE-02 verification — pasted photos survive a failed save

Date: 2026-11-02. Branch `fix-keep-pasted-photos`. Local only (SQLite, `php artisan serve` + Vite); nothing ran against production.

Problem reported by the Owner: photos pasted with Ctrl+V disappeared when "Publish" came back with a missing required field (size in ml), so they had to be pasted again. A browser cannot refill a file input after the server returns the form.

## Change

- `resources/js/ui/product-photo-paste.js`: when the form has pasted photos, the submit waits for one IndexedDB write (`qammaris-admin-photos`, key `photos:<form path>`) and then submits with the same button. A page that comes back with validation errors (`data-restore` on the photo box, set when `$errors->any()`) puts those photos back as "Belum disimpan" with "N foto dikembalikan…". The copy is dropped when that page loads again without errors, on a submit without pasted photos, or after one hour; it is never restored on a page without errors. If IndexedDB is unavailable the upload still happens, only the safety copy is missing.
- `resources/js/ui/product-publish-check.js`: "Publish" with an empty size or price is stopped in the browser with the native message ("Isi ukuran (ml) sebelum publish.") and focus on the field; drafts are never blocked; a read-only App price is left to the server check.

## Browser checks (1440 and 390)

| Case | Result |
|---|---|
| Edit: 2 photos pasted, Publish with empty size | no reload, size field focused with the message, both photos still in the box ([publish-blocked-size-1440](publish-blocked-size-1440.jpg)) |
| Edit: size and price filled, description empty, Publish | server rejects ("Isi deskripsi sebelum mempublikasikan produk."), page returns with both photos restored and 100 ml kept ([restored-after-error-1440](restored-after-error-1440.jpg)) |
| Same at 390 with 1 photo | restored, no horizontal overflow ([restored-after-error-390](restored-after-error-390.jpg)) |
| Then "Simpan draft" | both photos stored as `.webp`, first one primary |
| Add product: Publish without size, then without description | blocked in the browser, then restored after the server error; the typed name kept |

## Tests

- `tests/js/product-photo-paste.test.mjs`: storage key per form page, one-hour freshness.
- `tests/js/product-publish-check.test.mjs`: publish blocked on the first empty field; drafts never blocked.
- `AdminProductImageTransactionTest::test_editor_sent_back_with_errors_asks_the_browser_to_restore_pasted_photos`.
- JS 85/85; full local PHP suite 833 passed, 10 skipped, 3 known local-only failures (VAPID, 2× J&T QR without GD).

Synthetic data (one draft, a temporary admin, two stored WebP files, the browser's IndexedDB copy) was removed afterwards.
