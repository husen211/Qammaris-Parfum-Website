<?php

$imageHosts = array_values(array_filter(array_map(
    static fn (string $host): string => mb_strtolower(trim($host)),
    explode(',', (string) env('PRODUCT_IMPORT_IMAGE_ALLOWED_HOSTS', 'down-id.img.susercontent.com,cf.shopee.co.id'))
)));

return [
    'image_acquisition' => [
        'allowed_hosts' => $imageHosts,
        'connect_timeout_seconds' => (int) env('PRODUCT_IMPORT_IMAGE_CONNECT_TIMEOUT', 5),
        'timeout_seconds' => (int) env('PRODUCT_IMPORT_IMAGE_TIMEOUT', 20),
        'max_bytes' => (int) env('PRODUCT_IMPORT_IMAGE_MAX_BYTES', 8 * 1024 * 1024),
        'max_dimension' => (int) env('PRODUCT_IMPORT_IMAGE_MAX_DIMENSION', 12000),
        'allowed_mime_types' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ],
    ],
];
