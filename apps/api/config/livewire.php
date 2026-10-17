<?php

return [
    'temporary_file_upload' => [
        'rules' => ['required', 'file', 'max:51200'],
        'middleware' => ['web', 'auth', 'throttle:10,1'],
        'max_upload_time' => 5,
    ],
];
