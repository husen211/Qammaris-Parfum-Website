<?php

namespace App\Support\OrderApi;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Captures the HTTP request for public/index.php. On PHP 8.4+, Symfony parses multipart PUT/PATCH bodies with
 * request_parse_body(), which consumes php://input: the signed raw body is then gone and every signed QR upload
 * fails HMAC (found by the ORD-02e smoke test over real HTTP). For signed Order API multipart requests only, the
 * request is built from the untouched raw body instead; SignedMultipart parses it after verification.
 */
final class RawBodyRequest
{
    /** @param  (Closure(): string)|null  $input  raw body reader (php://input by default) */
    public static function capture(?Closure $input = null): Request
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $multipart = str_starts_with(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '')), 'multipart/form-data');

        if (! in_array($method, ['PUT', 'PATCH'], true) || ! $multipart || ! str_starts_with($path, '/'.OrderApiResponse::PATH.'/')) {
            return Request::capture();
        }

        Request::enableHttpMethodParameterOverride();
        $raw = $input ? $input() : (string) file_get_contents('php://input');

        return Request::createFromBase(new SymfonyRequest($_GET, [], [], $_COOKIE, [], $_SERVER, $raw));
    }
}
