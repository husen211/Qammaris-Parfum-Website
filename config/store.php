<?php

return [
    // ORD-07: these environments never open a chat with the real store. Any other APP_ENV, production included,
    // keeps the store number, so an unexpected production APP_ENV value cannot break the live checkout.
    'test_environments' => ['local', 'development', 'testing', 'uat', 'staging'],

    // Optional WhatsApp number for those environments (never the store number). Empty: no wa.me link is built;
    // pages show the message with a copy button instead.
    'whatsapp_test_number' => env('STORE_WHATSAPP_TEST_NUMBER'),

    // ORD-04: a Super Admin may opt THEIR browser in to the official store number for a short UAT test (encrypted
    // cookie). Everyone else keeps the protection; every test-environment message starts with "[UAT]".
    'uat_official_cookie' => 'qam_uat_wa_official',
    'uat_official_minutes' => 120,

    // PWA-01 kill switch for the installable public website. Off by default: nothing is registered until enabled,
    // and while off, installed copies replace their worker with one that removes itself and the public caches.
    'pwa_enabled' => (bool) env('PUBLIC_PWA_ENABLED', false),

    // SEO-01: the one name/address/phone the website and its structured data use (Owner confirmed 2026-10-25).
    'profile' => [
        'name' => 'Qammaris Perfumes',
        'street' => 'Jl. Sis Aljufri, Siranindi, Kec. Palu Barat',
        'locality' => 'Kota Palu',
        'region' => 'Sulawesi Tengah',
        'postal_code' => '94111',
        'country' => 'ID',
        'telephone' => '+6285144924931',
        'maps_url' => 'https://maps.app.goo.gl/npKqotHHTo2AAyag9',
        'opening_days' => ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'],
        'opens' => '09:00',
        'closes' => '21:00',
        'same_as' => [
            'https://www.instagram.com/qammaris',
            'https://www.tiktok.com/@qammaris.parfum',
            'https://www.linkedin.com/company/qammaris-perfumes',
        ],
    ],
];
