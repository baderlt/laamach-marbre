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
        return ['default' => 'mysql', 'connections' => ['mysql' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3308, 'database' => 'laamach_marbre_test', 'url' => null, 'unix_socket' => '']]];
    }

    public function test_accepts_a_local_testing_configuration_before_connection(): void
    {
        TestDatabaseGuard::assertConfigured('testing', $this->database());
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
        TestDatabaseGuard::assertConfigured('testing', $database);
    }

    public static function unsafeOverrides(): array
    {
        return [['url', 'mysql://localhost/laamach_marbre'], ['read', ['database' => 'other']], ['write', ['database' => 'other']], ['host', 'remote.invalid'], ['unix_socket', '/other/mysql.sock'], ['driver', 'sqlite'], ['port', 3307], ['port', null]];
    }

    #[DataProvider('unsafeOverrides')]
    public function test_refuses_connection_overrides(string $key, mixed $value): void
    {
        $database = $this->database();
        $database['connections']['mysql'][$key] = $value;
        $this->expectException(RuntimeException::class);
        TestDatabaseGuard::assertConfigured('testing', $database);
    }

    public function test_refuses_a_non_testing_environment(): void
    {
        $this->expectException(RuntimeException::class);
        TestDatabaseGuard::assertConfigured('local', $this->database());
    }

    public function test_refuses_a_different_default_connection(): void
    {
        $database = $this->database();
        $database['default'] = 'sqlite';
        $this->expectException(RuntimeException::class);
        TestDatabaseGuard::assertConfigured('testing', $database);
    }

    public function test_qa_guard_rejects_preview_port_before_opening_a_connection(): void
    {
        $database = $this->database();
        $database['connections']['mysql']['port'] = 3306;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Disposable QA requires its dedicated MySQL port 3308.');
        TestDatabaseGuard::assertSafe('testing', $database);
    }
}
