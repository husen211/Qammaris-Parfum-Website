<?php

namespace App\Exceptions;

/** The order changed since the caller read it (contract `revision_conflict`). */
class OrderRevisionConflict extends OnlineOrderRejected
{
    public function __construct(public readonly int $currentRevision)
    {
        parent::__construct('Pesanan sudah berubah. Muat ulang lalu periksa sebelum mengulang.');
    }
}
