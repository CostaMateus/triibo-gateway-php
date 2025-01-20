<?php

namespace App\Http\Controllers\Vtex;

use Throwable;
use App\Models\Vtex\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
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
    public function updateOrCreateSku( Request $request ) : JsonResponse
    {
        $vtex_data = $request->all();

        // Log::channel( channel: "vtex" )->info( message: "updateOrCreateSku", context: $vtex_data );

        if ( empty( $vtex_data ) )
        {
            return response()->json( [
                "error_code" => null,
                "message"    => "Empty data",
                "data"       => null,
            ], 400 );
        }

        // Prepare successful response data.
        $code      = 200;
        $data      = [
            "error_code" => null,
            "message"    => "ok",
            "data"       => null,
        ];

        try
        {
            $origin  = $vtex_data[ "An" ];
            $product = Product::where( "sku", $vtex_data[ "IdSku" ] )
                              ->where( "origin", $origin )
                              ->first();

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
}
