<?php

namespace App\Console\Commands;

use App\Support\OrderApi\AppEmployees;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\VAPID;
use Throwable;

/**
 * ORD-08/09 release check (read-only). Run on the server after a deploy: one table tells whether the courier-cost
 * integration and the admin push can work with this environment. It reads configuration and asks the App for its
 * employee list (signed GET); it writes nothing and prints no secret (only a short public-key fingerprint).
 */
class ReleaseCheck extends Command
{
    protected $signature = 'qammaris:release-check';

    protected $description = 'Read-only production readiness check for ORD-08/09 (courier cost, App employees, admin push)';

    /** @var list<array{status: string, check: string, detail: string}> */
    private array $checks = [];

    public function handle(Migrator $migrator): int
    {
        $ran = $migrator->getRepository()->getRan();
        $pending = collect($migrator->getMigrationFiles(database_path('migrations')))->keys()->diff($ran)->values();
        $this->add($pending->isEmpty() ? 'PASS' : 'FAIL', 'migrasi', $pending->isEmpty() ? 'tidak ada yang tertunda' : 'tertunda: '.$pending->implode(', '));

        $apiOn = (bool) config('orders_api.enabled') && (bool) config('orders_api.webhook_enabled');
        $this->add($apiOn ? 'PASS' : 'FAIL', 'integrasi App', $apiOn ? 'Order API dan webhook aktif' : 'QAMMARIS_ORDER_API_ENABLED / QAMMARIS_ORDER_WEBHOOK_ENABLED belum aktif');
        $this->add(config('orders_api.app_task_links') ? 'WARN' : 'PASS', 'link grup staf',
            config('orders_api.app_task_links') ? 'masih membuka App (set QAMMARIS_ORDER_APP_TASK_LINKS=false)' : 'membuka halaman pesanan Website');

        $this->appChecks();
        $this->pushChecks();

        $failed = collect($this->checks)->where('status', 'FAIL')->count();
        $this->table(['Status', 'Pemeriksaan', 'Detail'], array_map(fn ($c) => [$c['status'], $c['check'], $c['detail']], $this->checks));
        $this->line($failed === 0 ? 'Release check OK.' : "Release check GAGAL: {$failed} pemeriksaan.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function appChecks(): void
    {
        $owners = (array) config('orders_api.app_owner_ids');
        $probe = AppEmployees::probe();
        if ($probe === null) {
            $this->add('FAIL', 'karyawan App', 'App tidak terjangkau atau menolak (cek alamat webhook, secret, dan versi App)');
            $this->add($owners === [] ? 'FAIL' : 'WARN', 'Owner persetujuan', $owners === [] ? 'QAMMARIS_APP_OWNER_IDS kosong' : 'belum bisa dicocokkan (App tidak terjangkau)');

            return;
        }
        $names = array_values($probe['employees']);
        $this->add($names === [] ? 'WARN' : 'PASS', 'karyawan App', $names === [] ? 'tidak ada karyawan aktif (EMPLOYEE)' : count($names).' aktif: '.implode(', ', $names));

        $approver = $probe['approver'];
        $this->add(match (true) {
            $approver === null => 'FAIL',
            in_array($approver['app_user_id'], $owners, true) => 'PASS',
            default => 'FAIL',
        }, 'Owner persetujuan', match (true) {
            $approver === null => 'App tidak punya Owner aktif',
            in_array($approver['app_user_id'], $owners, true) => $approver['display_name'].' ada di QAMMARIS_APP_OWNER_IDS',
            default => $approver['display_name'].' ('.$approver['app_user_id'].') belum ada di QAMMARIS_APP_OWNER_IDS: reimburse otomatis tidak tampil "disetujui" di Website',
        });
    }

    private function pushChecks(): void
    {
        $public = (string) config('admin_push.vapid.public_key');
        $private = (string) config('admin_push.vapid.private_key');
        if ($public === '' && $private === '') {
            $this->add('WARN', 'push admin', 'mati (ADMIN_PUSH_VAPID_* kosong); jalankan php artisan admin-push:vapid lalu isi .env');

            return;
        }
        try {
            VAPID::validate(['subject' => (string) config('admin_push.vapid.subject'), 'publicKey' => $public, 'privateKey' => $private]);
            $this->add('PASS', 'push admin', 'kunci VAPID valid (publik '.substr(hash('sha256', $public), 0, 8).')');
        } catch (Throwable) {
            $this->add('FAIL', 'push admin', 'kunci VAPID tidak valid atau subject bukan mailto:/https:');
        }
        $this->add(config('admin.pwa_enabled') ? 'PASS' : 'FAIL', 'service worker admin', config('admin.pwa_enabled') ? 'ADMIN_PWA_ENABLED aktif' : 'ADMIN_PWA_ENABLED harus true agar push diterima');
        $sync = config('queue.default') === 'sync';
        $failedJobs = rescue(fn () => DB::table('failed_jobs')->where('payload', 'like', '%SendAdminPush%')->count(), 0, false);
        $this->add($sync ? 'WARN' : 'PASS', 'antrean push', ($sync ? 'QUEUE_CONNECTION=sync (push dikirim langsung saat checkout)' : 'antrean '.config('queue.default').' / '.config('qammaris_app.queue').' (worker harus mendengarkan antrean ini)').', gagal: '.$failedJobs);
    }

    private function add(string $status, string $check, string $detail): void
    {
        $this->checks[] = compact('status', 'check', 'detail');
    }
}
