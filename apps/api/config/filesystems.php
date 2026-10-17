<?php

return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local', 'root' => storage_path('app/private'),
            'serve' => false, 'throw' => true, 'report' => false,
        ],
        // Configuration only in M1. The S3 adapter and media pipeline belong to M2.
        'r2_private' => [
            'driver' => 's3', 'key' => env('R2_ACCESS_KEY_ID'), 'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_REGION', 'auto'), 'bucket' => env('R2_PRIVATE_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'), 'use_path_style_endpoint' => false, 'throw' => true,
        ],
        'r2_published' => [
            'driver' => 's3', 'key' => env('R2_ACCESS_KEY_ID'), 'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_REGION', 'auto'), 'bucket' => env('R2_PUBLISHED_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'), 'url' => env('R2_PUBLISHED_URL'),
            'use_path_style_endpoint' => false, 'throw' => true,
        ],
    ],
    'links' => [],
];
