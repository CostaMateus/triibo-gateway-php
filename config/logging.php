<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            "permission" => 0777,
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'permission' => 0664,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'permission' => 0664,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
            'permission' => 0664,
        ],

        // programa de afiliados
        "affiliate"  => [
            "permission" => 0777,
            "driver" => "single",
            "path"   => storage_path( "logs/affiliate.log" ),
            "level"  => "debug"
        ],
        "awin"       => [
            "permission" => 0777,
            "driver" => "single",
            "path"   => storage_path( "logs/awin.log" ),
            "level"  => "debug"
        ],
        "cofry"      => [
            "permission" => 0777,
            "driver" => "single",
            "path"   => storage_path( "logs/cofry.log" ),
            "level"  => "debug"
        ],
        "socialsoul" => [
            "permission" => 0777,
            "driver" => "single",
            "path"   => storage_path( "logs/socialsoul.log" ),
            "level"  => "debug"
        ],

        // marketplace
        "marketplace"  => [
            "permission" => 0777,
            "driver" => "single",
            "path"   => storage_path( "logs/marketplace.log" ),
            "level"  => "debug"
        ],

        // triibo
        "triibo"       => [
            "permission" => 0777,
            "driver" => "single",
            "path"   => storage_path( "logs/triibo.log" ),
            "level"  => "debug"
        ],

        // fastshop
        "fastshop"     => [
            "permission" => 0777,
            "driver"     => "single",
            "path"       => storage_path( "logs/fastshop.log" ),
            "level"      => "debug"
        ],

        // vtex
        "vtex"         => [
            "permission" => 0777,
            "driver"     => "single",
            "path"       => storage_path( "logs/vtex.log" ),
            "level"      => "debug"
        ],
    ],

];
