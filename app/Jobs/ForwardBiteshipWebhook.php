<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * ADR-060: Biteship allows one order.status webhook per account and the Website holds it, so a Biteship event for an
 * order the Website does not know (the App's Shopee drivers) is passed on to the App as it came. The App re-reads the
 * order from Biteship; this body is only a hint. Nothing here changes the Website's answer to Biteship.
 */
class ForwardBiteshipWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 15;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(public readonly string $body)
    {
        // Same integration queue as the order webhook (the production worker listens to it, not "default").
        $this->onQueue(config('qammaris_app.queue'));
    }

    public static function enabled(): bool
    {
        return str_starts_with((string) config('services.biteship.forward_url'), 'https://')
            && strlen((string) config('services.biteship.forward_secret')) >= 32;
    }

    public function handle(): void
    {
        if (! self::enabled()) {
            return;
        }
        try {
            $status = Http::timeout(5)->connectTimeout(3)
                ->withHeaders(['X-Qammaris-Biteship-Secret' => (string) config('services.biteship.forward_secret')])
                ->withBody($this->body, 'application/json')
                ->post((string) config('services.biteship.forward_url'))
                ->status();
        } catch (Throwable $failure) {
            // Only the kind of failure is logged: never the body (driver phone) or the secret.
            Log::warning('biteship.forward_failed', ['error' => $failure::class]);
            throw new RuntimeException('biteship_forward_unreachable');
        }
        if ($status >= 500 || $status === 429) {
            Log::warning('biteship.forward_failed', ['status' => $status]);
            throw new RuntimeException('biteship_forward_retry');
        }
        if ($status >= 300) {
            // The App refused (wrong secret, unknown path): retrying would not help.
            Log::warning('biteship.forward_refused', ['status' => $status]);
        }
    }
}
