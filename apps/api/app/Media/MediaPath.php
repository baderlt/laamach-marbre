<?php

namespace App\Media;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaPath
{
    public static function relative(string $path): string
    {
        if (strlen($path) > 512 || preg_match('/[\\\\\x00-\x1f\x7f:%?#]/', $path) || str_starts_with($path, '/') || array_intersect(explode('/', $path), ['', '.', '..'])) {
            throw ValidationException::withMessages(['source' => 'Unsafe relative media path.']);
        }

        return $path;
    }

    public static function original(string $asset, string $hash): string
    {
        return 'originals/'.$asset.'/'.$hash;
    }

    public static function assertOriginal(string $key, string $asset, string $hash): void
    {
        if (! Str::isUuid($asset) || ! preg_match('/^[a-f0-9]{64}$/D', $hash) || $key !== self::original($asset, $hash)) {
            throw ValidationException::withMessages(['media' => 'Invalid original key.']);
        }
    }

    public static function assertVariant(string $key, string $asset, string $version, string $hash, string $format): void
    {
        $prefix = match ($format) {
            'mp4' => 'videos', 'glb' => 'models', default => 'images'
        };
        if (! Str::isUuid($asset) || ! preg_match('/^[a-z0-9-]{1,80}$/D', $version) || ! in_array($format, ['webp', 'avif', 'mp4', 'glb'], true) || ! preg_match('/^[a-f0-9]{64}$/D', $hash) || $key !== "$prefix/$asset/$version/$hash.$format") {
            throw ValidationException::withMessages(['media' => 'Invalid derived image key.']);
        }
    }
}
