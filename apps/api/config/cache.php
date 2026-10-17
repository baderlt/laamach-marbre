<?php

return [
    'default' => env('CACHE_STORE', 'database'),
    'stores' => [
        'array' => ['driver' => 'array', 'serialize' => false],
        'database' => [
            'driver' => 'database', 'connection' => null, 'table' => 'cache',
            'lock_connection' => null, 'lock_table' => 'cache_locks',
        ],
        'file' => [
            'driver' => 'file', 'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],
    ],
    'prefix' => 'laamach_marbre:'.env('APP_ENV', 'local').':',
    'serializable_classes' => false,
];
