<?php

namespace App\Helpers;

use DateTime;
use Exception;
use Throwable;
use DateInterval;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Devio\Pipedrive\Pipedrive;
use Illuminate\Support\Facades\Http;
use App\Notifications\ScheduleFailure;

class Helper
{
    /**
     * Traduz o status/label do Deal
     *
     * @param [type]  $label Valor do campo
     * @param [type]  $id    ID do stage
     * @param boolean $type  TRUE para status, FALSE para label
     * @return void
     */
    public static function statusLabelDeal($label, $id, bool $type = true)
    {
        if (!$label || !$id) return 0;

        $resp = 0;

        if ($type)
        {
            switch ($label) {
                case "open":
                    $resp = "$id:A";
                break;

                case "won":
                    $resp = "$id:G";
                break;

                case "lost":
                    $resp = "$id:P";
                break;

                case "deleted":
                    $resp = "$id:E";
                break;

                default:
                    $resp = 0;
                break;
            }
        }
        else
        {
            $labels = self::getLabelsDeal();

            foreach ($labels as $lbl)
            {
                if ($lbl["id"] == (int) $label)
                {
                    $resp = "$id:" . $lbl["label"];
                    break;
                }
            }
        }

        return $resp;
    }

    /**
     * Auxilio para função statusLabelDeal()
     *
     * @return array
     */
    private static function getLabelsDeal()
    {
        // Etiqueta / id interno: 12463

        $token   = config('pipedrive.token');
        $pipeApi = new Pipedrive($token);
        $labels  = $pipeApi->DealFields->find(12463)->getData();

        foreach ($labels->options as $t)
        {
            if (in_array(mb_strtolower($t->label), ["em espera", "em degustação"]))
            {
                $label    = explode(" ", $t->label)[1];
                $t->label = ucfirst($label);
            }

            $resp[] = [
                "id"    => $t->id,
                "label" => $t->label[0]
            ];
        }

        return $resp;
    }

    /**
     * Recupera o campo segundo o $idField passado.
     * Apenas campos select / com options

     * @param integer $idField    ID do campo
     * @param integer $idSelected ID da opção selecionada
     * @return string label do campo
     */
    public static function getOrgCustomField(int $idField, int $idSelected)
    {
        // Fonte do Lead / id interno: 4028
        // Vendedor resp / id interno: 4032

        if ($idSelected > 0)
        {
            $token   = config('pipedrive.token');
            $pipeApi = new Pipedrive($token);
            $opts    = $pipeApi->OrganizationFields->find($idField)->getData()->options;

            foreach($opts as $opt)
            {
                if ($opt->id == $idSelected) return $opt->label;
            }
        }

        return " ";
    }

    public static function convertDateSS( string $date )
    {
        // 18/11/2022 23:59:00
        $date      = explode( " ",  $date      );
        $date[ 0 ] = explode( "/",  $date[ 0 ] );
        $date[ 0 ] = array_reverse( $date[ 0 ] );
        $date[ 0 ] = implode( "-",  $date[ 0 ] );
        $date      = implode( " ",  $date );

        return $date;
    }










    public static function prepareDataFs( $array, $field )
    {
        echo "Formatting SKUs to query in FS\n";

        $items  = [];

        foreach ( $array as $sku )
        {
            $id      = ( strpos( $sku[ $field ], "FS-" ) === false )
                        ? $sku[ $field ]
                        : substr( $sku[ $field ], 3 );

            $items[] = [
                "id"       => $id,
                "quantity" => 1,
                "seller"   => "FSH"
            ];
        }

        return [
            "items"          => $items,
            "postalCode"     => "",
            "country"        => "",
            "geoCoordinates" => []
        ];
    }

    public static function getStockPriceFS( $body )
    {
        $env       = config( "fastshop.env"             );
        $fsUrl     = config( "fastshop.$env.fs.url"     );
        $fsHeaders = config( "fastshop.$env.fs.headers" );

        $count     = count( $body[ "items" ] );

        log_message( channel: "fastshop", message: "Stock|Price: Querying {$count} SKUs at once...", print: true );

        try
        {
            $url      = "$fsUrl/pvt/orderForms/simulation";
            $response = Http::withHeaders( $fsHeaders )->withBody( json_encode( $body ), "application/json" )->post( $url );

            if ( $response->successful() ) return $response->json();

            throw new Exception( "Unable to query FastShop products data" );
        }
        catch ( Throwable $e )
        {
            $message = "Stock|Price: SKUs query failed";

            log_exception( channel: "fastshop", message: $message, method: "getStockPriceFS", type: "Throwable", e: $e );

            return [];
        }
    }

    public static function addTagsToDescriptionCofry( string $desc, array $tags )
    {
        foreach ( $tags as $tag )
        {
            if ( $tag == "Cofry" ) continue;

            $tag   = "\n#cofry_" . Str::slug( $tag, "_" );
            $desc .= $tag;
        }

        return $desc;
    }

    public static function awinReportStatus( string $status )
    {
        // pending, approved, declined
        $status = strtolower( $status );
        $status = ( $status == "declined" ) ? "denied" : $status;

        return $status;
    }

    /**
     * Get datetime ms
     *
     * @param   string  $format
     * @param   bool    $immutable
     * @return  int
     */
    public static function getDateTimeUnix( string $format = "Y-m-d H:i:s.u", bool $immutable = false )
    {
        $date = ( !$immutable )
                    ? new DateTime( date( $format ) )
                    : new DateTimeImmutable( $format );

        $date = ( int ) $date->format( "Uv" ) * 1000;

        return $date;
    }

    /**
     * Get datetime ms, with some interval
     *
     * @param   string  $interval
     * @return  int
     */
    public static function getDateTimeUnixInterval( string $interval )
    {
        $date = new DateTime( date( "Y-m-d H:i:s.u" ) );
        $date = $date->add( new DateInterval( $interval ) );
        $date = ( int ) $date->format( "Uv" );

        return $date;
    }

    /**
     * Generates a short description for a product based on its full description, name, and attributes.
     *
     * It includes the voltage attribute if present and formats the description to a maximum length.
     *
     * @param   string  $description    The full description of the product.
     * @param   string  $product_name   The name of the product.
     * @param   array   $attributes     An array of product attributes.
     * @return  string                  The generated short description.
     */
    public static function getFsProductShortDescription( string $description, string $product_name, array $attributes )
    {
        // Extract the first line of the full description.
        $short     = explode( "\r\n", $description )[ 0 ];

        // Use the product name if the first line is too short.
        $short     = ( strlen( $short ) <= 50 ) ? $product_name : $short;

        // Limit the description to 140 characters.
        $short     = Str::limit( $short, 140, " (...)" );

        // Find the voltage attribute, if it exists.
        $volt_attr = current( array_filter( $attributes, function ( $att )
        {
            return Str::lower( $att[ "name" ] ) === "voltagem";
        } ) );

        // Append voltage information to the short description.
        if ( $volt_attr )
        {
            $val = reset( $volt_attr[ "options" ] );
            $val = in_array( $val, [ "110", "220" ] ) ? $val . "V" : $val;

            if ( $val !== "NA" )
                $short .= "\r\n\r\n" . Str::ucfirst( "voltagem" ) . " : $val";
        }

        return $short;
    }

    /**
     * Converts product specifications into an array of WooCommerce-compatible attributes.
     *
     * @param   array   $specifications     An array of product specifications.
     * @return  array                       An array of formatted attributes for WooCommerce.
     */
    public static function getFsProductAttributes( array $specifications )
    {
        $attributes = [];

        // Iterate through each specification.
        foreach ( $specifications as $key => $attr )
        {
            // Skip the ProductLineId field.
            if ( $attr[ "fieldName" ] === "ProductLineId" )
                continue;

            // Convert boolean 'true' to 'SIM'.
            $field_value  = $attr[ "fieldValue" ] === "true" ? "SIM" : $attr[ "fieldValue" ];

            // Add the attribute to the array.
            $attributes[] = [
                "name"      => $attr[ "fieldName" ],
                "position"  => $key,
                "visible"   => true,
                "variation" => true,
                "options"   => [ $field_value ],
            ];
        }

        return $attributes;
    }

    /**
     * Formats an array of data into a structured string for display.
     *
     * This function is used to create a textual representation of the information
     * contained in an array, with a title and a set of attributes and their labels.
     *
     * @param   array   $data           The input data array.
     * @param   string  $title          The title for the section of formatted information.
     * @param   array   $attributes     An associative array mapping data keys to their display labels.
     * @return  string                  A formatted string containing the information.
     */
    public static function formatInfo( array $data, string $title, array $attributes )
    {
        $info = "$title\n";

        foreach ( $attributes as $key => $label )
            if ( isset( $data[ $key ] ) )
                $info .= " $label: {$data[$key]}\n";

        return $info;
    }

    /**
     * Maps the logistics information from WooCommerce to the format required by Fast Shop (FS).
     *
     * @param   array   $data   The logistics information from a WooCommerce order.
     * @return  array           The logistics information formatted for FS.
     */
    public static function mapFsLogisticsInfo( array $data )
    {
        // Map logistics information to the FS format.
        return array_map( function ( $item )
        {
            // Search for the index of the SLA that corresponds to the "delivery" delivery channel.
            $sla_id       = array_search( "delivery", array_column( $item[ "slas" ], "deliveryChannel" ) );

            // Select the SLA based on the found index.
            $selected_sla = $item[ "slas" ][ $sla_id ];

            // Return an array with the logistics information mapped to the FS format.
            return [
                "itemIndex"        => $item[ "itemIndex" ],
                "selectedSla"      => $selected_sla[ "id" ],
                "lockTTL"          => "3d",
                "shippingEstimate" => $selected_sla[ "shippingEstimate" ],
                "price"            => $selected_sla[ "price"            ],
                "deliveryWindow"   => [],
            ];

        }, $data );
    }

    /**
     * Maps order items from a given logistics array to the format required by Fast Shop (FS).
     *
     * @param   array   $logistics  The logistics information related to the order items.
     * @param   array   $items      The actual items from the order.
     * @return  array               The order items formatted for FS.
     */
    public static function mapFsOrderItems( array $logistics, array $items )
    {
        // Use array_map to transform each item to the FS format using the logistics array.
        return array_map( function ( $item ) use ( $items )
        {
            // Retrieve the corresponding item from the items array using the itemIndex.
            $item_id  = $item[ "itemIndex" ];
            $aux_item = $items[ $item_id ];

            // Return an array with the item data mapped to the FS format.
            return [
                "id"                => $aux_item[ "id"             ],
                "quantity"          => $aux_item[ "quantity"       ],
                "price"             => $aux_item[ "price"          ],
                "unitMultiplier"    => $aux_item[ "unitMultiplier" ],
                "Seller"            => $aux_item[ "seller"         ],
                "priceTags"         => $aux_item[ "priceTags"      ],
                "isGift"            => false,
                "commission"        => 10,
                "freightCommission" => 0,
            ];

        }, $logistics );
    }

    /**
     * Retrieves stock and price information for a given product.
     *
     * @param   object  $product    The product object from which data will be extracted.
     * @return  array               An associative product array.
     */
    public static function getProductStockPrice( object $product )
    {
        return [
            "regular_price"  => $product->regular_price,
            "sale_price"     => $product->sale_price,
            "stock_quantity" => $product->stock_quantity,
            "stock_status"   => $product->stock_status,
        ];
    }
}
