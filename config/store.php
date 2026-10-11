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
];
