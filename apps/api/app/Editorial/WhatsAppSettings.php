<?php

namespace App\Editorial;

class WhatsAppSettings
{
    public static function number(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\+?[0-9 ()-]{8,40}$/D', $value)) {
            return null;
        }
        $number = preg_replace('/[ ()-]/', '', $value);
        $number = str_starts_with($number, '00') ? substr($number, 2) : ltrim($number, '+');

        return preg_match('/^[1-9][0-9]{7,14}$/D', $number) ? $number : null;
    }

    public static function publicData(array $data): array
    {
        $number = self::number($data['number'] ?? null);
        $enabled = ($data['enabled'] ?? false) === true && $number !== null;

        return ['number' => $enabled ? $number : null, 'enabled' => $enabled,
            'material' => $enabled && ($data['material'] ?? false) === true,
            'similar' => $enabled && ($data['similar'] ?? false) === true,
            'application' => $enabled && ($data['application'] ?? false) === true];
    }
}
