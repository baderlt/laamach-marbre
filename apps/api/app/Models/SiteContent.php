<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteContent extends Model
{
    public const KEYS = ['site', 'home', 'products', 'projects', 'services', 'about', 'contact'];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'translations' => 'array', 'data' => 'array'];
    }

    public function mediaReferences()
    {
        return $this->hasMany(MediaReference::class, 'owner_key', 'key')->where('owner_type', 'site_contents');
    }
}
