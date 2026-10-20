<?php

use App\Http\Middleware\AdminLocale;
use App\Models\MediaAsset;

return [
    'temporary_file_upload' => [
        'rules' => ['required', 'file', 'max:51200'],
        'middleware' => ['web', 'auth', 'can:uploadImage,'.MediaAsset::class, AdminLocale::class, 'throttle:10,1'],
        'max_upload_time' => 5,
    ],
];
