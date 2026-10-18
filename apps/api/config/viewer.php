<?php

return [
    'messages' => json_decode(file_get_contents(dirname(__DIR__, 3).'/config/viewer-messages.json'), true, 32, JSON_THROW_ON_ERROR),
    'validator_version' => 1,
    'max_bytes' => 8 * 1024 * 1024,
    'max_json_bytes' => 1024 * 1024,
    'max_vertices' => 100000,
    'max_triangles' => 100000,
    'max_primitives' => 64,
    'max_texture_count' => 8,
    'max_texture_dimension' => 2048,
    'max_texture_pixels' => 8 * 1024 * 1024,
    'max_accessors' => 256,
    'max_nodes' => 128,
    'max_decoded_components' => 1500000,
    'max_seconds' => 8,
    'memory_budget' => 128 * 1024 * 1024,
    'extensions' => ['KHR_materials_clearcoat', 'KHR_texture_transform'],
];
