<?php

return [
    'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),
    'published_disk' => env('MEDIA_PUBLISHED_DISK', 'local_published'),
    'max_bytes' => 20 * 1024 * 1024,
    'max_dimension' => 8192,
    'max_pixels' => 20000000,
    'memory_budget' => 256 * 1024 * 1024,
    'import_max_files' => 5000,
    'manifest_max_bytes' => 1024 * 1024,
    'formats' => ['webp'], // AVIF is opt-in after `laamach:media-capabilities` passes on the worker.
    'quality' => ['webp' => 85, 'avif' => 60],
    'encoder_version' => 'gd-1',
    // Fit inside this longest edge, preserve aspect ratio and never upscale.
    // Roles select a small set; ingest does not generate every profile.
    'profiles' => [
        'thumb' => ['version' => 'v1', 'edge' => 320],
        'card' => ['version' => 'v1', 'edge' => 800],
        'gallery' => ['version' => 'v1', 'edge' => 1600],
        'banner' => ['version' => 'v1', 'edge' => 2560],
        'social' => ['version' => 'v1', 'edge' => 1200],
    ],
    'roles' => [
        'brand_hero' => ['target' => 'brand', 'profiles' => ['thumb', 'banner']],
        'brand_banner' => ['target' => 'brand', 'profiles' => ['thumb', 'banner']],
        'brand_general' => ['target' => 'brand', 'profiles' => ['thumb', 'gallery']],
        'category_cover' => ['target' => 'category', 'profiles' => ['thumb', 'card']],
        'product_cover' => ['target' => 'product', 'profiles' => ['thumb', 'card']],
        'product_gallery' => ['target' => 'product', 'profiles' => ['thumb', 'gallery']],
        'product_size' => ['target' => 'product', 'profiles' => ['thumb', 'card']],
        'project_cover' => ['target' => 'project', 'profiles' => ['thumb', 'card']],
        'project_gallery' => ['target' => 'project', 'profiles' => ['thumb', 'gallery']],
        'project_video' => ['target' => 'project', 'profiles' => []],
        'social_preview' => ['target' => 'brand', 'profiles' => ['social']],
    ],
];
