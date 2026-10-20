<?php

namespace App\Support;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;

final class SecurityConfiguration
{
    public static function validate(Repository $config): void
    {
        $origins = $config->get('security.allowed_origins', []);
        if (! is_array($origins) || count($origins) > 12) {
            throw new InvalidArgumentException('Configure a bounded list of frontend origins.');
        }
        foreach ($origins as $origin) {
            $parts = parse_url($origin);
            if (! $parts || ! in_array($parts['scheme'] ?? '', ['https', 'http'], true) || empty($parts['host']) || str_contains($origin, '*') || preg_match('/[\x00-\x20\x7f]/', $origin) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true) || ($config->get('app.env') === 'production' && $parts['scheme'] !== 'https')) {
                throw new InvalidArgumentException('Frontend origins must be exact HTTP(S) origins; production requires HTTPS.');
            }
        }
        foreach ($config->get('security.trusted_proxies', []) as $proxy) {
            $parts = explode('/', $proxy);
            if (count($parts) > 2 || ! filter_var($parts[0], FILTER_VALIDATE_IP) || (isset($parts[1]) && (! ctype_digit($parts[1]) || (int) $parts[1] > (str_contains($parts[0], ':') ? 128 : 32)))) {
                throw new InvalidArgumentException('Trusted proxies must be explicit IP addresses or CIDR ranges.');
            }
        }
    }
}
