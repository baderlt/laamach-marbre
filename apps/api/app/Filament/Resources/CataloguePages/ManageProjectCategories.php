<?php

namespace App\Filament\Resources\CataloguePages;

use App\Filament\Resources\ProjectCategoryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageProjectCategories extends ManageRecords
{
    protected static string $resource = ProjectCategoryResource::class;
}
