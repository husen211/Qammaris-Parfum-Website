<?php

namespace App\Http\Middleware;

use App\Support\OrderApi\OrderApiResponse;
use App\Support\OrderApi\OrderApiSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * App → Website request authentication (contract r4.1 §3). Checks run in this order so the cheapest refusal
 * comes first; the raw path+query and raw body are signed exactly as received.
 */
class AuthenticateOrderApi
{
    public function handle(Request $request, Closure $next): Response
    {
        OrderApiResponse::requestId($request);
        $appRequestId = (string) $request->header('X-Request-Id', '');
        if ($appRequestId !== '' && preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $appRequestId)) {
            $request->attributes->set('order_api_app_request_id', $appRequestId);
        }

        $secrets = array_values(array_filter([config('orders_api.secret'), config('orders_api.secret_previous')]));
        if (! config('orders_api.enabled') || $secrets === [] || config('orders_api.client_id') === '') {
            return OrderApiResponse::error($request, 503, 'unavailable', 'API pesanan sedang tidak aktif.');
        }

        $limit = $request->isMethod('PUT') && str_ends_with($request->path(), '/jnt/qr') ? config('orders_api.max_qr_body_bytes') : config('orders_api.max_body_bytes');
        if ((int) $request->header('Content-Length', 0) > $limit || strlen($request->getContent()) > $limit) {
            return OrderApiResponse::error($request, 413, 'payload_too_large', 'Body terlalu besar.');
        }

        $client = (string) $request->header('X-Qammaris-Client', '');
        if (! hash_equals((string) config('orders_api.client_id'), $client)) {
            return OrderApiResponse::error($request, 401, 'unknown_client', 'Klien tidak dikenal.');
        }

        $timestamp = (string) $request->header('X-Qammaris-Timestamp', '');
        if (! preg_match('/^\d{10}$/', $timestamp) || abs(now()->timestamp - (int) $timestamp) > config('orders_api.timestamp_tolerance_seconds')) {
            return OrderApiResponse::error($request, 401, 'stale_timestamp', 'Timestamp di luar toleransi 300 detik. Periksa jam server.');
        }

        $signature = strtolower((string) $request->header('X-Qammaris-Signature', ''));
        $valid = false;
        foreach ($secrets as $secret) {
            // Rotation: current and previous secret are both accepted until previous is cleared.
            $expected = OrderApiSignature::sign($secret, (int) $timestamp, $request->getMethod(), $request->getRequestUri(), $request->getContent());
            $valid = $valid || hash_equals($expected, $signature);
        }
        if (! $valid) {
            return OrderApiResponse::error($request, 401, 'invalid_signature', 'Tanda tangan tidak valid.');
        }

        $key = 'order-api:'.$client;
        if (RateLimiter::tooManyAttempts($key, config('orders_api.rate_limit_per_minute'))) {
            return OrderApiResponse::error($request, 429, 'rate_limited', 'Terlalu banyak request.')
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        $request->attributes->set('order_api_client', $client);

        return $next($request);
    }
}
