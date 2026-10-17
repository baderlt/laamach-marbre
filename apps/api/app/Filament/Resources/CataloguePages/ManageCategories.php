<?php

namespace App\Filament\Resources\CataloguePages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCategories extends ManageRecords
{
    protected static string $resource = ProductCategoryResource::class;
}
