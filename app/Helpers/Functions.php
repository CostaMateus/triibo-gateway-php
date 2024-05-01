<?php
/**
 * Helper functions for various tasks in the application.
 */

use App\Helpers\Helper;

if ( !function_exists( "log_message" ) )
{
    /**
     * Log a message to a specified channel.
     *
     * @param   string  $channel    The name of the log channel to use.
     * @param   string  $message    The message to log.
     * @param   string  $level      The log level (e.g., 'info', 'warning', 'error'). Default is 'info'.
     * @param   array   $context    Additional context data to include with the log entry.
     * @param   bool    $print      Whether to print the message to the console or not.
     * @param   bool    $notify     Whether to send a notification email or not.
     * @return  void
     */
    function log_message( string $channel, string $message, string $level = "info", array $context = [], bool $print = false, bool $notify = false )
    {
        // Log the message using the specified log channel and level.
        Log::channel( $channel )->$level( message: $message, context: $context );

        // If $print is true, print the message to the console.
        if ( $print )
            echo $message . "\n";

        // If $notification is true, send a notification email.
        if ( $notify )
        {
            $exception = $context[ "exception" ];
            $method    = $context[ "method"    ];
            $type      = $context[ "type"      ];

            // Send an email notification about the exception.
            Helper::sendMailNotification( blade: $channel, method: $method, catch: $type, e: $exception );
        }
    }
}

if ( !function_exists( "log_exception" ) )
{
    /**
     * Log an exception to a specified channel.
     *
     * @param   string      $channel    The name of the log channel to use.
     * @param   string      $message    The message to log.
     * @param   string      $level      The log level (e.g., 'info', 'warning', 'error'). Default is 'info'.
     * @param   string      $method     The method that threw the exception.
     * @param   string      $type       The type of exception that was thrown.
     * @param   Throwable   $e          The exception object.
     * @return  void
     */
    function log_exception( string $channel, string $message, string $level = "critical", string $method = "", string $type = "", ?Throwable $e = null )
    {
        $context = [
            "error_code" => $e->getCode(),
            "message"    => $e->getMessage(),
            "method"     => $method,
            "type"       => $type,
            "exception"  => $e
        ];

        log_message( channel: $channel, level: $level, message: $message, context: $context, print: true, notify: true );
    }
}

if ( !function_exists( "get_files_names" ) )
{
    /**
     * Extracts the file names from an array of file paths.
     *
     * @param   array   $files  An array of file paths.
     * @return  array           An array of file names extracted from the file paths.
     */
    function get_file_names( array $files ) : array
    {
        // Initialize an array to hold the file names.
        $files_name = [];

        // Loop through each file path in the provided array.
        // Extract the file name using pathinfo() and add it to the $files_name array.
        foreach( $files as $file )
            $files_name[] = pathinfo( path: $file )[ "basename" ];

        // Return the array of file names.
        return $files_name;
    }
}

if ( !function_exists( "merge_data" ) )
{
    /**
     * Merge data fields from an existing array into a new array.
     *
     * @param   array   $new_data       The new data array.
     * @param   array   $existing_data  The existing data array from Firebase.
     * @param   array   $fields         The fields to merge.
     * @return  array                   The merged data array.
     */
    function merge_data( array $new_data, array $existing_data, array $fields ) : array
    {
        foreach ( $fields as $field )
            if ( isset( $existing_data[ $field ] ) )
                $new_data[ $field ] = $existing_data[ $field ];

        return $new_data;
    }
}

if ( !function_exists( "set_empty_string" ) )
{
    /**
     * Function to replace null values with empty strings in an array.
     *
     * This function iterates through an array and replaces any null values with empty strings.
     * If the value is another array, it recursively calls the function to process that sub-array.
     *
     * @param   array   $data
     * @return  array
     */
    function set_empty_string( array $data )
    {
        // Auxiliary array to store the processed values.
        $result = [];

        // Loop through each element of the array.
        // If the value it's an array, recursively call the function.
        // Else keep the original value, or replace null with an empty string .
        foreach ( $data as $key => $value )
            $result[ $key ] = is_array( $value ) ? set_empty_string( $value ) : ( $value ?? "" );

        // Return the processed array.
        return $result;
    }
}

if ( !function_exists( "filter_data" ) )
{
    /**
     * Filters a multidimensional array based on a list of required fields and a specific origin.
     *
     * @param   array   $data       The multidimensional array to be filtered.
     * @param   array   $blacklist  A list of values to be ignored.
     * @param   array   $fields     A list of required fields.
     * @param   string  $origin     The desired origin for the values.
     * @return  array               An array containing the values from $data where all fields in $fields are set and the origin matches $origin.
     */
    function filter_data( array $data, array $blacklist, array $fields, string $origin ) : array
    {
        // Initialize an empty array to store the filtered values.
        $result = [];

        foreach ( $data as $key => $value )
        {
            $blacklisted = false;

            // Check if the key is in the blacklist.
            foreach ( $blacklist as $prefix )
            {
                if ( str_starts_with( $key, $prefix ) )
                {
                    $blacklisted = true;
                    break;;
                }
            }

            // Skip the value if it is
            if ( $blacklisted ) continue;


            // Check if the origin of the value is different from the desired origin and skip the value if it is.
            if( isset( $value[ "origin" ] ) && $value[ "origin" ] !== $origin )
                continue;

            // Initialize a variable to control if the value is valid.
            $valid = true;

            // Check if all required fields are set in the value.
            foreach ( $fields as $field )
            {
                if ( !isset( $value[ $field ] ) )
                {
                    // Set $valid to false if any field is not set.
                    $valid = false;
                    break;
                }
            }

            // If the value is valid (all required fields are set), add it to the result array.
            if ( $valid )
                $result[ $key ] = $value;
        }

        // Return the result array containing the filtered values.
        return $result;
    }
}
