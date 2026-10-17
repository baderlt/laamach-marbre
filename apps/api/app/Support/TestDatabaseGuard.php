<?php

namespace App\Support;

use RuntimeException;

final class TestDatabaseGuard
{
    public const DATABASE = 'laamach_marbre_test';

    public static function assertSafe(string $environment, array $database): void
    {
        $connection = $database['connections']['mysql'] ?? [];
        if ($environment !== 'testing' || ($database['default'] ?? null) !== 'mysql'
            || ($connection['driver'] ?? null) !== 'mysql'
            || ($connection['database'] ?? null) !== self::DATABASE
            || ! in_array($connection['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)
            || (string) ($connection['url'] ?? '') !== ''
            || (string) ($connection['unix_socket'] ?? '') !== ''
            || isset($connection['read']) || isset($connection['write'])) {
            throw new RuntimeException('Tests require local MySQL laamach_marbre_test, testing environment and no URL/socket/read/write override.');
        }
    }
}
