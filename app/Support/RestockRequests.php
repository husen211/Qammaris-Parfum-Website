<?php

namespace App\Support;

use App\Jobs\DeliverRestockRequest;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\RestockRequest;
use App\Support\OrderApi\OrderApiSignature;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * WAIT-01: "Kabari saya saat ready" → Qammaris App Daftar Tunggu (`waitlist.requested`, contract agreed with the App
 * 2026-10-26). Signed like the order webhook (same client and secret, its own path). The App answers 200 for new and
 * duplicate entries, 400/422 for a payload it will never accept, and 401/503/5xx while it cannot take it yet.
 */
final class RestockRequests
{
    /** The App keeps a waiting entry this long, so a repeat within it would only be a duplicate there. */
    public const REPEAT_DAYS = 60;

    public static function enabled(): bool
    {
        return (bool) config('orders_api.waitlist_enabled');
    }

    /** Records the request once per product and number; a repeat returns the earlier request without a new event. */
    public static function record(Product $product, string $name, string $whatsapp): RestockRequest
    {
        $hash = hash_hmac('sha256', $whatsapp, (string) config('app.key'));
        $earlier = RestockRequest::query()->where('product_id', $product->id)->where('whatsapp_hash', $hash)
            ->whereNull('failed_at')->where('created_at', '>=', now()->subDays(self::REPEAT_DAYS))->latest('id')->first();
        if ($earlier) {
            return $earlier;
        }

        $requestId = (string) Str::ulid();
        $request = RestockRequest::create([
            'request_id' => $requestId, 'product_id' => $product->id, 'customer_name' => $name, 'whatsapp' => $whatsapp,
            'whatsapp_hash' => $hash, 'body' => json_encode(self::event($requestId, $product, $name, $whatsapp), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'next_attempt_at' => now(),
        ]);
        DeliverRestockRequest::dispatch($request->id)->afterCommit();

        return $request;
    }

    public static function event(string $requestId, Product $product, string $name, string $whatsapp): array
    {
        $product->loadMissing(['brand', 'activeOffer', 'externalIdentities']);
        $appProductId = $product->externalIdentities
            ->firstWhere('provider', ProductExternalIdentity::PROVIDER_QAMMARIS_APP)?->external_product_id;

        return [
            'event_id' => $requestId,
            'type' => 'waitlist.requested',
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'request_id' => $requestId,
                'product' => [
                    'app_product_id' => $appProductId,
                    'name' => Str::limit($product->name, 120, ''),
                    'brand' => $product->brand?->name,
                    'volume_ml' => $product->activeOffer?->volume,
                    'url' => route('products.show', $product->slug),
                ],
                'customer' => ['name' => $name, 'whatsapp' => $whatsapp],
                'note' => null,
            ],
        ];
    }

    /** Claims a due row atomically, then sends one attempt. Returns true when the App accepted it. */
    public static function attempt(int $id, bool $manual = false): bool
    {
        $claimed = RestockRequest::query()->whereKey($id)->whereNull('delivered_at')->whereNull('failed_at')
            ->when(! $manual, fn ($query) => $query->where('next_attempt_at', '<=', now()))
            ->update(['next_attempt_at' => now()->addSeconds(120)]);
        if ($claimed !== 1 || ! self::enabled()) {
            return false;
        }
        $row = RestockRequest::findOrFail($id);
        $url = (string) config('orders_api.waitlist_url');
        $path = (string) parse_url($url, PHP_URL_PATH).(($query = parse_url($url, PHP_URL_QUERY)) ? '?'.$query : '');
        $timestamp = now()->timestamp;
        $status = null;
        $error = null;
        try {
            $response = Http::timeout((int) config('orders_api.webhook_timeout_seconds'))
                ->withOptions(['allow_redirects' => false])
                ->withHeaders([
                    'X-Qammaris-Client' => (string) config('orders_api.webhook_client_id'),
                    'X-Qammaris-Timestamp' => (string) $timestamp,
                    'X-Qammaris-Signature' => OrderApiSignature::sign((string) config('orders_api.webhook_secret'), $timestamp, 'POST', $path, $row->body),
                    'Idempotency-Key' => $row->request_id,
                    'X-Qammaris-Api-Version' => '1',
                ])
                ->withBody($row->body, 'application/json')
                ->post($url);
            $status = $response->status();
            $error = $response->successful() ? null : 'HTTP '.$status;
        } catch (Throwable $exception) {
            // Connection problems only; no headers, secrets or body in the stored error.
            $error = Str::limit(class_basename($exception).': '.preg_replace('/https?:\/\/\S+/', '[url]', $exception->getMessage()), 280);
        }

        $first = $row->first_attempt_at ?? now();
        $attempts = $row->attempts + 1;
        $log = ['attempts' => $attempts, 'first_attempt_at' => $first, 'last_attempt_at' => now(), 'last_status' => $status, 'last_error' => $error];
        if ($error === null) {
            // The App holds the entry now; the Website keeps only the repeat-check hash.
            $row->update($log + ['delivered_at' => now(), 'next_attempt_at' => null, 'customer_name' => null, 'whatsapp' => null, 'body' => null]);

            return true;
        }
        $schedule = config('orders_api.webhook_retry_schedule_seconds');
        $next = now()->addSeconds($schedule[$attempts] ?? end($schedule));
        $rejected = in_array($status, [400, 422], true);
        $giveUp = $rejected || $next->gt($first->copy()->addSeconds((int) config('orders_api.webhook_give_up_after_seconds')));
        $row->update($log + ['next_attempt_at' => $giveUp ? null : $next, 'failed_at' => $giveUp ? now() : null]);

        return false;
    }

    /** Scheduler sweep for due retries. */
    public static function deliverDue(int $limit = 50): int
    {
        $delivered = 0;
        foreach (RestockRequest::query()->whereNull('delivered_at')->whereNull('failed_at')->where('next_attempt_at', '<=', now())->orderBy('id')->limit($limit)->pluck('id') as $id) {
            $delivered += self::attempt($id) ? 1 : 0;
        }

        return $delivered;
    }

    /** Puts requests that gave up (for example while the App endpoint was off) back in the queue with a new window. */
    public static function retryFailed(): int
    {
        return RestockRequest::query()->whereNull('delivered_at')->whereNotNull('failed_at')->whereNotNull('body')
            ->update(['failed_at' => null, 'first_attempt_at' => null, 'next_attempt_at' => now()]);
    }
}
