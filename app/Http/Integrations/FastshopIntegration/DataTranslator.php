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
            $regularPrice              = Helper::insertDecimal( $item[ "listPrice" ] );
            $salePrice                 = Helper::insertDecimal( $item[ "price"     ] );

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
        $images     = array_map( function ( $img )
        {
            return [
                "src"  => $img[ "imageUrl"  ],
                "name" => $img[ "imageName" ],
                "alt"  => $img[ "imageName" ]
            ];

        }, array_filter( $data[ "Images" ], function ( $img )
        {
            return Helper::validateImageUrl( $img[ "imageUrl" ] );
        } ) );

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
            "regular_price"     => Helper::insertDecimal( $data[ "Pricing" ][ "ListPrice" ] ),
            "sale_price"        => Helper::insertDecimal( $data[ "Pricing" ][ "SalePrice" ] ),
            "manage_stock"      => true,
            "stock_quantity"    => $data[ "AvailableQuantity" ],
            "stock_status"      => $data[ "AvailableQuantity" ] > 0 ? "instock" : "outofstock",
            "backorders"        => "no",
            "sold_individually" => false,
            "weight"            => Helper::convertWeight( $data[ "WeightKg" ] ),
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
     * Prepara uma consulta de frete do WooCommerce para a Fast Shop
     *
     * @param array $data
     * @return array
     */
    private static function freightCalcFs( array $data )
    {
        $items = [];

        foreach ( $data[ "items" ] as $item )
        {
            $items[] = [
                "id"       => explode( "FS-", $item[ "sku" ] )[ 1 ],
                "quantity" => $item[ "quantity" ],
                "seller"   => self::DEFAULT_SELLER,
            ];
        }

        return [
            "items"          => $items,
            "postalCode"     => $data[ "postalCode" ],
            "country"        => self::DEFAULT_COUNTRY,
            "geoCoordinates" => []
        ];
    }

    /**
     * Converte retorno de frete da Fast Shop para WooCommerce
     *
     * @param array $data
     * @return array
     */
    private static function freightCalcWc( array $data )
    {
        $hubs = [
            "KAIVEHUB_Normal_05",
            "KAIVEHUB_Normal_14",
            "KAIVEHUB_Normal_33",

            "KAIVEHUB_Normal_4E",
            "KAIVEHUB_Normal_25",
            "KAIVEHUB_Normal_60",
            "KAIVEHUB_Normal_61",

            "KAIVEHUB_Normal_19",
            "KAIVEHUB_Normal_74",

            "KAIVEHUB_Normal_3E",

            "KAIVEHUB_Normal_97",
        ];

        $logistics = [];

        if ( !empty( $data[ "logisticsInfo" ] ) && !empty( $data[ "items" ] ) )
        {
            foreach ( $data[ "logisticsInfo" ] as $key => $lg )
            {
                $idChannel = array_search( "delivery", array_column( $lg[ "deliveryChannels" ], "id"              ) );
                $idSlas    = array_search( "delivery", array_column( $lg[ "slas"             ], "deliveryChannel" ) );

                if ( $idChannel !== false && $idSlas !== false )
                {
                    $stock        = $lg[ "deliveryChannels" ][ $idChannel ][ "stockBalance"     ];
                    $price        = $lg[ "slas"             ][ $idSlas    ][ "price"            ];
                    $deliveryTime = $lg[ "slas"             ][ $idSlas    ][ "shippingEstimate" ];
                    $hub          = $lg[ "slas"             ][ $idSlas    ][ "id"               ];

                    $code         = null;
                    $message      = "ok";

                    // Notifica HUB não mapeado
                    if ( !in_array( $hub, $hubs ) )
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
                    $stock        = $lg[ "stockBalance" ];
                    $price        = 0;
                    $deliveryTime = 0;
                    $hub          = "";

                    $code         = 4000;
                    $message      = "Delivery not found";
                }

                $availableQuantity = ( $lg[ "quantity" ] <= $stock ) ? true : false;
                $logistics[]       = [
                    "sku"               => "FS-" . $data[ "items" ][ $lg[ "itemIndex" ] ][ "id" ],
                    "quantity"          => $lg[ "quantity" ],
                    "stock"             => $stock,
                    "deliveryPrice"     => Helper::convertPrice( $price ),
                    "deliveryTime"      => Helper::convertDeliveryTime( $deliveryTime ),
                    "availableQuantity" => $availableQuantity,
                    "regular_price"     => Helper::convertPrice( $data[ "items" ][ $lg[ "itemIndex" ] ][ "listPrice" ] ),
                    "sale_price"        => Helper::convertPrice( $data[ "items" ][ $lg[ "itemIndex" ] ][ "price"     ] ),
                    "hub"               => $hub,

                    "code"              => $code,
                    "message"           => $message,
                ];
            }
        }
        else
        {
            $logistics = self::freightCalcFailed( $data );
        }

        $logistics = count( $logistics ) > 1 ? $logistics : $logistics[ 0 ];

        return $logistics;
    }

    /**
     * Converte retorno de frete da Fast Shop para WooCommerce
     * Quando há falta de estoque ou entrega indisponivel
     *
     * @param array $data
     * @return array
     */
    private static function freightCalcFailed( array $data )
    {
        $logistics = [];

        if ( !empty( $data[ "items" ] ) )
        {
            foreach ( $data[ "items" ] as $key => $product )
            {
                $logistics[] = [
                    "sku"               => "FS-" . $product[ "id" ],
                    "quantity"          => $product[ "quantity" ],
                    "stock"             => 0,
                    "deliveryPrice"     => "0.00",
                    "deliveryTime"      => "0",
                    "availableQuantity" => false,
                    "regular_price"     => Helper::convertPrice( $product[ "listPrice" ] ),
                    "sale_price"        => Helper::convertPrice( $product[ "price"     ] ),
                    "hub"               => "",
                    "code"              => 4001,
                    "message"           => "SKU currently out-of-stock",
                ];
            }
        }
        else
        {
            $logistics[] = [
                "stock"             => 0,
                "deliveryPrice"     => "0.00",
                "deliveryTime"      => "0",
                "availableQuantity" => false,
                "hub"               => "",
                "code"              => 4002,
                "message"           => "SKU currently out-of-stock",
            ];
        }

        return $logistics;
    }

    /**
     * Converte retorno de frete da Fast Shop para WooCommerce, novo pedido
     *
     * @param array $data
     * @return array
     */
    private static function freightCalcNewOrder( array $data )
    {
        $items = [];

        foreach ( $data[ "line_items" ] as $item )
        {
            $items[] = [
                "id"       => explode( "FS-", $item[ "sku" ] )[ 1 ],
                "quantity" => $item[ "quantity" ],
                "seller"   => self::DEFAULT_SELLER,
            ];
        }

        return [
            "items"          => $items,
            "postalCode"     => $data[ "shipping" ][ "postcode" ],
            "country"        => self::DEFAULT_COUNTRY,
            "geoCoordinates" => []
        ];
    }

    /**
     * Undocumented function
     *
     * @param   array $data
     * @return  array
     */
    private static function breakingUpFreight( array $data )
    {
        $newData = [];

        if ( count( $data[ "items" ] ) > 1 )
        {
            foreach ( $data[ "items" ] as $item )
            {
                $newData[] = [
                    "items"          => [ $item ],
                    "postalCode"     => $data[ "postalCode" ],
                    "country"        => $data[ "country"    ],
                    "geoCoordinates" => []
                ];
            }
        }
        else
        {
            $newData[] = $data;
        }

        return $newData;
    }

    /**
     * Converts WooCommerce order data to the format required by FS (Fast Shop).
     *
     * @param   array   $data   The original order data from WooCommerce.
     * @return  array           The order data formatted for FS.
     */
    private static function newOrderFs( array $data )
    {
        // Generate the URL for the invoice notification endpoint using the order ID
        $url           = route( "api.marketplace.invoicenotification", [ "orderId" => $order[ "id" ] ] );

        // Extract parent order data if available, otherwise use the current order data.
        $parent        = $data[ "parent" ] ?? null;
        $order         = $data[ "order"  ];

        // Extract the FS information from the order data.
        $fsInfo        = $order[ "fs_info" ];

        // Map each item to the FS format using the FS information.
        $items         = array_map( function ( $item ) use ( $fsInfo )
        {
            $idItem  = $item[ "itemIndex" ];
            $auxItem = $fsInfo[ "items" ][ $idItem ];

            return [
                "id"                => $auxItem[ "id"             ],
                "quantity"          => $auxItem[ "quantity"       ],
                "price"             => $auxItem[ "price"          ],
                "unitMultiplier"    => $auxItem[ "unitMultiplier" ],
                "Seller"            => $auxItem[ "seller"         ],
                "priceTags"         => $auxItem[ "priceTags"      ],
                "isGift"            => false,
                "commission"        => 10,
                "freightCommission" => 0,
            ];

        }, $fsInfo[ "logisticsInfo" ] );

        // Map logistics information to the FS format.
        $logisticsInfo = array_map( function ( $item )
        {
            $idSla       = array_search( "delivery", array_column( $item[ "slas" ], "deliveryChannel" ) );
            $selectedSla = $item[ "slas" ][ $idSla ];

            return [
                "itemIndex"        => $item[ "itemIndex" ],
                "selectedSla"      => $selectedSla[ "id" ],
                "lockTTL"          => "3d",
                "shippingEstimate" => $selectedSla[ "shippingEstimate" ],
                "price"            => $selectedSla[ "price"            ],
                "deliveryWindow"   => [],
            ];

        }, $fsInfo[ "logisticsInfo" ] );

        // Calculate the total payment value for the marketplace.
        $total         = $order[ "total" ] + ( $order[ "coupon_lines" ][ 0 ][ "discount" ] ?? 0 );
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
                    "addressType"  => "",
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
     * Converte o erro retornado na criação do pedido
     *
     * @param array $data
     * @return string
     */
    private static function newOrderFsError( array $data )
    {
        $code    = $data[ "errorCode" ];
        $message = "ERROR FS\n";

        switch ( $code )
        {
            case "4001";
                $message .= "SKU fora de estoque";
            break;

            case "4002";
                $message .= "Entrega indisponível para o CEP informado";
            break;

            case "5001";
                $message .= "Não foi possível informar o pedido a Fast Shop";
            break;
        }

        foreach ( $data[ "sku" ] as $error )
            foreach ( $error as $sku => $msg )
                $message .= "\n{$sku} : {$msg}";

        return "$message \nCode: $code";
    }

    /**
     * Converte retorno da FS para alteração do status do pedido
     *
     * @param array $data
     * @return string
     */
    private static function orderStatusChange( array $data )
    {
        $message = "";

        foreach ( $data as $key => $value )
        {
            if ( $key == "date" )
            {
                $value    = Helper::convertDeliveryDate( $value );
                $message .= "$value\n";
            }
            else if ( $key == "marketPlaceOrderId" )
            {
                $message .= "Pedido: #$value\n";
            }
            else if ( $key == "orderId" )
            {
                $message .= "FS Pedido: #$value\n";
            }
            else if ( $key == "receipt" )
            {
                $message .= "Protocolo: $value\n";
            }
            else
            {
                $message .= "$key: $value\n";
            }
        }

        return $message;
    }

    /**
     * Converte os dados do pedido para enviar email
     *
     * @package array $data
     * @return array
     */
    private static function orderError( array $data )
    {
        if ( empty( $data[ "parent" ] ) )
        {
            $orderId    = $data[ "order"  ][ "id" ];
            $subOrderId = "";
        }
        else
        {
            $orderId    = $data[ "parent" ][ "id" ];
            $subOrderId = $data[ "order"  ][ "id" ];
        }

        return [
            "orderId"     => $orderId,
            "subOrderId"  => $subOrderId,
            "customer_id" => $data[ "order" ][ "customer_id" ],
            "name"        => $data[ "order" ][ "billing"     ][ "first_name" ] . " " . $data[ "order" ][ "billing" ][ "last_name" ],
            "email"       => $data[ "order" ][ "billing"     ][ "email"      ],
            "phone"       => $data[ "order" ][ "billing"     ][ "phone"      ],
        ];
    }

    /**
     * Nota fiscal
     * Converte os dados da notificação da FS para a anotação do WC
     */
    private static function invoice( array $data )
    {
        $invoiceNumber = $data[ "invoiceNumber" ];
        $invoiceKey    = $data[ "invoiceKey"    ];
        $invoiceUrl    = $data[ "invoiceUrl"    ];

        return "NOTA FISCAL\n Nº: $invoiceNumber\n Chave: $invoiceKey\n URL: $invoiceUrl";
    }

    /**
     * Rastreamento
     * Converte os dados da notificação da FS para a anotação do WC
     */
    private static function tracking( array $data )
    {
        $courier        = $data[ "courier"        ];
        $trackingNumber = $data[ "trackingNumber" ];
        $trackingUrl    = $data[ "trackingUrl"    ];

        return "RASTREAMENTO\n Transportadora: $courier\n Código de rastreio: $trackingNumber\n URL: $trackingUrl";
    }


}
