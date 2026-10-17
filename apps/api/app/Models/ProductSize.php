<?php

namespace App\Models;

class ProductSize extends CatalogueRecord
{
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
