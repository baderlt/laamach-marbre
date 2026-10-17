<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

abstract class CatalogueRecord extends Model
{
    use HasUuids;

    protected $guarded = ['*'];

    public function cover()
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function mediaReferences()
    {
        return $this->hasMany(MediaReference::class, 'owner_key')->where('owner_type', $this->getTable());
    }

    public function translations()
    {
        $foreign = match ($this->getTable()) {
            'product_categories' => 'product_category_id', 'products' => 'product_id', 'product_sizes' => 'product_size_id', 'finishes' => 'finish_id',
        };
        $related = (new CatalogueTranslation)->setTable(match ($this->getTable()) {
            'product_categories' => 'product_category_translations', 'products' => 'product_translations', 'product_sizes' => 'product_size_translations', 'finishes' => 'finish_translations',
        });

        return $this->newHasMany($related->newQuery(), $this, $related->qualifyColumn($foreign), $this->getKeyName());
    }
}
