<?php

namespace App\Http\Controllers;

use App\Media\MediaPath;
use App\Media\MediaStorage;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MediaController
{
    public function published(string $key, MediaStorage $storage)
    {
        abort_unless(app()->environment(['local', 'testing']) && config('media.published_disk') === 'local_published', 404);
        $variant = MediaVariant::where('object_key', $key)->where('disk', 'local_published')->where('state', 'published')->whereNotNull('published_at')->firstOrFail();
        if ($variant->format === 'mp4') {
            MediaPath::assertVariant($key, $variant->media_asset_id, $variant->profile_version, $variant->checksum, 'mp4');
            $path = Storage::disk('local_published')->path($key);
            abort_unless(is_file($path) && filesize($path) <= config('video.max_bytes') && hash_file('sha256', $path) === $variant->checksum, 404);

            return response()->file($path, ['Content-Type' => 'video/mp4', 'Cache-Control' => MediaStorage::CACHE_CONTROL, 'ETag' => '"'.$variant->checksum.'"']);
        }

        return response($storage->variantBytes($variant))->header('Content-Type', 'image/'.$variant->format)->header('Cache-Control', MediaStorage::CACHE_CONTROL)->header('ETag', '"'.$variant->checksum.'"');
    }

    public function preview(MediaAsset $asset, MediaStorage $storage)
    {
        Gate::authorize('view', $asset);
        abort_if($asset->type === 'video', 404); // Videos are never embedded/autoplayed as admin thumbnails.
        // Only decoded, metadata-free derivatives are embedded in admin, never raw originals.
        $variant = $asset->variants()->orderBy('width')->latest('created_at')->firstOrFail();

        return response($storage->variantBytes($variant))->header('Content-Type', 'image/'.$variant->format)->header('Cache-Control', 'private, no-store');
    }

    public function original(MediaAsset $asset, MediaStorage $storage)
    {
        Gate::authorize('view', $asset);

        return response($storage->originalBytes($asset))->header('Content-Type', 'application/octet-stream')->header('Content-Disposition', 'attachment; filename="original"')->header('Cache-Control', 'private, no-store');
    }
}
