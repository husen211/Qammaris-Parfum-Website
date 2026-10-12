<?php

namespace App\Jobs;

use App\Support\OrderApi\OrderWebhookOutbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** One delivery attempt of an outbox row; retries are scheduled by `qammaris:orders:deliver-webhooks`. */
class DeliverOrderWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $outboxId)
    {
        // The production worker consumes the integration queue (tools/hostinger/production-qammaris-worker-watchdog.sh),
        // not "default"; the per-minute scheduler sweep remains the fallback either way.
        $this->onQueue(config('qammaris_app.queue'));
    }

    public function handle(): void
    {
        OrderWebhookOutbox::attempt($this->outboxId);
    }
}
