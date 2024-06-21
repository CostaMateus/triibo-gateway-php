<?php

namespace App\Http\Integrations\FastshopIntegration;

use App\Helpers\Helper;
use Illuminate\Support\Str;

class DataTranslator
{
    // Define the default country for the query
    private const DEFAULT_COUNTRY = "BRA";

    // Define the default seller for the query
    private const DEFAULT_SELLER  = "FSH";

    /**
     * Translates the provided data based on the specified type.
     * This method uses the PHP 8 match expression to determine the appropriate translation method to call.
     * Each case in the match expression corresponds to a different type of operation that requires translation.
     * If the type is not recognized, an InvalidArgumentException is thrown.
     *
     * @param   string  $type               The type of translation to perform.
     * @param   array   $data               The data to be translated.
     * @throws  InvalidArgumentException    If the provided type is unknown.
     * @return  array|string                The result of the translation, which can be an array or a string.
     */
    public static function translate( string $type, array $data )
    {
        return match ( $type )
        {
            "updateStockPrice"    => self::updateStockPrice( $data ),     // Handles the translation for updating stock price.
            "queryStockPrice"     => self::queryStockPrice( $data[ 0 ] ), // Handles the translation for querying stock price.
            "newProduct"          => self::newProductFsToWc( $data ),     // Handles the translation for adding a new product.
            "freightCalcFs"       => self::freightCalcFs( $data ),        // Handles the translation for freight calculation from Fast Shop.
            "freightCalcWc"       => self::freightCalcWc( $data ),        // Handles the translation for freight calculation for WooCommerce.
            "freightCalcNewOrder" => self::freightCalcNewOrder( $data ),  // Handles the translation for freight calculation for a new order.
            "breakingUpFreight"   => self::breakingUpFreight( $data ),    // Handles the translation for breaking up freight into multiple shipments.
            "newOrderFs"          => self::newOrderFs( $data ),           // Handles the translation for creating a new order in Fast Shop.
            "newOrderFsError"     => self::newOrderFsError( $data ),      // Handles the translation for errors when creating a new order in Fast Shop.
            "orderStatusChange"   => self::orderStatusChange( $data ),    // Handles the translation for an order status change.
            "orderError"          => self::orderError( $data ),           // Handles the translation for an order error.
            "invoice"             => self::invoice( $data ),              // Handles the translation for generating an invoice.
            "tracking"            => self::tracking( $data ),             // Handles the translation for tracking information.
            // "freightCalcFailed" => self::freightCalcFailed( $data ),      // This case is commented out and not currently used.
            default               => throw new \InvalidArgumentException("Unknown type: {$type}"), // Throws an exception for unknown types.
        };
    }

    /**
     * This function takes data from a Fast Shop product update and transforms it into the format
     * required by WooCommerce. It handles logistics information to determine stock status and
     * calculates prices, ensuring that the product data is ready for WooCommerce operations.
     *
     * @param   array   $data   The product data from Fast Shop.
     * @return  array           The transformed product data for WooCommerce.
     */
    private static function updateStockPrice( array $data )
    {
        // Attempt to get the product data, defaulting to null if not available.
        $product       = reset( $data ) ?: null;

        // Get the last element of the data array which contains the update information.
        $data          = end( $data );

        // Set default values for the WooCommerce data.
        $shippingClass = "fast-shop";
        $quantity      = 0;
        $stockStatus   = "outofstock";
        $hubValue      = "-";

        // Check if logistics information is available to update stock status and shipping class.
        if ( !empty( $data[ "logisticsInfo" ] ) )
        {
            $logisticsInfo = reset( $data[ "logisticsInfo" ] );

            // If shipping information is available, extract the hub ID and calculate the shipping class.
            if ( !empty( $logisticsInfo[ "slas" ] ) )
            {
                $slas          = reset( $logisticsInfo[ "slas" ] );
                $hubValue      = $slas[ "id" ];
                $hubParts      = explode( "_", $hubValue );
                $shippingClass = "fast-shop-" . end( $hubParts );

                // Update the quantity and stock status based on the available balance.
                $quantity      = $logisticsInfo[ "stockBalance" ];
                $stockStatus   = ( $quantity > 0 ) ? "instock" : "outofstock";
            }
        }
        elseif ( $product )
        {
            // If the product exists, retrieve the hub value and shipping class from its metadata.
            $metaDataIndex = array_search( "_fastshop_hub", array_column( $product->meta_data, "key" ) );
            $hubValue      = ( $metaDataIndex !== false ) ? $product->meta_data[ $metaDataIndex ]->value : "-";
            $shippingClass = $product->shipping_class;
        }

        // Prepare the WooCommerce data array with stock and shipping information.
        $wcData        = [
            "stock_quantity" => $quantity,
            "stock_status"   => $stockStatus,
            "manage_stock"   => true,
            "shipping_class" => $shippingClass,
            "meta_data"      => [
                [
                    "key"   => "_fastshop_hub",
                    "value" => $hubValue,
                ]
            ]
        ];

        // If item pricing information is available, add it to the WooCommerce data.
        if ( !empty( $data[ "items" ] ) )
        {
            $item                      = $data[ "items" ][ 0 ];
            $regularPrice              = insert_decimal( $item[ "listPrice" ] );
            $salePrice                 = insert_decimal( $item[ "price"     ] );

            $wcData[ "regular_price" ] = $regularPrice;
            $wcData[ "sale_price"    ] = $salePrice;
        }

        // Return the prepared WooCommerce data.
        return $wcData;
    }

    /**
     * Prepares the query for price and stock information.
     *
     * @param   string  $sku    Stock Keeping Unit identifier.
     * @return  array           The formatted query with stock and price information.
     */
    private static function queryStockPrice( string $sku )
    {
        return [
            "items"          => [
                [
                    "id"       => $sku,
                    "quantity" => 1,
                    "seller"   => self::DEFAULT_SELLER
                ]
            ],
            "postalCode"     => "04024020",
            "country"        => self::DEFAULT_COUNTRY,
            "geoCoordinates" => []
        ];
    }

    /**
     * Converts a Fast Shop product into a WooCommerce-compatible product array.
     *
     * This function processes categories, images, specifications, and pricing to create an array with the necessary
     * information to register or update a product in WooCommerce.
     *
     * @param   array   $data   The product data from Fast Shop.
     * @return  array           The formatted product data for WooCommerce.
     */
    private static function newProductFsToWc( array $data )
    {
        // Map Fast Shop categories to WooCommerce category format.
        $categories = array_map( function ( $ctg )
        {
            return [ "id" => $ctg ];

        }, $data[ "CategoryFullPath" ] ?? [] );

        // Filter and map Fast Shop images to WooCommerce image format.
        $images     = array_map(
            function ( $img )
            {
                return [
                    "src"  => $img[ "imageUrl"  ],
                    "name" => $img[ "imageName" ],
                    "alt"  => $img[ "imageName" ]
                ];

            },
            array_filter(
                $data[ "Images" ],
                function ( $img )
                {
                    return validate_image_url( $img[ "imageUrl" ] );
                }
            )
        );

        // Determine the SKU based on Fast Shop product and seller SKU.
        $sku        = $data[ "ProductId" ] == $data[ "SellerStockKeepingUnitId" ] ? $data[ "ProductId" ] : $data[ "SellerStockKeepingUnitId" ];

        // Get product attributes from Fast Shop data.
        $attributes = Helper::getFsProductAttributes( array_merge( $data[ "SkuSpecifications" ], $data[ "ProductSpecifications" ] ) );

        // Generate a short description for the product.
        $shortDescr = Helper::getFsProductShortDescription( $data[ "ProductDescription" ], $data[ "ProductName" ], $attributes );

        // Return the WooCommerce product array.
        return [
            "name"              => $data[ "ProductName" ],
            "slug"              => Str::slug( $data[ "ProductName" ] ),
            "description"       => $data[ "ProductDescription" ],
            "short_description" => $shortDescr,
            "sku"               => "FS-{$sku}",
            "regular_price"     => insert_decimal( $data[ "Pricing" ][ "ListPrice" ] ),
            "sale_price"        => insert_decimal( $data[ "Pricing" ][ "SalePrice" ] ),
            "manage_stock"      => true,
            "stock_quantity"    => $data[ "AvailableQuantity" ],
            "stock_status"      => $data[ "AvailableQuantity" ] > 0 ? "instock" : "outofstock",
            "backorders"        => "no",
            "sold_individually" => false,
            "weight"            => convert_weight( $data[ "WeightKg" ] ),
            "dimensions"        => [
                "length" => ( string ) $data[ "Length" ],
                "width"  => ( string ) $data[ "Width"  ],
                "height" => ( string ) $data[ "Height" ]
            ],
            "shipping_class"    => "fast-shop",
            "upsell_ids"        => [],
            "cross_sell_ids"    => [],
            "parent_id"         => 0,
            "purchase_note"     => "",
            "tags"              => [ [ "id" => 289 ] ],
            "categories"        => $categories,
            "images"            => $images,
            "attributes"        => $attributes,
        ];
    }

    /**
     * Prepares a WooCommerce shipping query for Fast Shop.
     *
     * This function transforms item data into a format suitable for a shipping query with Fast Shop.
     *
     * @param   array   $data  The array of data containing items and shipping details.
     * @return  array          The formatted array suitable for a Fast Shop shipping query.
     */
    private static function freightCalcFs( array $data )
    {
        return [
            // Maps each line item to the desired structure.
            "items" => array_map( function ( $item )
            {
                return [
                    "id"       => str_replace( "FS-", "", $item[ "sku" ] ),
                    "quantity" => $item[ "quantity" ],
                    "seller"   => self::DEFAULT_SELLER,
                ];

            }, $data[ "items" ] ),

            "postalCode"     => $data[ "postalCode" ],
            "country"        => self::DEFAULT_COUNTRY,
            "geoCoordinates" => []
        ];
    }

    /**
     * Converts Fast Shop freight return to WooCommerce format.
     *
     * This function processes logistics information and converts it to a format compatible with WooCommerce.
     *
     * @param   array   $data   The input data containing logistics and item information.
     * @return  array           Returns an array formatted with logistics information for WooCommerce.
     */
    private static function freightCalcWc( array $data )
    {
        // Initialize an empty array to store logistics information.
        $logistics = [];

        // Define a list of known hubs for validation.
        $hubs      = [
            "KAIVEHUB_Normal_05", "KAIVEHUB_Normal_14", "KAIVEHUB_Normal_33",
            "KAIVEHUB_Normal_4E", "KAIVEHUB_Normal_25", "KAIVEHUB_Normal_60",
            "KAIVEHUB_Normal_61", "KAIVEHUB_Normal_19", "KAIVEHUB_Normal_74",
            "KAIVEHUB_Normal_3E", "KAIVEHUB_Normal_97", "KAIVEHUB_Normal_83",
        ];

        // Check if logistics information and items are present in the data.
        if ( !empty( $data[ "logisticsInfo" ] ) && !empty( $data[ "items" ] ) )
        {
            // Iterate over each logistics information entry.
            foreach ( $data[ "logisticsInfo" ] as $lg )
            {
                // Find the delivery channel and SLA for 'delivery'.
                $deliveryChannel = current( array_filter( $lg[ "deliveryChannels" ], function ( $channel )
                {
                    return $channel[ "id" ] === "delivery";
                } ) );
                $slas            = current( array_filter( $lg[ "slas" ], function ( $sla )
                {
                    return $sla[ "deliveryChannel" ] === "delivery";
                } ) );

                // If both delivery channel and SLA are found, process them.
                if ( $deliveryChannel && $slas )
                {
                    $stock        = $deliveryChannel[ "stockBalance" ];
                    $hub          = $slas[ "id"               ];
                    $price        = $slas[ "price"            ];
                    $deliveryTime = $slas[ "shippingEstimate" ];

                    // Default values for code and message.
                    $code         = null;
                    $message      = "ok";

                    // Notify if the hub is not in the known list of hubs.
                    if ( !in_array( $hub, $hubs, true ) )
                    {
                        $error = [
                            "cep" => $data[ "postalCode" ],
                            "sku" => "FS-" . $data[ "items" ][ $lg[ "itemIndex" ] ][ "id" ],
                            "hub" => $hub,
                        ];

                        Notification::notify( "hubError", $error );
                    }
                }
                else
                {
                    // If delivery channel or SLA is not found, set default values.
                    $stock   = $lg[ "stockBalance" ] ?? 0;
                    $price   = $deliveryTime = $hub = "";
                    $code    = 4000;
                    $message = "Delivery not found";
                }

                // Check if the item index exists in the items array.
                $itemIndex = $lg[ "itemIndex" ] ?? null;

                if ( $itemIndex !== null && isset( $data[ "items" ][ $itemIndex ] ) )
                {
                    $item      = $data[ "items" ][ $itemIndex ];

                    // Add the logistics information for the current item.
                    $logistics = [
                        "sku"               => "FS-" . $item[ "id" ],
                        "quantity"          => $lg[ "quantity" ],
                        "stock"             => $stock,
                        "deliveryPrice"     => convert_price( $price ),
                        "deliveryTime"      => convert_delivery_time( $deliveryTime ),
                        "availableQuantity" => ( $lg[ "quantity" ] <= $stock ),
                        "regular_price"     => convert_price( $item[ "listPrice" ] ),
                        "sale_price"        => convert_price( $item[ "price"     ] ),
                        "hub"               => $hub,
                        "code"              => $code,
                        "message"           => $message,
                    ];
                }
            }
        }

        if ( empty( $logistics ) )
        {
            // If logistics information or items are missing, call the fallback function.
            $logistics = self::freightCalcFailed( $data );
        }

        // Return the logistics information array.
        return $logistics;
    }

    /**
     * Convert the Fast Shop freight return to the WooCommerce format.
     *
     * Deals with situations where there is a lack of stock or delivery is unavailable.
     *
     * @param   array   $data   Input data containing the items and their information.
     * @return  array           Returns an array formatted with logistics information for WooCommerce.
     */
    private static function freightCalcFailed( array $data )
    {
        // Initialize the logistics array with default values in case there are no items.
        $logistics = [
            "stock"             => 0,
            "deliveryPrice"     => "0.00",
            "deliveryTime"      => "0",
            "availableQuantity" => false,
            "hub"               => "",
            "code"              => 4002,
            "message"           => "SKU currently out-of-stock",
        ];

        // Check if items exist before processing.
        if ( !empty( $data[ "items" ] ) )
        {
            // Populate with actual item data.
            foreach ( $data[ "items" ] as $product )
            {
                $logistics = [
                    "sku"               => "FS-" . $product[ "id" ],
                    "quantity"          => $product[ "quantity" ],
                    "stock"             => 0,
                    "deliveryPrice"     => "0.00",
                    "deliveryTime"      => "0",
                    "availableQuantity" => false,
                    "regular_price"     => convert_price( $product[ "listPrice" ] ),
                    "sale_price"        => convert_price( $product[ "price"     ] ),
                    "hub"               => "",
                    "code"              => 4001,
                    "message"           => "SKU currently out-of-stock",
                ];
            }
        }

        return $logistics;
    }

    /**
     * Converts Fast Shop freight return to WooCommerce format for a new order.
     *
     * @param   array   $data   The input data containing order line items and shipping information.
     * @return  array           Returns an array formatted with items and shipping information for a new order.
     */
    private static function freightCalcNewOrder( array $data )
    {
        return [
            // Maps each line item to the desired structure.
            "items" => array_map( function ( $item )
            {
                return [
                    "id"       => str_replace( "FS-", "", $item[ "sku" ] ),
                    "quantity" => $item[ "quantity" ],
                    "seller"   => self::DEFAULT_SELLER,
                ];

            }, $data[ "line_items" ] ),

            "postalCode"     => $data[ "shipping" ][ "postcode" ],
            "country"        => self::DEFAULT_COUNTRY,
            "geoCoordinates" => []
        ];
    }

    /**
     * Splits items into separate arrays for individual freight calculation.
     *
     * @param   array $data The input data containing items and shipping information.
     * @return  array An array of data with each item broken out for individual freight calculation.
     */
    private static function breakingUpFreight( array $data )
    {
        // If there is only one item, return the original data wrapped in an array.
        if ( count( $data[ "items" ] ) <= 1 )
            return [ $data ];

        // Maps each line item to the desired structure.
        return array_map( function ( $item ) use ( $data )
        {
            return [
                "items"          => [ $item ],
                "postalCode"     => $data[ "postalCode" ],
                "country"        => self::DEFAULT_COUNTRY,
                "geoCoordinates" => []
            ];

        }, $data[ "items" ] );
    }

    /**
     * Converts WooCommerce order data to the format required by FS (Fast Shop).
     *
     * @param   array   $data   The original order data from WooCommerce.
     * @return  array           The order data formatted for FS.
     */
    private static function newOrderFs( array $data )
    {
        // Extract parent order data if available, otherwise use the current order data.
        $parent        = $data[ "parent" ] ?? null;
        $order         = $data[ "order"  ];

        // Generate the URL for the invoice notification endpoint using the order ID
        $url           = route( "api.marketplace.invoicenotification", [ "orderId" => $order[ "id" ] ] );

        // Extract the FS information from the order data.
        $fsLogistics   = $order[ "fs_info" ][ "logisticsInfo" ];
        $fsItems       = $order[ "fs_info" ][ "items" ];

        // Map each item to the FS format using the FS information.
        $items         = Helper::mapFsOrderItems( $fsLogistics, $fsItems );

        // Map logistics information to the FS format.
        $logisticsInfo = Helper::mapFsLogisticsInfo( $fsLogistics );

        // Retrieve the discount
        $discount      = ( empty( $order[ "coupon_lines" ] ) )
                            ? $order[ "discount_total" ]
                            : ( $order[ "coupon_lines" ][ 0 ][ "discount" ] ?? 0 );

        // Calculate the total payment value for the marketplace.
        $total         = $order[ "total" ] + $discount;
        $total         = number_format( ( float ) $total, 2, ".", "" );
        $total         = ( int ) str_replace( ".", "", $total );

        // Extract billing and shipping information, using parent data if available.
        $billing       = $parent[ "billing"  ] ?? $order[ "billing"  ];
        $shipping      = $parent[ "shipping" ] ?? $order[ "shipping" ];

        // Determine if the order is from a corporate entity based on the person type.
        $isCorporate   = isset( $billing[ "persontype" ] ) && $billing[ "persontype" ] === "J";

        // Return the formatted order data for FS.
        return [
            "marketplaceOrderId"          => $order[ "id" ],
            "marketplaceServicesEndpoint" => $url,
            "marketplacePaymentValue"     => $total,
            "items"                       => $items,
            "clientProfileData"           => [
                "email"             => $billing[ "email"      ],
                "firstName"         => $billing[ "first_name" ],
                "lastName"          => $billing[ "last_name"  ],
                "phone"             => $billing[ "phone"      ],
                "documentType"      => $isCorporate ? "cnpj"                : "cpf",
                "document"          => $isCorporate ? ""                    : $billing[ "cpf" ],
                "corporateDocument" => $isCorporate ? $billing[ "cnpj"    ] : "",
                "corporateName"     => $isCorporate ? $billing[ "company" ] : "",
                "tradeName"         => $isCorporate ? $billing[ "company" ] : "",
                "corporatePhone"    => $isCorporate ? $billing[ "phone"   ] : "",
                "isCorporate"       => $isCorporate,
                "stateInscription"  => null,
            ],
            "shippingData" => [
                "logisticsInfo" => $logisticsInfo,
                "address"       => [
                    "addressType"  => "Desconhecido",
                    "receiverName" => $shipping[ "first_name"   ] . " " . $shipping[ "last_name" ],
                    "postalCode"   => $shipping[ "postcode"     ],
                    "street"       => $shipping[ "address_1"    ],
                    "number"       => $shipping[ "number"       ],
                    "neighborhood" => $shipping[ "neighborhood" ],
                    "complement"   => $shipping[ "address_2"    ],
                    "city"         => $shipping[ "city"         ],
                    "state"        => $shipping[ "state"        ],
                    "country"      => self::DEFAULT_COUNTRY,
                ],
            ],
        ];
    }

    /**
     * Converts the error returned during order creation
     *
     * @param   array   $data   An associative array containing the error code and SKU errors.
     * @return  string          A formatted error message string for display or logging.
     */
    private static function newOrderFsError( array $data )
    {
        $code    = $data[ "errorCode" ];
        $message = "ERROR FS";

        // Mapping of error codes to messages.
        $errorMessages = [
            "4001" => "SKU fora de estoque",
            "4002" => "Entrega indisponível para o CEP informado",
            "5001" => "Não foi possível informar o pedido a Fast Shop",
        ];

        // Adds the corresponding error message for the code, if it exists.
        if ( isset( $errorMessages[ $code ] ) )
            $message .= "\n" . $errorMessages[ $code ];

        // Appends SKU-specific error messages.
        foreach ( $data[ "sku" ] as $error )
            foreach ( $error as $sku => $msg )
                $message .= "\n{$sku} : {$msg}";

        // Appends the error code at the end of the message.
        $message .= "\nCode: $code";

        return $message;
    }

    /**
     * Converts the response from FS to change the order status.
     *
     * @param   array   $data   The input data array containing order status details.
     * @return  string          A formatted string representing the order status change for display or logging.
     */
    private static function orderStatusChange( array $data )
    {
        // Initialize the lines array.
        $lines   = [];

        // Define an associative array to map keys to their respective formats.
        $formats = [
            "date"               => fn( $value ) => convert_delivery_date( $value ),
            "marketPlaceOrderId" => fn( $value ) => "Order: #{$value}",
            "orderId"            => fn( $value ) => "FS Order: #{$value}",
            "receipt"            => fn( $value ) => "Protocol: {$value}",
        ];

        // Iterate over each key-value pair in the data array.
        foreach ( $data as $key => $value )
        {
            // Check if there is a format defined for the current key.
            if ( isset( $formats[ $key ] ) )
            {
                // Apply the format and add to the lines array.
                $lines[] = $formats[ $key ]( $value );
            }
            else
            {
                // For all other keys, add the key and value to the lines array.
                $lines[] = "{$key}: {$value}";
            }
        }

        return implode( "\n", $lines );
    }

    /**
     * Processes order data to prepare it for an email notification in case of an order error.
     *
     * @param    array   $data   The input data array containing order details.
     * @return   array           An associative array with key order information for constructing the email notification.
     */
    private static function orderError( array $data )
    {
        // Determine the main order ID. If the 'parent' key exists, use its ID; otherwise, use the 'order' ID.
        $orderId    = $data[ "parent" ][ "id" ] ?? $data[ "order" ][ "id" ];

        // Determine the sub-order ID only if the 'parent' key exists, otherwise set it to an empty string.
        $subOrderId = $data[ "parent" ] ? $data[ "order" ][ "id" ] : "";

        // Extract the customer ID from the order data.
        $customerId = $data[ "order" ][ "customer_id" ];

        // Extract the billing information for convenience and readability.
        $billing    = $data[ "order" ][ "billing"     ];

        // Construct and return the associative array with the formatted order information.
        return [
            "orderId"     => $orderId,
            "subOrderId"  => $subOrderId,
            "customer_id" => $customerId,
            "name"        => $billing[ "first_name" ] . " " . $billing[ "last_name" ],
            "email"       => $billing[ "email"      ],
            "phone"       => $billing[ "phone"      ],
        ];
    }

    /**
     * Formats invoice information for display.
     *
     * @param   array   $data   The input data array containing invoice details.
     * @return  string          A formatted string containing the invoice information.
     */
    private static function invoice( array $data )
    {
        return Helper::formatInfo( $data, "NOTA FISCAL", [
            "invoiceNumber" => "Nº",
            "invoiceKey"    => "Chave",
            "invoiceUrl"    => "URL"
        ] );
    }

    /**
     * Formats tracking information for display.
     *
     * @param   array   $data   The input data array containing tracking details.
     * @return  string          A formatted string containing the tracking information.
     */
    private static function tracking( array $data )
    {
        return Helper::formatInfo( $data, "RASTREAMENTO", [
            "courier"        => "Transportadora",
            "trackingNumber" => "Código de rastreio",
            "trackingUrl"    => "URL"
        ] );
    }
}
