<?php

namespace App\Models;

use App\Media\MediaPath;
use App\Media\MediaStorage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MediaVariant extends Model
{
    use HasUuids;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $variant) {
            MediaPath::assertVariant($variant->object_key, $variant->media_asset_id, $variant->profile_version, $variant->checksum, $variant->format);
            $disks = match ($variant->state) {
                'private' => ['local', 'r2_private'],
                'published' => ['local_published', 'r2_published'],
                default => [],
            };
            if (! in_array($variant->disk, $disks, true) || ($variant->exists && $variant->getOriginal('state') === 'published' && $variant->isDirty())) {
                throw ValidationException::withMessages(['media' => 'Invalid disk or mutation of immutable published variant.']);
            }
        });
        static::deleting(function (self $variant) {
            if ($variant->state === 'published' || $variant->references()->exists()) {
                throw ValidationException::withMessages(['media' => 'Referenced and published variants are protected.']);
            }
        });
        static::deleted(fn (self $variant) => DB::afterCommit(fn () => app(MediaStorage::class)->deletePrivateVariant($variant)));
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function references(): HasMany
    {
        return $this->hasMany(MediaReference::class);
    }
}
