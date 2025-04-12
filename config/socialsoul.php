<?php

declare(strict_types = 1);

return [

    "env" => env( "SOCIAL_SOUL_ENV", "local" ),

    "local" => [
        "url" => "http://sandbox-api.lomadee.com",
        "token" => "1667591011850def5f2f6",
        "source_id" => "37326586",
        "publisher_id" => "23052104",
        "login" => [
            "user" => "paulo.pinho@triibo.com.br",
            "pass" => "Triibo@2022!05",
        ],
    ],

    "production" => [
        "url" => "http://api.lomadee.com",
        "token" => "1667591011850def5f2f6",
        "source_id" => "37326586",
        "publisher_id" => "23052104",
        "login" => [
            "user" => "paulo.pinho@triibo.com.br",
            "pass" => "Triibo@2022!05",
        ],
    ],
];
