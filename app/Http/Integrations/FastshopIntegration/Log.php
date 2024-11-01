<?php

namespace App\Http\Integrations\FastshopIntegration;

use Exception;
use Illuminate\Support\Facades\Log as LogSys;

class Log
{
    /**
     * Logs the update of product stock and price.
     *
     * This method logs the old and new values of a product's stock and price, indicating if there was an error during the update.
     *
     * @param   object          $old    The object containing the old stock and price data.
     * @param   object|null     $new    The object containing the new stock and price data, if available.
     * @param   bool            $error  Indicates if there was an error fetching the new data.
     * @return  void
     */
    public static function updateStockPrice( object $old, ?object $new = null, bool $error = false )
    {
        self::logData( "info", "updateStockPrice", [
            $old->sku => [
                "old" => Helper::getProductStockPrice( $old ),
                "new" => ( !$error )
                            ? Helper::getProductStockPrice( $new )
                            : [ "failed to get data for update" ],
            ],
        ] );
    }

    /**
     * Logs the creation or update of a product.
     *
     * This method logs whether a product was created or updated based on the provided parameters.
     *
     * @param   string  $func       The name of the function that is being logged.
     * @param   string  $sku        The SKU of the product that was created or updated.
     * @param   bool    $update     Indicates if the product was updated (true) or created (false).
     * @return  void
     */
    public static function createOrUpdateProduct( string $func, string $sku, bool $update )
    {
        self::logData( "info", $func, [
            $sku => ( $update ) ? "product update" : "product create"
        ] );
    }

    /**
     * Logs an event where invalid credentials were used in a request.
     *
     * This method logs the app key and token used in a request that failed due to invalid credentials.
     *
     * @param   string|null     $key    The app key used in the request.
     * @param   string|null     $token  The app token used in the request.
     * @return  void
     */
    public static function invalidCredentials( ?string $key = null, ?string $token = null )
    {
        self::logData( "error", "Invalid Credentials", [
            "appKey"   => $key,
            "appToken" => $token
        ] );
    }

    /**
     * Logs a failed attempt to calculate shipping.
     *
     * This method logs the data related to a failed shipping calculation query.
     *
     * @param   array   $data   The data associated with the shipping calculation attempt.
     * @return  void
     */
    public static function freightCalculation( array $data )
    {
        self::logData( "info", "Shipping query failed", $data );
    }

    /**
     * Logs an order-related event, such as a cancellation request failure.
     *
     * This method logs order-related events, including the type of event and associated data.
     *
     * @param   string  $type   The type of log entry (e.g., 'info', 'error').
     * @param   string  $title  The title of the log entry, usually describing the event.
     * @param   array   $data   The data associated with the order event.
     * @return  void
     */
    public static function order( string $type, string $title, array $data )
    {
        self::logData( $type, $title, $data );
    }

    /**
     * Logs exceptions that occur during the execution of a specific function.
     *
     * This method is responsible for logging details of caught exceptions,
     * including the SKU of the product if provided, to facilitate debugging and tracking of issues.
     *
     * @param   string          $func   The name of the function where the exception occurred.
     * @param   Exception       $e      The exception object that was caught.
     * @param   string|null     $sku    The SKU of the product related to the exception, if applicable.
     * @return  void
     */
    public static function exception( string $func, Exception $e, ?string $sku = null )
    {
        // Prepare the error data, including the SKU if available.
        $err = $sku ? [ $sku, $e ] : [ $e ];

        // Log the error data with the function name as the title.
        self::logData( "error", "Exception - {$func}", $err );
    }

    /**
     * Standard log method, purely informational.
     *
     * This method is used for logging general information that is not necessarily related to errors or exceptions.
     * It provides a way to track events or data changes within the application.
     *
     * @param   string  $func   The name of the function that is being logged.
     * @param   array   $data   The data to be logged, typically an array of key-value pairs.
     * @return  void
     */
    public static function default( string $func, array $data )
    {
        // Log the provided data with the function name as the title.
        self::logData( "info", $func, $data );
    }

    /**
     * Logs data to a specified log channel.
     *
     * This private method is a helper that abstracts the logging mechanism, allowing other methods
     * to log data of different types (e.g., info, error) without directly interacting with the logging system.
     *
     * @param   string  $type   The type of log entry (e.g., 'info', 'error').
     * @param   string  $title  The title of the log entry, usually the function name.
     * @param   array   $data   The data to be logged, which can include various details depending on the context.
     * @return  void
     */
    private static function logData( string $type, string $title, array $data )
    {
        // Access the specified log channel and log the data with the given type and title.
        LogSys::channel( "marketplace" )->{$type}( $title, $data );
    }
}
