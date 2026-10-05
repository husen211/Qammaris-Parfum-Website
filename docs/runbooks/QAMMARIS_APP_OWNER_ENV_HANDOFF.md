# Owner handoff — staging API activation

Scope: internal-app **backend Node** at `api.qammarisapp.com` and Laravel website staging. Do not redeploy the app frontend, modify app database or switch to the website production receiver.

Two distinct random 32-byte hexadecimal values were generated without printing them. The local secret store is Windows DPAPI-encrypted, bound to this Windows user, outside Git at `%LOCALAPPDATA%\QammarisWebsite\IntegrationSecrets\staging-api-secrets.clixml`. Directory/file access is restricted to this user and SYSTEM. Do not open/export it as plaintext or attach it to chat. Re-running initialization reuses the pair; it never rotates them silently.

## Backend app env — Owner action when panel control is unavailable

Use the authenticated Hostinger panel for **api.qammarisapp.com backend**, not the frontend site. Set only:

- `WEBSITE_API_KEY`
- `WEBSITE_WEBHOOK_SECRET`
- `WEBSITE_WEBHOOK_URL` = `https://staging.qammarisparfum.id/integrations/qammaris-app/webhook`
- Optional `WEBSITE_FEED_SAFE_DELAY_MS` = `5000` (already the default)

From a local PowerShell terminal in the website repository, copy one value directly from the encrypted store to the clipboard; the helper prints no secret:

```powershell
./tools/Manage-QammarisStagingSecrets.ps1 -Action CopyApiKey
```

Paste it **only** into the protected `WEBSITE_API_KEY` backend env field. Then:

```powershell
./tools/Manage-QammarisStagingSecrets.ps1 -Action CopyWebhookSecret
```

Paste it **only** into `WEBSITE_WEBHOOK_SECRET`. Set the public staging URL above, save and restart/redeploy **only the backend**. Use the host's normal secure configuration editor; do not screenshot revealed fields or paste them into terminal commands/chat. Clear the copied value after pasting:

```powershell
./tools/Manage-QammarisStagingSecrets.ps1 -Action ClearClipboard
```

This clears the clipboard only when it still contains one of this pair. Remove a secret from clipboard history too if the operating system retained it; do not sync/share the clipboard contents. The store is user/machine-bound; do not transfer its file to another computer as a handoff mechanism.

Reply only **“env backend terisi, backend sudah restart”**. No values are needed in chat. The same pair **is already installed** in website staging as `QAMMARIS_APP_API_KEY` and `QAMMARIS_APP_WEBHOOK_SECRET`, with base URL `https://api.qammarisapp.com/api/public/v1`. Website env and cached config are 0600. Do not generate a different pair for the app.

## Website staging process supervision

The worker and `schedule:work` are running for staging tests. SSH has no `crontab` executable. Durable supervision requires configuration in the **website staging** hosting panel, separately from app backend env.

If using Hostinger custom cron, configure these as two separate once-per-minute entries, without credentials in commands:

```text
bash /home/u429527638/domains/staging.qammarisparfum.id/public_html/storage/app/private/p8-03-worker-watchdog.sh
bash /home/u429527638/domains/staging.qammarisparfum.id/public_html/storage/app/private/p8-03-scheduler.sh
```

After the website team verifies cron execution, it must stop the recorded temporary `schedule:work` process to avoid duplicate scheduler invocations. The worker watchdog shares a file lock to prevent a second integration worker. A hosting process manager may be used if available; crash/reboot recovery must be verified. Do not change other sites' jobs.

## Next verification, after backend configuration

Without a key the feed must change from 503 to 401. Laravel then tests the key and drains the sequence feed from checkpoint 0, preserving hidden tombstones internally and excluding them from the public catalog. Record aggregate counts and one public-safe product example, not complete response bodies.

After initial sync and one reviewed staging product/UUID mapping, Owner changes that exact source product's availability (sold out → revert/inbound), allowing the website team to measure the real webhook/worker/public-label path. A price-only source edit exercises revision delivery but does **not** change the website selling price, which remains a reviewed proposal by business rule. Do not alter an operational product merely for testing without selecting it explicitly.

Verify duplicate/old signals are no-op, actual 30-minute reconciliation, and queue restart recovery. Website production cutover and changing `WEBSITE_WEBHOOK_URL` to the production website remain separately approved work.
