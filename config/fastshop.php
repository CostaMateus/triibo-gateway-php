<?php

return [

    'env' => env('MKT_ENV', 'local'),

    'local' => [

        'fs' => [
            'url'     => env('FS_API_URL_HML',   ''),
            'key'     => env('FS_API_KEY_HML',   ''),
            'token'   => env('FS_API_TOKEN_HML', ''),
            'headers' => [
                'Accept'              => 'application/json',
                'Content-Type'        => 'application/json',
                'X-VTEX-API-AppKey'   => env('FS_API_KEY_HML'  , ''),
                'X-VTEX-API-AppToken' => env('FS_API_TOKEN_HML', '')
            ],
        ],

        'wc' => [
            'key'     => env('WC_API_KEY_HML',   ''),
            'token'   => env('WC_API_TOKEN_HML', ''),
            'storeId' => "3158",
        ],
    ],

    'production' => [

        'fs' => [
            'url'     => env('FS_API_URL',   ''),
            'key'     => env('FS_API_KEY',   ''),
            'token'   => env('FS_API_TOKEN', ''),
            'headers' => [
                'Accept'              => 'application/json',
                'Content-Type'        => 'application/json',
                'X-VTEX-API-AppKey'   => env('FS_API_KEY',   ''),
                'X-VTEX-API-AppToken' => env('FS_API_TOKEN', '')
            ],
        ],

        'wc' => [
            'key'     => env('WC_API_KEY',   ''),
            'token'   => env('WC_API_TOKEN', ''),
            'storeId' => "???",
        ],
    ],

];
