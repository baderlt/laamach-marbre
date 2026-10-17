<?php

namespace App\Models;

use Illuminate\Validation\ValidationException;

class ProductCategory extends CatalogueRecord
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $category) {
            if ($category->parent_id && ($category->parent_id === $category->id || ! self::whereKey($category->parent_id)->whereNull('parent_id')->exists() || ($category->exists && $category->children()->exists()))) {
                throw ValidationException::withMessages(['parent_id' => 'Only a root category can be the parent. Third levels and cycles are forbidden.']);
            }
            if ($category->exists && ! $category->parent_id && $category->products()->exists()) {
                throw ValidationException::withMessages(['parent_id' => 'A category containing products must remain a child.']);
            }
        });
    }
}
