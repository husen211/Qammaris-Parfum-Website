<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class BlogAutomationEnabled
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('blog_automation.enabled'), 503, 'Blog automation is not enabled.');
        // Before auth: framework middleware priority otherwise puts auth before throttle.
        $key = 'blog-auth:'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 120), 429, 'Wait before retrying.', ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        RateLimiter::hit($key, 60);

        return $next($request)->header('Cache-Control', 'no-store, private');
    }
}
