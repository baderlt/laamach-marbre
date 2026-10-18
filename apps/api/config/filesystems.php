<?php

return [
    'default' => 'local',
    'disks' => [
        // Livewire selects this disk for HTTP requests in the isolated testing environment.
        ...(env('APP_ENV') === 'testing' ? ['tmp-for-tests' => [
            'driver' => 'local', 'root' => storage_path('framework/testing/'.getmypid().'/livewire'),
            'serve' => false, 'throw' => true, 'report' => false,
        ]] : []),
        'local' => [
            'driver' => 'local', 'root' => storage_path('app/private'),
            'serve' => false, 'throw' => true, 'report' => false,
        ],
        'local_published' => [
            'driver' => 'local', 'root' => storage_path('app/published'),
            'serve' => false, 'throw' => true, 'report' => false,
        ],
        'r2_private' => [
            'driver' => 's3', 'key' => env('R2_ACCESS_KEY_ID'), 'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_REGION', 'auto'), 'bucket' => env('R2_PRIVATE_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'), 'use_path_style_endpoint' => true, 'throw' => true,
        ],
        'r2_published' => [
            'driver' => 's3', 'key' => env('R2_ACCESS_KEY_ID'), 'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_REGION', 'auto'), 'bucket' => env('R2_PUBLISHED_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'), 'url' => env('R2_PUBLISHED_URL'),
            'use_path_style_endpoint' => true, 'throw' => true,
        ],
    ],
    'links' => [],
];
