# Journal media and components — BLOG-04 review

Not deployed. BLOG-01/02/03 and additive migration2026_10_07_000005 are prerequisites. No automatic historical media backfill or purge. Owner approves staging writes and production release separately.

## Editor workflow

1. Save the article first. Media → Upload media and tinjau crop opens media belonging to that article. Returning to the editor reloads its current revision; keep a copy of unsaved writing before leaving.
2. Upload JPEG/PNG/WebP, up to5MB,6000px per side and12MP. Supply descriptive alt and rights/license; external photography also needs its HTTPS source and credit. Target1600×900; smaller sources work with a quality warning. Review JPEG orientation; rotate before upload if necessary.
3. Choose Gambar utuh,16:9,4:3 or1:1 and focal percentages0–100. Preview shows the same crop rectangle used by the server. Save metadata/crop and inspect the result. Original and prior crops remain available; “Lihat gambar asli” opens the stored original.
4. Return to the editor. Select an owned image as main image, or add image/gallery from “Tambahkan komponen artikel”. A new direct main-image file takes precedence over the selector. Gallery takes2–8 different images in chosen order. Callout, CTA, YouTube ID/URL and inline related article have labeled inputs; visual mode is required for insertion. HTML mode accepts only validated canonical markers.
5. Relasi lists let you add, move and remove products/articles/FAQ/references. Limits are displayed. Removing all explicitly clears the list on save; omitted fields from other write paths preserve existing values. FAQ text is plain text, references HTTPS. Products/articles not public are hidden on read. Products show current catalog price/size/status without changing catalog data.
6. Preview desktop/mobile, then save. Preview writes no article/files/history/views. It is sandboxed; gallery can scroll and FAQ can expand, but JavaScript arrow controls/video playback are not active there. Check those on a local/staging public article before release.

Empty media has an upload instruction. During save there is pending feedback; after15seconds the button permits retry without claiming success. Validation/storage failures retain the failed form's text and existing files; select the upload again. Revision conflict requires explicit “Muat ulang media terbaru” after copying unsaved changes. Do not repeatedly submit a stale revision. Feature image must be replaced before its media can be archived; archive retains files and makes inline use disappear publicly.

## Runtime preflight and release

Use the actual web PHP runtime, not just CLI: GD, JPEG/PNG decoding, WebP encoding, sufficient memory and upload/post limits. `BLOG_MEDIA_RESIZE=false` deliberately disables processing; capability failure retains original and reports a warning, never fake derivatives. Check media disk writable/persistent storage, storage link/URL and nested blog/variants delivery. CI enables GD; local tests must invoke PHP with GD in the test process itself, since artisan test/serve can spawn a child without CLI -d flags.

Before an approved release, rehearse additive000005 on staging MySQL with existing articles. Compare IDs/slugs/body/author/image references and public visibility. Test actual upload, recrop, storage/DB failure, responsive srcset and genuine touch/keyboard. Existing original links must remain valid. This phase changes no accounts, credentials, catalog rows or production data.

Recovery: keep originals, all derivative generations, blog_media/featured pointer/JSON lists and blog history. Failed creation attempts remove only new unattached files, logging exception class on cleanup failure for operator follow-up. Do not delete entire blog storage or run populated migration down. Restore a previous compatible release or forward-fix; code predating these markers can hide content or strip components on save. No automatic cleanup or media restoration UI is provided.

[ADR-035](../architecture/decisions/ADR-035-journal-media-and-components.md), [editor guide](BLOG_EDITOR.md), [evidence](../verification/blog-04/README.md).
