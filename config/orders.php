<?php

return [
    // ORD-02 cutover point: orders created while true use the V2 state model; existing orders keep theirs.
    'v2_enabled' => (bool) env('ORDERS_V2_ENABLED', false),

    // ORD-03 (UAT flag): customer payment preference on the order link, and Staff Order may set the
    // customer-charged shipping fee while no payment is recorded. Off unless the environment enables it.
    'simple_ux' => (bool) env('ORDERS_SIMPLE_UX', false),

    // Payment methods a customer may prefer on the order link. A preference never marks an order paid.
    'payment_preferences' => ['transfer' => 'Transfer bank', 'qris' => 'QRIS'],

    // ORD-04 (UAT flag, needs v2_enabled): the cart checkout saves a guest order that waits for Staff Order
    // confirmation before it becomes active work. Off: the cart only opens WhatsApp and stores nothing (ADR-028).
    'website_checkout' => (bool) env('ORDERS_WEBSITE_CHECKOUT', false),

    // Delivery choices offered at checkout, mapped to order fulfillment types. The instant courier is chosen by staff.
    'checkout_deliveries' => [
        'pickup' => ['label' => 'Ambil di toko', 'help' => 'Ambil sendiri di toko Qammaris setelah pesanan dikonfirmasi.'],
        'local_delivery' => ['label' => 'Pengiriman Instan — Kota Palu', 'help' => 'Dikirim kurir online (ojol). Perkiraan 1–2 jam setelah pembayaran dikonfirmasi.'],
        'intercity' => ['label' => 'Pengiriman Luar Kota — J&T', 'help' => 'Dikirim lewat J&T. Ongkir dihitung staf sesuai alamat.'],
    ],

    // Phase-2 extension point (no calculator yet): where a shipping estimate came from. The final charge is shipping_fee.
    'shipping_estimate_sources' => ['manual' => 'Diisi staf', 'zone' => 'Zona', 'distance' => 'Jarak', 'api' => 'API kurir'],
];
