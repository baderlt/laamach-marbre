<?php

return [
    'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),
    'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
    'version' => 'v1',
    'max_bytes' => 50 * 1024 * 1024,
    'max_duration' => 60,
    'max_dimension' => 1920,
    'max_pixels' => 2073600,
    'max_fps' => 30,
    'timeout' => 45,
    'mime_types' => ['video/mp4'],
];
