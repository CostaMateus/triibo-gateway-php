<?php

return [

    "env"        => env( key: "VTEX_ENV", default: "local" ),

    "local"      => [
        "affiliated_id" => "xxxxxxxx",           // fastshop precisa fornecer
        "account_name"  => "xxxxxxxx",           // fastshop precisa fornecer
        "environment"   => "vtexcommercestable", // nao sei ao certo
        "url"           => [
            "prefix"              => "https://",
            "sufix"               => ".com.br/api/",

            "category-mapper"     => "mkp-category-mapper",
            "order-integration"   => "order-integration/orders",
            "checkout-simulation" => "checkout/pub/orderForms/simulation",
            "fulfillment"         => "fulfillment/pvt/orders",
        ],

        "headers"       => [
            "Accept"              => "application/json",
            "Content-Type"        => "application/json",
            "X-VTEX-API-AppKey"   => env( key: "VTEX_FS_API_KEY_HML"   ),
            "X-VTEX-API-AppToken" => env( key: "VTEX_FS_API_TOKEN_HML" )
        ],
    ],

    // "production" => [],
];
