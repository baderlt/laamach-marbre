<?php

namespace App\Support;

use InvalidArgumentException;

final class LocaleConfiguration
{
    public static function validate(array $configuration): void
    {
        $locales = $configuration['locales'] ?? null;
        if (! is_array($locales) || $locales === []) {
            throw new InvalidArgumentException('At least one enabled locale is required.');
        }
        $codes = [];
        foreach ($locales as $locale) {
            if (! is_array($locale) || ! is_string($locale['code'] ?? null)
                || ! preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $locale['code'])
                || isset($codes[strtolower($locale['code'])])
                || ! is_string($locale['name'] ?? null) || trim($locale['name']) === '' || mb_strlen($locale['name']) > 80
                || ! in_array($locale['direction'] ?? null, ['ltr', 'rtl'], true)) {
                throw new InvalidArgumentException('Invalid or duplicate locale entry.');
            }
            $codes[strtolower($locale['code'])] = $locale['code'];
        }
        foreach (['default', 'fallback'] as $key) {
            if (! in_array($configuration[$key] ?? null, $codes, true)) {
                throw new InvalidArgumentException("$key must be an enabled locale.");
            }
        }
    }
}
