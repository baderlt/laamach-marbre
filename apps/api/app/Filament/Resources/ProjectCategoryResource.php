<?php

namespace App\Filament\Resources;

use App\Models\ProjectCategory;

class ProjectCategoryResource extends ProjectResource
{
    protected static ?string $model = ProjectCategory::class;

    protected static bool $category = true;

    public static function getPages(): array
    {
        return ['index' => CataloguePages\ManageProjectCategories::route('/')];
    }
}
