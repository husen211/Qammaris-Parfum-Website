<?php

namespace App\Exceptions;

use App\Models\OnlineOrder;
use RuntimeException;

/** The same create-form submission already made this order (double tap or retry). */
class DuplicateOnlineOrderSubmission extends RuntimeException
{
    public function __construct(public readonly OnlineOrder $order)
    {
        parent::__construct('Pesanan '.$order->code.' sudah dibuat dari kiriman ini.');
    }
}
