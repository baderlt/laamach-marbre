<?php

use Monolog\Handler\NullHandler;

return [
    'default' => 'daily',
    'deprecations' => ['channel' => 'null', 'trace' => false],
    'channels' => [
        'daily' => ['driver' => 'daily', 'path' => storage_path('logs/laravel.log'), 'level' => 'warning', 'days' => 14, 'replace_placeholders' => true],
        'null' => ['driver' => 'monolog', 'handler' => NullHandler::class],
    ],
];
