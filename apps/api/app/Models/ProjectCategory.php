<?php

namespace App\Models;

class ProjectCategory extends CatalogueRecord
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_category_project');
    }
}
