<?php

return [
    // ORD-09 (Owner 2026-10-17): push notification to Super Admin and Staff Order when someone orders on the website.
    // Off until both VAPID keys are set (generate once with `php artisan admin-push:vapid`, keep the private key secret).
    'vapid' => [
        'subject' => env('ADMIN_PUSH_VAPID_SUBJECT', 'mailto:admin@qammaris.com'),
        'public_key' => env('ADMIN_PUSH_VAPID_PUBLIC_KEY', ''),
        'private_key' => env('ADMIN_PUSH_VAPID_PRIVATE_KEY', ''),
    ],
];
