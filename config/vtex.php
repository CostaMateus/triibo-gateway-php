<?php

return [

    "env"        => env( "VTEX_ENV", "local" ),

    "local"      => [
        "account_name" => "xxxxxxxx",
        "environment"  => "xxxxxxxx",

        "url"          => [
            "prefix"              => "https://",
            "sufix"               => ".com.br/api/",

            "category-mapper"     => "mkp-category-mapper",
            "order-integration"   => "order-integration/orders",
            "checkout-simulation" => "checkout/pub/orderForms/simulation",
            "fulfillment"         => "fulfillment/pvt/orders",
        ],

        /**
         * https://portal       .{environment}.com.br/api/mkp-category-mapper/connector/register
         * https://portal       .{environment}.com.br/api/mkp-category-mapper/categories/marketplace/{id}
         * https://{accountName}.{environment}.com.br/api/order-integration/orders
         * https://{accountName}.{environment}.com.br/api/order-integration/orders/status
         * https://{accountName}.{environment}.com.br/api/checkout/pub/orderForms/simulation
         * https://{accountName}.{environment}.com.br/api/fulfillment/pvt/orders
         * https://{accountName}.{environment}.com.br/api/fulfillment/pvt/orders/{orderId}/fulfill
         */
    ],

    // "production" => [
    //     "account_name" => "FastShop",
    // ],
];
