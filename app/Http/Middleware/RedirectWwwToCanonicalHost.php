<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** SEO-01: www.<host> served a full second copy of the site; it now moves permanently to the APP_URL host. */
class RedirectWwwToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = parse_url((string) config('app.url'));
        $host = $canonical['host'] ?? null;
        if ($host && strcasecmp($request->getHost(), 'www.'.$host) === 0) {
            $target = ($canonical['scheme'] ?? 'https').'://'.$host.$request->getRequestUri();

            return redirect()->away($target, $request->isMethodSafe() ? 301 : 308);
        }

        return $next($request);
    }
}
