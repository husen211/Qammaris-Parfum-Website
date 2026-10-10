<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

final class FragranceBrowser
{
    public const COOKIE = 'qammaris_preference_browser';

    public function handle(Request $request, Closure $next)
    {
        $token = $request->cookie(self::COOKIE);
        $fresh = ! is_string($token) || ! preg_match('/\A[a-f0-9]{64}\z/', $token);
        if ($fresh) {
            $token = bin2hex(random_bytes(32));
        }
        $request->attributes->set('preference_browser_hash', hash_hmac('sha256', $token, (string) config('app.key')));
        try {
            $response = $next($request);
        } catch (\Throwable $error) {
            $response = app(ExceptionHandler::class)->render($request, $error);
        }
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        if ($fresh) {
            // Laravel EncryptCookies wraps this token. The DB receives only its HMAC.
            $response->headers->setCookie(Cookie::make(self::COOKIE, $token, 7 * 24 * 60, '/fragrance-quiz', null, $request->isSecure() || app()->environment('production'), true, false, 'lax'));
        }

        return $response;
    }
}
