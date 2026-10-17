<?php

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::get('/media/{key}', [MediaController::class, 'published'])->where('key', '(images/[a-f0-9-]{36}/[a-z0-9-]+/[a-f0-9]{64}\.(webp|avif)|videos/[a-f0-9-]{36}/[a-z0-9-]+/[a-f0-9]{64}\.mp4)')->name('media.published');
Route::middleware('auth')->group(function () {
    Route::get('/admin/media-preview/{asset}', [MediaController::class, 'preview'])->name('media.preview');
    Route::get('/admin/media-original/{asset}', [MediaController::class, 'original'])->name('media.original');
});
