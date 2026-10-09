<?php

/*
 * Private Order API v1 (ORD-02e, contract r4.1 baseline `1.0.0-rc.4.1`). Secret values live only in env;
 * they are never logged, shown in admin, or sent anywhere except as HMAC keys.
 */
return [
    // Off: every /integrations/qammaris-app/orders/v1 request answers 503 `unavailable`.
    'enabled' => (bool) env('QAMMARIS_ORDER_API_ENABLED', false),

    // App → Website requests.
    'client_id' => env('QAMMARIS_ORDER_API_CLIENT_ID', ''),
    'secret' => env('QAMMARIS_ORDER_API_SECRET', ''),
    'secret_previous' => env('QAMMARIS_ORDER_API_SECRET_PREVIOUS', ''),
    'timestamp_tolerance_seconds' => 300,
    'rate_limit_per_minute' => 120,
    'max_body_bytes' => 64 * 1024,
    'max_qr_body_bytes' => 2 * 1024 * 1024 + 64 * 1024,
    'idempotency_days' => 7,
    // Recipient contact data disappears from closed orders after this many days (contract §6.1).
    'closed_recipient_days' => 30,

    // Website → App webhook (outbox). Retried with a new timestamp and signature on every attempt (K-B).
    'webhook_enabled' => (bool) env('QAMMARIS_ORDER_WEBHOOK_ENABLED', false),
    'webhook_url' => env('QAMMARIS_ORDER_WEBHOOK_URL', 'https://api.qammarisapp.com/api/integrations/website/orders/events'),
    'webhook_client_id' => env('QAMMARIS_ORDER_WEBHOOK_CLIENT_ID', 'qammaris-website'),
    'webhook_secret' => env('QAMMARIS_ORDER_WEBHOOK_SECRET', ''),
    'webhook_secret_previous' => env('QAMMARIS_ORDER_WEBHOOK_SECRET_PREVIOUS', ''),
    'webhook_timeout_seconds' => 10,
    'webhook_retry_schedule_seconds' => [0, 60, 300, 900, 3600, 21600],
    'webhook_give_up_after_seconds' => 86400,

    // WhatsApp group task links: Admin PWA until the App integration is live, then the App order page.
    'app_task_links' => (bool) env('QAMMARIS_ORDER_APP_TASK_LINKS', false),

    // Contract r4.2: App user IDs of the App Owners who may create or change a proof waiver. Set on the server only;
    // never taken from a request. Empty means no new waiver decision is accepted.
    'app_owner_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('QAMMARIS_APP_OWNER_IDS', ''))))),

    // Contract r4.2: Website (Admin PWA) staff may open V2 issues while the API is on, once the App reads
    // Issue.opened_by_source. Off until the App confirms r4.2 support.
    'website_issues' => (bool) env('QAMMARIS_ORDER_API_WEBSITE_ISSUES', false),
    'app_orders_url' => env('QAMMARIS_APP_ORDERS_URL', 'https://qammarisapp.com/orders'),
];
