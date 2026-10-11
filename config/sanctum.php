<?php

return [
    // This integration accepts machine Bearer tokens, never a human web session.
    'guard' => [],
    'expiration' => 90 * 24 * 60,
    'token_prefix' => '',
];
