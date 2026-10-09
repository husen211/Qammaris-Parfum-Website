<?php

namespace App\Exceptions;

/** Input does not fit the order (contract `validation_failed`, with `details.fields`). */
class OrderValidationFailed extends OnlineOrderRejected
{
    /** @param  array<string, string>  $fields */
    public function __construct(string $message, public readonly array $fields = [])
    {
        parent::__construct($message);
    }
}
