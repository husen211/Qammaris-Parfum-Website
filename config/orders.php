<?php

return [
    // ORD-02 cutover point: orders created while true use the V2 state model; existing orders keep theirs.
    'v2_enabled' => (bool) env('ORDERS_V2_ENABLED', false),

    // ORD-03 (UAT flag): customer payment preference on the order link, and Staff Order may set the
    // customer-charged shipping fee while no payment is recorded. Off unless the environment enables it.
    'simple_ux' => (bool) env('ORDERS_SIMPLE_UX', false),

    // Payment methods a customer may prefer on the order link. A preference never marks an order paid.
    'payment_preferences' => ['transfer' => 'Transfer bank', 'qris' => 'QRIS'],
];
