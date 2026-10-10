<?php

return [
    // ORD-07: these environments never open a chat with the real store. Any other APP_ENV, production included,
    // keeps the store number, so an unexpected production APP_ENV value cannot break the live checkout.
    'test_environments' => ['local', 'development', 'testing', 'uat', 'staging'],

    // Optional WhatsApp number for those environments (never the store number). Empty: no wa.me link is built;
    // pages show the message with a copy button instead.
    'whatsapp_test_number' => env('STORE_WHATSAPP_TEST_NUMBER'),
];
