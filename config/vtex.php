<?php

return [

    "fast_shop"  => [
        "env"        => env( key: "VTEX_ENV", default: "local" ),

        "local"      => [
            "affiliated_id" => "xxxxxxxx",           // TODO fastshop precisa fornecer
            "sales_channel" => "xxxxxxxx",           // TODO fastshop precisa fornecer
            "account_name"  => "xxxxxxxx",           // TODO fastshop precisa fornecer
            "environment"   => "vtexcommercestable", // nao sei ao certo
            "url"           => [
                "prefix"                   => "https://",
                "sufix"                    => ".com.br/api/",

                "category-register"        => "mkp-category-mapper/connector/register",
                "category-marketplace"     => "mkp-category-mapper/categories/marketplace/",
                "order-integration"        => "order-integration/orders",
                "order-integration-status" => "order-integration/orders/status",

                "fulfillment-simulation"   => "checkout/pub/orderForms/simulation",
                "fulfillment-order"        => "fulfillment/pvt/orders",
                "fulfillment-dispatch"     => "fulfillment/pvt/orders/{orderId}/fulfill",
            ],

            "headers"       => [
                "Accept"              => "application/json",
                "Content-Type"        => "application/json",
                "X-VTEX-API-AppKey"   => env( key: "VTEX_FS_API_KEY_HML"   ),
                "X-VTEX-API-AppToken" => env( key: "VTEX_FS_API_TOKEN_HML" )
            ],
        ],

        // "production" => [],
    ],

];
