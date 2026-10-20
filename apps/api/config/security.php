<?php

$local = in_array(env('APP_ENV', 'local'), ['local', 'testing'], true);
$origins = env('CORS_ALLOWED_ORIGINS', $local ? 'http://127.0.0.1:3100,http://127.0.0.1:3101,http://127.0.0.1:3105' : '');

return [
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', $origins)))),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))))),
    'public_requests_per_minute' => 120,
    'max_body_bytes' => 60 * 1024 * 1024,
    'hsts_enabled' => env('HSTS_ENABLED', false),
];
