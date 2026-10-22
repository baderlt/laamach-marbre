<?php

namespace App\Media;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;

class MediaConfiguration
{
    public static function validate(Repository $config): void
    {
        $private = $config->get('media.private_disk');
        $public = $config->get('media.published_disk');
        if (! in_array($private, ['local', 'r2_private'], true) || ! in_array($public, ['local_published', 'r2_published'], true)) {
            throw new InvalidArgumentException('Media disk roles are invalid.');
        }
        $production = in_array($config->get('app.env'), ['production', 'staging'], true);
        if ($production && [$private, $public] !== ['r2_private', 'r2_published']) {
            throw new InvalidArgumentException('Hosted environments require separate R2 media disks.');
        }
        $privateRoot = str_replace('\\', '/', $config->get('filesystems.disks.local.root'));
        $publicRoot = str_replace('\\', '/', $config->get('filesystems.disks.local_published.root'));
        foreach (['local', 'local_published'] as $disk) {
            if ($config->get("filesystems.disks.$disk.driver") !== 'local' || $config->get("filesystems.disks.$disk.serve") !== false) {
                throw new InvalidArgumentException('Local media must use non-served local disks.');
            }
        }
        foreach (['r2_private', 'r2_published'] as $disk) {
            if ($config->get("filesystems.disks.$disk.driver") !== 's3') {
                throw new InvalidArgumentException('R2 disks require the S3 adapter.');
            }
        }
        if ($privateRoot === $publicRoot || str_starts_with($privateRoot.'/', $publicRoot.'/') || str_starts_with($publicRoot.'/', $privateRoot.'/')) {
            throw new InvalidArgumentException('Local media roots must be disjoint.');
        }
        if ($production || $private === 'r2_private' || $public === 'r2_published') {
            foreach (['key', 'secret', 'bucket', 'endpoint', 'region'] as $field) {
                foreach (['r2_private', 'r2_published'] as $disk) {
                    if (! is_string($config->get("filesystems.disks.$disk.$field")) || trim($config->get("filesystems.disks.$disk.$field")) === '') {
                        throw new InvalidArgumentException('R2 media configuration is incomplete.');
                    }
                }
            }
            if ($config->get('filesystems.disks.r2_private.bucket') === $config->get('filesystems.disks.r2_published.bucket')) {
                throw new InvalidArgumentException('Private and published R2 buckets must differ.');
            }
            foreach (['filesystems.disks.r2_private.endpoint', 'filesystems.disks.r2_published.endpoint', 'filesystems.disks.r2_published.url'] as $key) {
                $parts = parse_url($config->get($key, ''));
                if (! $parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
                    throw new InvalidArgumentException('R2 endpoints/media URL must be clean HTTPS origins.');
                }
            }
            foreach (['r2_private', 'r2_published'] as $disk) {
                $host = parse_url($config->get("filesystems.disks.$disk.endpoint"), PHP_URL_HOST);
                if (! str_ends_with($host, '.r2.cloudflarestorage.com') || $config->get("filesystems.disks.$disk.endpoint") === $config->get('filesystems.disks.r2_published.url')) {
                    throw new InvalidArgumentException('Use the account R2 S3 API endpoint, never the public CDN hostname.');
                }
            }
            if ($config->get('filesystems.disks.r2_private.url')) {
                throw new InvalidArgumentException('Private R2 must not define a public URL.');
            }
        }
        foreach (['max_bytes' => 20971520, 'max_pixels' => 20000000, 'max_dimension' => 8192, 'memory_budget' => 536870912, 'import_max_files' => 5000, 'manifest_max_bytes' => 1048576] as $key => $ceiling) {
            $value = $config->get("media.$key");
            if (! is_int($value) || $value < 1 || $value > $ceiling) {
                throw new InvalidArgumentException('Invalid bounded media limit: '.$key);
            }
        }
        $formats = $config->get('media.formats');
        if (! is_array($formats) || ! in_array('webp', $formats, true) || array_diff($formats, ['webp', 'avif']) || count(array_unique($formats)) !== count($formats)) {
            throw new InvalidArgumentException('Media formats must include WebP and optionally AVIF.');
        }
        foreach ($formats as $format) {
            if (! is_int($config->get("media.quality.$format")) || $config->get("media.quality.$format") < 1 || $config->get("media.quality.$format") > 100) {
                throw new InvalidArgumentException('Invalid image quality.');
            }
        }
        if (! preg_match('/^[a-z0-9-]{1,30}$/D', $config->get('media.encoder_version', '')) || ! $config->get('media.profiles') || count($config->get('media.profiles')) > 8) {
            throw new InvalidArgumentException('Invalid media profiles/encoder version.');
        }
        foreach ($config->get('media.profiles') as $name => $profile) {
            if (! preg_match('/^[a-z0-9-]{1,20}$/D', $name) || ! preg_match('/^[a-z0-9-]{1,20}$/D', $profile['version'] ?? '') || ! is_int($profile['edge'] ?? null) || $profile['edge'] < 1 || $profile['edge'] > 2560) {
                throw new InvalidArgumentException('Invalid media profile.');
            }
        }
    }
}
