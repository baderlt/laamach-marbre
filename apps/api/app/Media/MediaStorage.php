<?php

namespace App\Media;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaStorage
{
    public const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function putImmutable(string $disk, string $key, string $bytes, string $mime): bool
    {
        $store = Storage::disk($disk);
        // Callers serialize writes per asset with a database row lock.
        if ($store->exists($key)) {
            $stream = $store->readStream($key);
            try {
                $hash = hash_init('sha256');
                hash_update_stream($hash, $stream);
                if (hash_final($hash) !== hash('sha256', $bytes)) {
                    throw new RuntimeException('Immutable media object differs; refusing overwrite.');
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            return false;
        }
        $options = ['ContentType' => $mime];
        if (in_array($disk, ['r2_published', 'local_published'], true)) {
            $options['CacheControl'] = self::CACHE_CONTROL;
        }
        if (! $store->put($key, $bytes, $options)) {
            throw new RuntimeException('Media write failed.');
        }

        return true;
    }

    public function originalBytes(MediaAsset $asset): string
    {
        MediaPath::assertOriginal($asset->original_key, $asset->id, $asset->checksum);
        if (! in_array($asset->original_disk, ['local', 'r2_private'], true) || $asset->bytes > config('media.max_bytes') || Storage::disk($asset->original_disk)->size($asset->original_key) > config('media.max_bytes')) {
            throw new RuntimeException('Invalid private original storage.');
        }
        $bytes = Storage::disk($asset->original_disk)->get($asset->original_key);
        if (hash('sha256', $bytes) !== $asset->checksum) {
            throw new RuntimeException('Private original checksum mismatch.');
        }

        return $bytes;
    }

    public function variantBytes(MediaVariant $variant): string
    {
        MediaPath::assertVariant($variant->object_key, $variant->media_asset_id, $variant->profile_version, $variant->checksum, $variant->format);
        $expected = $variant->state === 'published' ? ['local_published', 'r2_published'] : ['local', 'r2_private'];
        if (! in_array($variant->disk, $expected, true) || Storage::disk($variant->disk)->size($variant->object_key) > config('media.max_bytes')) {
            throw new RuntimeException('Invalid variant storage.');
        }
        $bytes = Storage::disk($variant->disk)->get($variant->object_key);
        if (hash('sha256', $bytes) !== $variant->checksum) {
            throw new RuntimeException('Variant checksum mismatch.');
        }

        return $bytes;
    }

    public function deletePrivateOriginal(MediaAsset $asset): void
    {
        MediaPath::assertOriginal($asset->original_key, $asset->id, $asset->checksum);
        if (in_array($asset->original_disk, ['local', 'r2_private'], true)) {
            Storage::disk($asset->original_disk)->delete($asset->original_key);
        }
    }

    public function deletePrivateVariant(MediaVariant $variant): void
    {
        MediaPath::assertVariant($variant->object_key, $variant->media_asset_id, $variant->profile_version, $variant->checksum, $variant->format);
        if ($variant->state === 'private' && in_array($variant->disk, ['local', 'r2_private'], true)) {
            Storage::disk($variant->disk)->delete($variant->object_key);
        }
    }
}
