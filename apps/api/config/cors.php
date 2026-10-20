<?php

return [
    'paths' => ['api/v1/*', 'media/*'],
    'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', in_array(env('APP_ENV', 'local'), ['local', 'testing'], true) ? 'http://127.0.0.1:3100,http://127.0.0.1:3101,http://127.0.0.1:3105' : ''))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type', 'Range', 'If-None-Match', 'If-Range'],
    'exposed_headers' => ['Accept-Ranges', 'Content-Range', 'Content-Length', 'ETag'],
    'max_age' => 600,
    'supports_credentials' => false,
];
