<?php

return [
    // ORD-02 cutover point: orders created while true use the V2 state model; existing orders keep theirs.
    'v2_enabled' => (bool) env('ORDERS_V2_ENABLED', false),
];
