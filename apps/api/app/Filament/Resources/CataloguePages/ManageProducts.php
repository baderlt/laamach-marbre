<?php

namespace App\Filament\Resources\CataloguePages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\ManageRecords;

class ManageProducts extends ManageRecords
{
    protected static string $resource = ProductResource::class;
}
