<?php

namespace App\Console\Commands;

use App\Support\OrderApi\OrderWebhookOutbox;
use Illuminate\Console\Command;

/** Scheduler sweep: sends due `order.changed` retries (contract §12). Safe to run every minute. */
class DeliverOrderWebhooks extends Command
{
    protected $signature = 'qammaris:orders:deliver-webhooks {--limit=50}';

    protected $description = 'Deliver due Order API webhook events with fresh signatures';

    public function handle(): int
    {
        $delivered = OrderWebhookOutbox::deliverDue((int) $this->option('limit'));
        $this->info("Delivered {$delivered} webhook event(s).");

        return self::SUCCESS;
    }
}
