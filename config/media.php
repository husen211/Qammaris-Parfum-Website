<?php

return [
    'product_disk' => env('PRODUCT_MEDIA_DISK', 'public'),
    'product_directory' => env('PRODUCT_MEDIA_DIRECTORY', 'products'),
    'migration_target_disk' => env('PRODUCT_MEDIA_TARGET_DISK', 'r2'),
    'manifest_disk' => env('PRODUCT_MEDIA_MANIFEST_DISK', 'local'),
    'manifest_directory' => env('PRODUCT_MEDIA_MANIFEST_DIRECTORY', 'media-migrations'),
];
