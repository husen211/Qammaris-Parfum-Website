# Isolated staging for ORD-02 E2E — deployment plan

Status: **plan only.** Nothing has been bought, provisioned, deployed or reconfigured.

Owner decision (2026-10-09):
- Do not run E2E on the old Hostinger staging.
- Build a staging that is isolated from production, ideally on its own hosting, account or VPS.
- `staging.qammarisparfum.id` may be reused with the right DNS.
- Database, credentials, `APP_KEY`, queue, scheduler, file storage and deploy rights must all be separate.
- No paid service may be bought or activated without Owner approval.
- No change to production.

## 1. What "isolated" means here

| Resource | Rule |
|---|---|
| Account / OS user | A different hosting account or VPS. Not the production Hostinger account or OS user. |
| Database | A new, empty MariaDB database and user on the staging server, holding synthetic data only. The old staging database, which holds the real catalog, is never used or copied. |
| Secrets | New `APP_KEY`, and a new Order API secret for each direction. The App catalog feed is off (`QAMMARIS_APP_API_KEY` empty). No production key of any kind. |
| Queue / scheduler | A database queue on the staging database. One worker and one scheduler, both on the staging server only. |
| File storage | The local disk of the staging server. No R2 bucket or other shared bucket. |
| Deploy rights | A new SSH deploy key, used only for this server, held in a new GitHub Environment `staging-e2e`. It has no path to production and is revocable on its own. |
| Mail / messaging | `MAIL_MAILER=log`. WhatsApp is only `wa.me` links. The code has no payment, Majoo or courier API. |
| Access | HTTPS, Basic Auth on every web path except the Order API (which has its own HMAC), plus `X-Robots-Tag: noindex` from the app. |

## 2. Minimum server

Production parity: PHP **8.2**, MariaDB **11.8**.

| Item | Minimum |
|---|---|
| CPU / RAM / disk | 1 vCPU, 2 GB RAM (1 GB works with swap), 20 GB SSD |
| OS (VPS) | Ubuntu 24.04 LTS |
| Web | Nginx (or Apache) serving `public/` only |
| PHP | 8.2 FPM with `ctype curl dom fileinfo filter hash mbstring openssl pcre pdo pdo_mysql session tokenizer xml zip gd intl`, plus Composer 2 |
| Database | MariaDB 11.8 with utf8mb4. Defaults as production: `explicit_defaults_for_timestamp=ON`, strict mode |
| Processes | `php artisan queue:work --sleep=1 --tries=1` under systemd or supervisor, and cron `* * * * * php artisan schedule:run` |
| TLS | Let's Encrypt (free) |
| Node | Not needed on the server. CI builds the assets (Node 22.20.0 per `.nvmrc`), as the current staging workflow does. |

## 3. Options (Owner chooses; each has a recurring cost)

| Option | Pros | Cons |
|---|---|---|
| **A. Separate Hostinger account** (shared plan) | Closest to production behaviour. Same deploy model (hPanel Git plus the existing "Staging release" workflow pattern). Little operating work. | Needs a second paid account. No root. Worker via cron `queue:work --stop-when-empty`. hPanel deploy still needs an Owner click per release. |
| **B. Small VPS** (any provider) | Full control: real worker, logs, SSH-only deploy from GitHub Actions without hPanel. Easy to rebuild. | Paid monthly. Needs patching, firewall and backups. Differs from production hosting. |

**Recommendation:** B (VPS) for E2E and tunnel work, because it gives a real queue worker and fully scripted deploy and rollback. Choose A if parity with the Hostinger deploy path matters more.

Cost: the Owner gets the actual price from the provider. Nothing is bought until the Owner approves.

## 4. Setup steps (after Owner approval)

1. **Provision** the server or account under a new login.
   - VPS: SSH key only, root login off, firewall open on 22/80/443, automatic security updates.
2. **DNS:** point `staging.qammarisparfum.id` (A/AAAA or CNAME) at the new server.
   - Only that record changes. Production records stay as they are.
   - The old staging site loses the name. Its files and database stay untouched until a separate cleanup decision.
3. **Install** the PHP 8.2, MariaDB 11.8 and Nginx stack, issue a TLS certificate, and add Basic Auth with an exception for `/integrations/qammaris-app/orders/v1/`.
4. **Database:** create a new database and a user with rights on that database only. Record nothing in Git.
5. **Deploy rights:**
   - generate a new deploy key and install it for a dedicated OS user;
   - create GitHub Environment `staging-e2e` holding host, port, user, key, known_hosts and path;
   - add a workflow `staging-e2e-release.yml`, triggered manually with a full SHA, that does: build assets → upload → `composer install --no-dev` → `php artisan migrate --force` (additive) → `optimize` → health check.
   - The current `staging-release.yml` and `production-release.yml` are not changed.
6. **`.env` on the server only**, with mode `0600`. Settings:
   - `APP_ENV=staging`, `APP_DEBUG=false`, a new `APP_KEY`;
   - database `SESSION_DRIVER`, `CACHE_STORE` and `QUEUE_CONNECTION`;
   - `MAIL_MAILER=log`;
   - `ORDERS_V2_ENABLED=true`;
   - `QAMMARIS_ORDER_API_ENABLED=true` with a new client and secret;
   - `QAMMARIS_ORDER_WEBHOOK_ENABLED=true` with a new secret, and the URL set per session to the App tunnel;
   - `QAMMARIS_ORDER_APP_TASK_LINKS=false`;
   - `QAMMARIS_APP_API_KEY=` (empty).
   Secrets for the App are handed over out of band, never in chat or Git.
7. **Processes:** start the worker (systemd/supervisor) and the scheduler cron.
8. **First data:** create a synthetic Super Admin (password set on the server), then run `php artisan qammaris:order-api:fixtures --actor=<username>`.
9. **Gate:** `php artisan qammaris:order-api:preflight` must be OK. Then run a signed external smoke test (as in [SMOKE_LOCAL](../verification/ord-02e/SMOKE_LOCAL.md)) and check for 401 without Basic Auth on web pages.
10. **Fingerprint check:** compare the preflight fingerprints (`APP_KEY` and secrets) with production's. They must differ.
11. **E2E with the App:** the App sets its tunnel URL, the Website sets `QAMMARIS_ORDER_WEBHOOK_URL`, and the App runs its harness in hosted mode (`e2e_signoff_ready=true`).

## 5. Rollback and data safety

- The workflow keeps the previous release directory. Rollback switches back to the previous release; schema changes are additive only.
- Kill switches: `QAMMARIS_ORDER_API_ENABLED=false` and `QAMMARIS_ORDER_WEBHOOK_ENABLED=false`. Neither deletes data.
- Backup: a nightly `mariadb-dump` of the staging database, kept 7 days on the server. Synthetic data only, so no off-site copy is needed.
- No production data is ever imported. A copy of real customer data would need a separately approved sanitisation process.

## 6. Owner approvals needed

1. Choose option A or B, and approve its recurring cost.
2. Approve the DNS change for `staging.qammarisparfum.id` only.
3. Approve creating the GitHub Environment `staging-e2e` and storing the new deploy key there.
4. Approve the first deploy of the ORD-02 branch to the new staging. No merge to `main`.
