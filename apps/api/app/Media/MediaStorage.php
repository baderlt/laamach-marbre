<?php

namespace App\Media;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaStorage
{
    public function putImmutableFile(string $disk, string $key, string $path, string $hash, string $mime): bool
    {
        if (filesize($path) > config('video.max_bytes') || hash_file('sha256', $path) !== $hash) {
            throw new RuntimeException('Video file changed or exceeds its byte limit.');
        }
        if (Storage::disk($disk)->exists($key)) {
            $existing = $this->temporaryCopy($disk, $key, $hash, config('video.max_bytes'));
            unlink($existing);

            return false;
        }
        $stream = fopen($path, 'rb');
        try {
            $options = ['ContentType' => $mime];
            if (in_array($disk, ['local_published', 'r2_published'], true)) {
                $options['CacheControl'] = self::CACHE_CONTROL;
            }
            if (! Storage::disk($disk)->put($key, $stream, $options)) {
                throw new RuntimeException('Video storage failed.');
            }
        } finally {
            fclose($stream);
        }

        return true;
    }

    public function temporaryCopy(string $disk, string $key, string $hash, int $limit): string
    {
        if (Storage::disk($disk)->size($key) > $limit) {
            throw new RuntimeException('Media exceeds the byte limit.');
        }
        $root = storage_path('framework/media-processing');
        File::ensureDirectoryExists($root);
        $path = tempnam($root, 'video-');
        $input = $output = null;
        try {
            $input = Storage::disk($disk)->readStream($key);
            $output = fopen($path, 'wb');
            if (! is_resource($input) || ! is_resource($output)) {
                throw new RuntimeException('Media stream unavailable.');
            }
            $copied = stream_copy_to_stream($input, $output, $limit + 1);
            fclose($output);
            $output = null;
            if ($copied === false || $copied > $limit || hash_file('sha256', $path) !== $hash) {
                throw new RuntimeException('Media checksum/byte limit failed.');
            }
        } catch (\Throwable $error) {
            if (is_resource($output)) {
                fclose($output);
                $output = null;
            }
            unlink($path);
            throw $error;
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
        }

        return $path;
    }

    public function publishVideo(MediaVariant $variant, string $disk): void
    {
        MediaPath::assertVariant($variant->object_key, $variant->media_asset_id, $variant->profile_version, $variant->checksum, $variant->format);
        if ($variant->state !== 'private' || ! in_array($variant->disk, ['local', 'r2_private'], true)) {
            throw new RuntimeException('Invalid private video disk.');
        }
        $path = $this->temporaryCopy($variant->disk, $variant->object_key, $variant->checksum, config('video.max_bytes'));
        try {
            $this->putImmutableFile($disk, $variant->object_key, $path, $variant->checksum, 'video/mp4');
        } finally {
            unlink($path);
        }
    }

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
        $limit = $asset->type === 'video' ? config('video.max_bytes') : config('media.max_bytes');
        if (! in_array($asset->original_disk, ['local', 'r2_private'], true) || $asset->bytes > $limit || Storage::disk($asset->original_disk)->size($asset->original_key) > $limit) {
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
