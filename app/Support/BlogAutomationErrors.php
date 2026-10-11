<?php

namespace App\Support;

use App\Exceptions\BlogPostConflict;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class BlogAutomationErrors
{
    public static function render(Throwable $error, Request $request)
    {
        if (! $request->is('api/automation/v1*')) {
            return null;
        }
        $status = match (true) {
            $error instanceof ValidationException => 422,
            $error instanceof AuthenticationException => 401,
            $error instanceof AuthorizationException => 403,
            $error instanceof ModelNotFoundException => 404,
            $error instanceof BlogPostConflict => 409,
            $error instanceof HttpExceptionInterface => $error->getStatusCode(),
            default => 500,
        };
        [$code, $message] = match ($status) {
            401 => ['unauthenticated', 'A valid machine Bearer token is required.'],
            403 => ['forbidden', 'This actor cannot perform this operation on this resource.'],
            404 => ['not_found', 'Resource not found.'],
            405 => ['method_not_allowed', 'This operation is not available.'],
            409 => [$error instanceof BlogPostConflict ? 'revision_conflict' : 'idempotency_conflict', 'Reload the draft revision or resolve the pending idempotent request before retrying.'],
            422 => ['validation_failed', 'Check the indicated fields.'],
            429 => ['rate_limited', 'Wait before retrying.'],
            503 => ['automation_disabled', 'Blog automation is not enabled.'],
            default => ['save_failed', 'The operation could not be completed. Retry with the same idempotency key.'],
        };
        $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];

        return response()->json(['error' => ['code' => $code, 'message' => $message, 'fields' => $error instanceof ValidationException ? $error->errors() : (object) []], 'meta' => (object) []], $status, $headers)
            ->header('Cache-Control', 'no-store, private');
    }
}
