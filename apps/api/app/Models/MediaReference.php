<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MediaReference extends Model
{
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::saving(function (self $reference) {
            if ($reference->media_variant_id && ! MediaVariant::whereKey($reference->media_variant_id)->where('media_asset_id', $reference->media_asset_id)->exists()) {
                throw ValidationException::withMessages(['media' => 'Variant reference belongs to another asset.']);
            }
        });
    }
}
