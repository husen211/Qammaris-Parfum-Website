<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Jobs\SyncQammarisAppProducts;
use App\Services\QammarisAppClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class QammarisAppWebhookController extends Controller
{
    public function __invoke(Request $request, QammarisAppClient $client): JsonResponse
    {
        if (! $client->configured()) {
            return response()->json(['error' => 'integration_unavailable'], 503);
        }
        $rawBody = $request->getContent();
        if (strlen($rawBody) > 16384) {
            return response()->json(['error' => 'payload_too_large'], 413);
        }
        $timestamp = $request->header('X-Qammaris-Timestamp', '');
        $signature = $request->header('X-Qammaris-Signature', '');
        if (! preg_match('/\A[0-9]{10}\z/', $timestamp)
            || abs(now()->timestamp - (int) $timestamp) > config('qammaris_app.signature_tolerance_seconds')
            || ! preg_match('/\A[a-f0-9]{64}\z/', $signature)
            || ! hash_equals(hash_hmac('sha256', $timestamp.'.'.$rawBody, config('qammaris_app.webhook_secret')), $signature)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }
        $payload = json_decode($rawBody, true);
        if (! is_array($payload) || Validator::make($payload, [
            'id' => ['required', 'uuid'],
            'revision' => ['required', 'integer', 'min:1', 'max:9007199254740991'],
            'change_seq' => ['required', 'integer', 'min:1', 'max:9007199254740991'],
            'sent_at' => ['required', 'date'],
        ])->fails() || ! is_int($payload['revision']) || ! is_int($payload['change_seq'])
            || $payload['revision'] !== $payload['change_seq']) {
            return response()->json(['error' => 'invalid_payload'], 422);
        }

        try {
            // Dispatch directly, so enqueue errors happen before the acknowledgement.
            Bus::dispatch(new SyncQammarisAppProducts);
        } catch (Throwable) {
            Log::warning('Qammaris app webhook enqueue failed.');

            return response()->json(['error' => 'queue_unavailable'], 503);
        }

        return response()->json(['accepted' => true], 202);
    }
}
