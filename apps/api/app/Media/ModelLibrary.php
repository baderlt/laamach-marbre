<?php

namespace App\Media;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ModelLibrary
{
    public function __construct(private GlbValidator $validator, private MediaStorage $storage) {}

    public function ingest(string $path, string $source, bool $clientSupplied = false): MediaAsset
    {
        MediaPath::relative($source);
        if (! is_file($path) || filesize($path) > min(8388608, config('viewer.max_bytes'))) {
            throw ValidationException::withMessages(['model_upload' => 'Upload a self-contained GLB up to the configured byte limit.']);
        }
        $bytes = file_get_contents($path);
        $validated = $this->validator->validate($bytes);

        return DB::transaction(function () use ($bytes, $validated, $source, $clientSupplied) {
            $hash = hash('sha256', $bytes);
            if ($existing = MediaAsset::where('checksum', $hash)->first()) {
                if ($existing->type !== 'model') {
                    throw ValidationException::withMessages(['model_upload' => 'Conflicting asset type.']);
                }

                return $existing;
            }
            $id = (string) Str::uuid();
            $disk = config('media.private_disk');
            $key = MediaPath::original($id, $hash);
            if ($this->storage->putImmutable($disk, $key, $bytes, 'model/gltf-binary')) {
                DB::afterRollBack(fn () => Storage::disk($disk)->delete($key));
            }
            $asset = new MediaAsset;
            $asset->forceFill(['id' => $id, 'type' => 'model', 'mime' => 'model/gltf-binary', 'checksum' => $hash, 'bytes' => strlen($bytes), 'original_disk' => $disk, 'original_key' => $key, 'original_filename' => basename($source), 'source_path' => $source, 'client_supplied' => $clientSupplied, 'approval_state' => 'pending', 'processing_state' => 'pending', 'imported_at' => now()])->save();
            $this->variant($asset, $validated);

            return $asset->fresh();
        }, 3);
    }

    public function process(MediaAsset $asset): string
    {
        return DB::transaction(function () use ($asset) {
            $asset = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if ($asset->type !== 'model') {
                throw ValidationException::withMessages(['model_upload' => 'A model asset is required.']);
            }

            return $this->variant($asset, $this->validator->validate($this->storage->originalBytes($asset)));
        }, 3);
    }

    private function variant(MediaAsset $asset, array $validated): string
    {
        $hash = hash('sha256', $validated['bytes']);
        $revision = hash('sha256', $asset->checksum.':glb:'.config('viewer.validator_version').':'.$hash);
        $version = 'glb-v'.config('viewer.validator_version').'-'.substr($revision, 0, 16);
        if (! $asset->variants()->where('revision', $revision)->exists()) {
            $disk = config('media.private_disk');
            $key = "models/$asset->id/$version/$hash.glb";
            if ($this->storage->putImmutable($disk, $key, $validated['bytes'], 'model/gltf-binary')) {
                DB::afterRollBack(fn () => Storage::disk($disk)->delete($key));
            }
            $variant = new MediaVariant;
            $variant->forceFill(['media_asset_id' => $asset->id, 'disk' => $disk, 'state' => 'private', 'profile' => 'model', 'profile_version' => $version, 'format' => 'glb', 'object_key' => $key, 'checksum' => $hash, 'bytes' => strlen($validated['bytes']), 'width' => 0, 'height' => 0, 'revision' => $revision, 'metadata' => $validated['metadata']])->save();
        }
        $asset->forceFill(['processing_state' => 'ready'])->save();

        return $revision;
    }
}
