<?php

namespace App\Filament\Resources;

use App\Models\Finish;

class FinishResource extends CatalogueResource
{
    protected static ?string $model = Finish::class;

    protected static string $kind = 'finish';

    protected static string $manager = CataloguePages\ManageFinishes::class;
}
