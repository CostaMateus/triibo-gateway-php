<?php

namespace App\Http\Controllers\Vtex;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class CategoryController extends Controller
{
    /**
     * https://{accountName}.{environment}.com.br/api/order-integration/orders
     * https://{accountName}.{environment}.com.br/api/order-integration/orders/status
     * https://{accountName}.{environment}.com.br/api/checkout/pub/orderForms/simulation
     * https://{accountName}.{environment}.com.br/api/fulfillment/pvt/orders
     * https://{accountName}.{environment}.com.br/api/fulfillment/pvt/orders/{orderId}/fulfill
     */

    /**
     * Define a class constant for the log channel.
     *
     * @var     string
     */
    private const LOG_CHANNEL = "vtex";

    /**
     * Env
     *
     * @var     string
     */
    private $env;

    /**
     * Account name
     *
     * @var     string
     */
    private $acc_name;

    /**
     * Environment
     *
     * @var     string
     */
    private $environment;

    public function __construct()
    {
        $this->env         = config( "vtex.env"                       );
        $this->acc_name    = config( "vtex.{$this->env}.account_name" );
        $this->environment = config( "vtex.{$this->env}.environment"  );
        $this->url_prefix  = config( "vtex.{$this->env}.url.prefix"   );
        $this->url_sufix   = config( "vtex.{$this->env}.url.sufix"    );
    }

    /**
     * Registers the marketplace connector with VTEX
     *
     * @return  JsonResponse    A JSON response indicating the result of the operation.
     */
    public static function registration() : JsonResponse
    {
        $code    = 200;
        $data    = [];

        // Retrieve configuration values.
        $env     = config( "vtex.env"                        );
        $acc     = config( "vtex.{$env}.account_name"        );
        $environ = config( "vtex.{$env}.environment"         );
        $prefix  = config( "vtex.{$env}.url.prefix"          );
        $sufix   = config( "vtex.{$env}.url.sufix"           );
        $base    = config( "vtex.{$env}.url.category-mapper" );

        // Construct the registration URL.
        $url     = implode( "", [
            $prefix,
            "portal.",
            "vtexcommercestable", // $environ,
            $sufix,
            $base,
            "/connector/register",
            "?an={$acc}",
        ] );

        // Prepare the request body.
        $body    = [
            "displayName"          => "Marketplace Triibo",
            "categoryTreeEndPoint" => route( "api.marketplace.vtex.categories.categories" ),
            "mappingEndPoint"      => route( "api.marketplace.vtex.categories.mapping"    ),
            "properties"           => [ "allowsRemap" => true ],
            // "CategoryTreeProcessingNotificationEndpoint" => "",
        ];

        try
        {
            // Send POST request to VTEX.
            $response = Http::withBody( json_encode( $body ), "application/json" )->post( $url );

            // Prepare response data.
            $data     = [
                "error_code" => null,
                "message"    => $response->reason(),
                "data"       => $response->json(),
            ];

            // Check not successful.
            if ( !$response->successful() )
            {
                // Log the error.
                log_message( channel: self::LOG_CHANNEL, level: "error", message: $response->reason(), context: $data, print: true, notify: false );

                // Update response code and error code.
                $code                 = 400;
                $data[ "error_code" ] = $response->status();
            }
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
            log_exception( channel: self::LOG_CHANNEL, message: $e->getMessage(), method: "registration", type: "Throwable", e: $e );
        }

        // Return JSON response with appropriate status code.
        return response()->json( $data, $code );
    }

    /**
     * Sends marketplace category mapping to VTEX.
     *
     * @return  JsonResponse            A JSON response indicating the result of the operation.
     */
    public static function sendMapping() : JsonResponse
    {
        $code    = 200;
        $data    = [];

        // Retrieve configuration values.
        $env     = config( "vtex.env"                        );
        $environ = config( "vtex.{$env}.environment"         );
        $prefix  = config( "vtex.{$env}.url.prefix"          );
        $sufix   = config( "vtex.{$env}.url.sufix"           );
        $base    = config( "vtex.{$env}.url.category-mapper" );

        // ID returned from registration process.
        $id      = 123123;

        // Construct the registration URL.
        $url     = implode( "", [
            $prefix,
            "portal.",
            "vtexcommercestable", // $environ,
            $sufix,
            $base,
            "/categories/marketplace",
            "/{$id}",
        ] );

        try
        {
            // Fetch all marketplace categories.
            $categories = get_marketplace_categories();

            // Format categories for VTEX integration.
            $categories = format_categories_to_vtex( $categories );

            // Prepare the request body.
            $body       = [ "categories" => $categories ];

            // Send POST request to VTEX.
            $response   = Http::withBody( json_encode( $body ), "application/json" )->post( $url );

            // Prepare response data.
            $data       = [
                "error_code" => null,
                "message"    => $response->reason(),
                "data"       => $response->json(),
            ];

            // Check not successful.
            if ( !$response->successful() )
            {
                // Log the error.
                log_message( channel: self::LOG_CHANNEL, level: "error", message: $response->reason(), context: $data, print: true, notify: false );

                // Update response code and error code.
                $code                 = 400;
                $data[ "error_code" ] = $response->status();
            }
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
            log_exception( channel: self::LOG_CHANNEL, message: $e->getMessage(), method: "sendMapping", type: "Throwable", e: $e );
        }

        // Return JSON response with appropriate status code.
        return response()->json( $data, $code );
    }

    /**
     * Handles the mapping of VTEX categories
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
            // Generate a timestamp for the filename.
            $date    = date( "Ymd-his" );
            $encoded = json_encode( $request->all(), JSON_UNESCAPED_SLASHES );

            Storage::put( "vtex-category-mapping-{$date}.json", $encoded );

            // Prepare successful response data.
            $data = [
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
