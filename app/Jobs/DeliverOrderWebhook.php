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

    public function __construct(public readonly int $outboxId) {}

    public function handle(): void
    {
        OrderWebhookOutbox::attempt($this->outboxId);
    }
}
