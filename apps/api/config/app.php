<?php

return [
    'name' => 'LAAMACH MARBRE',
    'env' => env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://127.0.0.1:8100'),
    'timezone' => 'UTC',
    'locale' => 'fr',
    'fallback_locale' => 'fr',
    'faker_locale' => 'fr_FR',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'previous_keys' => [],
    'maintenance' => ['driver' => 'file', 'store' => 'database'],
];
