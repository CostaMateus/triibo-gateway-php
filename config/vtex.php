<?php

return [

    "fast_shop"  => [
        "env"        => env( key: "VTEX_ENV", default: "local" ),

        "api"        => [
            "category-register"        => "mkp-category-mapper/connector/register",
            "category-marketplace"     => "mkp-category-mapper/categories/marketplace/{id}",
            "order-integration"        => "order-integration/orders",
            "order-integration-status" => "order-integration/orders/status",

            "fulfillment-simulation"   => "checkout/pub/orderForms/simulation",
            "fulfillment-order"        => "fulfillment/pvt/orders",
            "fulfillment-dispatch"     => "fulfillment/pvt/orders/{orderId}/fulfill",

            "all-skus-list"            => "catalog_system/pvt/sku/stockkeepingunitidsbysaleschannel",
            "get-detail-sku"           => "catalog/pvt/stockkeepingunit/{skuId}",
            "get-detail-product"       => "catalog_system/pvt/products/productget/{productId}",
        ],

        "local"      => [
            "sales_channel" => 8,
            "affiliated_id" => "TRB",
            "account_name"  => "fastshopbrqa2",
            "raw_url"       => "https://{account_name}.vtexcommercestable.com.br/api/",
            "headers"       => [
                "Accept"              => "application/json",
                "Content-Type"        => "application/json",
                "X-VTEX-API-AppKey"   => env( key: "VTEX_FS_API_KEY_HML"   ),
                "X-VTEX-API-AppToken" => env( key: "VTEX_FS_API_TOKEN_HML" )
            ],
        ],

        "production" => [
            "sales_channel" => "",
            "affiliated_id" => "",
            "account_name"  => "",
            "raw_url"       => "https://{account_name}.vtexcommercestable.com.br/api/",
            "headers"       => [
                "Accept"              => "application/json",
                "Content-Type"        => "application/json",
                "X-VTEX-API-AppKey"   => env( key: "VTEX_FS_API_KEY_PRD"   ),
                "X-VTEX-API-AppToken" => env( key: "VTEX_FS_API_TOKEN_PRD" )
            ],
        ],
    ],

];
