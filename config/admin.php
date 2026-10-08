<?php

return [
    // Owner D7: admin sessions end after 12 idle hours. SESSION_LIFETIME must be at least this long.
    'idle_minutes' => (int) env('ADMIN_IDLE_MINUTES', 720),
];
