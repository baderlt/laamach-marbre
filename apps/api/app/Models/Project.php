<?php

namespace App\Models;

use Illuminate\Validation\ValidationException;

class Project extends CatalogueRecord
{
    protected function casts(): array
    {
        return ['featured' => 'boolean', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $project) {
            if ($project->exists && ($project->isDirty('reference') || ($project->getOriginal('published_at') && $project->isDirty('slug')))) {
                throw ValidationException::withMessages(['slug' => 'References and previously published slugs are immutable.']);
            }
        });
    }

    public function categories()
    {
        return $this->belongsToMany(ProjectCategory::class, 'project_category_project')->orderBy('sort_order')->orderBy('project_categories.id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'project_products')->withPivot('sort_order')->orderByPivot('sort_order')->orderBy('products.id');
    }

    public function gallery()
    {
        return $this->hasMany(ProjectGalleryItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
