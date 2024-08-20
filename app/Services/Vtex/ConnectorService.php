<?php

namespace App\Services\Vtex;

use Throwable;
use Illuminate\Support\Facades\Http;

class ConnectorService
{
    /**
     * Define a class constant for the log channel.
     *
     * @var     string
     */
    private const LOG_CHANNEL = "vtex";

    /**
     * Seller.
     *
     * @var     string
     */
    private $seller;

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
     * Headers.
     *
     * @var     array
     */
    private $headers;

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
    private $log_notify  = false;

    /**
     * Stores the origin of the call (e.g., a specific method or function).
     *
     * @var     string
     */
    private $call_origin = "";

    /**
     * Constructor.
     *
     * @param   string  $seller
     */
    public function __construct( string $seller )
    {
        $this->seller  = $seller;
        $this->env     = config( "vtex.{$this->seller}.env" );
        $this->headers = config( "vtex.{$this->seller}.{$this->env}.headers" );

        $this->enableLogMessage()->enableLogNotify();
    }

    /**
     * Build the URL for a VTEX API call.
     *
     * @param   string          $api            The name of the API endpoint to be called.
     * @param   string|null     $account        Account for the API call (default value will be used if not provided).
     * @param   string|null     $environment    Environment for the API call (default value will be used if not provided).
     * @param   string|null     $param          Additional query string parameters to the URL (default null).
     * @return  ConnectorService                The object itself, allowing method chaining.
     */
    public function buildUrl( string $api, ?string $account = null, ?string $environment = null, ?string $param = null ) : ConnectorService
    {
        $base        = config( "vtex.{$this->seller}.{$this->env}.url.{$api}" );
        $url_prefix  = config( "vtex.{$this->seller}.{$this->env}.url.prefix" );
        $url_sufix   = config( "vtex.{$this->seller}.{$this->env}.url.sufix"  );

        $account     = $account     ?? config( "vtex.{$this->seller}.{$this->env}.account_name" );
        $environment = $environment ?? config( "vtex.{$this->seller}.{$this->env}.environment"  );

        $this->url   = implode( "", [ $url_prefix, "{$account}.", $environment, $url_sufix, $base, $param ] );

        return $this;
    }

    /**
     * Enable log message flag.
     *
     * @param   bool    $register   Whether to enable or disable logging messages. Defaults to true.
     * @return  ConnectorService    The object itself, allowing method chaining.
     */
    public function enableLogMessage( bool $register = true ) : ConnectorService
    {
        $this->log_message = $register;

        return $this;
    }

    /**
     * Enable the notification flag.
     * Used to determine whether a notification should be sent when an error occurs.
     *
     * @param   bool    $notify     Whether to enable or disable sending notifications. Defaults to true.
     * @return  ConnectorService    The object itself, allowing method chaining.
     */
    public function enableLogNotify( bool $notify = true ) : ConnectorService
    {
        $this->log_notify = $notify;

        return $this;
    }

    /**
     * Set the origin of a call.
     * Used to track the source of a specific request or action.
     *
     * @param   string  $origin     The origin of the call (e.g. "API Request" or "Manual Trigger").
     * @return  ConnectorService    The object itself, allowing method chaining.
     */
    public function setCallOrigin( string $origin = "" ) : ConnectorService
    {
        $this->call_origin = $origin;

        return $this;
    }

    /**
     * Sends a request to the Vtex API based on the provided method and data.
     *
     * @param   string  $method     The HTTP method to be used (e.g. "POST", "GET").
     * @param   array   $body       The request body as an associative array.
     * @return  array               An array containing the response code and data, or a default error message if the request was not successful.
     */
    public function sendRequest( string $method, array $body = [] ) : array
    {
        try
        {
            $pending  = Http::withHeaders( headers: $this->headers );

            if ( !empty( $body ) )
                $pending = $pending->withBody( content: json_encode( $body ), contentType: "application/json" );

            $response = $pending->{ $method }( url: $this->url );

            // Prepare response data.
            $data     = [
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
            elseif ( $this->log_message )
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

    /**
     * Replaces placeholders in the URL with corresponding values from the provided data array.
     *
     * This function iterates through the provided associative array, where each key represents
     * a placeholder in the URL, and each value is the replacement for that placeholder.
     *
     * @param   array   $data  An associative array where keys are placeholders and values are replacements.
     * @return  void
     */
    public function replaceUrl( array $data )
    {
        foreach ( $data as $key => $value )
            $this->url = str_replace( $key, $value, $this->url );
    }
}
