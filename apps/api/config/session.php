<?php

return [
    'driver' => env('SESSION_DRIVER', 'database'),
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'connection' => null,
    'table' => 'sessions',
    'store' => null,
    'lottery' => [2, 100],
    'cookie' => 'laamach_marbre_'.env('APP_ENV', 'local').'_session',
    'path' => '/',
    'domain' => null,
    'secure' => env('APP_ENV', 'local') === 'production',
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
];
