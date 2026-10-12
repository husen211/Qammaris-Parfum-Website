<?php

namespace App\Exceptions\OrderApi;

use RuntimeException;

/** A contract error code with its HTTP status (contract r4.1 §10). */
class OrderApiException extends RuntimeException
{
    /** @param  array<string, mixed>  $details */
    public function __construct(public readonly int $status, public readonly string $errorCode, string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
