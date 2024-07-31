<?php

return [

    "env"        => env( "VTEX_ENV", "local" ),

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
    ],

    // "production" => [],
];
