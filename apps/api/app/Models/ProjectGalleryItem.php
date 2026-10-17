<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectGalleryItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['captions' => 'array'];
    }

    public function asset()
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function poster()
    {
        return $this->belongsTo(MediaAsset::class, 'poster_media_id');
    }
}
