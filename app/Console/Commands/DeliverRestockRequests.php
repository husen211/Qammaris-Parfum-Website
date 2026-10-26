<?php

namespace App\Console\Commands;

use App\Support\RestockRequests;
use Illuminate\Console\Command;

/** Scheduler sweep: sends due "Kabari saya" requests to the Qammaris App Daftar Tunggu. Safe to run every minute. */
class DeliverRestockRequests extends Command
{
    protected $signature = 'qammaris:restock-requests:deliver {--limit=50} {--retry-failed : Requeue requests that gave up}';

    protected $description = 'Deliver due restock (waitlist) requests to Qammaris App';

    public function handle(): int
    {
        if ($this->option('retry-failed')) {
            $this->info('Requeued '.RestockRequests::retryFailed().' request(s).');
        }
        $delivered = RestockRequests::deliverDue((int) $this->option('limit'));
        $this->info("Delivered {$delivered} request(s).");

        return self::SUCCESS;
    }
}
