<?php

return [

    "partners"    => [
        "fast_shop" => [
            "env"            => env( key: "FS_VTEX_ENV", default: "local" ),

            "connector_name" => "Marketplace Triibo", // TODO fastshop precisa confirmar

            "local"          => [
                "sales_channel" => 8,
                "affiliated_id" => "TRB",
                "account_name"  => "fastshopbrqa2",
                "raw_url"       => "https://{account_name}.vtexcommercestable.com.br/api/",
                "headers"       => [
                    "Accept"              => "application/json",
                    "Content-Type"        => "application/json",
                    "X-VTEX-API-AppKey"   => env( key: "FS_VTEX_HML_KEY"   ),
                    "X-VTEX-API-AppToken" => env( key: "FS_VTEX_HML_TOKEN" )
                ],
            ],

            "production"     => [
                "sales_channel" => "",
                "affiliated_id" => "",
                "account_name"  => "",
                "raw_url"       => "https://{account_name}.vtexcommercestable.com.br/api/",
                "headers"       => [
                    "Accept"              => "application/json",
                    "Content-Type"        => "application/json",
                    "X-VTEX-API-AppKey"   => env( key: "FS_VTEX_PRD_KEY"   ),
                    "X-VTEX-API-AppToken" => env( key: "FS_VTEX_PRD_TOKEN" )
                ],
            ],
        ],
    ],

    "api"         => [
        "manager-connector"        => "channel-manager/connector",

        "category-register"        => "mkp-category-mapper/connector/register",
        "category-marketplace"     => "mkp-category-mapper/categories/marketplace/{id}",
        "order-integration"        => "order-integration/orders",
        "order-integration-status" => "order-integration/orders/status",

        // preço/estoque/frete | é pra sempre ter preço e estoque | só tem frete se mandar o CEP no body
        // https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-orders#post-/api/checkout/pub/orderForms/simulation
        "fulfillment-simulation"   => "checkout/pub/orderForms/simulation",

        "fulfillment-order"        => "fulfillment/pvt/orders",
        "fulfillment-dispatch"     => "fulfillment/pvt/orders/{orderId}/fulfill",

        "all-skus-list"            => "catalog_system/pvt/sku/stockkeepingunitidsbysaleschannel",

        // Contexto de um SKU
        // https://developers.vtex.com/docs/api-reference/catalog-api#get-/api/catalog_system/pvt/sku/stockkeepingunitbyid/-skuId-
        "get-sku-context"          => "catalog_system/pvt/sku/stockkeepingunitbyid/{skuId}",

        // Dados de um SKU
        // https://developers.vtex.com/docs/api-reference/catalog-api#get-/api/catalog/pvt/stockkeepingunit/-skuId-
        "get-sku-data"             => "catalog/pvt/stockkeepingunit/{skuId}",

        // Recupera um produto pelo ID
        // https://developers.vtex.com/docs/api-reference/catalog-api#get-/api/catalog/pvt/product/-productId-
        "get-detail-product"       => "catalog/pvt/product/{productId}",
    ],

    "order_codes" => [
        "response"      => [
            "SOI001" => "The order was integrated into VTEX successfully",
            "SOI002" => "The order was approved in VTEX successfully",
            "SOI003" => "The order was successfully enqueued in our service for processing",
            "EOI000" => "An unknown error occurred when processing the order, contact VTEX for support",
            "EOI001" => "An unmapped error occurred when processing the order, more details in the response error list",
            "EOI002" => "There are no SLAs or items available to fulfill this order.", // That is: the item(s) can be without stock, inactive in the catalog or not included in connector’s trade policy; the SLA/pickup point chosen in the order is not available or doesn’t have stock for the order’s items; or there aren’t any SLAs available at all to fulfill the order",
            "EOI003" => "The order total payment value differs from what is expected by the seller, and they do not have Order Authorization rules set up to deal with price divergence",
            "EOI004" => "This order's cart is invalid.", // That can mean that either the item(s) in it don’t exist in VTEX catalog or the order's data doesn’t comply with the store configuration (for example, the minimum total value or quantity of items set by the seller was not reached in the order)",
            "EOI005" => "A validation error occurred while trying to process the order.", // That is, some field/value sent in the order’s contract is invalid and not filled according to our’s or Checkout/Fulfillment’s documentation",
            "EOI006" => "An unexpected error occurred in one of VTEX's internal services. Please try to send the request again later, as it’s possible one of our services is going through instabilities",
            "EOI007" => "The order specified already exists in VTEX and we are not able to create duplicated orders",
            "EOI008" => "The order cannot be approved because it’s not integrated in VTEX yet",
            "EOI009" => "This order is out of the valid status to approve, such as canceled, for example",
            "EOI010" => "The request to our API timed out, meaning we exceeded the response time limit",
            "EOI011" => "The order was integrated with a price divergence, and we cannot approve it yet because we are waiting for the seller's manual authorization before proceeding",
        ],
        "notifications" => [
            "SOI001" => "Order integrated",
            "SOI002" => "Order approved",
            "EOI000" => "Unknown error",
            "EOI001" => "Unmapped error",
            "EOI002" => "SLA not available",
            "EOI003" => "Price divergence",
            "EOI004" => "Invalid cart",
            "EOI005" => "Validation error",
            "EOI006" => "VTEX unexpected error",
            "EOI007" => "Duplicated order",
            "EOI008" => "Cannot approve an order that is not integrated yet",
            "EOI009" => "Order out of valid status to approve",
        ],
    ],

];
