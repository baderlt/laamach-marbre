<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogueTranslation extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['technical_information' => 'array'];
    }
}
