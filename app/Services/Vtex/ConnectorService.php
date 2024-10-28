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
    private const LOG_CHANNEL = "marketplace";

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
     * Flag indicating whether to send a notification when an error occurs.
     *
     * @var     boolean
     */
    private $log_notify  = true;

    /**
     * Stores the origin of the call (e.g., a specific method or function).
     *
     * @var     string
     */
    private $call_origin = "";

    /**
     * Seller.
     *
     * @var     string
     */
    public $seller;

    /**
     * Constructor.
     *
     * @param   string  $seller
     */
    public function __construct( string $seller = "fast_shop" )
    {
        $this->seller  = $seller;
        $this->env     = config( "vtex.partners.{$this->seller}.env"                  );
        $this->headers = config( "vtex.partners.{$this->seller}.{$this->env}.headers" );
    }

    /**
     * Build the URL for a VTEX API call.
     *
     * @param   string          $api            The name of the API endpoint to be called.
     * @param   string|null     $account        Account for the API call (default value will be used if not provided).
     * @param   string|null     $param          Additional query string parameters to the URL (default null).
     * @return  ConnectorService                The object itself, allowing method chaining.
     */
    public function buildUrl( string $api, ?string $account = null, ?string $param = null ) : ConnectorService
    {
        $account   = $account ?? config( "vtex.partners.{$this->seller}.{$this->env}.account_name" );

        $url_sufix = config( "vtex.api.{$api}" );
        $url_raw   = config( "vtex.partners.{$this->seller}.{$this->env}.raw_url" );

        $url_base  = str_replace( "{account_name}", $account, $url_raw );

        $this->url = implode( "", [ $url_base, $url_sufix, $param ] );

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
     * Replaces placeholders in the URL with corresponding values from the provided data array.
     *
     * This function iterates through the provided associative array, where each key represents
     * a placeholder in the URL, and each value is the replacement for that placeholder.
     *
     * @param   array   $data  An associative array where keys are placeholders and values are replacements.
     * @return  void
     */
    public function replaceUrl( array $data ) : void
    {
        foreach ( $data as $key => $value )
            $this->url = str_replace( $key, $value, $this->url );
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
        $code = 200;
        $data = [];

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
                );

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
     * Registration of connector on Vtex.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-mapper#post-/api/channel-manager/connector
     *
     * @return  array
     */
    public function register() : array
    {
        $response = $this->searchRegistration();

        if ( $response[ 0 ] === 200 )
        {
            $results = $response[ 1 ][ "data" ][ "results" ];

            if ( count( $results ) == 1 )
            {
                $response[ 1 ][ "data" ] = $results[ 0 ];

                return $response;
            }
        }

        $account  = config( "vtex.partners.{$this->seller}.{$this->env}.account_name" );

        // Prepare the request body.
        $body     = [
            "name"                   => "Marketplace Triibo",
            "website"                => "marketplace.triibo.com.br",
            "logo"                   => "http://php.triibo.com.br/images/logo/logo-triibo.png",
            "description"            => "Uma das empresas do grupo Synoro, a Triibo é uma plataforma de engajamento baseada no conceito de comunidade, que conecta empresas, pessoas, marcas e afins de maneira descomplicada e intuitiva.",
            "segment"                => "OTHERS",
            "availableCountries"     => [ "BRA" ],
            "type"                   => "EXTERNAL",
            "isActive"               => true,
            "helpUrl"                => config( "app.url" ),
            "connectorApiEndpoint"   => config( "app.url" ),
            "connectorPageUrl"       => config( "app.url" ),
            "connectorVtexAdminPath" => "/bridge/#/settings?openConfig=marketplacetriibo",
            "channelId"              => 8
        ];

        $this->setCallOrigin(
                origin  : "register"
            )
            ->buildUrl(
                api     : "manager-connector",
                account : "portal",
                param   : "?an={$account}"
            );

        return $this->sendRequest( method: "post", body: $body );
    }

    /**
     * Search connector already registered on Vtex.
     *
     * @see     https://developers.vtex.com/docs/api-reference/marketplace-protocol-external-marketplace-mapper#post-/api/channel-manager/connector
     *
     * @return  array
     */
    public function searchRegistration() : array
    {
        $account  = config( "vtex.partners.{$this->seller}.{$this->env}.account_name" );

        $name     = "Marketplace Triibo";
        $country  = "BRA";

        $this->setCallOrigin(
                origin  : "searchRegistration"
            )
            ->buildUrl(
                api     : "manager-connector",
                account : "portal",
                param   : "/all?an={$account}&name={$name}&country={$country}"
            );

        return $this->sendRequest( method: "get" );
    }
}
