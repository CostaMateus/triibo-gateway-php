<?php

namespace App\Http\Integrations\FastshopIntegration;

use App\Helpers\Helper;
use Illuminate\Support\Str;

class DataTranslator
{

    public static function translate( string $type, array $data )
    {
        switch ( $type ) {
            case "updateStockPrice":
                return self::updateStockPrice( $data );
            break;
            case "queryStockPrice":
                return self::queryStockPrice( $data[ 0 ] );
            break;

            case "newProduct":
                return self::newProductFsToWc( $data );
            break;

            case "freightCalcFs":
                return self::freightCalcFs( $data );
            break;
            case "freightCalcWc":
                return self::freightCalcWc( $data );
            break;
            // case "freightCalcFailed":
            //     return self::freightCalcFailed( $data );
            // break;
            case "freightCalcNewOrder":
                return self::freightCalcNewOrder( $data );
            break;
            case "breakingUpFreight":
                return self::breakingUpFreight( $data );
            break;

            case "newOrderFs":
                return self::newOrderFs( $data );
            break;
            case "newOrderFsError":
                return self::newOrderFsError( $data );
            break;
            case "orderStatusChange":
                return self::orderStatusChange( $data );
            break;
            case "orderError":
                return self::orderError( $data );
            break;

            case "invoice":
                return self::invoice( $data );
            break;
            case "tracking":
                return self::tracking( $data );
            break;
        }
    }

    /**
     * Converte a atualização de um produto da Fastshop em WooCommerce
     *
     * @param array $data
     * @return array
     */
    private static function updateStockPrice( array $data )
    {
        $product = reset( $data );
        $data    = end( $data );

        $value   = "-";
        $class   = "fast-shop";
        $qntty   = 0;
        $status  = "outofstock";

        if ( !empty( $data[ "logisticsInfo" ] ) )
        {
            $aux = reset( $data[ "logisticsInfo" ] );

            if ( !empty( $aux[ "slas" ] ) )
            {
                $slas   = reset( $aux[ "slas" ] );
                $value  = $slas[ "id" ];
                $hub    = explode( "_", $value );
                $class  = "fast-shop-" . end( $hub );

                $qntty  = $aux[ "stockBalance" ];
                $status = ( $qntty > 0 ) ? "instock" : "outofstock";
            }
        }
        else
        {
            $index = ( !$product ) ? "" : ( array_search( "_fastshop_hub", array_column( $product->meta_data, "key" ) ) );
            $value = ( !$product ) ? "" : ( ( $index !== false ) ? $product->meta_data[ $index ]->value : "-"           );
            $class = ( !$product ) ? "" : ( $product->shipping_class                                                    );
        }

        if ( !empty( $data[ "items" ] ) )
        {
            $item    = $data[ "items" ][ 0 ];
            $regular = Helper::insertDecimal( $item[ "listPrice" ] );
            $sale    = Helper::insertDecimal( $item[ "price"     ] );

            $wcData  = [
                "regular_price"  => $regular,
                "sale_price"     => $sale,
                "stock_quantity" => $qntty,
                "stock_status"   => $status,
                "manage_stock"   => true
            ];
        }
        else
        {
            $wcData  = [
                "stock_quantity" => $qntty,
                "stock_status"   => $status,
                "manage_stock"   => true
            ];
        }

        $wcData[ "shipping_class" ] = $class;
        $wcData[ "meta_data"      ] = [ [
            "key"   => "_fastshop_hub",
            "value" => $value,
        ] ];

        return $wcData;
    }

    /**
     * Prepara a consulta de preço e estoque
     *
     * @param string $sku
     * @return array
     */
    private static function queryStockPrice( string $sku )
    {
        return [
            "items" => [ [
                "id"       => $sku,
                "quantity" => 1,
                "seller"   => "FSH"
            ] ],
            "postalCode"     => "04024020",
            "country"        => "BRA",
            "geoCoordinates" => []
        ];
    }

    /**
     * Converte um produto da Fastshop em WooCommerce
     *
     * @param array $data
     * @return array
     */
    private static function newProductFsToWc( array $data )
    {
        $attributes = [];
        $categories = [];
        $images     = [];

        // $slug       = Helper::hyphenize( $data[ "ProductName" ] );
        $slug       = \Str::slug( $data[ "ProductName" ] );
        $sku        = $data[ "ProductId" ] == $data[ "SellerStockKeepingUnitId" ] ? $data[ "ProductId" ] : $data[ "SellerStockKeepingUnitId" ];

        if ( !empty( $data[ "CategoryFullPath" ] ) )
        {
            foreach ( $data[ "CategoryFullPath" ] as $ctg )
            {
                $categories[] = [ "id" => $ctg ];
            }
        }

        foreach ( $data[ "Images" ] as $img )
        {
            if ( !Helper::validateImageUrl( $img[ "imageUrl" ] ) ) continue;

            $images[] = [
                "src"  => $img[ "imageUrl"  ],
                "name" => $img[ "imageName" ],
                "alt"  => $img[ "imageName" ]
            ];
        }

        // VERIFICAR OUTROS PRODUTOS
        foreach ( $data[ "SkuSpecifications" ] as $key => $attr )
        {
            $attributes[] = [
                "name"      => $attr[ "fieldName" ],
                "position"  => $key,
                "visible"   => true,
                "variation" => true,
                "options"   => [
                    $attr[ "fieldValue" ]
                ],
            ];
        }
        foreach ( $data[ "ProductSpecifications" ] as $key => $attr )
        {
            if ( $attr[ "fieldName" ] == "ProductLineId" ) continue;

            $fieldValue   = ( $attr[ "fieldValue" ] === "true" ) ? "SIM" : $attr[ "fieldValue" ];

            $attributes[] = [
                "name"      => $attr[ "fieldName" ],
                "position"  => $key,
                "visible"   => true,
                "variation" => true,
                "options"   => [ $fieldValue ],
            ];
        }

        $description   = $data[ "ProductDescription" ];

        $shortDescript = explode( "\r\n", $description )[ 0 ];
        $shortDescript = ( strlen( $shortDescript ) <= 50 ) ? $data[ "ProductName" ] : $shortDescript;
        $shortDescript = Str::limit( $shortDescript, 140, " ( ... )" );

        $attr_name     = "";
        $attr_val      = "";

        foreach ( $attributes as $att )
        {
            $name = Str::lower( $att[ "name" ] );

            if ( $name == "voltagem" )
            {
                $attr_name = Str::ucfirst( $name );
                $val       = reset( $att[ "options" ] );
                $attr_val  = ( $val == "110" || $val == "220" ) ? $val . "V" : $val;
            }
        }

        if ( $attr_name && $attr_val && $attr_val != "NA" ) $shortDescript .= "\r\n\r\n$attr_name : $attr_val";

        $wcData         = [
            "name"              => $data[ "ProductName" ],
            "slug"              => $slug,

            "description"       => $description,
            "short_description" => $shortDescript,
            "sku"               => "FS-$sku",

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

        $wcData = json_encode( $wcData, JSON_UNESCAPED_SLASHES );
        $wcData = json_decode( $wcData, true                   );

        return $wcData;
    }

    /**
     * Prepara uma consulta de frete do WooCommerce para a Fastshop
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
                "seller"   => "FSH",
            ];
        }

        return [
            "items"          => $items,
            "postalCode"     => $data[ "postalCode" ],
            "country"        => "BRA",
            "geoCoordinates" => []
        ];
    }

    /**
     * Converte retorno de frete da Fastshop para WooCommerce
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
     * Converte retorno de frete da Fastshop para WooCommerce
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
     * Converte retorno de frete da Fastshop para WooCommerce, novo pedido
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
                "seller"   => "FSH",
            ];
        }

        return [
            "items"          => $items,
            "postalCode"     => $data[ "shipping" ][ "postcode" ],
            "country"        => "BRA",
            "geoCoordinates" => []
        ];
    }

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
     * Converte os dados do pedido WC para enviar a FS
     *
     * @param array $data
     * @return array
     */
    private static function newOrderFs( array $data )
    {
        $parent        = $data[ "parent" ];
        $data          = $data[ "order"  ];

        $url           = route( "api.marketplace.invoicenotification", [ "orderId" => $data[ "id" ] ] );
        $name          = $data[ "billing" ][ "first_name" ] . " " . $data[ "billing" ][ "last_name" ];
        $auxData       = $data[ "fs_info" ];

        $items         = [];
        $logisticsInfo = [];

        foreach ( $auxData[ "logisticsInfo" ] as $item )
        {
            $idItem  = $item[ "itemIndex" ];
            $auxItem = $auxData[ "items" ][ $idItem ];

            $items[] = [
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

            $idSla           = array_search( "delivery", array_column( $item[ "slas" ], "deliveryChannel" ) );
            $selectedSla     = $item[ "slas" ][ $idSla ][ "id"               ];
            $eta             = $item[ "slas" ][ $idSla ][ "shippingEstimate" ];
            $price           = $item[ "slas" ][ $idSla ][ "price"            ];

            $logisticsInfo[] = [
                "itemIndex"        => $idItem,
                "selectedSla"      => $selectedSla,
                "lockTTL"          => "3d",
                "shippingEstimate" => $eta,
                "price"            => $price,
                "deliveryWindow"   => []
            ];
        }

        $total = $data[ "total" ];

        if ( isset( $data[ "coupon_lines" ][ 0 ] ) )
            $total += $data[ "coupon_lines" ][ 0 ][ "discount" ];

        // $total = $total + $data[ "discount_total" ];
        $total = number_format( (float) $total, 2, ".", "" );
        $total = (int) str_replace( ".", "", $total );

        $new   = [
            "marketplaceOrderId"          => $data[ "id" ], // id do pedido no mkt
            "marketplaceServicesEndpoint" => $url,          // api que recebe a "Order Invoice Notification"
            "marketplacePaymentValue"     => $total,
            "items"                       => $items,
            // wc_order billing
            "clientProfileData" => [
                "firstName"    => $data[ "billing" ][ "first_name" ],
                "lastName"     => $data[ "billing" ][ "last_name"  ],
                "email"        => $data[ "billing" ][ "email"      ],
                "phone"        => $data[ "billing" ][ "phone"      ],
                "document"     => ( $parent ) ? $parent[ "billing" ][ "cpf" ] : $data[ "billing" ][ "cpf" ],
                "documentType" => "cpf",
                "isCorporate"  => false,

                // "isCorporate"       => true,
                // "corporateName"     => "TD - Power Informatica LTDA",
                // "tradeName"         => "TD - Power Informatica LTDA",
                // "corporateDocument" => "07776469000166",
                // "corporatePhone"    => "(11) 99200-5881",
                // "email"             => "ppinho@me.com",
                // "phone"             => "(11) 99200-5881",
            ],
            "shippingData"      => [
                // wc_order shipping
                "address"       => [
                    "addressType"  => "addrType",
                    "addressId"    => "addrId",
                    "receiverName" => $name,
                    "number"       => ( $parent ) ? $parent[ "shipping" ][ "number"       ] : $data[ "shipping" ][ "number"       ],
                    "neighborhood" => ( $parent ) ? $parent[ "shipping" ][ "neighborhood" ] : $data[ "shipping" ][ "neighborhood" ],
                    "street"       => $data[ "shipping" ][ "address_1" ],
                    "complement"   => $data[ "shipping" ][ "address_2" ],
                    "city"         => $data[ "shipping" ][ "city"      ],
                    "state"        => $data[ "shipping" ][ "state"     ],
                    "postalCode"   => $data[ "shipping" ][ "postcode"  ],
                    "country"      => "BRA",

                    // "receiverName" => "Paulo Pinho",
                ],
                "logisticsInfo" => $logisticsInfo,
            ],
        ];

        return $new;
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
