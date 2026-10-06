<?php

namespace App\Console\Commands;

use App\Jobs\SyncQammarisAppProducts;
use App\Services\QammarisAppClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class SyncQammarisApp extends Command
{
    protected $signature = 'qammaris-app:sync';

    protected $description = 'Queue Qammaris app availability reconciliation from the saved checkpoint';

    public function handle(QammarisAppClient $client): int
    {
        if (! $client->configured()) {
            $this->warn('Qammaris app integration is not configured; no job queued.');

            return self::SUCCESS;
        }
        Bus::dispatch(new SyncQammarisAppProducts);
        $this->info('Qammaris app reconciliation queued.');

        return self::SUCCESS;
    }
}
