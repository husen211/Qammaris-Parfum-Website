<?php

namespace App\Http\Controllers\Integrations;

use App\Actions\Orders\BiteshipCourier;
use App\Http\Controllers\Controller;
use App\Jobs\ForwardBiteshipWebhook;
use App\Models\OnlineOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ORD-14: Biteship order events (order.status, order.price, order.waybill_id). Biteship documents no signature, so
 * the URL carries a long secret and the body is only a hint: the order is re-read from Biteship before anything
 * changes. Biteship's own URL check (an empty body) and unknown orders get a plain 200. Biteship allows this one
 * webhook per account, so an event for an order the Website does not know (the App's) is passed on to the App
 * (ADR-060), without changing the answer to Biteship.
 */
class BiteshipWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, BiteshipCourier $courier): JsonResponse
    {
        $expected = (string) config('services.biteship.webhook_secret');
        if (strlen($expected) < 32 || ! hash_equals($expected, $secret)) {
            abort(404);
        }
        $id = $request->input('order_id');
        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id)) {
            return response()->json(['ok' => true]);
        }
        $order = OnlineOrder::query()->where('courier_reference', $id)->whereNotNull('courier_booking')->first();
        if ($order === null) {
            $this->forward($request);

            // Biteship can report a new order before its booking answer is saved here; while a booking is still in
            // flight, ask Biteship to retry instead of dropping the event.
            return $this->bookingInFlight() ? response()->json(['ok' => false], 503) : response()->json(['ok' => true]);
        }
        try {
            $courier->refresh($order);
        } catch (Throwable $failure) {
            // Biteship retries a failed delivery; the order code is enough to find it again.
            Log::warning('biteship.webhook_refresh_failed', ['order' => $order->code, 'error' => $failure::class]);

            return response()->json(['ok' => false], 503);
        }

        return response()->json(['ok' => true]);
    }

    private function forward(Request $request): void
    {
        $body = $request->getContent();
        if (! ForwardBiteshipWebhook::enabled() || $body === '' || strlen($body) > 16384) {
            return;
        }
        try {
            ForwardBiteshipWebhook::dispatch($body);
        } catch (Throwable $failure) {
            // The queue is down: the App's own status check catches up; Biteship still gets the Website's answer.
            Log::warning('biteship.forward_not_queued', ['error' => $failure::class]);
        }
    }

    private function bookingInFlight(): bool
    {
        return OnlineOrder::query()->where('courier_booking->state', 'booking')->get(['id', 'courier_booking'])
            ->contains(fn (OnlineOrder $order) => now()->getTimestamp() - (int) ($order->courier_booking['started_at'] ?? 0) < 120);
    }
}
