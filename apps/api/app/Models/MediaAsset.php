<?php

namespace App\Models;

use App\Media\MediaPath;
use App\Media\MediaStorage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MediaAsset extends Model
{
    use HasUuids;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['client_supplied' => 'boolean', 'imported_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $asset) {
            if ($asset->exists && $asset->isDirty(['original_disk', 'original_key', 'checksum', 'mime', 'bytes', 'width', 'height', 'type', 'source_path', 'original_filename', 'imported_at'])) {
                throw ValidationException::withMessages(['media' => 'Original identity is immutable; ingest replacement content as a new asset.']);
            }
            MediaPath::assertOriginal($asset->original_key, $asset->id, $asset->checksum);
            if (! in_array($asset->original_disk, ['local', 'r2_private'], true) || ! in_array($asset->approval_state, ['pending', 'approved', 'rejected'], true) || ! in_array($asset->processing_state, ['pending', 'ready', 'failed'], true)) {
                throw ValidationException::withMessages(['media' => 'Invalid private asset state.']);
            }
        });
        static::deleting(function (self $asset) {
            if ($asset->active_revision || $asset->references()->exists() || $asset->imports()->exists() || $asset->variants()->where('state', 'published')->exists()) {
                throw ValidationException::withMessages(['media' => 'Asset is referenced, imported or has retained published variants.']);
            }
            $asset->variants()->get()->each->delete();
        });
        static::deleted(fn (self $asset) => DB::afterCommit(fn () => app(MediaStorage::class)->deletePrivateOriginal($asset)));
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(MediaTranslation::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(MediaImport::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(MediaReference::class);
    }
}
