<?php

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use App\Notifications\FastShop\OrderError;
use Illuminate\Support\Facades\{ DB, Storage };
use Codexshaper\WooCommerce\Facades\{ Category, Note, Order };
use App\Services\Vtex\{ OrderService, ConnectorService, FulfillmentService };

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
        return DB::table( "marketplace_product_categories" )
                 ->get( [ "cat_id", "cat_name", "cat_parent" ] )
                 ->toArray();
    }
}

if ( !function_exists( "get_categories_ids" ) )
{
    /**
     * Retrieves category IDs based on provided IDs and names.
     *
     * @param   string  $ids    A string containing category IDs separated by slashes.
     * @param   array   $names  An associative array where keys are category IDs and values are category names.
     * @return  array           An array of associative arrays, each containing the 'id' of a category.
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
                try
                {
                    $search = Category::where( "slug", $slug )->first();

                    if ( $search->isNotEmpty() )
                    {
                        $new = [
                            "id"     => $search[ "id"     ],
                            "slug"   => $search[ "slug"   ],
                            "name"   => $search[ "name"   ],
                            "parent" => $search[ "parent" ],
                        ];
                    }
                    else
                    {
                        $parent = ( $index == 0 ) ? 0 : $categories[ $index - 1 ];

                        $new    = Category::create( [
                            "name"   => $names[ $vtex_category_id ],
                            "parent" => $parent,
                        ] );
                    }

                    $categories[] = $new[ "id" ];

                    DB::table( "marketplace_product_categories" )->insert( [
                        "cat_id"     => $new[ "id"     ],
                        "cat_slug"   => $new[ "slug"   ],
                        "cat_name"   => $new[ "name"   ],
                        "cat_parent" => $new[ "parent" ],
                    ] );
                }
                catch ( Throwable $e )
                {
                    if ( $e->getMessage() != "JSON ERROR: Syntax error" )
                    {
                        log_message(
                            channel : $channel,
                            level   : "error",
                            message : "VTEX - vtex_get_order 1",
                            context : [
                                "seller"   => $seller,
                                "response" => $response,
                            ]
                        );
                    }
                }
            }
        }

        return $categories;
    }
}

if ( !function_exists( "get_email_order_error" ) )
{
    /**
     * Retrieves email order error details based on the provided parent and order arrays.
     *
     * @param   array|null  $parent     An optional array containing parent order details.
     * @param   array|null  $order      An optional array containing order details.
     * @return  array                   An associative array with order and customer information.
     */
    function get_email_order_error( ?array $parent = null, ?array $order = null ) : array
    {
        if ( !empty( $parent ) )
        {
            $order_id     = $parent[ "id" ] ?? "";
            $sub_order_id = $order[  "id" ] ?? "";
        }
        else
        {
            $order_id     = $order[ "id" ] ?? "";
            $sub_order_id = "";
        }

        $customer_id = $order[ "customer_id" ] ?? "";
        $billing     = $order[ "billing"     ] ?? "";

        $f_name      = $billing[ "first_name" ] ?? "";
        $l_name      = $billing[ "last_name"  ] ?? "";

        return [
            "order_id"     => $order_id,
            "sub_order_id" => $sub_order_id,
            "customer_id"  => $customer_id,
            "name"         => implode( " ", [ $f_name, $l_name ] ),
            "email"        => $billing[ "email" ] ?? "",
            "phone"        => $billing[ "phone" ] ?? "",
        ];
    }
}

if ( !function_exists( "get_partner" ) )
{
    /**
     * Get partner index.
     *
     * @param   string  $affiliated_id
     * @return  string|null
     */
    function get_partner( string $affiliated_id ) : string|null
    {
        $partners = config( "vtex.partners" );

        foreach ( $partners as $index => $data )
        {
            $env = $data[ "env" ];

            if ( isset( $data[ $env ][ "affiliated_id" ] ) && $data[ $env ][ "affiliated_id" ] === $affiliated_id )
                return $index;
        }

        return null;
    }
}

if ( !function_exists( "get_wc_order" ) )
{
    /**
     * Retrieves a WooCommerce order and its parent order if applicable.
     *
     * @param   string  $channel    The logging channel to use for error logging.
     * @param   string  $order_id   The ID of the order to retrieve.
     * @param   string  $seller_id  The ID of the seller associated with the order.
     * @return  array               An array containing the parent order and the order details, or [false, false] in case of an error.
     */
    function get_wc_order( string $channel, string $order_id, string $seller_id ) : array
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
            if ( $e->getMessage() != "JSON ERROR: Syntax error" )
            {
                // Log the exception for debugging purposes.
                log_exception(
                    channel : $channel,
                    message : $e->getMessage(),
                    method  : "get_wc_order",
                    type    : "Throwable",
                    e       : $e
                );

                $order = [ "id" => $order_id ];

                send_notification( null, $order, "error_mkt_order" );

                return [ false, false ];
            }
        }

        $store_id = config( "woocommerce.partners.{$seller_id}.store_id" );

        if ( !$order || $order[ "store" ][ "id" ] != $store_id )
            return [ null, null ];

        return [ $parent, $order ];
    }
}

if ( !function_exists( "create_order_note" ) )
{
    /**
     * Creates a note for a specific order.
     *
     * @param   string  $channel    The logging channel to use for error logging.
     * @param   string  $order_id   The ID of the order to which the note will be added.
     * @param   string  $message    The message content of the note.
     * @return  void
     */
    function create_order_note( string $channel, string $order_id, string $message ) : void
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
            message : "Error trying to create note: '{$message}'",
            method  : "create_order_note",
            type    : "Throwable",
            e       : $e ?? null
        );
    }
}

if ( !function_exists( "create_error_note" ) )
{
    /**
     * Creates an error note for a specific order.
     *
     * @param   string          $channel        The logging channel to use for error logging.
     * @param   string          $order_id       The ID of the order to which the error note will be added.
     * @param   string          $error_option   The specific error code to generate the message.
     * @param   string|null     $custom         An optional custom message to include in the note.
     * @return  void
     */
    function create_error_note( string $channel, string $order_id, string $error_option, ?string $custom = null ) : void
    {
        $message     = "ERROR FS\n";
        $error_codes = [
            // "order_approval_error_1" => "Falha ao informar sobre a aprovação do pedido a Fast Shop",
            "order_approval_error_2" => "Falha ao informar sobre a aprovação do pedido a Fast Shop - pedido sem meta '_fastshop_order_id'",
            "order_approval_error_3" => "Pedido já aprovado na Fast Shop",

            "order_creation_error_1" => "Falha na consulta do frete",
            "order_creation_error_2" => "Falha na criação | SKU indisponível",

            "custom"                 => null,
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
     * Updates the metadata for a specific order.
     *
     * @param   string  $channel    The logging channel to use for error logging.
     * @param   string  $order_id   The ID of the order whose metadata will be updated.
     * @param   array   $data       An associative array of metadata to update.
     * @return  void
     */
    function update_order_meta( string $channel, string $order_id, array $data ) : void
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

        log_exception(
            channel : $channel,
            message : "Error trying to update order: #{$order_id} " . json_encode( $data ),
            method  : "create_order_note",
            type    : "Throwable",
            e       : $e ?? null
        );
    }
}

if ( !function_exists( "format_product_to_freight" ) )
{
    /**
     * Formats the order products for freight calculation.
     *
     * @param   array   $order  The order array containing shipping and line items information.
     * @return  array           The formatted data including items and postal code.
     */
    function format_product_to_freight( array $order ) : array
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

if ( !function_exists( "vtex_append_voltage" ) )
{
    /**
     * Append voltage information to the short description.
     *
     * @param   string  $short_desc
     * @param   array   $attributes
     * @return  string
     */
    function vtex_append_voltage( string $short_desc, array $attributes ) : string
    {
        // Find the voltage attribute, if it exists.
        $volt = current( array_filter( $attributes, function ( $attr )
        {
            return Str::lower( $attr[ "name" ] ) === "voltagem";
        } ) );

        // Append voltage information to the short description.
        if ( $volt )
        {
            $value = reset( $volt[ "options" ] );
            $value = in_array( $value, [ "110", "220" ] )
                        ? "<br><b>Voltagem : {$value}V</b>"
                        : $value;

            if ( $value !== "NA" ) $short_desc .= $value;
        }

        return $short_desc;
    }
}

if ( !function_exists( "vtex_get_product_images" ) )
{
    /**
     * Retrieves and formats product images from Vtex.
     *
     * @param   array   $images     The array of images from Vtex.
     * @return  array               The formatted array of images suitable for WooCommerce.
     */
    function vtex_get_product_images( array $images ) : array
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
     * Transforms Vtex product attributes into a structured format.
     *
     * @param   array   $attributes     The array of attributes from Vtex.
     * @return  array                   The filtered and transformed array of product attributes.
     */
    function vtex_get_product_attributes( array $attributes ) : array
    {
        $specifications = array_map( function ( $attr, $key )
        {
            $field_value = strip_tags( $attr[ "FieldValues" ][ 0 ] );

            $field_value = $field_value === "true" ? "SIM" : $attr[ "FieldValues" ][ 0 ];
            $field_value = $field_value === "S"    ? "Sim" : $field_value;
            $field_value = $field_value === "N"    ? "Não" : $field_value;

            $field_name  = $attr[ "FieldName" ];

            if ( $field_name === "CONT_ESPECIAL_DESKTOP" )
                $visible = false;

            elseif ( $field_name === "CONT_ESPECIAL_MOBILE" )
                $visible = false;

            elseif ( $field_name === "meli_shipping_mode"   )
                $visible = false;

            elseif ( $field_name === "MARKETPLACESTORE"     )
                $visible = false;

            elseif ( $field_name === "Parcelamento"         )
                $visible = false;

            else
                $visible = true;

            // Return the transformed attribute.
            return [
                "name"      => $field_name,
                "position"  => $key,
                "visible"   => $visible,
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
                "id"       => ( string ) $category->cat_id,
                "name"     =>            $category->cat_name,
                "parent"   => ( string ) $category->cat_parent,
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
        $is_corporate = isset( $data[ "persontype" ] ) && $data[ "persontype" ] === "J";
        // Determine if the order is from a corporate entity based on documents field.
        $is_corporate = $is_corporate || ( empty( $data[ "cpf" ] ) && !empty( $data[ "cnpj" ] ) );

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
            "stateInscription"  => $is_corporate && isset( $data[ "ie" ] ) ? $data[ "ie" ] : null,
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

if ( !function_exists( "vtex_get_order" ) )
{
    /**
     * Retrieves an order from VTEX.
     *
     * @param   string  $channel    The logging channel.
     * @param   string  $seller     The seller identifier.
     * @param   string  $order_id   The ID of the order to retrieve.
     * @return  array               The response data containing order details or error information.
     */
    function vtex_get_order( string $channel, string $seller, string $order_id ) : array
    {
        $connector = new ConnectorService( $seller );
        $service   = new OrderService( $connector );

        try
        {
            $response = $service->getOrder( $order_id );

            if ( $response[ 0 ] != 200 )
            {
                log_message(
                    channel : $channel,
                    level   : "error",
                    message : "VTEX - vtex_get_order 1",
                    context : [
                        "seller"   => $seller,
                        "response" => $response,
                    ]
                );

                $data = [
                    "error_code" => 5001,
                    "message"    => "Something wrong when retrieving the order",
                    "data"       => null,
                ];

                if ( isset( $response[ 1 ][ "data" ] ) )
                {
                    $error = $response[ 1 ][ "data" ][ "error" ] ?? $response[ 1 ][ "data" ][ "code" ];
                    $data  = [
                        "error_code" => $error[ "code"    ],
                        "message"    => $error[ "message" ],
                        "data"       => $error,
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
                method  : "VTEX - vtex_get_order 2",
                type    : "Throwable",
                e       : $e
            );

            return [
                "error_code" => 5000, // 5000 - erro generico
                "message"    => $e->getMessage(),
                "data"       => [ $order_id, $response ],
            ];
        }
    }
}

if ( !function_exists( "vtex_cancel_order" ) )
{
    /**
     * Cancels an order in VTEX.
     *
     * @param   string  $channel    The logging channel.
     * @param   string  $seller     The seller identifier.
     * @param   string  $order_id   The ID of the order to cancel.
     * @return  array               The response data indicating success or error information.
     */
    function vtex_cancel_order( string $channel, string $seller, string $order_id ) : array
    {
        $connector = new ConnectorService( $seller );
        $service   = new OrderService( $connector );

        try
        {
            $response = $service->cancelOrder( $order_id );

            if ( $response[ 0 ] != 200 )
            {
                log_message(
                    channel : $channel,
                    level   : "error",
                    message : "VTEX - vtex_cancel_order 1",
                    context : [
                        "seller"   => $seller,
                        "response" => $response,
                    ]
                );

                $data = [
                    "error_code" => 5001,
                    "message"    => "Something wrong when cancelling the order",
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
                method  : "VTEX - vtex_cancel_order 2",
                type    : "Throwable",
                e       : $e
            );

            return [
                "error_code" => 5000, // 5000 - erro generico
                "message"    => $e->getMessage(),
                "data"       => [ $order_id, $response ],
            ];
        }
    }
}

if ( !function_exists( "vtex_new_order_prepare" ) )
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
    function vtex_new_order_prepare( string $seller, array $freight, array $order, ?array $parent = null ) : array
    {
        // Generate the URL for the invoice notification endpoint using the order ID
        $url       = route( "api.marketplace.vtex.order.notification.invoice", [ "order_id" => $order[ "id" ] ] );
        $url       = explode( "/pub/orders/", $url )[ 0 ];

        if ( config( "app.env" ) == "local" )
            $url = str_replace( "http://127.0.0.1:8000/", "http://php.triibo.com.br/", $url );

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

if ( !function_exists( "vtex_new_order_exec" ) )
{
    /**
     * Executes the creation of a new order in VTEX.
     *
     * @param   string      $channel    The logging channel.
     * @param   string      $seller     The seller identifier.
     * @param   array       $freight    The freight information for the order.
     * @param   array       $order      The order details.
     * @param   array|null  $parent     Optional parent order data.
     * @return  array                   The response data indicating success or error information.
     */
    function vtex_new_order_exec( string $channel, string $seller, array $freight, array $order, ?array $parent = null ) : array
    {
        $connector = new ConnectorService( $seller );
        $service   = new OrderService( $connector );

        try
        {
            $data     = vtex_new_order_prepare( $seller, $freight, $order, $parent );
            $response = $service->newOrder( $data );

            if ( $response[ 0 ] != 200 )
            {
                log_message(
                    channel : $channel,
                    level   : "error",
                    message : "VTEX - vtex_new_order_exec 1",
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
                method  : "VTEX - vtex_new_order_exec 2",
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

if ( !function_exists( "vtex_update_order_prepare" ) )
{
    /**
     * Formats the order data for updating in VTEX.
     *
     * @param   string  $seller     The seller identifier (e.g., fast_shop).
     * @param   string  $order_id   The unique identifier of the order.
     * @return  array               A formatted array containing the updated order data for VTEX.
     */
    function vtex_update_order_prepare( string $seller, string $order_id ) : array
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

if ( !function_exists( "vtex_update_order_exec" ) )
{
    /**
     * Executes the update of an order status in VTEX.
     *
     * @param   string  $channel    The logging channel.
     * @param   string  $seller     The seller identifier.
     * @param   string  $order_id   The ID of the order to update.
     * @return  array               The response data indicating success or error information.
     */
    function vtex_update_order_exec( string $channel, string $seller, string $order_id ) : array
    {
        $connector = new ConnectorService( $seller );
        $service   = new OrderService( $connector );

        try
        {
            $data     = vtex_update_order_prepare( $seller, $order_id );
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
     * Executes the freight calculation in VTEX.
     *
     * @param   string  $channel    The logging channel.
     * @param   string  $seller     The seller identifier.
     * @param   array   $wc_data    The WooCommerce data for the freight calculation.
     * @param   bool    $full_data  Optional flag to return full data.
     * @return  JsonResponse|array  The response data indicating success or error information.
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

if ( !function_exists( "vtex_product_to_mkt" ) )
{
    /**
     * Converts a Vtex product data structure to a WooCommerce product format.
     *
     * @param   array   $data       The product data from Vtex.
     * @param   array   $simulate   The simulation data for pricing.
     * @return  array               The formatted WooCommerce product array.
     */
    function vtex_product_to_mkt( array $data, array $simulate ) : array
    {
        // Vtex categories to WooCommerce category format.
        $categories = get_categories_ids( $data[ "ProductCategoryIds" ], $data[ "ProductCategories" ] );

        $images     = vtex_get_product_images( $data[ "Images" ] );

        // Get product attributes from Fast Shop data.
        $attributes = vtex_get_product_attributes( array_merge( $data[ "SkuSpecifications" ], $data[ "ProductSpecifications" ] ) );

        $short_desc = strip_tags( $data[ "ProductDescription" ] );
        $short_desc = Str::limit( $short_desc, 140, " (...)" );
        $short_desc = vtex_append_voltage( short_desc: $short_desc, attributes: $attributes );

        // Return the WooCommerce product array.
        return [
            "name"              => $data[ "ProductName" ],
            "slug"              => Str::slug( $data[ "ProductName" ] ),
            "description"       => $data[ "ProductDescription" ],
            "short_description" => $short_desc,
            "sku"               => "FS-" . $data[ "ProductRefId" ],
            "regular_price"     => isset( $simulate[ "items" ] ) ? convert_price( $simulate[ "items" ][ 0 ][ "price"        ] ?? 0 ) : 0,
            "sale_price"        => isset( $simulate[ "items" ] ) ? convert_price( $simulate[ "items" ][ 0 ][ "sellingPrice" ] ?? 0 ) : 0,

            // TODO ?
            // "stock_quantity"    => 0,
            // TODO ?
            "manage_stock"      => false,
            // TODO ?
            "stock_status"      => isset( $simulate[ "items" ] ) ? ( ( $simulate[ "items" ][ 0 ][ "availability" ] == "available" ) ? "instock" : "outofstock" ) : "outofstock",

            "backorders"        => "no",
            "sold_individually" => false,
            "weight"            => ( string ) $data[ "Dimension" ][ "weight" ],
            "dimensions"        => [
                "length" => ( string ) $data[ "Dimension" ][ "length" ],
                "width"  => ( string ) $data[ "Dimension" ][ "width"  ],
                "height" => ( string ) $data[ "Dimension" ][ "height" ]
            ],
            "shipping_class"    => "fast-shop-normal",
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
                    "value" => $data[ "Id" ],
                ]
            ]
        ];
    }
}

if ( !function_exists( "vtex_freight_to_woo"  ) )
{
    /**
     * Converts Vtex freight information to WooCommerce format.
     *
     * @param   array   $data   The freight data from Vtex.
     * @return  array           The formatted logistics information for WooCommerce.
     */
    function vtex_freight_to_woo( array $data ) : array
    {
        $logistics = [];

        if ( !empty( $data[ "logisticsInfo" ] ) && !empty( $data[ "items" ] ) )
        {
            foreach ( $data[ "logisticsInfo" ] as $log_info )
            {
                $deliveryChannel = current( array_filter( $log_info[ "deliveryChannels" ], function ( $channel )
                {
                    return $channel[ "id" ] === "delivery";
                } ) );

                $slas            = current( array_filter( $log_info[ "slas" ], function ( $sla )
                {
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
     * Handles the failure case for converting Vtex freight information to WooCommerce format.
     *
     * @param   array   $data   The freight data from Vtex.
     * @return  array           The logistics information indicating failure.
     */
    function vtex_freight_to_woo_failed( array $data ) : array
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
    /**
     * Sends a notification regarding an order error.
     *
     * @param   array|null  $parent         The parent order data, if available.
     * @param   array|null  $order          The order data.
     * @param   string      $error_option   The error option to determine the notification message.
     * @return  void
     */
    function send_notification( ?array $parent = null, ?array $order = null, string $error_option = "" ) : void
    {
        $data        = get_email_order_error( $parent, $order );

        $error_codes = [
            "order_approval_error_1"   => "Falha ao informar a Fast Shop sobre a aprovação do pedido",
            "order_approval_error_2"   => "Falha ao informar a Fast Shop sobre a aprovação do pedido - pedido sem meta '_fastshop_order_id'",
            "order_approval_error_3"   => "Pedido já aprovado na Fast Shop",

            "order_cancellation_e1"    => "Falha ao recuperar pedido na Fast Shop",
            "order_cancellation_e2"    => "Falha ao cancelar pedido na Fast Shop",
            "order_cancellation_e3"    => "Falha ao informar a Fast Shop sobre o cancelamento do pedido",
            "order_cancelled"          => "Pedido cancelado pela Fast Shop",

            "order_creation_error"     => "Falha na criação do pedido abaixo na Fast Shop",

            "error_mkt_order"          => "Falha ao recuperar pedido do Marketplace",
            "error_mkt_order_creation" => "Falha ao recuperar pedido do Marketplace na criação do pedido na Fast Shop",
            "error_mkt_order_approval" => "Falha ao recuperar pedido do Marketplace na aprovação do pedido na Fast Shop",
            "error_order_cancellation" => "Falha ao recuperar pedido do Marketplace no cancelamento do pedido na Fast Shop",

            "invoice_error"            => "Notificação enviado pela Fast Shop não mapeada",
        ];

        $error_title = $error_codes[ $error_option ] ?? "Erro desconhecido - Falha genérica";

        $order_error = new OrderError( [
            "subject" => "Falha em pedido Fast Shop",
            "title"   => $error_title,
            "data"    => $data,
        ] );

        $users       = User::whereIn( "email", [
            "mateus.costa@triibo.com.br",
            "equipemarketplace@triibo.com.br",
        ] )->get();

        foreach ( $users as $user )
            $user->notify( $order_error );
    }
}

if ( !function_exists( "process_handle_key_missing" ) )
{
    /**
     * Processes the handling of a missing key in the order.
     *
     * @param   string      $channel    The logging channel.
     * @param   array|null  $parent     The parent order data, if available.
     * @param   array       $order      The order data.
     * @param   array       $data       The data related to the missing key.
     * @return  JsonResponse            The JSON response indicating the error.
     */
    function process_handle_key_missing( string $channel, array $parent = null, array $order, array $data ) : JsonResponse
    {
        log_message(
            channel : $channel,
            message : $data[ "function" ],
            level   : "error",
            context : [ "order_id" => $order[ "id" ] ],
        );

        send_notification( $parent, $order, $data[ "type" ] );

        create_error_note( $channel, $order[ "id" ], $data[ "type" ] );

        return response()->json( $data[ "response" ], 400 );
    }
}

if ( !function_exists( "handle_order_meta_fs_id_missing" ) )
{
    /**
     * Handles the case where the Fast Shop ID is missing from the order metadata.
     *
     * @param   string      $channel    The logging channel.
     * @param   array|null  $parent     The parent order data, if available.
     * @param   array       $order      The order data.
     * @param   string      $function   The function name that triggered the check.
     * @return  mixed|null              The result of the process handling or null.
     */
    function handle_order_meta_fs_id_missing( string $channel, array $parent = null, array $order, string $function ) : mixed
    {
        $data = [
            "type"     => "order_approval_error_2",
            "function" => "VTEX - {$function} 1",
            "response" => [
                "error_code" => "4002",
                "message"    => "Order does not have meta_data '_fastshop_order_id'"
            ]
        ];

        $meta_key = array_search( "_fastshop_order_id", array_column( $order[ "meta_data" ], "key" ) );

        if ( $meta_key === false )
            return process_handle_key_missing( $channel, $parent, $order, $data );

        return null;
    }
}

if ( !function_exists( "handle_order_meta_fs_approval_missing" ) )
{
    /**
     * Handles the case where the Fast Shop approval metadata is missing from the order.
     *
     * @param   string      $channel    The logging channel.
     * @param   array|null  $parent     The parent order data, if available.
     * @param   array       $order      The order data.
     * @param   string      $function   The function name that triggered the check.
     * @return  mixed|null              The result of the process handling or null.
     */
    function handle_order_meta_fs_approval_missing( string $channel, array $parent = null, array $order, string $function ) : mixed
    {
        $data = [
            "type"     => "order_approval_error_3",
            "function" => "VTEX - {$function} 2",
            "response" => [
                "error_code" => "4002",
                "message"   => "Order already approved"
            ]
        ];

        $meta_key = array_search( "_fastshop_order_approved", array_column( $order[ "meta_data" ], "key" ) );

        if ( $meta_key !== false )
            return process_handle_key_missing( $channel, $parent, $order, $data );

        return null;
    }
}
