<?php

namespace App\Console\Commands;

use App\Models\IntegrationOutbox;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderIssue;
use App\Support\OrderApi\OrderSerializer;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Throwable;

/**
 * Read-only staging check before joint tests with Qammaris App (ORD-02e). Never prints secret values: only whether
 * they are set and a short one-way fingerprint the Owner can compare with production's to prove they differ.
 * Exit 1 when any check fails.
 */
class OrderApiPreflight extends Command
{
    protected $signature = 'qammaris:order-api:preflight {--json : Machine-readable output}';

    protected $description = 'Read-only staging/integration preflight for the Order API (config, isolation, data, fixtures)';

    /** Production Qammaris App hosts: a staging Website must never call them. */
    private const APP_PRODUCTION_HOSTS = ['api.qammarisapp.com', 'qammarisapp.com', 'www.qammarisapp.com'];

    private array $checks = [];

    public function handle(Migrator $migrator): int
    {
        $this->environmentChecks();
        $this->flagChecks();
        $this->secretChecks();
        $this->isolationChecks();
        $this->deliveryChecks();
        $this->migrationCheck($migrator);
        $this->dataChecks();
        $this->fixtureChecks();

        $failed = collect($this->checks)->where('status', 'FAIL')->count();
        if ($this->option('json')) {
            $this->line(json_encode(['ok' => $failed === 0, 'checks' => $this->checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Status', 'Check', 'Detail'], array_map(fn ($c) => [$c['status'], $c['check'], $c['detail']], $this->checks));
            $this->line($failed === 0 ? 'Preflight OK.' : "Preflight GAGAL: {$failed} pemeriksaan.");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function environmentChecks(): void
    {
        $env = app()->environment();
        $this->add($env === 'production' ? 'FAIL' : 'PASS', 'environment', "APP_ENV={$env} (production ditolak untuk uji integrasi)");
        $this->add(config('app.debug') ? 'WARN' : 'PASS', 'debug', config('app.debug') ? 'APP_DEBUG=true' : 'APP_DEBUG=false');
        $this->add('INFO', 'app_url', (string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $this->add('INFO', 'app_key', 'fingerprint '.$this->fingerprint((string) config('app.key')));
    }

    private function flagChecks(): void
    {
        $this->add(config('orders.v2_enabled') ? 'PASS' : 'FAIL', 'ORDERS_V2_ENABLED', config('orders.v2_enabled') ? 'on' : 'off');
        $this->add(config('orders_api.enabled') ? 'PASS' : 'FAIL', 'QAMMARIS_ORDER_API_ENABLED', config('orders_api.enabled') ? 'on' : 'off');
        $this->add(config('orders_api.webhook_enabled') ? 'PASS' : 'FAIL', 'QAMMARIS_ORDER_WEBHOOK_ENABLED', config('orders_api.webhook_enabled') ? 'on' : 'off');
        // Owner: task links stay on the Admin PWA until the App order page is ready.
        $this->add(config('orders_api.app_task_links') ? 'FAIL' : 'PASS', 'QAMMARIS_ORDER_APP_TASK_LINKS', config('orders_api.app_task_links') ? 'on (harus off)' : 'off');
    }

    private function secretChecks(): void
    {
        $api = (string) config('orders_api.secret');
        $apiPrevious = (string) config('orders_api.secret_previous');
        $hook = (string) config('orders_api.webhook_secret');
        $this->add(config('orders_api.client_id') ? 'PASS' : 'FAIL', 'api_client_id', config('orders_api.client_id') ? 'terisi' : 'kosong');
        foreach (['api_secret' => $api, 'webhook_secret' => $hook] as $name => $value) {
            $status = $value === '' ? 'FAIL' : (strlen($value) < 32 ? 'FAIL' : 'PASS');
            $this->add($status, $name, $value === '' ? 'kosong' : (strlen($value) < 32 ? 'kurang dari 32 karakter' : 'terisi, fingerprint '.$this->fingerprint($value)));
        }
        if ($api !== '' && $api === $hook) {
            $this->add('FAIL', 'secret_per_direction', 'secret API dan webhook sama; tiap arah wajib berbeda');
        }
        if ($apiPrevious !== '') {
            $this->add($apiPrevious === $api ? 'FAIL' : 'INFO', 'api_secret_previous', $apiPrevious === $api ? 'sama dengan secret aktif' : 'terisi (rotasi berjalan)');
        }
        // r4.2: without an Owner allowlist no new proof waiver is accepted (fails closed).
        $owners = (array) config('orders_api.app_owner_ids');
        $this->add($owners === [] ? 'WARN' : 'PASS', 'app_owner_ids', $owners === [] ? 'kosong: keputusan waiver baru akan ditolak 403' : count($owners).' Owner App terdaftar');
        $this->add('INFO', 'website_issues_open', config('orders_api.website_issues') ? 'Admin PWA boleh mencatat kendala selama API aktif' : 'kendala baru hanya dari App selama API aktif');
        if ($api !== '' && $api === (string) config('qammaris_app.webhook_secret')) {
            $this->add('FAIL', 'secret_reuse', 'secret Order API sama dengan secret webhook katalog App');
        }
    }

    private function isolationChecks(): void
    {
        $hook = (string) config('orders_api.webhook_url');
        $hookHost = (string) parse_url($hook, PHP_URL_HOST);
        $hookStatus = in_array($hookHost, self::APP_PRODUCTION_HOSTS, true) ? 'FAIL' : (str_starts_with($hook, 'https://') || in_array($hookHost, ['127.0.0.1', 'localhost'], true) ? 'PASS' : 'FAIL');
        $this->add($hookStatus, 'webhook_target', $hookHost === '' ? 'kosong' : $hookHost.($hookStatus === 'FAIL' ? ' (production App atau bukan https)' : ''));

        $catalogHost = (string) parse_url((string) config('qammaris_app.base_url'), PHP_URL_HOST);
        $catalogOn = (string) config('qammaris_app.api_key') !== '';
        $this->add($catalogOn && in_array($catalogHost, self::APP_PRODUCTION_HOSTS, true) ? 'FAIL' : 'PASS', 'catalog_sync',
            $catalogOn ? "aktif ke {$catalogHost}" : 'nonaktif (QAMMARIS_APP_API_KEY kosong)');

        $mailer = (string) config('mail.default');
        $this->add(in_array($mailer, ['log', 'array'], true) ? 'PASS' : 'FAIL', 'mail', "MAIL_MAILER={$mailer} (staging: log/array)");
        $this->add('INFO', 'storage', 'product disk '.config('media.product_disk').', QR disk local (privat)');
        $this->add('INFO', 'runtime', 'session '.config('session.driver').', cache '.config('cache.default').', queue '.config('queue.default'));
    }

    private function deliveryChecks(): void
    {
        $stuck = IntegrationOutbox::query()->whereNull('delivered_at')->whereNull('failed_at')->where('next_attempt_at', '<', now()->subMinutes(3))->count();
        $this->add($stuck > 0 ? 'FAIL' : 'PASS', 'webhook_worker', $stuck > 0 ? "{$stuck} event jatuh tempo >3 menit belum dicoba: scheduler/worker tidak berjalan" : 'tidak ada event tertahan');
        $this->add('INFO', 'webhook_counts', sprintf('terkirim %d, menunggu %d, gagal %d',
            IntegrationOutbox::query()->whereNotNull('delivered_at')->count(),
            IntegrationOutbox::query()->whereNull('delivered_at')->whereNull('failed_at')->count(),
            IntegrationOutbox::query()->whereNotNull('failed_at')->whereNull('delivered_at')->count()));
    }

    private function migrationCheck(Migrator $migrator): void
    {
        $ran = $migrator->getRepository()->getRan();
        $pending = collect($migrator->getMigrationFiles(database_path('migrations')))->keys()->diff($ran)->values();
        $this->add($pending->isEmpty() ? 'PASS' : 'FAIL', 'migrations', $pending->isEmpty() ? 'tidak ada yang tertunda' : 'tertunda: '.$pending->implode(', '));
    }

    /** Every V2 order the API can return must serialise; r4.2 serves Website-opened issues with opened_by_source=website. */
    private function dataChecks(): void
    {
        $websiteIssues = OnlineOrderIssue::query()->whereNull('opened_by_app_user_id')
            ->whereHas('order', fn ($query) => $query->where('state_model', OnlineOrder::STATE_V2))->count();
        $this->add('INFO', 'website_issues', "{$websiteIssues} kendala V2 dari Website (r4.2: opened_by_source=website)");

        $broken = [];
        $count = 0;
        OnlineOrder::query()->where('state_model', OnlineOrder::STATE_V2)->with(['items', 'issues', 'claims', 'costs'])
            ->chunkById(100, function ($orders) use (&$broken, &$count) {
                foreach ($orders as $order) {
                    $count++;
                    try {
                        OrderSerializer::order($order);
                        OrderSerializer::summary($order);
                    } catch (Throwable $error) {
                        $broken[] = $order->public_id.' ('.class_basename($error).')';
                    }
                }
            });
        $this->add($broken === [] ? 'PASS' : 'FAIL', 'serializable', $broken === [] ? "{$count} pesanan V2 terbaca lewat API" : 'gagal: '.implode(', ', $broken));
    }

    /** Matches the App preflight: active, unclaimed, preparation not started, no issues/costs, recipient "E2E …". */
    private function fixtureChecks(): void
    {
        foreach (OrderApiFixtures::SCENARIOS as $scenario => $spec) {
            if (($spec['website_issue'] ?? false) && ! config('orders_api.website_issues')) {
                continue;
            }
            $order = OnlineOrder::query()->where('state_model', OnlineOrder::STATE_V2)->where('staff_note', '[e2e-fixture:'.$scenario.']')
                ->where('lifecycle', 'active')->latest('id')->first();
            if (! $order) {
                $this->add('WARN', "fixture:{$scenario}", 'belum ada; jalankan qammaris:order-api:fixtures');

                continue;
            }
            $problems = OrderApiFixtures::problems($order, $scenario);
            $this->add($problems === [] ? 'PASS' : 'WARN', "fixture:{$scenario}", $order->public_id.' rev '.$order->revision.($problems === [] ? '' : ' — '.implode(', ', $problems)));
        }
    }

    private function add(string $status, string $check, string $detail): void
    {
        $this->checks[] = compact('status', 'check', 'detail');
    }

    private function fingerprint(string $value): string
    {
        return $value === '' ? '—' : substr(hash('sha256', 'qammaris-preflight:'.$value), 0, 8);
    }
}
