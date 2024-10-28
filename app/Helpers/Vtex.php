<?php

use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Services\Vtex\OrderService;
use App\Services\Vtex\ConnectorService;
use App\Services\Vtex\FulfillmentService;
use App\Notifications\FastShop\OrderError;
use Codexshaper\WooCommerce\Facades\{ Note, Order };

/**
 * Helper functions for various tasks in the application.
 */

if ( !function_exists( "get_marketplace_categories" ) )
{
    /**
     * Retrieves all marketplace categories and organizes them by ID.
     *
     * @return  array   An array of categories indexed by their IDs.
     */
    function get_marketplace_categories() : array
    {
        $page       = 1;
        $aux_cats   = [];
        $categories = [];

        do
        {
            $data     = Category::all( [ "per_page" => 100, "page" => $page ] )->toArray();
            $aux_cats = array_merge( $aux_cats, $data );
            $page++;
        }
        while ( count( $data ) == 100 );

        foreach ( $aux_cats as $cat )
            $categories[ $cat->id ] = $cat;

        ksort( $categories );

        return $categories;
    }
}

if ( !function_exists( "get_categories_ids" ) )
{
    /**
     * Undocumented function
     *
     * @param   string  $ids
     * @param   array   $names
     * @return  array
     */
    function get_categories_ids( string $ids, array $names ) : array
    {
        $data       = array_values( array_filter( explode( "/", $ids ) ) );
        $categories = [];

        foreach ( $data as $index => $vtex_category_id )
        {
            $slug     = Str::slug( $names[ $vtex_category_id ], "-" );
            $category = DB::table( "marketplace_product_categories" )->where( "cat_slug", $slug )->first();

            if ( $category )
            {
                $categories[] = $category->cat_id;
            }
            else
            {
                $search = Category::where( "slug", $slug )->first();

                if ( $search->isNotEmpty() )
                {
                    $new_category = [
                        "id"   => $search[ "id"   ],
                        "slug" => $search[ "slug" ],
                    ];
                }
                else
                {
                    $parent       = ( $index == 0 ) ? 0 : $categories[ $index - 1 ];

                    $new_category = Category::create( [
                        "name"   => $names[ $vtex_category_id ],
                        "parent" => $parent,
                    ] );
                }

                $categories[] = $new_category[ "id" ];

                DB::table( "marketplace_product_categories" )->insert( [
                    "cat_id"   => $new_category[ "id"   ],
                    "cat_slug" => $new_category[ "slug" ]
                ] );
            }
        }

        return $categories;
    }
}

if ( !function_exists( "get_email_order_error" ) )
{
    function get_email_order_error( array $parent, array $order )
    {
        if ( !empty( $parent ) )
        {
            $order_id     = $parent[ "id" ];
            $sub_order_id = $order[ "id" ];
        }
        else
        {
            $order_id     = $order[ "id" ];
            $sub_order_id = "";
        }

        $customer_id = $order[ "customer_id" ];
        $billing     = $order[ "billing"     ];

        return [
            "order_id"     => $order_id,
            "sub_order_id" => $sub_order_id,
            "customer_id"  => $customer_id,
            "name"         => $billing[ "first_name" ] . " " . $billing[ "last_name" ],
            "email"        => $billing[ "email"      ],
            "phone"        => $billing[ "phone"      ],
        ];
    }
}

if ( !function_exists( "get_wc_order" ) )
{
    /**
     * Undocumented function
     *
     * @param   string  $channel
     * @param   string  $order_id
     * @param   string  $seller_id
     * @return  array
     */
    function get_wc_order( string $channel, string $order_id, string $seller_id )
    {
        $parent = null;
        $order  = null;

        try
        {
            $order = Order::find( $order_id );
            $order = json_decode( json_encode( $order ), true );

            if ( $order[ "parent_id" ] != 0 )
            {
                do
                {
                    $parent = Order::find( $order[ "parent_id" ] );
                    $parent = json_decode( json_encode( $parent ), true );

                    if ( $parent[ "parent_id" ] != 0 )
                        $order[ "parent_id" ] = $parent[ "parent_id" ];
                }
                while ( $parent[ "parent_id" ] != 0 );
            }
        }
        catch ( Throwable $e )
        {
            // Log the exception for debugging purposes.
            log_exception(
                channel : $channel,
                message : $e->getMessage(),
                method  : "get_wc_order",
                type    : "Throwable",
                e       : $e
            );

            $order = false;

            return [ $parent, $order ];
        }

        $store_id = config( "woocommerce.partners.{$seller_id}.store_id" );

        if ( $order[ "store" ][ "id" ] != $store_id )
            return [ null, null ];

        return [ $parent, $order ];
    }
}

if ( !function_exists( "create_order_note" ) )
{
    /**
     * Undocumented function
     *
     * @param   string  $channel
     * @param   string  $order_id
     * @param   string  $message
     * @return  void
     */
    function create_order_note( string $channel, string $order_id, string $message )
    {
        $retry  = 0;
        $failed = false;

        do
        {
            try
            {
                Note::create( $order_id, [ "note" => $message ] );
                return;
            }
            catch ( Throwable $e )
            {
                $retry++;
                $failed = true;
            }
        }
        while ( $failed && $retry < 3 );

        log_exception(
            channel : $channel,
            message : $e->getMessage(),
            method  : "create_order_note",
            type    : "Throwable",
            e       : $e ?? null
        );
    }
}

if ( !function_exists( "create_error_note" ) )
{
    /**
     * Undocumented function
     *
     * @param   string          $channel
     * @param   string          $order_id
     * @param   string          $error_option
     * @param   string|null     $custom
     * @return  void
     */
    function create_error_note( string $channel, string $order_id, string $error_option, ?string $custom = null )
    {
        $message     = "ERROR FS\n";
        $error_codes = [
            "new_order_error"         => "Falha na consulta do frete",
            "new_order_error_2"       => "Falha na criação | SKU indisponível",
            "cancelation_order_error" => "Falha no cancelamento do pedido na Fast Shop",
            "cancelation_order_fs"    => "Pedido cancelado pela Fast Shop",
            "approved_order_error_1"  => "Falha ao informar sobre a aprovação do pedido a Fast Shop",
            "approved_order_error_2"  => "Falha ao informar sobre a aprovação do pedido a Fast Shop - pedido sem meta '_fastshop_order_id'",
            "approved_order_error_3"  => "Pedido já aprovado na Fast Shop",
            "custom"                  => null,
        ];

        if ( $error_codes[ $error_option ] !== null )
            $message .= $error_codes[ $error_option ];

        elseif ( $custom !== null )
            $message .= $custom;

        else
            $message .= "Erro desconhecido - Falha genérica";

        create_order_note( $channel, $order_id, $message );
    }
}

if ( !function_exists( "update_order_meta" ) )
{
    /**
     * Undocumented function
     *
     * @param   string  $order_id
     * @param   array   $data
     * @return  void
     */
    function update_order_meta( string $order_id, array $data )
    {
        $retry  = 0;
        $failed = false;

        do
        {
            try
            {
                Order::update( $order_id, $data );
                return;
            }
            catch ( Throwable $e )
            {
                $retry++;
                $failed = true;
            }
        }
        while ( $failed && $retry < 3 );
    }
}

if ( !function_exists( "format_product_to_freight" ) )
{
    function format_product_to_freight( array $order )
    {
        $data = [
            "items"      => [],
            "postalCode" => $order[ "shipping" ][ "postcode" ],
        ];

        $products = $order[ "line_items" ];

        foreach ( $products as $product )
        {
            $index = array_search_by_key( "_fs_product_id", "key", $product[ "meta_data" ] );

            $data[ "items" ][] = [
                "product_id" => $product[ "meta_data" ][ $index ][ "value" ],
                "quantity"   => $product[ "quantity"  ],
                "sku"        => $product[ "sku"       ],
            ];
        }

        return $data;
    }
}

if ( !function_exists( "add_sub_category" ) )
{
    /**
     * Recursively adds a child category to its parent in a hierarchical structure.
     *
     * This function searches for the parent of a given child category in the result array
     * or its nested children, and adds the child to the appropriate parent's children array.
     *
     * @param   array   &$result    The array of categories to search through. Passed by reference.
     * @param   array   $child      The child category to be added.
     * @return  void
     */
    function add_sub_category( array &$result, array $child ) : void
    {
        foreach ( $result as &$item )
        {
            if ( $item[ "id" ] === $child[ "parent" ] )
            {
                unset( $child[ "parent" ] );

                $child[ "specifications" ] = [ [
                    "attributeName" => "NONE",
                    "required"      => false,
                    "attributeValues" => [ [ "valueName" => "NONE" ] ]
                ] ];

                $item[ "children" ][]      = $child;
                return;
            }
            elseif ( !empty( $item[ "children" ] ) )
            {
                add_sub_category( result: $item[ "children" ], child: $child );
            }
        }
    }
}

if ( !function_exists( "format_categories" ) )
{
    /**
     * Formats a flat array of category items into a hierarchical structure.
     *
     * @param   array   $items  Array of category items.
     * @return  array           Array of categories organized in a hierarchical structure.
     */
    function format_categories( array $items ) : array
    {
        $result = [];

        foreach ( $items as $item )
        {
            if ( $item[ "parent" ] === "0" )
            {
                unset( $item[ "parent" ] );

                // Root level item.
                $result[ $item[ "id" ] ] = $item;
            }
            else
            {
                // Child item, recursively find the parent and add to its children.
                add_sub_category( result: $result, child: $item );
            }
        }

        return $result;
    }
}

if ( !function_exists( "save_file" ) )
{
    /**
     * Saves an array of data to a file with a timestamped name.
     *
     * @param   string  $name   The base name of the file (without extension).
     * @param   array   $data   The data to be saved as JSON.
     * @return  void
     */
    function save_file( string $name, array $data ) : void
    {
        $date    = date( "Ymd-his" );
        $encoded = json_encode( $data, JSON_UNESCAPED_SLASHES );

        Storage::put( "{$name}-{$date}.json", $encoded );
    }
}

if ( !function_exists( "vtex_get_product_images" ) )
{
    /**
     * Undocumented function
     *
     * @param   array   $images
     * @return  array
     */
    function vtex_get_product_images( array $images )
    {
        // Filter Vtex images.
        $filtered = array_filter( $images, function ( $image ) {
            return validate_image_url( $image[ "ImageUrl" ] );
        } );

        // Map Vtex images to WooCommerce image format.
        $data     = array_map( function ( $image ) {
            return [
                "src"  => explode( "?", $image[ "ImageUrl" ] )[ 0 ],
                "name" => $image[ "ImageName" ],
                "alt"  => $image[ "ImageName" ]
            ];
        }, $filtered );

        return $data;
    }
}

if ( !function_exists( "vtex_get_product_attributes" ) )
{
    /**
     * Undocumented function
     *
     * @param   array   $attributes
     * @return  array
     */
    function vtex_get_product_attributes( array $attributes )
    {
        $specifications = array_map( function ( $attr, $key )
        {
            // TODO possível ajuste qnd em prod

            // // Skip the ProductLineId field.
            // if ( $attr[ "FieldName" ] === "ProductLineId" )
            //     return null;

            // Convert boolean 'true' to 'SIM'.
            $field_value = $attr[ "FieldValues" ][ 0 ] === "true"
                            ? "SIM"
                            : $attr[ "FieldValues" ][ 0 ];

            // Return the transformed attribute.
            return [
                "name"      => $attr[ "FieldName" ],
                "position"  => $key,
                "visible"   => true,
                "variation" => true,
                "options"   => [ $field_value ],
            ];
        }, $attributes, array_keys( $attributes ) );

        return array_filter( $specifications );
    }
}

if ( !function_exists( "vtex_format_categories" ) )
{
    /**
     * Formats category data for VTEX integration.
     *
     * @param   array   $data   Raw category data.
     * @return  array           Formatted and hierarchically organized category data.
     */
    function vtex_format_categories( array $data ) : array
    {
        $categories = [];

        foreach ( $data as $category )
        {
            $categories[] = [
                "id"       => ( string ) $category->id,
                "name"     => $category->name,
                "parent"   => ( string ) $category->parent,
                "children" => [],
            ];
        }

        return format_categories( items: $categories );
    }
}

if ( !function_exists( "vtex_map_order_items" ) )
{
    /**
     * Maps order items from a given logistics array to the format required by VTEX.
     *
     * @param   array   $logistics  The logistics information related to the order items.
     * @param   array   $items      The actual items from the order.
     * @return  array               The order items formatted for VTEX.
     */
    function vtex_map_order_items( array $logistics, array $items ) : array
    {
        // Use array_map to transform each item to the VTEX format using the logistics array.
        return array_map( function ( $item ) use ( $items )
        {
            // Retrieve the corresponding item from the items array using the itemIndex.
            $item_id  = $item[ "itemIndex" ];
            $aux_item = $items[ $item_id ];

            // Return an array with the item data mapped to the VTEX format.
            return [
                "id"       => $aux_item[ "id"       ],
                "price"    => $aux_item[ "price"    ],
                "quantity" => $aux_item[ "quantity" ],
            ];

        }, $logistics );
    }
}

if ( !function_exists( "vtex_map_logistics_info" ) )
{
    /**
     * Maps the logistics information from WooCommerce to the format required by VTEX.
     *
     * @param   array   $data   The logistics information from a WooCommerce order.
     * @return  array           The logistics information formatted for VTEX.
     */
    function vtex_map_logistics_info( array $data ) : array
    {
        // Map logistics information to the VTEX format.
        return array_map( function ( $item )
        {
            // Search for the index of the SLA that corresponds to the "normal delivery" delivery channel.
            $sla          = current( array_filter( $item[ "slas" ], function ( $sla )
            {
                // TODO ? em prod pode mudar isso bem aqui
                return strtolower( $sla[ "id" ] ) === "normal";
            } ) );

            // Return an array with the logistics information mapped to the VTEX format.
            return [
                "itemIndex"               => $item[ "itemIndex" ],
                "selectedDeliveryChannel" => "delivery", // or 'pickup-in-point'
                "lockTTL"                 => "3d",       // 'd' or 'bd'
                "price"                   => $sla[ "price"            ],
                "selecetdSla"             => $sla[ "id"               ],
                "shippingEstimate"        => $sla[ "shippingEstimate" ],
                // "deliveryIds"             => [ "warehouseId" => "" ],
            ];

        }, $data );
    }
}

if ( !function_exists( "vtex_map_client_data" ) )
{
    /**
     * Maps VTEX client data to a standardized format.
     *
     * @param   array   $data   The user data array.
     * @return  array           The mapped client data array.
     */
    function vtex_map_client_data( array $data ) : array
    {
        // Determine if the order is from a corporate entity based on the person type.
        $is_corporate  = isset( $data[ "persontype" ] ) && $data[ "persontype" ] === "J";
        $state_inscrpt = ( $is_corporate && isset( $data[ "state_inscription" ] ) ) ? $data[ "state_inscription" ] : null;

        return [
            "email"             => $data[ "email"      ],
            "firstName"         => $data[ "first_name" ],
            "lastName"          => $data[ "last_name"  ],
            "phone"             => $data[ "phone"      ],
            "document"          => $data[ "cpf"        ],
            "corporateDocument" => $is_corporate ? $data[ "cnpj"    ] : null,
            "corporatePhone"    => $is_corporate ? $data[ "phone"   ] : null,
            "corporateName"     => $is_corporate ? $data[ "company" ] : null,
            "tradeName"         => $is_corporate ? $data[ "company" ] : null,
            "stateInscription"  => $state_inscrpt,
        ];
    }
}

if ( !function_exists( "vtex_map_selected_addresses" ) )
{
    /**
     * Maps VTEX selected address to a standardized format.
     *
     * @param   array   $data   The user data array.
     * @return  array           An array containing a single address object.
     */
    function vtex_map_selected_addresses( array $data ) : array
    {
        $name = implode( " ", [ $data[ "first_name" ], $data[ "last_name" ] ] );

        return [
            [
                "addressId"    => "1",
                "addressType"  => "residential", // or 'commercial', 'pickup'
                "receiverName" => $name,
                "postalCode"   => $data[ "postcode"     ],
                "street"       => $data[ "address_1"    ],
                "number"       => $data[ "number"       ],
                "complement"   => $data[ "address_2"    ],
                "neighborhood" => $data[ "neighborhood" ],
                "city"         => $data[ "city"         ],
                "state"        => $data[ "state"        ],
                "country"      => "BRA",
            ]
        ];
    }
}

if ( !function_exists( "vtex_new_order" ) )
{
    /**
     * Converts WooCommerce order data to the format required by VTEX.
     *
     * @param   string      $seller
     * @param   array       $freight
     * @param   array       $order
     * @param   array|null  $parent
     * @return  array
     */
    function vtex_new_order( string $seller, array $freight, array $order, ?array $parent = null ) : array
    {
        // Generate the URL for the invoice notification endpoint using the order ID
        $url       = route( "api.marketplace.vtex.order.notification.invoice", [ "order_id" => $order[ "id" ] ] );

        if ( config( "app.env" ) == "local" )
            $url = str_replace( "http://127.0.0.1:8000/", "http://php.triibo.com.br/", $url );

        // Extract the VTEX information from the order data.
        $info      = $freight[ "logisticsInfo" ];

        // Retrieve the discount
        $discount  = ( empty( $order[ "coupon_lines" ] ) )
                        ? $order[ "discount_total" ]
                        : ( $order[ "coupon_lines" ][ 0 ][ "discount" ] ?? 0 );

        // Calculate the total payment value for the marketplace.
        $total     = $order[ "total" ] + $discount;
        $total     = number_format( ( float ) $total, 2, ".", "" );
        $total     = ( int ) str_replace( ".", "", $total );

        // Extract billing and shipping information, using parent data if available.
        $billing   = $parent[ "billing"  ] ?? $order[ "billing"  ];
        $shipping  = $parent[ "shipping" ] ?? $order[ "shipping" ];

        $connector = config( "vtex.partners.{$seller}.connector_name" );

        $items     = vtex_map_order_items( $freight[ "logisticsInfo" ], $freight[ "items" ] );
        $client    = vtex_map_client_data( $billing );
        $logistics = vtex_map_logistics_info( $freight[ "logisticsInfo" ] );
        $address   = vtex_map_selected_addresses( $shipping );

        // Return the formatted order data for VTEX.
        return [
            "marketplaceOrderId"      => ( string ) $order[ "id" ],
            "marketplaceOrderStatus"  => "new",  // or 'approved'
            "marketplacePaymentValue" => $total,
            "connectorName"           => $connector,
            "connectorEndpoint"       => $url,
            "allowFranchises"         => false,
            "items"                   => $items,
            "clientProfileData"       => $client,
            "shippingData"            => [
                "isFob"                    => false,
                "isMarketplaceFulfillment" => false,
                "logisticsInfo"            => $logistics,
                "selectedAddresses"        => $address,
            ],
        ];
    }
}

if ( !function_exists( "vtex_update_order" ) )
{
    /**
     * Formats the order data for updating in VTEX.
     *
     * @param   string  $seller     The seller identifier (e.g., fast_shop).
     * @param   string  $order_id   The unique identifier of the order.
     * @return  array               A formatted array containing the updated order data for VTEX.
     */
    function vtex_update_order( string $seller, string $order_id ) : array
    {
        $connector = config( "vtex.partners.{$seller}.connector_name" );

        // Generate the URL for the invoice notification endpoint using the order ID
        $url       = route( "api.marketplace.vtex.order.notification.invoice", [ "order_id" => $order_id ] );

        if ( config( "app.env" ) == "local" )
            $url = str_replace( "http://127.0.0.1:8000/", "http://php.triibo.com.br/", $url );

        // Return the formatted order data for VTEX.
        return [
            "marketplaceOrderId"      => $order_id,
            "marketplaceOrderStatus"  => "approved",
            "connectorName"           => $connector,
            "connectorEndpoint"       => $url,
        ];
    }
}

if ( !function_exists( "vtex_fulfillment_order" ) )
{
    /**
     * Converts WooCommerce order data to the format required by VTEX.
     *
     * @param   array   $data   The original order data from WooCommerce.
     * @return  array           The order data formatted for VTEX.
     */
    function vtex_fulfillment_order( array $data ) : array
    {
        // Extract parent order data if available, otherwise use the current order data.
        $parent   = $data[ "parent" ] ?? null;
        $order    = $data[ "order"  ];

        // Generate the URL for the invoice notification endpoint using the order ID
        $url      = route( "api.marketplace.vtex.order.notification.invoice", [ "order_id" => $order[ "id" ] ] );

        if ( config( "app.env" ) == "local" )
            $url = str_replace( "http://127.0.0.1:8000/", "http://php.triibo.com.br/", $url );

        // Calculate the total payment value for the marketplace.
        $total    = $order[ "total" ] + $discount;
        $total    = number_format( ( float ) $total, 2, ".", "" );
        $total    = ( int ) str_replace( ".", "", $total );

        // Extract billing and shipping information, using parent data if available.
        $billing  = $parent[ "billing"  ] ?? $order[ "billing"  ];
        $shipping = $parent[ "shipping" ] ?? $order[ "shipping" ];

        // Return the formatted order data for VTEX.
        return [
            "marketplaceOrderId"          => $order[ "id" ],
            "marketplaceServicesEndpoint" => $url,
            "marketplacePaymentValue"     => $total,

            // @see https://help.vtex.com/en/tutorial/configuring-price-divergence-rule--awAKP0sS5J8jgLs2g7pPe
            // true if the order was created asynchronously.
            "isCreatedAsync"              => true, // TODO confirmar

            "items"                       => vtex_map_order_items( $info[ "logisticsInfo" ], $info[ "items" ] ),
            "clientProfileData"           => vtex_map_client_data( $billing ),
            "shippingData"                => [
                "address"       => vtex_map_selected_addresses( $shipping ),
                "logisticsInfo" => tex_map_logistics_info( $info[ "logisticsInfo" ] ),
                "updateStatus"  => "updated"
            ],
            "openTextField"               => "",
            "paymentData"                 => null,
            "marketingData"               => [
                "utmSource"    => "",
                "utmMedium"    => "",
                "utmCampaign"  => "",
                "utmiPage"     => "",
                "utmiPart"     => "",
                "utmiCampaign" => "",
            ],
        ];
    }
}

if ( !function_exists( "vtex_order_exec" ) )
{
    /**
     * Undocumented function
     *
     * @param   string      $channel
     * @param   string      $seller
     * @param   array       $freight
     * @param   array       $order
     * @param   array|null  $parent
     * @return  array
     */
    function vtex_order_exec( string $channel, string $seller, array $freight, array $order, ?array $parent = null )
    {
        $connector = new ConnectorService( $seller );
        $service   = new OrderService( $connector );

        try
        {
            $data     = vtex_new_order( $seller, $freight, $order, $parent );
            $response = $service->newOrder( $data );

            if ( $response[ 0 ] != 200 )
            {
                log_message(
                    channel : $channel,
                    level   : "error",
                    message : "VTEX - vtex_order_exec 1",
                    context : [
                        "seller"   => $seller,
                        "response" => $response,
                    ]
                );

                $data = [
                    "error_code" => 5001,
                    "message"    => "Something wrong when creating order",
                    "data"       => null,
                ];

                if ( isset( $response[ 1 ][ "data" ] ) )
                {
                    $data = [
                        "error_code" => $response[ 1 ][ "data" ][ "code"    ],
                        "message"    => $response[ 1 ][ "data" ][ "message" ],
                        "data"       => $response[ 1 ][ "data" ],
                    ];
                }

                return $data;
            }

            return $response[ 1 ];
        }
        catch( Throwable $e )
        {
            // Log the exception for debugging purposes.
            log_exception(
                channel : $channel,
                message : $e->getMessage(),
                method  : "VTEX - vtex_order_exec 2",
                type    : "Throwable",
                e       : $e
            );

            return [
                "error_code" => 5000, // 5000 - erro generico
                "message"    => $e->getMessage(),
                "data"       => $order,
            ];
        }
    }
}

if ( !function_exists( "vtex_update_order_exec" ) )
{
    /**
     * Undocumented function
     *
     * @param   string      $channel
     * @param   string      $seller
     * @param   string      $order_id
     * @return  array
     */
    function vtex_update_order_exec( string $channel, string $seller, string $order_id )
    {
        $connector = new ConnectorService( $seller );
        $service   = new OrderService( $connector );

        try
        {
            $data     = vtex_update_order( $seller, $order_id );
            $response = $service->updateOrderStatus( $data );

            if ( $response[ 0 ] != 200 )
            {
                log_message(
                    channel : $channel,
                    level   : "error",
                    message : "VTEX - vtex_update_order_exec 1",
                    context : [
                        "seller"   => $seller,
                        "response" => $response,
                    ]
                );

                $data = [
                    "error_code" => 5001,
                    "message"    => "Something wrong when creating order",
                    "data"       => null,
                ];

                if ( isset( $response[ 1 ][ "data" ] ) )
                {
                    $data = [
                        "error_code" => $response[ 1 ][ "data" ][ "code"    ],
                        "message"    => $response[ 1 ][ "data" ][ "message" ],
                        "data"       => $response[ 1 ][ "data" ],
                    ];
                }

                return $data;
            }

            return $response[ 1 ];
        }
        catch( Throwable $e )
        {
            // Log the exception for debugging purposes.
            log_exception(
                channel : $channel,
                message : $e->getMessage(),
                method  : "VTEX - vtex_update_order_exec 2",
                type    : "Throwable",
                e       : $e
            );

            return [
                "error_code" => 5000, // 5000 - erro generico
                "message"    => $e->getMessage(),
                "data"       => $order,
            ];
        }
    }
}

if ( !function_exists( "vtex_freight_prepare" ) )
{
    /**
     * Converts WooCommerce freight data to the format required by VTEX.
     *
     * @param   array   $data       The original freight data from WooCommerce.
     * @param   string  $seller     The seller identifier (e.g., fast_shop).
     * @return  array               The freight data formatted for VTEX.
     */
    function vtex_freight_prepare( array $data, string $seller = "1" ) : array
    {
        return [
            // Maps each line item to the desired structure.
            "items" => array_map( function ( $item ) use ( $seller )
            {
                return [
                    "id"       => $item[ "product_id" ],
                    "quantity" => $item[ "quantity"   ],
                    "seller"   => $seller,
                ];

            }, $data[ "items" ] ),

            "isCheckedIn" => false,
            "country"     => "BRA",
            "selectedSla" => "Normal",
            "postalCode"  => $data[ "postalCode" ],
        ];
    }
}

if ( !function_exists( "vtex_freight_exec" ) )
{
    /**
     * Undocumented function
     *
     * @param   string  $channel
     * @param   string  $seller
     * @param   array   $wc_data
     * @return  JsonResponse|array
     */
    function vtex_freight_exec( string $channel, string $seller, array $wc_data, bool $full_data = false ) : JsonResponse|array
    {
        $connector = new ConnectorService( $seller );
        $service   = new FulfillmentService( $connector );

        try
        {
            $req_data   = vtex_freight_prepare( $wc_data );
            $response   = $service->simulate( $req_data );

            if ( $response[ 0 ] != 200 )
            {
                log_message(
                    channel : $channel,
                    level   : "error",
                    message : "VTEX - vtex_freight_exec",
                    context : [
                        "seller"   => $seller,
                        "response" => $response,
                    ]
                );

                if ( $full_data )
                    return $response[ 1 ];

                return response()->json( [
                    "error_code" => 5001,
                    "message"    => "Something wrong when consulting the delivery price",
                    "data"       => null,
                ], 400 );
            }

            $vtex_data  = $response[ 1 ][ "data" ];
            $data       = [];

            if ( $full_data )
                return $response[ 1 ];

            $translated = vtex_freight_to_woo( $vtex_data );

            foreach ( $req_data[ "items" ] as $product )
            {
                if ( isset( $translated[ $product[ "id" ] ] ) )
                {
                    $temp_data = $translated[ $product[ "id" ] ];
                    $code      = $temp_data[ "code"    ];
                    $message   = $temp_data[ "message" ];

                    unset(
                        $temp_data[ "code"    ],
                        $temp_data[ "message" ]
                    );
                }
                else
                {
                    $code      = 5002;
                    $message   = "Something wrong when consulting the delivery price";
                    $temp_data = null;
                }

                $wc_index        = array_search_by_key( $product[ "id" ], "product_id", $wc_data[ "items" ] );
                $wc_sku          = $wc_data[ "items" ][ $wc_index ][ "sku" ];

                $data[ $wc_sku ] = [
                    "error_code" => $code,
                    "message"   => $message,
                    "data"      => $temp_data,
                ];
            }

            return response()->json( [
                "error_code" => null,
                "message"    => "ok",
                "data"       => $data,
            ], 200 );
        }
        catch ( Throwable $e )
        {
            // Log the exception for debugging purposes.
            log_exception(
                channel : $channel,
                message : $e->getMessage(),
                method  : "VTEX - vtex_freight_exec",
                type    : "Throwable",
                e       : $e
            );

            $data = [
                "error_code" => 5000, // 5000 - erro generico
                "message"    => $e->getMessage(),
                "data"       => $wc_data[ "items" ],
            ];

            if ( $full_data ) return $data;

            return response()->json( $data, 500 );
        }
    }
}

if ( !function_exists( "vtex_product_to_mkt" ) )
{
    function vtex_product_to_mkt( array $data, array $simulate ) : array
    {
        // Vtex categories to WooCommerce category format.
        $categories = get_categories_ids( $data[ "ProductCategoryIds" ], $data[ "ProductCategories" ] );

        $images     = vtex_get_product_images( $data[ "Images" ] );

        // Get product attributes from Fast Shop data.
        $attributes = vtex_get_product_attributes( array_merge( $data[ "SkuSpecifications" ], $data[ "ProductSpecifications" ] ) );

        // Return the WooCommerce product array.
        return [
            "name"              => $data[ "ProductName" ],
            "slug"              => Str::slug( $data[ "ProductName" ] ),
            "description"       => $data[ "ProductDescription" ],
            "short_description" => $data[ "ProductDescription" ],
            "sku"               => "FS-" . $data[ "ProductRefId" ],
            "regular_price"     => isset( $simulate[ "items" ] ) ? convert_price( $simulate[ "items" ][ 0 ][ "price"        ] ?? 0 ) : 0,
            "sale_price"        => isset( $simulate[ "items" ] ) ? convert_price( $simulate[ "items" ][ 0 ][ "sellingPrice" ] ?? 0 ) : 0,
            "manage_stock"      => false,                                                                                     // TODO ?
            // "stock_quantity"    => 0,                                                                                      // TODO ?
            "stock_status"      => isset( $simulate[ "items" ] ) ? ( ( $simulate[ "items" ][ 0 ][ "availability" ] == "available" ) ? "instock" : "outofstock" ) : "outofstock", // TODO ?
            "backorders"        => "no",
            "sold_individually" => false,
            "weight"            => ( string ) $data[ "Dimension" ][ "weight" ],
            "dimensions"        => [
                "length" => ( string ) $data[ "Dimension" ][ "length" ],
                "width"  => ( string ) $data[ "Dimension" ][ "width"  ],
                "height" => ( string ) $data[ "Dimension" ][ "height" ]
            ],
            "shipping_class"    => "fast-shop",
            "upsell_ids"        => [],
            "cross_sell_ids"    => [],
            "parent_id"         => 0,
            "purchase_note"     => "",
            "tags"              => [],
            "categories"        => $categories,
            "images"            => $images,
            "attributes"        => $attributes,
            "meta_data"         => [
                [
                    "key"   => "_fs_product_id",
                    "value" => $data[ "ProductId" ],
                ]
            ]
        ];
    }
}

if ( !function_exists( "vtex_freight_to_woo"  ) )
{
    /**
     * Undocumented function
     *
     * @param   array   $data
     * @return  array
     */
    function vtex_freight_to_woo( array $data )
    {
        if ( !empty( $data[ "logisticsInfo" ] ) && !empty( $data[ "items" ] ) )
        {
            $logistics = [];

            foreach ( $data[ "logisticsInfo" ] as $log_info )
            {
                $deliveryChannel = current( array_filter( $log_info[ "deliveryChannels" ], function ( $channel )
                {
                    return $channel[ "id" ] === "delivery";
                } ) );

                $slas            = current( array_filter( $log_info[ "slas" ], function ( $sla )
                {
                    // TODO ? em prod pode mudar isso bem aqui
                    return strtolower( $sla[ "id" ] ) === "normal";
                } ) );

                if ( $deliveryChannel && $slas )
                {
                    $hub     = strtolower( $slas[ "id" ] );
                    $price   = $slas[ "price"            ];
                    $time    = $slas[ "shippingEstimate" ];

                    $code    = null;
                    $message = "ok";
                }
                else
                {
                    $price   = 0;
                    $time    = "";
                    $hub     = "";
                    $code    = 4000;
                    $message = "Delivery not found";
                }

                $itemIndex = $log_info[ "itemIndex" ] ?? null;

                if ( $itemIndex !== null && isset( $data[ "items" ][ $itemIndex ] ) )
                {
                    $product                       = $data[ "items" ][ $itemIndex ];

                    $logistics[ $product[ "id" ] ] = [
                        "_fs_product_id" => $product[ "id"           ],
                        "quantity"       => $product[ "quantity"     ],
                        "availability"   => $product[ "availability" ] == "available" ? true : false,
                        "delivery_price" => convert_price( $price ),
                        "delivery_time"  => convert_delivery_time( $time ),
                        "sale_price"     => convert_price( $product[ "price"     ] ),
                        "regular_price"  => convert_price( $product[ "listPrice" ] ),
                        "hub"            => $hub,
                        "code"           => $code,
                        "message"        => $message,
                    ];
                }
            }

            return $logistics;
        }

        if ( empty( $logistics ) )
            return vtex_freight_to_woo_failed( $data );
    }
}

if ( !function_exists( "vtex_freight_to_woo_failed" ) )
{
    /**
     * Undocumented function
     *
     * @param   array   $data
     * @return  array
     */
    function vtex_freight_to_woo_failed( array $data )
    {
        $logistics = [];

        if ( !empty( $data[ "items" ] ) )
        {
            foreach ( $data[ "items" ] as $product )
            {
                $logistics[ $product[ "id" ] ] = [
                    "_fs_product_id" => $product[ "id"       ],
                    "quantity"       => $product[ "quantity" ],
                    "availability"   => false,
                    "delivery_price" => "0.00",
                    "delivery_time"  => "0",
                    "sale_price"     => convert_price( $product[ "price"     ] ),
                    "regular_price"  => convert_price( $product[ "listPrice" ] ),
                    "hub"            => "",
                    "code"           => 4001,
                    "message"        => "SKU currently unavailable",
                ];
            }
        }

        return $logistics;
    }
}

if ( !function_exists( "send_notification" ) )
{
    function send_notification( $parent, $order, $error_option )
    {
        $data        = get_email_order_error( $parent, $order );

        $error_codes = [
            "new_order_error"         => "Falha na criação do pedido abaixo na Fast Shop",
            "cancelation_order_error" => "Falha no cancelamento do pedido abaixo na Fast Shop",
            "cancelation_order_fs"    => "Pedido cancelado pela Fast Shop",
            "approved_order_error_1"  => "Falha ao informar sobre a aprovação do pedido abaixo na Fast Shop",
            "approved_order_error_2"  => "Falha ao informar sobre a aprovação do pedido abaixo na Fast Shop - pedido sem meta '_fastshop_order_id'",
            "approved_order_error_3"  => "Pedido já aprovado na Fast Shop"
        ];

        $error_title = $error_codes[ $error_option ] ?? "Erro desconhecido - Falha genérica";

        $order_error = new OrderError( [
            "subject" => "Falha em pedido Fast Shop",
            "title"   => $error_title,
            "data"    => $data,
        ] );

        $to_notify    = [
            "mateus.costa@triibo.com.br",
            "equipemarketplace@triibo.com.br",
        ];

        $users        = User::whereIn( "email", $to_notify )->get();

        foreach ( $users as $user )
            $user->notify( $order_error );
    }
}
