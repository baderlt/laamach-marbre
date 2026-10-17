<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaImport extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['mapping' => 'array'];
    }
}
