<?php

namespace App\Exceptions;

/** The actor may not do this to this order (contract `action_not_allowed`). */
class OrderActionNotAllowed extends OnlineOrderRejected
{
    /** @param  array<string, mixed>  $details  safe API `error.details` (e.g. the task and its holder) */
    public function __construct(string $message = '', public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
