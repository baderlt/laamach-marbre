<?php

namespace App\Media;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoLibrary
{
    public function __construct(private VideoProcessor $processor, private MediaStorage $storage) {}

    public function ingest(string $path, string $source, bool $clientSupplied = false): MediaAsset
    {
        MediaPath::relative($source);
        $info = $this->processor->inspect($path);

        return DB::transaction(function () use ($path, $source, $clientSupplied, $info) {
            if ($existing = MediaAsset::where('checksum', $info['checksum'])->first()) {
                return $existing;
            }
            $id = (string) Str::uuid();
            $disk = config('media.private_disk');
            $key = MediaPath::original($id, $info['checksum']);
            if ($this->storage->putImmutableFile($disk, $key, $path, $info['checksum'], 'video/mp4')) {
                DB::afterRollBack(fn () => Storage::disk($disk)->delete($key));
            }
            $asset = new MediaAsset;
            $asset->forceFill(['id' => $id, ...$info, 'type' => 'video', 'original_disk' => $disk, 'original_key' => $key, 'original_filename' => basename($source), 'source_path' => $source, 'client_supplied' => $clientSupplied, 'approval_state' => 'pending', 'processing_state' => 'pending', 'imported_at' => now()])->save();

            return $asset;
        }, 3);
    }

    public function process(MediaAsset $asset): string
    {
        return DB::transaction(function () use ($asset) {
            $asset = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            abort_unless($asset->type === 'video' && in_array($asset->original_disk, ['local', 'r2_private'], true), 422);
            MediaPath::assertOriginal($asset->original_key, $asset->id, $asset->checksum);
            $version = $this->processor->version();
            $revision = hash('sha256', $asset->checksum.$version);
            if ($asset->variants()->where('revision', $revision)->exists()) {
                $asset->forceFill(['processing_state' => 'ready'])->save();

                return $revision;
            }
            $source = $this->storage->temporaryCopy($asset->original_disk, $asset->original_key, $asset->checksum, config('video.max_bytes'));
            $output = $source.'.mp4';
            try {
                $info = $this->processor->runtime($source, $output);
                $disk = config('media.private_disk');
                $key = "videos/$asset->id/$version/{$info['checksum']}.mp4";
                if ($this->storage->putImmutableFile($disk, $key, $output, $info['checksum'], 'video/mp4')) {
                    DB::afterRollBack(fn () => Storage::disk($disk)->delete($key));
                }
                $variant = new MediaVariant;
                $variant->forceFill(['media_asset_id' => $asset->id, 'disk' => $disk, 'state' => 'private', 'profile' => 'video', 'profile_version' => $version, 'format' => 'mp4', 'object_key' => $key, 'checksum' => $info['checksum'], 'bytes' => $info['bytes'], 'width' => $info['width'], 'height' => $info['height'], 'revision' => $revision, 'metadata' => ['duration' => $info['duration'], 'video_codec' => 'h264', 'pixel_format' => 'yuv420p', 'audio' => 'aac-or-none', 'metadata_stripped' => true, 'faststart' => true, 'full_decode_validated' => true]])->save();
                $asset->forceFill(['processing_state' => 'ready'])->save();

                return $revision;
            } finally {
                File::delete([$source, $output]);
            }
        }, 3);
    }
}
