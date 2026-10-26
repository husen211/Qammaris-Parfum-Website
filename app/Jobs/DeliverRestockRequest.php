<?php

namespace App\Jobs;

use App\Support\RestockRequests;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** One delivery attempt of a restock request; retries are scheduled by `qammaris:restock-requests:deliver`. */
class DeliverRestockRequest implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $restockRequestId)
    {
        // Same integration queue as the order webhook; the per-minute scheduler sweep is the fallback.
        $this->onQueue(config('qammaris_app.queue'));
    }

    public function handle(): void
    {
        RestockRequests::attempt($this->restockRequestId);
    }
}
