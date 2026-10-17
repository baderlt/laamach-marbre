<?php

namespace Tests\Feature;

use App\Support\FoundationConfiguration;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public static function unsafeConfiguration(): array
    {
        return [
            ['app.key', 'base64:not-a-valid-key'],
            ['app.url', 'https://user:password@example.invalid'],
            ['app.url', 'https://example.invalid/path'],
            ['app.url', 'https://example.invalid?query=1'],
            ['app.url', 'file:///tmp/app'],
            ['app.env', 'unknown'],
            ['session.cookie', 'shared_session'],
            ['cache.prefix', 'shared_cache'],
        ];
    }

    #[DataProvider('unsafeConfiguration')]
    public function test_invalid_environment_and_isolation_values_are_rejected(string $key, mixed $value): void
    {
        $configuration = new Repository(config()->all());
        $configuration->set($key, $value);
        $this->expectException(\InvalidArgumentException::class);
        FoundationConfiguration::validate($configuration);
    }

    public function test_production_requires_https_and_debug_disabled(): void
    {
        $configuration = new Repository(config()->all());
        $configuration->set('app.env', 'production');
        $configuration->set('database.connections.mysql.database', 'laamach_marbre');
        $configuration->set('cache.prefix', 'laamach_marbre:production:');
        $configuration->set('session.cookie', 'laamach_marbre_production_session');
        $configuration->set('app.url', 'https://api.example.invalid');
        $configuration->set('app.debug', false);
        FoundationConfiguration::validate($configuration);
        $configuration->set('app.debug', true);
        $this->expectException(\InvalidArgumentException::class);
        FoundationConfiguration::validate($configuration);
    }
}
