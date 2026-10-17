<?php

use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class)->name('api.v1.health');
    Route::get('product-categories', [CatalogueController::class, 'categories']);
    Route::get('products', [CatalogueController::class, 'index']);
    Route::get('products/{slug}', [CatalogueController::class, 'show']);
});
