# Journal agent activation — 2026-10-08

Owner explicitly permitted machine credentials for laptop/cloud and subsequently chose Codex. Scope: activate existing production draft API and configure a local Codex token. Cloud-only provisioning is not configured; no cloud secret store was selected or modified. No message/secret was sent to another chat. Store-hours PR34 is separate and remains unmerged.

## Observed result

- Production revision92886bc5b00d8f22b3e2aa499c5fb893873d8f64; zero pending migrations; PHP8.2.33/GD/WebP and file cache supported. Approved BLOG-06 staging MySQL/auth/replay/ownership/media rehearsal was already complete.
- Protected shared env updated only BLOG_AUTOMATION_ENABLED=true, atomically with mode retained. Config cache rebuilt and enabled value verified. Existing env secrets were never exported. No new application code/dependency/deployment/migration. Anonymous draft API returns401, while public health/Journal return200; server metadata confirms zero owned drafts, three published articles and453catalog products.
- Separate machine actor1, token1; allowed abilities blog:read/blog:write/blog:media/catalog:read. Expiry2027-01-06T04:47:58Z (90 days). Sanctum stores its hash; human admin/feed credentials untouched.
- Token captured over verified SSH directly to process memory, then saved in Windows current-user environment as QAMMARIS_JOURNAL_TOKEN, alongside non-secret QAMMARIS_JOURNAL_BASE_URL. No raw token in chat/tool output/Git/helper script/receipt. User environment is a persistent current-user registry value, not an encrypted secrets manager; applications running as this Windows user can read it.
- HTTPS GET taxonomy, own drafts and public products each returned200 with data. A second PowerShell request explicitly reading the current-user value passed and returned zero owned drafts. Existing process environment need not be refreshed.
- No production draft, media upload, published article, catalog/order change or human account change was made. Source code/guard regression tests are the previously recorded BLOG-05/06 results, not newly rerun here. No UI change/browser screenshots needed for credential setup.

[Non-secret receipt](receipt.json), [final runtime/anonymous checks](final-checks.json), [API contract](../../api/JOURNAL_AUTOMATION.md), [agent instructions](../../runbooks/JOURNAL_AGENT_GUIDE.md). Raw tokens must not be included in reports or messages. Never test secrets by printing env values.

## Containment and remaining work

Revoke exactly token1 belonging to actor1, or disable actor1, using the operator runbook. To close the whole API, set BLOG_AUTOMATION_ENABLED=false and rebuild config cache. Preserve actors/audit attribution/additive schema/media. Clearing local current-user Journal environment values removes only laptop access; it does not revoke the server token. No automatic expiry renewal.

Cloud-only executor access, provider-managed proxy/APM header logging, sustained concurrent production writes, production upload and Owner editorial walkthrough remain unconfirmed. No claim of completed cloud provisioning or publishing is made. Next is a real Owner-selected article topic saved as a draft, not automatic publication or order work.
