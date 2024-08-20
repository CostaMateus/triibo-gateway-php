<?php
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

if( !function_exists( "add_sub_category" ) )
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

if ( !function_exists( "format_categories_to_vtex" ) )
{
    /**
     * Formats category data for VTEX integration.
     *
     * @param   array   $data   Raw category data.
     * @return  array           Formatted and hierarchically organized category data.
     */
    function format_categories_to_vtex( array $data ) : array
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
            // Search for the index of the SLA that corresponds to the "delivery" delivery channel.
            $sla_id       = array_search( "delivery", array_column( $item[ "slas" ], "deliveryChannel" ) );

            // Select the SLA based on the found index.
            $selected_sla = $item[ "slas" ][ $sla_id ];

            // Return an array with the logistics information mapped to the VTEX format.
            return [
                "selectedDeliveryChannel" => "delivery", // or 'pickup-in-point'
                "lockTTL"                 => "3d", // 'b' or 'bd'
                "price"                   => $selected_sla[ "price"            ],
                "selectedSla"             => $selected_sla[ "id"               ],
                "shippingEstimate"        => $selected_sla[ "shippingEstimate" ],
                "deliveryIds"             => [ "warehouseId" => "" ],
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
                "addressId"      => "1",
                "addressType"    => "residential", // or 'commercial', 'pickup'
                "receiverName"   => $name,
                "postalCode"     => $data[ "postcode"     ],
                "street"         => $data[ "address_1"    ],
                "number"         => $data[ "number"       ],
                "complement"     => $data[ "address_2"    ],
                "neighborhood"   => $data[ "neighborhood" ],
                "city"           => $data[ "city"         ],
                "state"          => $data[ "state"        ],
                "country"        => "BRA",
            ]
        ];
    }
}

if ( !function_exists( "vtex_new_order" ) )
{
    /**
     * Converts WooCommerce order data to the format required by VTEX.
     *
     * @param   array   $data   The original order data from WooCommerce.
     * @return  array           The order data formatted for VTEX.
     */
    function vtex_new_order( array $data ) : array
    {
        // Extract parent order data if available, otherwise use the current order data.
        $parent   = $data[ "parent" ] ?? null;
        $order    = $data[ "order"  ];

        // Generate the URL for the invoice notification endpoint using the order ID
        $url      = route( "api.marketplace.invoicenotification", [ "orderId" => $order[ "id" ] ] );

        if ( config( "app.env" ) == "local" )
            $url = str_replace( "http://127.0.0.1:8000/", "http://php.triibo.com.br/", $url );

        // Extract the VTEX information from the order data.
        $info     = $order[ "fs_info" ][ "logisticsInfo" ];

        // Retrieve the discount
        $discount = ( empty( $order[ "coupon_lines" ] ) )
                            ? $order[ "discount_total" ]
                            : ( $order[ "coupon_lines" ][ 0 ][ "discount" ] ?? 0 );

        // Calculate the total payment value for the marketplace.
        $total    = $order[ "total" ] + $discount;
        $total    = number_format( ( float ) $total, 2, ".", "" );
        $total    = ( int ) str_replace( ".", "", $total );

        // Extract billing and shipping information, using parent data if available.
        $billing  = $parent[ "billing"  ] ?? $order[ "billing"  ];
        $shipping = $parent[ "shipping" ] ?? $order[ "shipping" ];

        // Return the formatted order data for VTEX.
        return [
            "marketplaceOrderId"      => $order[ "id" ],
            "marketplaceOrderStatus"  => "new",  // or 'approved'
            "marketplacePaymentValue" => $total,
            "connectorName"           => "???",  // TODO fastshop precisa fornecer/confirmar
            "connectorEndpoint"       => "???",  // TODO fastshop precisa fornecer/confirmar
            "allowFranchises"         => false,
            "items"                   => vtex_map_order_items( $info[ "logisticsInfo" ], $info[ "items" ] ),
            "clientProfileData"       => vtex_map_client_data( $billing ),
            "shippingData"            => [
                "isFob"                    => false,
                "isMarketplaceFulfillment" => false,

                "logisticsInfo"            => vtex_map_logistics_info( $info[ "logisticsInfo" ] ),
                "selectedAddresses"        => vtex_map_selected_addresses( $shipping ),
            ],
        ];
    }
}

if ( !function_exists( "vtex_update_order" ) )
{
    /**
     * Formats the order data for updating in VTEX.
     *
     * @param   string  $order_id   The unique identifier of the order.
     * @return  array               A formatted array containing the updated order data for VTEX.
     */
    function vtex_update_order( string $order_id ) : array
    {
        // Return the formatted order data for VTEX.
        return [
            "marketplaceOrderId"      => $order_id,
            "marketplaceOrderStatus"  => "approved",
            "connectorName"           => "???",  // TODO fastshop precisa fornecer/confirmar
            "connectorEndpoint"       => "???",  // TODO fastshop precisa fornecer/confirmar
        ];
    }
}
