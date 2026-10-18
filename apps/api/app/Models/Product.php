<?php

namespace App\Models;

use Illuminate\Validation\ValidationException;

class Product extends CatalogueRecord
{
    protected function casts(): array
    {
        return ['viewer_3d_enabled' => 'boolean', 'viewer_3d_config' => 'array', 'featured' => 'boolean', 'custom_size_allowed' => 'boolean', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $product) {
            if (($product->category_id || $product->status === 'published') && ! ProductCategory::whereKey($product->category_id)->whereNotNull('parent_id')->exists()) {
                throw ValidationException::withMessages(['category_id' => 'Products require a child category.']);
            }
            if ($product->exists && ($product->isDirty('reference') || ($product->getOriginal('published_at') && $product->isDirty('slug')))) {
                throw ValidationException::withMessages(['slug' => 'Reference and previously published slugs are immutable.']);
            }
        });
    }

    public function viewerReferences()
    {
        return $this->hasMany(MediaReference::class, 'owner_key')->where('owner_type', 'product_viewers');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function sizes()
    {
        return $this->hasMany(ProductSize::class)->orderBy('sort_order')->orderBy('id');
    }

    public function units()
    {
        return $this->hasMany(ProductUnit::class)->orderBy('unit');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_products')->withPivot('sort_order');
    }

    public function finishes()
    {
        return $this->belongsToMany(Finish::class, 'product_finishes')->withPivot(['active', 'is_default', 'sort_order'])->orderByPivot('sort_order')->orderBy('finishes.id');
    }
}
