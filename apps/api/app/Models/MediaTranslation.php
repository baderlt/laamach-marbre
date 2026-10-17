<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MediaTranslation extends Model
{
    protected $fillable = ['locale', 'alt', 'caption', 'credit'];

    protected static function booted(): void
    {
        static::saving(fn (self $translation) => Validator::make($translation->getAttributes(), [
            'locale' => ['required', Rule::in(array_column(config('localization.locales'), 'code'))],
            'alt' => 'nullable|string|max:500', 'caption' => 'nullable|string|max:5000', 'credit' => 'nullable|string|max:500',
        ])->validate());
    }
}
