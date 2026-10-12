<?php

namespace App\Console\Commands;

use App\Models\IntegrationOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Removes idempotency records past their 7 days and delivered webhook rows older than 30 days. Failed rows stay. */
class PruneOrderApiRecords extends Command
{
    protected $signature = 'qammaris:orders:prune-api';

    protected $description = 'Prune expired Order API idempotency keys and old delivered webhook events';

    public function handle(): int
    {
        $keys = DB::table('integration_idempotency_keys')->where('expires_at', '<', now())->delete();
        $events = IntegrationOutbox::query()->whereNotNull('delivered_at')->where('delivered_at', '<', now()->subDays(30))->delete();
        $this->info("Pruned {$keys} idempotency key(s) and {$events} delivered event(s).");

        return self::SUCCESS;
    }
}
