<?php

namespace App\Media;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaLibrary
{
    public function __construct(private ImageProcessor $images, private MediaStorage $storage) {}

    public function ingest(string $path, string $source, bool $clientSupplied = false): MediaAsset
    {
        MediaPath::relative($source);
        $info = $this->images->inspect($path);

        return DB::transaction(function () use ($path, $source, $clientSupplied, $info) {
            $existing = MediaAsset::where('checksum', $info['checksum'])->first();
            if ($existing) {
                return $existing;
            }
            $id = (string) Str::uuid();
            $key = MediaPath::original($id, $info['checksum']);
            $bytes = file_get_contents($path);
            if (hash('sha256', $bytes) !== $info['checksum']) {
                throw ValidationException::withMessages(['media' => 'Source changed during ingestion.']);
            }
            $disk = config('media.private_disk');
            if ($this->storage->putImmutable($disk, $key, $bytes, $info['mime'])) {
                DB::afterRollBack(fn () => Storage::disk($disk)->delete($key));
            }
            $asset = new MediaAsset;
            $asset->forceFill(['id' => $id, ...$info, 'original_disk' => $disk, 'original_key' => $key, 'original_filename' => basename($source), 'source_path' => $source, 'client_supplied' => $clientSupplied, 'approval_state' => 'pending', 'processing_state' => 'pending', 'imported_at' => now()])->save();

            return $asset;
        }, 3);
    }

    public function process(MediaAsset $asset, array $profiles = ['thumb', 'card']): string
    {
        $profiles = array_values(array_unique($profiles));
        sort($profiles);
        if (! $profiles || array_diff($profiles, array_keys(config('media.profiles')))) {
            throw ValidationException::withMessages(['profiles' => 'Choose configured image profiles.']);
        }
        try {
            return DB::transaction(function () use ($asset, $profiles) {
                $asset = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
                $revision = hash('sha256', json_encode([$asset->checksum, $profiles, config('media.profiles'), config('media.formats'), config('media.quality'), config('media.encoder_version'), GD_VERSION, phpversion()], JSON_THROW_ON_ERROR));
                if ($asset->variants()->where('revision', $revision)->exists()) {
                    $asset->forceFill(['processing_state' => 'ready'])->save();

                    return $revision;
                }
                foreach ($this->images->derivatives($this->storage->originalBytes($asset), $profiles) as $data) {
                    $data['profile_version'] .= '-'.substr($revision, 0, 16);
                    $key = "images/$asset->id/{$data['profile_version']}/{$data['checksum']}.{$data['format']}";
                    $disk = config('media.private_disk');
                    if ($this->storage->putImmutable($disk, $key, $data['content'], 'image/'.$data['format'])) {
                        DB::afterRollBack(fn () => Storage::disk($disk)->delete($key));
                    }
                    unset($data['content']);
                    $variant = new MediaVariant;
                    $variant->forceFill([...$data, 'media_asset_id' => $asset->id, 'disk' => $disk, 'state' => 'private', 'object_key' => $key, 'revision' => $revision])->save();
                }
                $asset->forceFill(['processing_state' => 'ready'])->save();

                return $revision;
            }, 3);
        } catch (\Throwable $exception) {
            MediaAsset::whereKey($asset->id)->update(['processing_state' => 'failed']);
            throw $exception;
        }
    }

    public function approve(MediaAsset $asset, User $user): void
    {
        Gate::forUser($user)->authorize('approve', $asset);
        DB::transaction(function () use ($asset, $user) {
            $asset = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if (! $asset->client_supplied) {
                throw ValidationException::withMessages(['media' => 'Confirm client-supplied provenance before usage approval.']);
            }
            $asset->forceFill(['approval_state' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function publish(MediaAsset $asset, string $revision, User $user): void
    {
        Gate::forUser($user)->authorize('publish', $asset);
        DB::transaction(function () use ($asset, $revision) {
            $asset = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $variants = $asset->variants()->where('revision', $revision)->get();
            if ($asset->approval_state !== 'approved' || ! $asset->client_supplied || $asset->processing_state !== 'ready' || $variants->isEmpty()) {
                throw ValidationException::withMessages(['media' => 'Publication requires approved client provenance and a ready image revision.']);
            }
            foreach ($variants as $variant) {
                if ($variant->state === 'published') {
                    continue;
                }
                $bytes = $this->storage->variantBytes($variant);
                $disk = config('media.published_disk');
                $this->storage->putImmutable($disk, $variant->object_key, $bytes, 'image/'.$variant->format);
                $variant->forceFill(['disk' => $disk, 'state' => 'published', 'publication_revision' => $revision, 'published_at' => now()])->save();
                // Private working copies are intentionally retained for retries/recovery.
            }
            $asset->forceFill(['active_revision' => $revision])->save();
        });
    }

    public function unpublish(MediaAsset $asset, User $user): void
    {
        Gate::forUser($user)->authorize('publish', $asset);
        DB::transaction(function () use ($asset) {
            $asset = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $asset->forceFill(['active_revision' => null])->save();
        });
    }

    public function delete(MediaAsset $asset, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $asset);
        DB::transaction(fn () => MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail()->delete());
    }
}
