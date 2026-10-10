<?php

return [
    // Reserved for the PREF-03 public adapter; this phase never changes the legacy controller.
    'enabled' => (bool) env('FRAGRANCE_PREFERENCE_ENABLED', false),
    'engine_version' => 'pref-03.2-provisional',
    'question_version' => 'preference-v1.2',
];
