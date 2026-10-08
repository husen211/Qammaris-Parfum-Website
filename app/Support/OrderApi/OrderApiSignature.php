<?php

namespace App\Support\OrderApi;

/** HMAC-SHA256 over `timestamp\nMETHOD\npath_with_query\nsha256_hex(raw_body)` (contract r4.1 §3). */
final class OrderApiSignature
{
    public static function sign(string $secret, int $timestamp, string $method, string $pathWithQuery, string $rawBody): string
    {
        return hash_hmac('sha256', implode("\n", [$timestamp, strtoupper($method), $pathWithQuery, hash('sha256', $rawBody)]), $secret);
    }
}
