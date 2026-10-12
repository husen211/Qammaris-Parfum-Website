<?php

namespace App\Support\OrderApi;

use App\Exceptions\OrderApi\OrderApiException;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Idempotency-Key handling (contract r4.1 §11). `(client, key)` stores the payload hash and the response for
 * 7 days. A network retry (same key, byte-identical payload) gets the stored response without a second effect;
 * a different payload under the same key is `idempotency_key_reused`. Business errors (4xx) are stored too, so a
 * retry sees the same answer; server errors (5xx) are not, so the same key can be retried.
 */
final class OrderApiIdempotency
{
    /** @param  Closure(): JsonResponse  $operation */
    public static function run(Request $request, Closure $operation): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if (! preg_match('/^[A-Za-z0-9_-]{16,128}$/', $key)) {
            throw new OrderApiException(400, 'bad_request', 'Idempotency-Key wajib: 16–128 karakter A-Z, a-z, 0-9, _ atau -.');
        }
        $client = (string) $request->attributes->get('order_api_client');
        $hash = hash('sha256', $request->getMethod()."\n".$request->getRequestUri()."\n".$request->getContent());

        if ($replay = self::replay($request, $client, $key, $hash)) {
            return $replay;
        }
        DB::table('integration_idempotency_keys')->where('client', $client)->where('idempotency_key', $key)->where('expires_at', '<', now())->delete();

        try {
            return self::transaction(function () use ($request, $client, $key, $hash, $operation): JsonResponse {
                // Reserve the key first: a concurrent duplicate blocks on the unique index until this commits.
                $id = self::store($request, $client, $key, $hash, 0, '');
                $response = $operation();
                DB::table('integration_idempotency_keys')->where('id', $id)->update(['status_code' => $response->getStatusCode(), 'response_body' => $response->getContent()]);

                return $response;
            });
        } catch (UniqueConstraintViolationException) {
            return self::replay($request, $client, $key, $hash) ?? throw new OrderApiException(409, 'idempotency_key_reused', 'Key sedang diproses; ulangi sebentar lagi.');
        } catch (Throwable $error) {
            $response = OrderApiResponse::fromThrowable($request, $error);
            if ($response->getStatusCode() < 500) {
                try {
                    self::store($request, $client, $key, $hash, $response->getStatusCode(), $response->getContent());
                } catch (UniqueConstraintViolationException) {
                    // Another attempt stored its answer first; this one still reports its own result.
                }
            }

            return $response;
        }
    }

    /**
     * MySQL/MariaDB: READ COMMITTED, so every read after the order row lock sees the latest committed rows (under
     * REPEATABLE READ an earlier read pins a stale snapshot), and no gap locks. Deadlocks and serialization failures
     * retry the whole transaction (found by the MariaDB concurrency tests, ORD-02e).
     */
    private static function transaction(Closure $callback): JsonResponse
    {
        for ($attempt = 1; ; $attempt++) {
            if (DB::getDriverName() === 'mysql' && DB::transactionLevel() === 0) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            }
            try {
                return DB::transaction($callback);
            } catch (QueryException $error) {
                if ($attempt >= 3 || ! in_array($error->errorInfo[1] ?? null, [1213, 1205], true)) {
                    throw $error;
                }
                usleep(random_int(20_000, 80_000));
            }
        }
    }

    private static function replay(Request $request, string $client, string $key, string $hash): ?JsonResponse
    {
        $stored = DB::table('integration_idempotency_keys')->where('client', $client)->where('idempotency_key', $key)
            ->where('expires_at', '>=', now())->first();
        if (! $stored || $stored->status_code === 0) {
            return null;
        }
        if (! hash_equals($stored->payload_hash, $hash)) {
            throw new OrderApiException(409, 'idempotency_key_reused', 'Idempotency-Key ini sudah dipakai untuk payload lain. Aksi baru memakai key baru.');
        }
        $response = OrderApiResponse::json($request, json_decode($stored->response_body, true), (int) $stored->status_code);
        $response->setContent($stored->response_body);
        $response->headers->set('X-Qammaris-Idempotent-Replay', 'true');

        return $response;
    }

    private static function store(Request $request, string $client, string $key, string $hash, int $status, string $body): int
    {
        return DB::table('integration_idempotency_keys')->insertGetId([
            'client' => $client, 'idempotency_key' => $key, 'method' => $request->getMethod(), 'path' => substr($request->getRequestUri(), 0, 255),
            'payload_hash' => $hash, 'status_code' => $status, 'response_body' => $body,
            'request_id' => $request->attributes->get('order_api_app_request_id'),
            'created_at' => now(), 'expires_at' => now()->addDays((int) config('orders_api.idempotency_days')),
        ]);
    }
}
