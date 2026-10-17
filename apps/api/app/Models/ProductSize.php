<?php

namespace App\Models;

class ProductSize extends CatalogueRecord
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
