# Hostinger inode cleanup (OPS-INODE-2026-10)

hPanel on 2026-10-17 showed 403,410 / 400,000 inodes (101%) and 9.63 / 20 GB disk for `qammarisparfum.id` + `staging.qammarisparfum.id`. Inodes count files and directories, not bytes. The limit is per hosting account, so production and staging share it. At 100% new files can fail: uploads, sessions, cache, logs and the next deploy.

## Cause

Every production deploy unpacks a complete release (code + `vendor`, ~13,200 files and directories) into `releases/qammaris-<sha>`. Nothing removed old releases or the per-deploy leftovers `releases/.incoming-<sha>.tar.gz` (~58 MB each) and `releases/.deploy-<sha>`. GitHub records 33 successful production deploys up to `535d5f3`, which is roughly 33 × 13.2k ≈ 435k inodes in releases alone (exact count: step 1). Staging (hPanel Git checkout with `.git`, `vendor`, storage) adds to the same quota.

From the next deploy, `deploy-production-release.sh` prunes after a successful health check: it keeps the live release, the release it switched away from (rollback target) and one more, and removes leftover archives and temp dirs. The existing backlog still has to be removed once by hand (step 2), because the deploy itself may fail while the account is over quota.

The scripts avoid process substitution (`<(...)`): Hostinger's CageFS has no `/dev/fd`, so it fails there.

## Result (2026-10-17)

Measured on the server: 403,361 inodes in the account, 328,975 of them in `releases/` (36 release dirs, 33 `.incoming` archives, 10 `.deploy` dirs, 2 old `.github-*.zip` / `.package-*`). The prune kept `535d5f3` (live), `2d81f8f` (previous in `deployments.log`) and `3c83143`, and removed 32 releases and 47 leftovers (289,331 inodes). Afterwards: 114,033 inodes (28.5%); `/up`, homepage, a product page and `/admin/login` returned 200 and the production worker kept running. `releases/qammaris-85ef088` (pre-cutover, 14 inodes, real `storage` and `.env`) is outside the release name pattern and was left alone.

Staging was then retired with Owner approval: database dump and `.env` saved off-server to the Owner laptop (`C:\projects\Qammaris App\backups\staging-retired-2026-10-17\`), both staging cron jobs and its queue worker stopped, the `Staging release` workflow disabled, and the hPanel website deleted together with `u429527638_qam_staging`. The account then held 70,907 inodes (17.7%). Staging used the same `QAMMARIS_APP_API_KEY` as production, so that key was not revoked; rotating it is a separate decision.

## What is never touched

Releases contain code only. `shared/qammaris-website/` holds `.env`, `storage` (product photos, logs, sessions, cache, private files) and deploy state; the database lives in MySQL. Each release links to `shared` with symlinks; deleting a release removes the link, never the target. `current` and the rollback release are always kept.

## Steps (Owner, over SSH from a laptop with this repository)

The commands stream the script over SSH, so nothing new is written on the full server. Replace `<port>` and `<host>` with the hPanel SSH details. Do not use the staging deploy key.

1. Read-only report. It prints paths and counts only:
   ```
   ssh -p <port> u429527638@<host> 'bash -s' < tools/hostinger/inode-report.sh
   ```
2. Preview, then remove old releases:
   ```
   ssh -p <port> u429527638@<host> 'bash -s' < tools/hostinger/prune-production-releases.sh
   ssh -p <port> u429527638@<host> 'bash -s -- --apply' < tools/hostinger/prune-production-releases.sh
   ```
   The first command is a dry run. It lists every directory with its inode count and the live release. Run `--apply` only if the live release shown is the one serving the site.
3. Check the site: `https://qammarisparfum.id/up`, the homepage, one product page and the admin login. Run step 1 again and compare.
4. hPanel → File Manager → **Trash**: files deleted in File Manager stay in the trash and still count. Empty it if it holds old deletions.

Other items from step 1, decided case by case:
- `shared/.../storage/framework/cache` large: with `CACHE_STORE=file` expired entries are never deleted. `php artisan cache:clear` in `current` is safe: config/route caches are separate, and the App employee list reloads within one page view.
- `storage/framework/sessions` large: run `php artisan session:gc` only if sessions use files. Active admins may need to log in again.
- An old `public_html` site, `~/.cache/composer` or `~/.npm`: report first; remove only when nothing links to it.

## Staging (retired 2026-10-17, see Result)

Kept as the record of how it was removed. Staging (`staging.qammarisparfum.id`) had its own website files, MySQL database (catalog snapshot), `.env` and Basic Auth; production never read it, but it shared the inode quota and the account-wide cron list.

Deleting staging:
1. Download a database export (phpMyAdmin → Export) and the staging `.env` (File Manager → Download) to the laptop. Keep them off the server and out of Git.
2. Remove the staging cron entries in hPanel → Cron Jobs (`staging-qammaris-worker-watchdog.sh` and any `staging.qammarisparfum.id` scheduler). Keep every `production-…` entry.
3. Disable the GitHub workflow `Staging release` (`gh workflow disable "Staging release"`). The `staging` environment secrets can stay until a later cleanup.
4. Delete the staging website in hPanel, then its database and database user.
5. Check production again (step 3 above).

Production does not depend on any of it. A new staging can be rebuilt from Git plus the saved export.
