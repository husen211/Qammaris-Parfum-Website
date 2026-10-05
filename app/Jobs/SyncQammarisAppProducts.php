<?php

namespace App\Jobs;

use App\Actions\Products\SyncQammarisAppFeed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncQammarisAppProducts implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct()
    {
        $this->onConnection('database');
        $this->onQueue(config('qammaris_app.queue'));
    }

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('qammaris-app-feed'))->shared()->releaseAfter(10)->expireAfter(80)];
    }

    public function handle(SyncQammarisAppFeed $sync): void
    {
        try {
            if ($sync->handle()) {
                self::dispatch();
            }
        } catch (Throwable) {
            DB::table('qammaris_app_sync_states')->where('id', 'products')->update([
                'last_error' => 'feed_sync_failed',
            ]);
            throw new RuntimeException('Qammaris app synchronization failed; checkpoint retained.');
        }
    }
}
