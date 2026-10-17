<?php

namespace Tests\Unit;

use App\Support\TestDatabaseGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TestDatabaseGuardTest extends TestCase
{
    private function database(): array
    {
        return ['default' => 'mysql', 'connections' => ['mysql' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'laamach_marbre_test', 'url' => null, 'unix_socket' => '']]];
    }

    public function test_accepts_only_the_dedicated_configuration(): void
    {
        TestDatabaseGuard::assertSafe('testing', $this->database());
        $this->addToAssertionCount(1);
    }

    public static function unsafeDatabases(): array
    {
        // Intentional forbidden-name fixtures, never connection targets.
        return [['time_stone'], ['time_stone_test'], ['laamach_marbre'], ['unknown'], [''], ['laamach_marbre_test_backup']];
    }

    #[DataProvider('unsafeDatabases')]
    public function test_refuses_other_database_names(string $name): void
    {
        $database = $this->database();
        $database['connections']['mysql']['database'] = $name;
        $this->expectException(RuntimeException::class);
        TestDatabaseGuard::assertSafe('testing', $database);
    }

    public static function unsafeOverrides(): array
    {
        return [['url', 'mysql://localhost/laamach_marbre'], ['read', ['database' => 'other']], ['write', ['database' => 'other']], ['host', 'remote.invalid'], ['unix_socket', '/other/mysql.sock'], ['driver', 'sqlite']];
    }

    #[DataProvider('unsafeOverrides')]
    public function test_refuses_connection_overrides(string $key, mixed $value): void
    {
        $database = $this->database();
        $database['connections']['mysql'][$key] = $value;
        $this->expectException(RuntimeException::class);
        TestDatabaseGuard::assertSafe('testing', $database);
    }

    public function test_refuses_a_non_testing_environment(): void
    {
        $this->expectException(RuntimeException::class);
        TestDatabaseGuard::assertSafe('local', $this->database());
    }

    public function test_refuses_a_different_default_connection(): void
    {
        $database = $this->database();
        $database['default'] = 'sqlite';
        $this->expectException(RuntimeException::class);
        TestDatabaseGuard::assertSafe('testing', $database);
    }
}
