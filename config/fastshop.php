<?php

return [

    'hml' => [
        'url'   => env('FS_API_URL_HML'),
        'key'   => env('FS_API_KEY_HML'),
        'token' => env('FS_API_TOKEN_HML'),
    ],

    'prod' => [
        'url'   => env('FS_API_URL', ''),
        'key'   => env('FS_API_KEY', ''),
        'token' => env('FS_API_TOKEN', ''),
    ]

];
