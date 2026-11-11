<?php

return [
    // ORD-02 cutover point: orders created while true use the V2 state model; existing orders keep theirs.
    'v2_enabled' => (bool) env('ORDERS_V2_ENABLED', false),

    // ORD-03 (UAT flag): customer payment preference on the order link, and Staff Order may set the
    // customer-charged shipping fee while no payment is recorded. Off unless the environment enables it.
    'simple_ux' => (bool) env('ORDERS_SIMPLE_UX', false),

    // ORD-04: "Pakai paperbag" adds this many rupiah per order to the customer total (snapshotted on the order).
    'paperbag_fee' => 4000,

    // Payment methods a customer may prefer on the order link. A preference never marks an order paid.
    'payment_preferences' => ['transfer' => 'Transfer bank', 'qris' => 'QRIS'],

    // ORD-04 (UAT flag, needs v2_enabled): the cart checkout saves a guest order that waits for Staff Order
    // confirmation before it becomes active work. Off: the cart only opens WhatsApp and stores nothing (ADR-028).
    'website_checkout' => (bool) env('ORDERS_WEBSITE_CHECKOUT', false),

    // Delivery choices offered at checkout, mapped to order fulfillment types. The instant courier is chosen by staff.
    'checkout_deliveries' => [
        'pickup' => ['label' => 'Ambil di toko', 'help' => 'Ambil sendiri di toko Qammaris setelah pesanan dikonfirmasi.'],
        'local_delivery' => ['label' => 'Pengiriman Dalam Kota', 'help' => 'Kurir online, Kota Palu. Tiba 1–2 jam setelah pesanan diproses.'],
        'intercity' => ['label' => 'Pengiriman Luar Kota — J&T', 'help' => 'Dikirim lewat J&T. Ongkir dihitung staf sesuai alamat.'],
    ],

    // Where a shipping estimate came from. The final charge is shipping_fee.
    'shipping_estimate_sources' => ['manual' => 'Diisi staf', 'zone' => 'Zona', 'distance' => 'Jarak', 'api' => 'API kurir'],

    /*
     * ORD-13 (flag, needs website_checkout and a Biteship key): checkout prices the courier itself. Instant couriers
     * are priced from the customer's map pin, J&T from the destination area; the store's discount is applied here and
     * the charged amount is locked on the order. Off, or when Biteship cannot answer: ongkir is confirmed by staff.
     */
    'shipping_quotes' => [
        'enabled' => (bool) env('ORDERS_SHIPPING_QUOTES', false),
        // Pickup point (store pin in Biteship) and its Biteship postal area for J&T.
        'origin' => ['latitude' => -0.8981125, 'longitude' => 119.8594844, 'postal_code' => 94221,
            // ORD-14: pickup contact for Biteship drivers; empty uses the store name, phone and address in Info Toko.
            'contact_name' => env('BITESHIP_ORIGIN_NAME', ''), 'contact_phone' => env('BITESHIP_ORIGIN_PHONE', ''), 'address' => env('BITESHIP_ORIGIN_ADDRESS', '')],
        // Owner 2026-11-11: every product counts as 800 g including box and bubble wrap.
        'item_weight_grams' => 800,
        'quote_minutes' => 30,
        // Charged ongkir is rounded down to this step, so a discount never shrinks through rounding.
        'round_down_to' => 500,
        'instant' => [
            'couriers' => ['grab' => 'GrabExpress', 'gojek' => 'GoSend'],
            'discount_percent' => 15,
            'discount_from' => 20000,
            'discount_max' => null,
        ],
        'jnt' => [
            'discount_percent' => 30,
            'discount_from' => 0,
            'discount_max' => 30000,
        ],
        // Pins outside this box are refused before Biteship is asked (Palu and its surroundings).
        'service_area' => ['south' => -1.2, 'north' => -0.6, 'west' => 119.6, 'east' => 120.1],
        // Starting views for the map; approximate centres, the customer still places the pin.
        'kecamatan' => [
            'Palu Barat' => [-0.8950, 119.8560],
            'Palu Timur' => [-0.8850, 119.8760],
            'Palu Selatan' => [-0.9160, 119.8860],
            'Tatanga' => [-0.9230, 119.8500],
            'Ulujadi' => [-0.8650, 119.8300],
            'Mantikulore' => [-0.8650, 119.9050],
            'Palu Utara' => [-0.7950, 119.8780],
            'Tawaeli' => [-0.7300, 119.8600],
        ],
    ],
];
