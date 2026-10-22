<?php

$r2 = [
    'driver' => 's3', 'key' => env('R2_ACCESS_KEY_ID'), 'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => env('R2_REGION', 'auto'), 'endpoint' => env('R2_ENDPOINT'),
    'use_path_style_endpoint' => true, 'visibility' => 'private', 'throw' => true, 'report' => false,
    'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
    'http' => ['connect_timeout' => 5, 'timeout' => 120], 'retries' => 2,
    // Every supported asset is <= 50 MiB. Keep conditional writes single-part and streamed.
    'options' => ['params' => ['IfNoneMatch' => '*'], 'mup_threshold' => 64 * 1024 * 1024, 'concurrency' => 1],
];
$migration = ['key' => env('R2_MIGRATION_ACCESS_KEY_ID'), 'secret' => env('R2_MIGRATION_SECRET_ACCESS_KEY'), 'token' => env('R2_MIGRATION_SESSION_TOKEN')];

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
        'r2_private' => $r2 + ['bucket' => env('R2_PRIVATE_BUCKET', 'laamach-private')],
        'r2_published' => $r2 + ['bucket' => env('R2_PUBLISHED_BUCKET', 'laamach-published'), 'url' => env('R2_PUBLISHED_URL', 'https://media.laamachmarbre.com')],
        // Dedicated short-lived credentials; never fall back to runtime credentials.
        'r2_migration_private' => array_replace($r2, $migration, ['bucket' => env('R2_PRIVATE_BUCKET', 'laamach-private')]),
        'r2_migration_published' => array_replace($r2, $migration, ['bucket' => env('R2_PUBLISHED_BUCKET', 'laamach-published')]),
    ],
    'links' => [],
];
