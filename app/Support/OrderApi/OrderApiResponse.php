<?php

namespace App\Support\OrderApi;

use App\Exceptions\ExpenseAlreadyLinked;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OnlineOrderRejected;
use App\Exceptions\OrderActionNotAllowed;
use App\Exceptions\OrderApi\OrderApiException;
use App\Exceptions\OrderRevisionConflict;
use App\Exceptions\OrderValidationFailed;
use App\Exceptions\ProofRequired;
use App\Exceptions\TaskAlreadyClaimed;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/** JSON responses and the error envelope for Order API v1 (contract §10). */
final class OrderApiResponse
{
    public const PATH = 'integrations/qammaris-app/orders/v1';

    public static function requestId(Request $request): string
    {
        if (! $request->attributes->has('order_api_request_id')) {
            $request->attributes->set('order_api_request_id', (string) Str::ulid());
        }

        return $request->attributes->get('order_api_request_id');
    }

    public static function json(Request $request, mixed $body, int $status = 200): JsonResponse
    {
        return self::headers(new JsonResponse($body, $status, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $request);
    }

    /** @param  array<string, mixed>  $details */
    public static function error(Request $request, int $status, string $code, string $message, array $details = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }

        return self::json($request, ['error' => $error, 'request_id' => self::requestId($request)], $status);
    }

    public static function fromThrowable(Request $request, Throwable $error): JsonResponse
    {
        return match (true) {
            $error instanceof OrderApiException => self::error($request, $error->status, $error->errorCode, $error->getMessage(), $error->details),
            $error instanceof TaskAlreadyClaimed => self::error($request, 409, 'task_already_claimed', $error->getMessage(), [
                'task' => $error->task, 'holder' => ['app_user_id' => $error->holderAppUserId, 'display_name' => $error->holderName], 'claimed_at' => $error->claimedAt,
            ]),
            $error instanceof OrderRevisionConflict => self::error($request, 409, 'revision_conflict', 'Order sudah berubah.', ['current_revision' => $error->currentRevision]),
            $error instanceof ExpenseAlreadyLinked => self::error($request, 409, 'expense_already_linked', $error->getMessage()),
            $error instanceof ProofRequired => self::error($request, 422, 'proof_required', $error->getMessage()),
            $error instanceof OrderValidationFailed => self::error($request, 422, 'validation_failed', $error->getMessage(), ['fields' => (object) $error->fields]),
            $error instanceof OrderActionNotAllowed => self::error($request, 403, 'action_not_allowed', $error->getMessage()),
            $error instanceof InvalidOrderTransition, $error instanceof OnlineOrderRejected => self::error($request, 409, 'invalid_transition', $error->getMessage()),
            $error instanceof ModelNotFoundException, $error instanceof NotFoundHttpException => self::error($request, 404, 'order_not_found', 'Order tidak ditemukan.'),
            $error instanceof MethodNotAllowedHttpException => self::error($request, 400, 'bad_request', 'Metode tidak didukung untuk path ini.'),
            $error instanceof HttpExceptionInterface && $error->getStatusCode() === 403 => self::error($request, 403, 'action_not_allowed', 'Aksi tidak diizinkan.'),
            default => self::serverError($request, $error),
        };
    }

    private static function serverError(Request $request, Throwable $error): JsonResponse
    {
        // No request body, headers or secrets in the log: only the class, a short message and the correlation IDs.
        Log::error('Order API failure', [
            'request_id' => self::requestId($request), 'app_request_id' => $request->attributes->get('order_api_app_request_id'),
            'exception' => $error::class, 'message' => Str::limit($error->getMessage(), 200),
        ]);

        return self::error($request, 500, 'server_error', 'Terjadi kesalahan di Website. Coba lagi dengan Idempotency-Key yang sama.');
    }

    private static function headers(JsonResponse $response, Request $request): JsonResponse
    {
        $response->headers->set('X-Qammaris-Api-Version', '1');
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Qammaris-Request-Id', self::requestId($request));

        return $response;
    }
}
