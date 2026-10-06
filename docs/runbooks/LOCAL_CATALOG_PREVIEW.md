# Local catalog preview — P8-02

URL: `http://127.0.0.1:8000/products`, only on this computer. The PHP development server is not a production deployment and is bound to loopback.

The current preview database is an ignored, isolated SQLite file at `storage/app/private/p8-02-preview/20261005/catalog.sqlite`. Public catalog tables came from the verified 2026-09-14 local baseline backup; authentication tables were not copied. It is not current production data or live API stock. Four synthetic UI records were removed after verification; final counts are 180 products, 64 offers, 19 image records and zero users. Do not create an admin login just to inspect public pages.

The configured local MySQL endpoint was unavailable. Its database and `.env` were not changed or repaired. All migrations used for preview creation ran only against the new SQLite file; never point these steps at the original or production database. Keep the existing media as read-only sources.

To restart after the current local server stops, open a dedicated PowerShell terminal in the repository, verify the exact preview file exists, then set **process-only** environment values:

```powershell
$previewDatabase = (Resolve-Path -LiteralPath 'storage/app/private/p8-02-preview/20261005/catalog.sqlite').Path
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $previewDatabase
$env:CACHE_STORE = 'database'
$env:SESSION_DRIVER = 'file'
$env:APP_URL = 'http://127.0.0.1:8000'
$env:QAMMARIS_APP_API_KEY = ''
$env:QAMMARIS_APP_WEBHOOK_SECRET = ''
& C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Close that dedicated terminal to discard its environment overrides. No `.env` edit, seed, dependency install or new migration is needed. Use Ctrl+C to stop the foreground preview. The originally started hidden preview keeps logs in the same private directory (`server.out.log`, `server.err.log`); identify its exact loopback server process before stopping it, rather than stopping all PHP processes.

Public smoke test: open catalog, search/filter, open an existing product with an offer such as Afnan Turathi Blue, add to inquiry and review the drawer/page. Sending the WhatsApp inquiry is a separate human action. Most copied products are legacy/manual, so their old confirmation labels are expected. App labels were verified with disposable synthetic records; do not fabricate live availability on real products to demonstrate them.

Staging/live connection follows `QAMMARIS_APP_INTEGRATION.md` and requires Owner credentials and approved activation. This preview does not verify production MySQL locking, real webhook delivery or synchronization latency.
