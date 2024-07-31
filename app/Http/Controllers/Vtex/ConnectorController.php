<?php

/**
 * This class is designed for making HTTP requests to a specific API.
 *
 * The class has several properties that control logging behavior and provide information about the request context.
 * It also has methods for constructing the request URL, making the request, and handling the response.
 */

namespace App\Http\Controllers\Vtex;

use Throwable;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class ConnectorController extends Controller
{
    /**
     * https://portal       .{environment}.com.br/api/mkp-category-mapper/connector/register
     * https://portal       .{environment}.com.br/api/mkp-category-mapper/categories/marketplace/{id}
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
     * Env (e.g., local, production) as a string.
     *
     * @var     string
     */
    private $env;

    /**
     * URL for making requests, constructed based on the environment and other settings.
     *
     * @var     string
     */
    private $url;

    /**
     * Flag indicating whether to log a message when an error occurs.
     *
     * @var     boolean
     */
    private $log_message = false;

    /**
     * Flag indicating whether to send a notification when an error occurs.
     *
     * @var     boolean
     */
    private $log_notify = false;

    /**
     * Stores the origin of the call (e.g., a specific method or function).
     *
     * @var     string
     */
    private $call_origin = "";

    public function __construct() {}

    /**
     * Construct the request URL based on the provided parameters.
     *
     * @param   string|null     $account        The account for which to make the request.
     * @param   string|null     $environment    The environment (e.g., development, production) for which to make the request.
     * @param   string          $api            The API endpoint for which to make the request.
     * @param   string|null     $parameter      Additional query parameters to include in the URL.
     * @return  string                          The constructed URL.
     */
    public function url( ?string $account = null, ?string $environment = null, string $api, ?string $param = null )
    {
        $this->env   = config( "vtex.env"                       );
        $url_prefix  = config( "vtex.{$this->env}.url.prefix"   );
        $url_sufix   = config( "vtex.{$this->env}.url.sufix"    );

        $account     = $account     ? $account     : config( "vtex.{$this->env}.account_name" );
        $environment = $environment ? $environment : config( "vtex.{$this->env}.environment"  );

        $base        = match ( $api )
        {
            "category-register"    => "mkp-category-mapper/connector/register",
            "category-marketplace" => "mkp-category-mapper/categories/marketplace/",
        };

        // Construct the registration URL.
        $this->url   = implode( "", [
            $url_prefix,
            $account . ".",
            $environment,
            $url_sufix,
            $base,
            $param,
        ] );
    }

    /**
     * Set the log message flag.
     *
     * @param   bool    $register   Whether to enable or disable logging messages. Defaults to true.
     * @return  void
     */
    public function logMessage( bool $register = true )
    {
        $this->log_message = $register;
    }

    /**
     * Set the notification flag.
     * Used to determine whether a notification should be sent when an error occurs.
     *
     * @param   bool    $notify     Whether to enable or disable sending notifications. Defaults to true.
     * @return  void
     */
    public function logNotify( bool $notify = true )
    {
        $this->log_notify  = $notify;
    }

    /**
     * Set the origin of a call.
     * Used to track the source of a specific request or action.
     *
     * @param string $origin The origin of the call (e.g. "API Request" or "Manual Trigger").
     * @return void
     */
    public function origin( string $origin = "" )
    {
        $this->call_origin = $origin;
    }

    /**
     * Sends a request to the Vtex API based on the provided method and data.
     *
     * @param   string  $method     The HTTP method to be used (e.g. "POST", "GET").
     * @param   array   $body       The request body as an associative array.
     * @return  array               An array containing the response code and data, or a default error message if the request was not successful.
     */
    public function call( string $method, array $body = [] )
    {
        try
        {
            if ( $method == "POST" && !empty( $body ) )
                $response = Http::withBody( json_encode( $body ), "application/json" )->post( $this->url );
            elseif ( $method == "POST" )
                $response = Http::post( $this->url );
            elseif ( $method == "GET" )
                $response = Http::get( $this->url );

            // Prepare response data.
            $data = [
                "error_code" => null,
                "message"    => $response->reason(),
                "data"       => $response->json(),
            ];

            // Check not successful.
            if ( !$response->successful() )
            {
                log_message(
                    channel : self::LOG_CHANNEL,
                    level   : "error",
                    message : $response->reason(),
                    context : $data,
                    print   : true,
                    notify  : $this->log_notify
                );

                // Update response code and error code.
                $code                 = 400;
                $data[ "error_code" ] = $response->status();
            }

            if ( $this->log_message )
            {
                log_message(
                    channel : self::LOG_CHANNEL,
                    level   : "info",
                    message : $response->reason(),
                    context : $data,
                    print   : true,
                    notify  : false
                );
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
            log_exception(
                channel : self::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : $this->call_origin,
                type    : "Throwable",
                e       : $e
            );
        }

        return [ $code, $data ];
    }
}
