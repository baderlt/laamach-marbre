<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class TestDatabaseGuard
{
    public const DATABASE = 'laamach_marbre_test';

    // Configuration validation alone does not authorize test or fixture writes.
    public static function assertConfigured(string $environment, array $database): void
    {
        $connection = $database['connections']['mysql'] ?? [];
        if ($environment !== 'testing' || ($database['default'] ?? null) !== 'mysql'
            || ($connection['driver'] ?? null) !== 'mysql'
            || ($connection['database'] ?? null) !== self::DATABASE
            || ($connection['host'] ?? null) !== '127.0.0.1'
            || ! in_array((string) ($connection['port'] ?? ''), ['3306', '3308'], true)
            || (string) ($connection['url'] ?? '') !== ''
            || (string) ($connection['unix_socket'] ?? '') !== ''
            || isset($connection['read']) || isset($connection['write'])) {
            throw new RuntimeException('The local testing/preview connection must use loopback MySQL laamach_marbre_test without URL, socket or read/write overrides.');
        }
    }

    public static function assertSafe(string $environment, array $database): void
    {
        self::assertConfigured($environment, $database);
        self::assertServer($database, 3308, dirname(__DIR__, 4).'/.tools/m8/mysql-data', 'Disposable QA');
    }

    // Explicit client-content imports only; never use this for automated tests or fixtures.
    public static function assertPreviewTarget(string $environment, array $database): void
    {
        self::assertConfigured($environment, $database);
        self::assertServer($database, 3306, 'C:/wamp64/bin/mysql/mysql8.3.0/data', 'Client preview');
    }

    private static function assertServer(array $database, int $port, string $directory, string $label): void
    {
        if ((int) ($database['connections']['mysql']['port'] ?? 0) !== $port) {
            throw new RuntimeException("{$label} requires its dedicated MySQL port {$port}.");
        }

        try {
            $identity = DB::selectOne('SELECT @@port AS port, @@datadir AS directory, DATABASE() AS db');
        } catch (Throwable) {
            throw new RuntimeException("{$label} MySQL is unavailable or its identity cannot be verified.");
        }

        $actual = self::canonicalDirectory((string) ($identity->directory ?? ''));
        $expected = self::canonicalDirectory($directory);
        if ((int) ($identity->port ?? 0) !== $port || ($identity->db ?? null) !== self::DATABASE
            || $actual === null || $expected === null || $actual !== $expected) {
            throw new RuntimeException("{$label} MySQL server identity does not match its dedicated port, database and data directory.");
        }
    }

    private static function canonicalDirectory(string $path): ?string
    {
        $resolved = realpath(rtrim($path, '\\/'));

        return $resolved === false || ! is_dir($resolved)
            ? null
            : strtolower(rtrim(str_replace('\\', '/', $resolved), '/'));
    }
}
