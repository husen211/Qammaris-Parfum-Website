# Writing Journal drafts with an agent

Use the [v1 API contract](../api/JOURNAL_AUTOMATION.md). This is a draft-writing integration. Production activation and local Codex credentials were approved and verified2026-10-08 ([evidence](../verification/journal-activation/README.md)); additional machine destinations/credentials remain separately scoped. Never use an admin login or Qammaris App feed key.

1. Retrieve token from your secret store in memory, never in committed code/prompt/body/log. Pick only required abilities. Fetch active categories/tags and public catalog metadata; preserve catalog internal IDs. Do not infer new taxonomy or invent product facts.
2. List/read your own drafts. Ask Owner to assign an existing draft through admin before touching it. 403 means stop; switching account or guessing IDs is not recovery.
3. Draft accurate editorial copy. Author defaults Qammaris Editorial; excerpt is a card summary, subtitle is optional introduction. Start meaningful sections at H2/H3. Avoid copied price/stock paragraphs: use product IDs/cards that read current data at render. FAQ/reference lists are optional structured fields with bounded counts.
4. POST with a stable unique key; keep the same validated payload for retries. Capture ID/slug/revision. Unknown fields are errors. Never send publication/status/actor/is_featured/view_count/slug or activation date.
5. Upload owned JPEG/PNG/WebP as a file, including alt/license/credit/source and crop/focal values. Source URL is provenance only, not a download request. Confirm rights and image quality for Owner; do not hotlink, fetch internal URLs or put arbitrary image URLs in HTML. Upload increments article revision; keep returned media ID/new revision.
6. PATCH at the latest revision to select hero and insert canonical owned media/gallery/product markers. Renderer HTML, arbitrary iframe/script, foreign files and cross-article media are invalid. Video uses approved YouTube marker. Omitted fields remain; explicit []clears lists. Use HTML compatible with the server sanitizer/editor.
7. 409 revision: GET current draft and reconcile. 409 pending replay: wait Retry-After then reuse same key/payload. 422: correct the named fields; a changed payload needs a new key only after the old attempt was rejected/rolled back. 429: honor Retry-After. 500: same-key retry, never create duplicate drafts. Persistent pending needs operator investigation, not a new key. 401/403/503 stop and report to Owner without exposing token.
8. Deliver draft link/ID, sources/rights notes, unresolved factual issues and current revision to Owner. Owner previews mobile/desktop and decides publication. Agent cannot publish/schedule/archive/delete. Do not call product write, feed, order or payment routes.

No draft automatically appears in the public Journal. Do not claim the article is live based on a successful draft save or upload.

## Codex on this Windows laptop

The approved token is in **Windows current-user environment**, not the repository `.env`, a prompt, or Git. Existing running apps may have an older process environment. Read the current-user value in the same PowerShell process as the request; never print it or dump environment variables.

```powershell
$journalToken = [Environment]::GetEnvironmentVariable('QAMMARIS_JOURNAL_TOKEN', 'User')
if (-not $journalToken) { throw 'Journal token is not configured for this Windows user.' }
try {
    Invoke-RestMethod -Method Get `
        -Uri 'https://qammarisparfum.id/api/automation/v1/blog-taxonomy' `
        -Headers @{ Authorization = ('Bearer ' + $journalToken); Accept = 'application/json' }
} finally {
    $journalToken = $null
}
```

Base URL is also available as QAMMARIS_JOURNAL_BASE_URL. For other requests use the same in-memory Authorization header, the API contract's fields, stable create/upload keys and latest revision. No new account or admin password is needed. Read current API/docs status rather than treating dated BLOG-05/06 pre-activation evidence as current configuration.

A cloud-only executor has no configured secret from this operation. Use its own scoped actor/token and actual supported secret store when its destination is selected; do not paste this laptop token into chat, a message to another thread, or committed setup scripts.
