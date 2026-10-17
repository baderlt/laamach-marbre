<?php

namespace App\Filament\Resources;

use App\Models\ProductCategory;

class ProductCategoryResource extends CatalogueResource
{
    protected static ?string $model = ProductCategory::class;

    protected static string $kind = 'category';

    protected static string $manager = CataloguePages\ManageCategories::class;
}
