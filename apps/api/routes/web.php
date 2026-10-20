<?php

use App\Http\Controllers\AdminLanguageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ViewerController;
use App\Http\Middleware\AdminLocale;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::post('/admin/interface-language', AdminLanguageController::class)->middleware(['auth', AdminLocale::class, 'throttle:10,1'])->name('admin.language');
Route::get('/media/{key}', [MediaController::class, 'published'])->where('key', '(images/[a-f0-9-]{36}/[a-z0-9-]+/[a-f0-9]{64}\.(webp|avif)|models/[a-f0-9-]{36}/[a-z0-9-]+/[a-f0-9]{64}\.glb|videos/[a-f0-9-]{36}/[a-z0-9-]+/[a-f0-9]{64}\.mp4)')->name('media.published');
Route::middleware('auth')->group(function () {
    Route::get('/admin/product-viewer/{product}', [ViewerController::class, 'preview'])->name('viewer.preview');
    Route::get('/admin/viewer-model/{variant}', [ViewerController::class, 'model'])->name('viewer.model');
    Route::get('/admin/media-preview/{asset}', [MediaController::class, 'preview'])->name('media.preview');
    Route::get('/admin/media-original/{asset}', [MediaController::class, 'original'])->name('media.original');
});
