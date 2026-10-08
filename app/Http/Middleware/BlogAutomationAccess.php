<?php

namespace App\Http\Middleware;

use App\Models\BlogAutomationActor;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class BlogAutomationAccess
{
    public function handle(Request $request, Closure $next, string $ability)
    {
        $actor = $request->user();
        $token = $actor instanceof BlogAutomationActor ? $actor->currentAccessToken() : null;
        abort_unless($request->bearerToken() && $token instanceof PersonalAccessToken && $token->expires_at, 401);
        abort_unless($actor->is_active && $token->can($ability), 403);

        return $next($request);
    }
}
