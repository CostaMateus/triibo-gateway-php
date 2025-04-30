<?php

namespace App\Http\Controllers\Vtex;

use Throwable;
use App\Models\Vtex\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\{JsonResponse, Request};

class ProductController extends VtexController
{
    /**
     * Handles the creation of a new SKU based on the request data.
     *
     * @see     https://developers.vtex.com/docs/guides/external-marketplace-integration-new-products
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function updateOrCreateSku( Request $request )
    {
        $vtex_data = $request->all();

        if ( empty( $vtex_data ) )
        {
            return response()->json( [
                "error_code" => 400,
                "message" => "Empty data",
                "data" => null,
            ], 400 );
        }

        $allow_origins = ["fastshopbrqa2", "fastshopbr"];
        $origin = $vtex_data[ "An" ];

        if ( ! in_array( $origin, $allow_origins ) )
        {
            return response()->json( [
                "error_code" => 401,
                "message" => "Unauthorized",
                "data" => null,
            ], 401 );
        }

        // Prepare successful response data.
        $code = 200;
        $data = [
            "error_code" => null,
            "message" => "ok",
            "data" => null,
        ];

        try
        {
            // $vtex_data_example = [
            //     "IdSku" => "15811",
            //     "An" => "fastshopbr",
            //     "IdAffiliate" => "TRB",
            //     "ProductId" => 15242,
            //     "DateModified" => "2025-04-30T14:47:33.8537266Z",
            //     "IsActive" => true,
            //     "StockModified" => true,
            //     "PriceModified" => false,
            //     "HasStockKeepingUnitModified" => false,
            //     "HasStockKeepingUnitRemovedFromAffiliate" => false,
            //     "Attempt" => 1
            // ];

            $action = "";
            $description = "";

            $product = DB::table( "marketplace_product_skus" )->where( "metadata_id", $vtex_data[ "IdSku" ] )->first();

            if ( ! $product )
            {
                $action = "create";
                $description = "New SKU to create.";
            }
            else
            {
                if ( $vtex_data[ "HasStockKeepingUnitRemovedFromAffiliate" ] )
                {
                    $action = "disable";
                    $description = "SKU removed from affiliate.";
                }
                else
                {
                    $action = "update";

                    if ( $vtex_data[ "HasStockKeepingUnitModified" ] )
                        $description .= "Data has changed. ";

                    if ( $vtex_data[ "StockModified" ] )
                        $description .= "Stock has changed. ";

                    if ( $vtex_data[ "PriceModified" ] )
                        $description .= "Price has changed. ";

                    if ( ! $vtex_data[ "IsActive" ] )
                        $description .= "SKU is inactive. ";
                }
            }

            Product::create( [
                "sku" => $vtex_data[ "IdSku" ],
                "origin" => $origin,
                "action" => $action,
                "status" => "unprocessed",
                "description" => trim( $description ),
                "meta" => json_encode( $vtex_data ),
            ] );
        }
        catch ( Throwable $e )
        {
            // Handle any unexpected errors.
            $code = 500;
            $data = [
                "error_code" => $e->getCode(),
                "message" => "Unexpected error",
                "data" => $vtex_data,
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
     * @param string $sku
     *
     * @return array
     */
    public function getProductData( string $sku )
    {
        dd( vtex_get_product( sku: $sku ) );
    }
}
