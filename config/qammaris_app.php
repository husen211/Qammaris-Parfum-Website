<?php

return [
    'base_url' => env('QAMMARIS_APP_BASE_URL', 'https://api.qammarisapp.com/api/public/v1'),
    'api_key' => env('QAMMARIS_APP_API_KEY', ''),
    'webhook_secret' => env('QAMMARIS_APP_WEBHOOK_SECRET', ''),
    'signature_tolerance_seconds' => 300,
    'queue' => 'qammaris-app',
    // ORD-04: after this many minutes without a successful feed sync, public labels stop implying current stock.
    'availability_stale_minutes' => (int) env('QAMMARIS_APP_AVAILABILITY_STALE_MINUTES', 120),
];
