<?php

namespace App\Exceptions;

/** ORD-04 P0: packing, courier booking and handover need a fully paid order (API: 409 invalid_transition, reason payment_required). */
class PaymentRequired extends InvalidOrderTransition
{
    public function __construct()
    {
        parent::__construct('Pesanan belum lunas. Catat pembayaran lunas dulu sebelum packing, memesan kurir, atau menyerahkan pesanan.');
    }
}
