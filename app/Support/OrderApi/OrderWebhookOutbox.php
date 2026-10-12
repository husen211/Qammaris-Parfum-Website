<?php

namespace App\Support\OrderApi;

use App\Jobs\DeliverOrderWebhook;
use App\Models\IntegrationOutbox;
use App\Models\OnlineOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Website → App `order.changed` outbox (contract r4.1 §12). A row is written in the same transaction as the order
 * revision; delivery happens after commit and is retried by the scheduler. Every attempt signs a new timestamp
 * (K-B); the event_id and raw body never change, and Idempotency-Key equals event_id.
 */
final class OrderWebhookOutbox
{
    /** Called when a V2 order's revision changes. */
    public static function record(OnlineOrder $order): void
    {
        if (! config('orders_api.webhook_enabled') || ! $order->isV2() || ! $order->public_id) {
            return;
        }
        $eventId = (string) Str::ulid();
        // A freshly inserted order has not loaded its database default yet; new orders start at revision 1.
        $revision = (int) ($order->revision ?? 1);
        $row = IntegrationOutbox::create([
            'event_id' => $eventId, 'online_order_id' => $order->id, 'revision' => $revision, 'type' => 'order.changed',
            'body' => json_encode(OrderSerializer::webhookEvent($eventId, $order, now(), $revision), JSON_UNESCAPED_SLASHES),
            'next_attempt_at' => now(),
        ]);
        DeliverOrderWebhook::dispatch($row->id)->afterCommit();
    }

    /** Claims a due row atomically (portable: no SKIP LOCKED), then sends one attempt. Returns true when delivered. */
    public static function attempt(int $outboxId, bool $manual = false): bool
    {
        $claimed = IntegrationOutbox::query()->whereKey($outboxId)->whereNull('delivered_at')->whereNull('failed_at')
            ->when(! $manual, fn ($query) => $query->where('next_attempt_at', '<=', now()))
            ->update(['next_attempt_at' => now()->addSeconds(120)]);
        if ($claimed !== 1 || ! config('orders_api.webhook_enabled')) {
            return false;
        }
        $row = IntegrationOutbox::findOrFail($outboxId);
        $url = (string) config('orders_api.webhook_url');
        $path = (string) parse_url($url, PHP_URL_PATH).(($query = parse_url($url, PHP_URL_QUERY)) ? '?'.$query : '');
        $timestamp = now()->timestamp;
        $status = null;
        $error = null;
        try {
            $response = Http::timeout((int) config('orders_api.webhook_timeout_seconds'))
                ->withHeaders([
                    'X-Qammaris-Client' => (string) config('orders_api.webhook_client_id'),
                    'X-Qammaris-Timestamp' => (string) $timestamp,
                    'X-Qammaris-Signature' => OrderApiSignature::sign((string) config('orders_api.webhook_secret'), $timestamp, 'POST', $path, $row->body),
                    'Idempotency-Key' => $row->event_id,
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
        if ($error === null) {
            $row->update(['attempts' => $attempts, 'first_attempt_at' => $first, 'last_attempt_at' => now(), 'last_status' => $status, 'last_error' => null, 'delivered_at' => now(), 'next_attempt_at' => null]);

            return true;
        }
        $schedule = config('orders_api.webhook_retry_schedule_seconds');
        $delay = $schedule[$attempts] ?? end($schedule);
        $giveUp = $first->copy()->addSeconds((int) config('orders_api.webhook_give_up_after_seconds'));
        $next = now()->addSeconds($delay);
        $row->update([
            'attempts' => $attempts, 'first_attempt_at' => $first, 'last_attempt_at' => now(), 'last_status' => $status, 'last_error' => $error,
            'next_attempt_at' => $next->gt($giveUp) ? null : $next, 'failed_at' => $next->gt($giveUp) ? now() : null,
        ]);

        return false;
    }

    /** Admin "Kirim ulang": same event and bytes, a new 24-hour window, sent now. */
    public static function resend(IntegrationOutbox $row): void
    {
        if ($row->delivered_at !== null) {
            return;
        }
        $row->update(['failed_at' => null, 'first_attempt_at' => null, 'next_attempt_at' => now()]);
        DeliverOrderWebhook::dispatch($row->id);
    }

    /** Scheduler sweep for due retries. */
    public static function deliverDue(int $limit = 50): int
    {
        $delivered = 0;
        foreach (IntegrationOutbox::query()->whereNull('delivered_at')->whereNull('failed_at')->where('next_attempt_at', '<=', now())->orderBy('id')->limit($limit)->pluck('id') as $id) {
            $delivered += self::attempt($id) ? 1 : 0;
        }

        return $delivered;
    }
}
