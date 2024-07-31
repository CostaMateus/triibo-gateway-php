<?php

namespace App\Http\Controllers\Vtex;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Vtex\ConnectorController;

class CategoryController extends ConnectorController
{
    /**
     * https://portal.{environment}.com.br/api/mkp-category-mapper/connector/register
     * https://portal.{environment}.com.br/api/mkp-category-mapper/categories/marketplace/{id}
     */

    /**
     * Connector controller.
     *
     * @var     ConnectorController
     */
    private $vtex;

    public function __construct()
    {
        $this->vtex = new ConnectorController();
    }

    /**
     * Registers the marketplace connector with VTEX.
     *
     * @return  JsonResponse    A JSON response indicating the result of the operation.
     */
    public function registration() : JsonResponse
    {
        $code    = 200;
        $data    = [];

        // Retrieve configuration values.
        $env     = config( "vtex.env"                 );
        $account = config( "vtex.{$env}.account_name" );

        // Prepare the request body.
        $body    = [
            "displayName"          => "Marketplace Triibo",
            "categoryTreeEndPoint" => route( "api.marketplace.vtex.categories.categories" ),
            "mappingEndPoint"      => route( "api.marketplace.vtex.categories.mapping"    ),
            "properties"           => [ "allowsRemap" => true ],
            // "CategoryTreeProcessingNotificationEndpoint" => "",
        ];

        $this->vtex->logMessage();
        $this->vtex->logNotify();
        $this->vtex->origin( origin: "registration" );
        $this->vtex->url( account: "portal", environment: "vtexcommercestable", api: "category-register", param: "?an={$account}" );

        [ $code, $data ] = $this->vtex->call( "POST", $body );

        save_file( "vtex-registration", $data );

        // Return JSON response with appropriate status code.
        return response()->json( $data, $code );
    }

    /**
     * Sends marketplace category mapping to VTEX.
     *
     * @return  JsonResponse            A JSON response indicating the result of the operation.
     */
    public function sendMapping() : JsonResponse
    {
        $code    = 200;
        $data    = [];

        /**
         * TODO remove | For test, this ID will be returned in registration() method.
         */
        $id      = 123123; // for test

        try
        {
            // Fetch all marketplace categories.
            $categories = get_marketplace_categories();

            // Format categories for VTEX integration.
            $categories = format_categories_to_vtex( $categories );

            $this->vtex->logMessage();
            $this->vtex->logNotify();
            $this->vtex->origin( origin: "sendMapping" );
            $this->vtex->url( account: "portal", environment: "vtexcommercestable", api: "category-marketplace", param: $id );

            $body = [ "categories" => $categories ];

            [ $code, $data ] = $this->vtex->call( "POST", $body );
        }
        catch ( Throwable $e )
        {
            // Log the exception for debugging purposes.
            log_exception( channel: self::LOG_CHANNEL, message: $e->getMessage(), method: "sendMapping", type: "Throwable", e: $e );
        }

        // Return JSON response with appropriate status code.
        return response()->json( $data, $code );
    }

    /**
     * Handles the mapping of VTEX categories.
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
            save_file( "vtex-category-mapping", $request->all() );

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
        return response()->json( $data, $code );
    }

    /**
     * Retrieves and formats marketplace categories for VTEX integration.
     *
     * @return  JsonResponse    A JSON response indicating the result of the operation.
     */
    public function categories() : JsonResponse
    {
        $code = 200;
        $data = [];

        try
        {
            // Fetch all marketplace categories.
            $categories = get_marketplace_categories();

            // Format categories for VTEX integration.
            $categories = format_categories_to_vtex( $categories );

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
        return response()->json( $data, $code );
    }

}
