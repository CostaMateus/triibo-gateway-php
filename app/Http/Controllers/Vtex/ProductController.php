<?php

namespace App\Http\Controllers\Vtex;

use Throwable;
use App\Models\Vtex\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

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
     * @return  JsonResponse
     */
    public function updateOrCreateSku( Request $request ) : JsonResponse
    {
        $vtex_data = $request->all();

        // Prepare successful response data.
        $code      = 200;
        $data      = [
            "error_code" => null,
            "message"    => "ok",
            "data"       => null,
        ];

        try
        {
            save_file( name: "Vtex-ProductController-updateOrCreateSku", data: $vtex_data );

            // TODO indentificar origem da requisição
            $origin  = "fastshop";

            $product = Product::where( "sku", $vtex_data[ "idSKU" ] )
                              ->where( "origin", $origin )
                              ->first();

            if ( !$product )
            {
                $create = [
                    "sku"    => $vtex_data[ "idSku" ],
                    "origin" => $origin,
                    "action" => "create",
                    "status" => "unprocessed",
                    "meta"   => json_encode( $vtex_data ),
                ];

                Product::create( $create );
            }
            else
            {
                $product->action = ( $vtex_data[ "isActive" ] == true ) ? "update" : "disable";
                $product->status = "unprocessed";
                $product->meta   = json_encode( $vtex_data );
                $product->save();
            }
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
            $code = 500;
            $data = [
                "error_code" => $e->getCode(),
                "message"    => "Unexpected error",
                "data"       => $vtex_data,
            ];
        }

        return response()->json( $data, $code );
    }
}
