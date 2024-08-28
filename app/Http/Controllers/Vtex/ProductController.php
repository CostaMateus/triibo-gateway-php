<?php

namespace App\Http\Controllers\Vtex;

use Throwable;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Vtex\Product;

class ProductController extends Controller
{
    /**
     * Define a class constant for the log channel.
     *
     * @var     string
     */
    private const LOG_CHANNEL = "vtex";

    /**
     * Handles the creation of a new SKU based on the request data.
     *
     * @see     https://developers.vtex.com/docs/guides/external-marketplace-integration-new-products
     *
     * @param   Request     $request
     * @return  void
     */
    public function updateOrCreateSku( Request $request )
    {
        try
        {
            $data    = $request->all();

            save_file( name: "Vtex-ProductController-updateOrCreateSku", data: $data );

            // TODO indentificar origem da requisição
            $origin  = "fastshop";

            $product = Product::where( "sku", $data[ "idSKU" ] )
                              ->where( "origin", $origin )
                              ->first();

            if ( !$product )
            {
                $create = [
                    "sku"    => $data[ "idSku" ],
                    "origin" => $origin,
                    "action" => "create",
                    "status" => "unprocessed",
                    "meta"   => json_encode( $data ),
                ];

                Product::create( $create );
            }
            else
            {
                $product->action = ( $data[ "isActive" ] == true ) ? "update" : "disable";
                $product->status = "unprocessed";
                $product->meta   = json_encode( $data );
                $product->save();
            }

            return response()->json( [ "message" => "ok" ], 200 );
        }
        catch ( Throwable $e )
        {
            // Log the data received
            log_message(
                channel : self::LOG_CHANNEL,
                message : "Error on ProductController::updateOrCreateSku",
                context : $request->all(),
                print   : true
            );

            // Log the exception for debugging purposes.
            log_exception(
                channel : self::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "Error on ProductController::updateOrCreateSku",
                type    : "Throwable",
                e       : $e
            );

            // Handle any unexpected errors.
            return response()->json( [
                "errorCode" => $e->getCode(),
                "message"   => "Unexpected error",
            ], $e->getCode() );
        }
    }
}
