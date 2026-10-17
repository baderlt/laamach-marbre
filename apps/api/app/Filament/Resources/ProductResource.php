<?php

namespace App\Filament\Resources;

use App\Models\Product;

class ProductResource extends CatalogueResource
{
    protected static ?string $model = Product::class;

    protected static string $kind = 'product';

    protected static string $manager = CataloguePages\ManageProducts::class;
}
