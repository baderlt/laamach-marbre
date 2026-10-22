<?php

namespace App\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Encryption\Encrypter;
use InvalidArgumentException;

final class FoundationConfiguration
{
    public static function validate(Repository $config, bool $requireKey = true): void
    {
        $key = (string) $config->get('app.key');
        if ($requireKey || $key !== '') {
            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7), true);
            }
            if (! is_string($key) || ! Encrypter::supported($key, $config->get('app.cipher'))) {
                throw new InvalidArgumentException('A valid independent APP_KEY is required. Run artisan key:generate.');
            }
        }
        $environment = $config->get('app.env');
        if (! in_array($environment, ['local', 'testing', 'staging', 'production'], true)) {
            throw new InvalidArgumentException('Unsupported application environment.');
        }
        LocaleConfiguration::validate($config->get('localization'));
        foreach (['app.url'] as $key) {
            $parts = parse_url((string) $config->get($key));
            if (! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])
                || isset($parts['user']) || isset($parts['pass'])
                || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
                throw new InvalidArgumentException('APP_URL must be an HTTP(S) origin.');
            }
            if ($environment === 'production' && $parts['scheme'] !== 'https') {
                throw new InvalidArgumentException('Production requires HTTPS.');
            }
        }
        if ($environment === 'testing') {
            TestDatabaseGuard::assertConfigured($environment, $config->get('database'));
        } elseif ($config->get('database.default') !== 'mysql'
            || $config->get('database.connections.mysql.database') !== 'laamach_marbre'
            || (string) $config->get('database.connections.mysql.url') !== '') {
            throw new InvalidArgumentException('The application requires its independent MySQL laamach_marbre database without a URL override.');
        }
        if ($environment === 'production' && $config->get('app.debug')) {
            throw new InvalidArgumentException('Production debug must be disabled.');
        }
        if ($config->get('cache.prefix') !== "laamach_marbre:$environment:"
            || $config->get('session.cookie') !== "laamach_marbre_{$environment}_session") {
            throw new InvalidArgumentException('Cache/session isolation configuration is invalid.');
        }
    }
}
