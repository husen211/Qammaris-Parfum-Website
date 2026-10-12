<?php

return [
    // ANALYTICS-01: Cloudflare Web Analytics beacon token (cookieless, ~5 KB). Empty = no beacon.
    'cloudflare_token' => env('CLOUDFLARE_WEB_ANALYTICS_TOKEN'),

    // Google Search Console HTML-tag verification code. Empty = no meta tag (DNS verification needs none).
    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),

    // ANALYTICS-02: opt-in GA4 city statistics. Empty = no consent UI or Google tag.
    'ga4_measurement_id' => env('GA4_MEASUREMENT_ID'),
];
