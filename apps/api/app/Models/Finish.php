<?php

namespace App\Models;

class Finish extends CatalogueRecord
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_finishes');
    }
}
