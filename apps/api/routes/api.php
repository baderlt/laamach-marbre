<?php

use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class)->name('api.v1.health');
    Route::get('product-categories', [CatalogueController::class, 'categories']);
    Route::get('products', [CatalogueController::class, 'index']);
    Route::get('products/{slug}', [CatalogueController::class, 'show']);
    Route::get('project-categories', [ProjectController::class, 'categories']);
    Route::get('projects', [ProjectController::class, 'index']);
    Route::get('projects/{slug}', [ProjectController::class, 'show']);
});
