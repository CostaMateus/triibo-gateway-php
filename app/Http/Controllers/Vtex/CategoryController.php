<?php

namespace App\Http\Controllers\Vtex;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    /**
     * Handles the mapping of VTEX categories.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-mapper#post-/api/mkp-category-mapper/connector/register
     *          Parameter mappingEndPoint
     *
     * @param   Request     $request    The incoming HTTP request containing category mapping data.
     * @return  JsonResponse            A JSON response indicating the result of the operation.
     */
    public function mapping( Request $request ) : JsonResponse
    {
        $code = 200;
        $data = [];

        try
        {
            save_file( name: "vtex-category-mapping", data: $request->all() );

            // Prepare successful response data.
            $data    = [
                "error_code" => null,
                "message"    => "ok",
                "data"       => null,
            ];
        }
        catch ( Throwable $e )
        {
            // Handle any unexpected errors.
            $code = 500;
            $data = [
                "error_code" => $e->getCode(),
                "message"    => "Unexpected error",
                "data"       => null,
            ];

            // Log the exception for debugging purposes.
            log_exception( channel: self::LOG_CHANNEL, message: $e->getMessage(), method: "mapping", type: "Throwable", e: $e );
        }

        // Return JSON response with appropriate status code.
        return response()->json( data: $data, status: $code );
    }

    /**
     * Retrieves and formats marketplace categories for VTEX integration.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-mapper#post-/api/mkp-category-mapper/connector/register
     *          Parameter categoryTreeEndPoint
     *
     * @param   Request     $request    The incoming HTTP request containing category mapping data.
     * @return  JsonResponse            A JSON response indicating the result of the operation.
     */
    public function categories( Request $request ) : JsonResponse
    {
        $code = 200;
        $data = [];

        try
        {
            // Fetch all marketplace categories.
            $categories = get_marketplace_categories();

            // Format categories for VTEX integration.
            $categories = vtex_format_categories( data: $categories );

            // Prepare successful response data.
            $data       = [
                "error_code" => null,
                "message"    => "ok",
                "data"       => $categories,
            ];
        }
        catch ( Throwable $e )
        {
            // Handle any unexpected errors.
            $code = 500;
            $data = [
                "error_code" => $e->getCode(),
                "message"    => "Unexpected error",
                "data"       => null,
            ];

            // Log the exception for debugging purposes.
            log_exception( channel: self::LOG_CHANNEL, message: $e->getMessage(), method: "categories", type: "Throwable", e: $e );
        }

        // Return JSON response with appropriate status code.
        return response()->json( data: $data, status: $code );
    }
}
