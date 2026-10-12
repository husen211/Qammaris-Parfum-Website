<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Staging/local never get indexed, even if Basic Auth is missing after a redeploy (ORD-02e staging). */
class NoIndexOutsideProduction
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! app()->environment('production')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
