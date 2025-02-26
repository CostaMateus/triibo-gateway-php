<?php

namespace App\Http\Controllers\Vtex;

use Throwable;
use App\Models\Vtex\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\Vtex\ProductService;
use App\Services\Vtex\ConnectorService;
use App\Services\Vtex\FulfillmentService;
use App\Http\Controllers\Vtex\VtexController;

class ProductController extends VtexController
{
    /**
     * Handles the creation of a new SKU based on the request data.
     *
     * @see     https://developers.vtex.com/docs/guides/external-marketplace-integration-new-products
     *
     * @param   Request     $request
     * @return  JsonResponse
     */
    public function updateOrCreateSku( Request $request )
    {
        $vtex_data     = $request->all();

        if ( empty( $vtex_data ) )
        {
            return response()->json( [
                "error_code" => 400,
                "message"    => "Empty data",
                "data"       => null,
            ], 400 );
        }

        $allow_origins = [ "fastshopbrqa2", "fastshopbr" ];
        $origin        = $vtex_data[ "An" ];

        if ( !in_array( $origin, $allow_origins ) )
        {
            return response()->json( [
                "error_code" => 401,
                "message"    => "Unauthorized",
                "data"       => null,
            ], 401 );
        }

        // Prepare successful response data.
        $code          = 200;
        $data          = [
            "error_code" => null,
            "message"    => "ok",
            "data"       => null,
        ];

        try
        {
            $product = Product::where( "sku", $vtex_data[ "IdSku" ] )->where( "origin", $origin )->first();

            if ( !$product )
            {
                $create = [
                    "sku"    => $vtex_data[ "IdSku" ],
                    "origin" => $origin,
                    "action" => "create",
                    "status" => "unprocessed",
                    "meta"   => json_encode( $vtex_data ),
                ];

                Product::create( $create );
            }
            else
            {
                $product->action = ( $vtex_data[ "IsActive" ] == true ) ? "update" : "disable";
                $product->status = "unprocessed";
                $product->meta   = json_encode( $vtex_data );
                $product->save();
            }
        }
        catch ( Throwable $e )
        {
            // Handle any unexpected errors.
            $code = 500;
            $data = [
                "error_code" => $e->getCode(),
                "message"    => "Unexpected error",
                "data"       => $vtex_data,
            ];

            // Log the exception for debugging purposes.
            log_exception(
                channel : parent::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "updateOrCreateSku",
                type    : "Throwable",
                e       : $e
            );
        }

        return response()->json( $data, $code );
    }

    /**
     *
     * @param   string  $sku
     * @return  array
     */
    public function getProductData( string $sku )
    {
        $connector    = new ConnectorService( seller: "fast_shop" );
        $connector->enableLogNotify( notify: false );

        $service_pr = new ProductService( connector: $connector );
        $service_sm = new FulfillmentService( connector: $connector );

        $data     = $service_pr->getSkuData( sku: $sku );
        $context  = $service_pr->getSkuContext( sku: $sku );
        $simulate = $$service_sm->simulate( data: [
            "items" => [ [
                "id"       => $sku,
                "quantity" => 1,
                "seller"   => "1"
            ] ],
            "isCheckedIn" => false,
            "country"     => "BRA",
            "selectedSla" => "Normal",
        ] );

        dd( [
            "data"     => $data,
            "context"  => $context,
            "simulate" => $simulate,
        ] );
    }
}
