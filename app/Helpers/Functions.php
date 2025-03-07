<?php
/**
 * Helper functions for various tasks in the application.
 */

use App\Models\User;
use Illuminate\Support\Str;
use App\Models\Marketplace\Order;
use Illuminate\Http\RedirectResponse;
use App\Notifications\ScheduleFailure;
use Kreait\Firebase\Database\Reference;
use Illuminate\Support\Facades\{ DB, Log };
use App\Services\Vtex\{ ConnectorService, FulfillmentService };

if ( !function_exists( "log_message" ) )
{
    /**
     * Log a message to a specified channel.
     *
     * @param   string  $channel    The name of the log channel to use.
     * @param   string  $message    The message to log.
     * @param   string  $level      The log level (e.g., 'info', 'warning', 'error'). Default is 'info'.
     * @param   array   $context    Additional context data to include with the log entry.
     * @return  void
     */
    function log_message( string $channel, string $message, string $level = "info", array $context = [] ) : void
    {
        // Log the message using the specified log channel and level.
        Log::channel( channel: $channel )->$level( message: trim( $message ), context: $context );
    }
}

if ( !function_exists( "log_exception" ) )
{
    /**
     * Log an exception to a specified channel.
     *
     * @param   string          $channel    The name of the log channel to use.
     * @param   string          $message    The message to log.
     * @param   string          $level      The log level (e.g., 'info', 'warning', 'error'). Default is 'info'.
     * @param   string          $method     The method that threw the exception.
     * @param   string          $type       The type of exception that was thrown.
     * @param   Throwable|null  $e          The exception object.
     * @param   array|null      $context    Additional context data to include with the log entry.
     * @return  void
     */
    function log_exception( string $channel, string $message, string $level = "critical", string $method = "", string $type = "", ?Throwable $e = null, ?array $context = null ) : void
    {
        $context   = [
            "error_code" => $e?->getCode(),
            "message"    => $e?->getMessage(),
            "method"     => $method,
            "type"       => $type,
            "exception"  => $e,
            "context"    => $context,
        ];

        // Log the message using the specified log channel and level.
        Log::channel( channel: $channel )->$level( message: trim( $message ), context: $context );

        // Send an email notification about the exception.
        send_mail_notification( blade: $channel, method: $method, catch: $type, e: $e );
    }
}

if ( !function_exists( "get_files_name" ) )
{
    /**
     * Extracts the file names from an array of file paths.
     *
     * @param   array   $files  An array of file paths.
     * @return  array           An array of file names extracted from the file paths.
     */
    function get_files_name( array $files ) : array
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
    function set_empty_string( array $data ) : array
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
     * @param   string  $type       The type of operation.
     * @param   string  $origin     The desired origin for the values.
     * @return  array               An array containing the values from $data where all fields in $fields are set and the origin matches $origin.
     */
    function filter_data( array $data, array $blacklist = [], array $fields = [], string $type = "isset" , string $origin = "" ) : array
    {
        // Initialize an empty array to store the filtered values.
        $result = [];

        foreach ( $data as $key => $value )
        {
            $blacklisted = false;

            if ( !empty( $blacklist ) )
            {
                // Check if the key is in the blacklist.
                foreach ( $blacklist as $prefix )
                {
                    if ( str_starts_with( $key, $prefix ) )
                    {
                        $blacklisted = true;
                        break;;
                    }
                }
            }

            // Skip the value if it is
            if ( $blacklisted ) continue;

            if ( !empty( $origin ) )
            {
                // Check if the origin of the value is different from the desired origin and skip the value if it is.
                if( isset( $value[ "origin" ] ) && $value[ "origin" ] !== $origin )
                    continue;
            }

            // Initialize a variable to control if the value is valid.
            $valid = true;

            if ( $type == "isset" )
            {
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
            }

            if ( $type == "compare" )
            {
                // Check if all required fields are set in or equal to the value.
                foreach ( $fields as $field => $f_value )
                {
                    if ( !isset( $value[ $field ] ) || $value[ $field ] != $f_value )
                    {
                        // Set $valid to false if any field is not set or different from the $f_value.
                        $valid = false;
                        break;
                    }
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

if ( !function_exists( "invalidate_past_changes" ) )
{
    /**
     * Invalidates past changes in a data set.
     *
     * @param   Reference   $reference  Reference to the data set to be processed.
     * @param   array       $fields     Fields to be considered during processing.
     * @return  array                   Differences found during processing.
     */
    function invalidate_past_changes( Reference $reference, array $fields )
    {
        $diffs   = [];
        $changes = filter_data( data: $reference->getValue(), fields: $fields, type: "compare" );

        foreach ( $changes as $change )
        {
            $diffs            = array_merge( $diffs, $change[ "changedFields" ] );

            $change[ "type" ] = "outdated";

            $reference->update( [ $change[ "id" ] => $change ] );
        }

        return $diffs;
    }
}

if ( !function_exists( "invalidate_past_promotion_changes" ) )
{
    /**
     * Invalidates past promotion changes.
     *
     * @param   Reference   $reference  Reference to the data set to be processed.
     * @param   string      $push_id    The ID of the promotion for which changes should be invalidated.
     * @return  array                   Differences found during processing.
     */
    function invalidate_past_promotion_changes( Reference $reference, string $push_id )
    {
        $fields  = [
            "storeId" => $push_id,
            "type"    => "validation",
        ];

        return invalidate_past_changes( reference: $reference, fields: $fields );
    }
}

if ( !function_exists( "invalidate_past_establishment_changes" ) )
{
    /**
     * Invalidates past establishment changes.
     *
     * @param   Reference   $reference  Reference to the data set to be processed.
     * @param   string      $push_id    The ID of the establishment for which changes should be invalidated.
     * @return  array                   Differences found during processing.
     */
    function invalidate_past_establishment_changes( Reference $reference, string $push_id )
    {
        $fields  = [
            "establishmentId" => $push_id,
            "type"            => "validation",
        ];

        return invalidate_past_changes( reference: $reference, fields: $fields );
    }
}

if ( !function_exists( "array_diff_assoc_recursive" ) )
{
    /**
     * For recursive diff of multiple arrays
     *
     * @param   array $array
     * @param   array ...$arrays
     * @return  array
     */
    function array_diff_assoc_recursive( array $array, array ...$arrays ) : array
    {
        $func = function ( $array1, $array2 ) use ( &$func )
        {
            $difference = [];

            foreach ( $array1 as $key => $value )
            {
                if ( is_array( $value ) )
                {
                    if ( !isset( $array2[ $key ] ) || !is_array( $array2[ $key ] ) )
                    {
                        $difference[ $key ] = $value;
                    }
                    else
                    {
                        $new_diff = $func( $value, $array2[ $key ] );

                        if ( !empty( $new_diff ) )
                            $difference[ $key ] = $new_diff;
                    }
                }
                else
                {
                    if ( !array_key_exists( $key, $array2 ) || $array2[ $key ] !== $value )
                        $difference[ $key ] = $value;
                }
            }

            return $difference;
        };

        $diffs = $array;

        foreach ( $arrays as $_array )
        {
            $diffs = $func( $diffs, $_array );
        }

        return $diffs;
    }
}

if ( !function_exists( "array_search_by_key" ) )
{
    /**
     * Search for a value in a specific column of an associative array.
     *
     * @param   string  $needle     The value to search for.
     * @param   string  $column     The column in which the value will be searched.
     * @param   array   $array      The associative array containing the data.
     * @return  int|string|false    The key for the searched value if it is found in the array, false otherwise.
     */
    function array_search_by_key( string $needle, string $column, array $array ) : int|string|false
    {
        // Create a new array containing only the values of the specified column
        $column_values = array_column( $array, $column );

        // Search for the $needle value in the new array
        return array_search( $needle, $column_values );
    }
}

if ( !function_exists( "hyphenize" ) )
{
    /**
     * Replace spaces and multiple hyphens with a single hyphen.
     *
     * @param   string  $text   The text to be processed.
     * @return  string          The text with spaces and multiple hyphens replaced by a single hyphen.
     */
    function hyphenize( string $text ) : string
    {
        return Str::slug( $text, "-" );
    }
}

if ( !function_exists( "insert_decimal" ) )
{
    /**
     * Inserts a decimal point.
     * Ex: 100 = 1.00, 150 = 1.50
     *
     * @param   string  $number   The number to be formatted.
     * @return  string            The number with the decimal point inserted.
     */
    function insert_decimal( string $number ) : string
    {
        return Str::substrReplace( $number, ".", -2, 0 );
    }
}

if ( !function_exists( "convert_weight" ) )
{
    /**
     * Formats the value with two decimal places
     * Ex: 1 = 0.01, 15 = 0.15
     *
     * @param   int     $weight   The weight value to be formatted.
     * @return  string            The formatted weight value with two decimal places.
     */
    function convert_weight( int $weight ) : string
    {
        $weight = Str::padLeft( $weight, 3, "0" );

        return insert_decimal( $weight );
    }
}

if ( !function_exists( "convert_price" ) )
{
    /**
     * Formats the value with two decimal places
     * Ex: 1 = 0.01, 15 = 0.15
     *
     * @param   int     $price   The price value to be formatted.
     * @return  string           The formatted price value with two decimal places.
     */
    function convert_price( int $price ) : string
    {
        return convert_weight( $price );
    }
}

if ( !function_exists( "convert_delivery_time" ) )
{
    /**
     * Converts the delivery time from FS to WC format
     *
     * @param   string  $time   The delivery time in FS format.
     * @return  string          The delivery time in WC format.
     */
    function convert_delivery_time( string $time ) : string
    {
        $time = explode( "bd", $time );

        return $time[ 0 ] ?? "1";
    }
}

if ( !function_exists( "convert_delivery_date" ) )
{
    /**
     * Converts the order delivery date from FS format to WC format.
     *
     * @param   string  $date_time  The delivery date in FS format.
     * @return  string              The delivery date in WC format.
     */
    function convert_delivery_date( string $date_time ) : string
    {
        return ( new \DateTime( $date_time ) )->format( "d/m/Y \à\s H:i" );
    }
}

if ( !function_exists( "validate_image_url" ) )
{
    /**
     * Validates if the provided URL corresponds to an image file.
     *
     * @param   string  $url    The URL to be validated.
     * @return  bool            Returns true if the URL corresponds to an image file, false otherwise.
     */
    function validate_image_url( string $url ) : bool
    {
        $url            = explode( "?", $url )[ 0 ];
        $extensions     = [ "jpg", "jpeg", "png", "webp" ];
        $file_extension = strtolower( pathinfo( $url, PATHINFO_EXTENSION ) );

        return in_array( $file_extension, $extensions );
    }
}

if ( !function_exists( "treat_exception_message_fs" ) )
{
    /**
     * Extracts the text enclosed within square brackets from a given string.
     *
     * This function uses a regular expression to search for the first occurrence of text
     * enclosed within square brackets `[ ]` in the provided `$message` string. If a match
     * is found, it returns the matched text (including the square brackets). If no match
     * is found, it returns `null`.
     *
     * @param   string  $message  The input string to search for text within square brackets.
     * @return  string|null       The matched text within square brackets, or `null` if no match is found.
     */
    function treat_exception_message_fs( string $message ) : ?string
    {
        if ( preg_match('/\[.*?\]/', $message, $matches ) )
            return $matches[ 0 ];

        return null;
    }
}

if ( !function_exists( "convert_date" ) )
{
    /**
     * Converts a date between the 'dd/mm/yyyy' and 'yyyy-mm-dd' formats.
     *
     * @param   string  $date   The date to be converted.
     * @return  string          The converted date in the desired format.
     */
    function convert_date( string $date ) : string
    {
        $patterns = [
            '/^(\d{4})-(\d{2})-(\d{2})$/'   => '$3/$2/$1',
            '/^(\d{2})\/(\d{2})\/(\d{4})$/' => '$3-$2-$1',
        ];

        foreach ( $patterns as $pattern => $replacement )
            if ( preg_match( $pattern, $date, $matches ) )
                return preg_replace( $pattern, $replacement, $date );

        return $date;
    }
}

if ( !function_exists( "triibo_usort" ) )
{
    /**
     * Sorts an associative array by a specific key.
     *
     * @param   string  $key    The key by which the array will be sorted.
     * @param   array   $data   The associative array to be sorted.
     * @return  void
     */
    function triibo_usort( string $key, array &$data ) : void
    {
        usort( $data, function( $a, $b ) use ( $key )
        {
            return $a[ $key ] <=> $b[ $key ];
        } );
    }
}

if ( !function_exists( "slugify" ) )
{
    /**
     * Undocumented function
     *
     * @param string $text
     * @param string $divider
     * @return string
     */
    function slugify( string $text, string $divider = "-" ) : string
    {
        return Str::slug( $text, $divider );
    }
}

if ( !function_exists( "get_schedules_operation" ) )
{
    /**
     * Returns an array of operation schedules or opening hours for each day of the week.
     *
     * @param   bool    $operation  Determines whether the returned array will contain operation schedules (true) or opening hours (false).
     * @return  array               An array containing the operation schedules or opening hours for each day of the week.
     */
    function get_schedules_operation( bool $operation = true ) : array
    {
        $template = $operation
                    ? [ "ativo" => true, "horarioFim" => "23:59", "horarioInicio" => "00:00" ]
                    : [ "tipo" => "aberto", "turnos" => [ [ "fim" => "23:59", "inicio" => "00:00" ] ] ];

        return array_fill( 0, 7, $template );
    }
}

if ( !function_exists( "send_mail_notification" ) )
{
    /**
     * Send an email notification when a schedule fails.
     *
     * This function sends an email notification to a specific user when a schedule fails.
     * It takes the following parameters:
     *
     * @param   string          $blade      The name of the blade (view) associated with the schedule.
     * @param   string          $method     The name of the method that failed.
     * @param   string          $catch      The catch block where the error occurred.
     * @param   Throwable|null  $e          The exception object containing the error details.
     * @return  void
     */
    function send_mail_notification( string $blade, string $method, string $catch, ?Throwable $e = null ) : void
    {
        // Determine the environment (HML or PRD) based on the app.env configuration.
        $env      = config( "app.env" ) == "local" ? "HML" : "PRD";

        // Define the email subjects for different blades.
        $subjects = [
            "awin"        => "{$env} - Integração Awin",
            "cofry"       => "{$env} - Integração Cofry",
            "socialsoul"  => "{$env} - Integração SocialSoul",
            "marketplace" => "{$env} - Integração Marketplace",
            "vtex"        => "{$env} - Integração VTEX",
        ];

        // Prepare the email data.
        $mail     = [
            "subject"    => $subjects[ $blade ],
            "title"      => "Erro no schedule: ",
            "method"     => $method,
            "catch"      => $catch,
            "stacktrace" => $e,
        ];

        // Get the user to be notified.
        $user     = User::firstWhere( "email", "mateus.costa@triibo.com.br" );

        // Send the notification to the user.
        $user->notify( new ScheduleFailure( $blade, $mail ) );
    }
}

if ( !function_exists( "triibo_parse_url" ) )
{
    /**
     * Extracts the domain from a given URL.
     *
     * This function takes a URL as input and returns only the domain part, removing the "www." prefix if present.
     *
     * @param   string  $url    The URL from which the domain will be extracted.
     * @return  string          The extracted domain from the URL.
     */
    function triibo_parse_url( string $url ) : string
    {
        // Parse the URL and extract only the host (domain) part.
        $parsed = parse_url( $url, PHP_URL_HOST );

        // Remove the "www." prefix from the domain, if present.
        $domain = preg_replace( "/^www\./", "", $parsed );

        return $domain;
    }
}

if ( !function_exists( "parse_url_awin" ) )
{
    /**
     * Removes campaign parameters and replaces %7Buid%7D with {uid} in a URL.
     *
     * @param   string  $url    The URL to be processed.
     * @return  string          The processed URL.
     */
    function parse_url_awin( string $url ) : string
    {
        $patterns = [
            "/&campaign=/",
            "/&clickref2=/",
            "/&clickref3=/",
            "/&clickref4=/",
            "/&clickref5=/",
            "/&clickref6=/",
        ];

        $url      = preg_replace( $patterns, "", $url );

        $url      = str_replace( "%7Buid%7D", "{uid}", $url );

        return $url;
    }
}

if ( !function_exists( "get_cashback" ) )
{
    /**
     * Calculates the cashback value based on the provided minimum and maximum values.
     *
     * @param   array   $data   An associative array containing the "min" and "max" values.
     * @return  float           The calculated cashback value.
     */
    function get_cashback( array $data ) : float
    {
        // Retrieve the "min" and "max" values from the input data, defaulting to 0 if not present.
        $min = $data[ "min" ] ?? 0;
        $max = $data[ "max" ] ?? 0;

        // Check if the "min" and "max" values are numeric, return 0.0 if not.
        if ( !is_numeric( $min ) || !is_numeric( $max ) )
            return 0.0;

        // Convert the "min" and "max" values to float.
        $min   = ( float ) $min;
        $max   = ( float ) $max;

        // Calculate the average and half of the average.
        $half  = ( $max + $min ) / 4;

        // Determine the cashback value based on the conditions.
        if ( $half >= $min )
        {
            $cashback = $min * 0.8;

            // Ensure the cashback value is at least 1.0.
            $cashback = max( $cashback, 1.0 );
        }
        else
        {
            $cashback = $half;
        }

        // Format the cashback value with one decimal place and return as a float.
        return ( float ) number_format( $cashback, 1, ".", "" );
    }
}

if ( !function_exists( "process_csv_file" ) )
{
    /**
     *
     * @param   mixed   $file
     * @return  bool
     */
    function process_csv_file( mixed $file )
    {
        $message = "";

        try
        {
            $file_name   = date( "ymdHis" ) . "_" . $file->getClientOriginalName();

            // Save csv file and get file path
            $csv_path    = $file->storeAs( "mkt-orders", $file_name, "local" );

            // Get csv content
            $csv_content = Storage::disk( "local" )->get( $csv_path );

            // Convert csv to array
            $csv_array   = array_map( function( $line )
            {
                return str_getcsv( $line, ",", '"' );
            }, explode( "\n", $csv_content ) );

            // Clean empty lines
            $csv_array = array_filter( $csv_array, function( $row )
            {
                return !empty( array_filter( $row ) );
            } );

            // Get headers
            $headers     = array_shift( $csv_array );

            DB::beginTransaction();

            try
            {
                foreach ( $csv_array as $i => $row )
                {
                    if ( count( $row ) === count( $headers ) )
                    {
                        $order    = [];
                        $billing  = [];
                        $shipping = [];

                        foreach ( $headers as $index => $header )
                        {
                            $value = $row[ $index ];

                            if ( Str::startsWith( $header, "billing_" ) )
                                $billing[ Str::after( $header, "billing_" ) ] = $value;

                            elseif ( Str::startsWith( $header, "shipping_" ) )
                                $shipping[ Str::after( $header, "shipping_" ) ] = $value;

                            elseif ( $header === "ord_id" )
                                $order[ $header ] = "ORD_" . $i + 1;

                            elseif ( $header === "ord_status" )
                                $order[ $header ] = "not_processed";

                            else
                                $order[ $header ] = $value;
                        }

                        $order[ "billing"      ] = $billing;
                        $order[ "shipping"     ] = $shipping;

                        $order[ "max_price"    ] = ( float ) $order[ "max_price"    ];
                        $order[ "product_id"   ] = ( int   ) $order[ "product_id"   ];
                        $order[ "product_qntt" ] = ( int   ) $order[ "product_qntt" ];

                        $order[ "file_name"    ] = $file_name;

                        Order::updateOrCreate(
                            [
                                "file_name" => $order[ "file_name" ],
                                "ord_id"    => $order[ "ord_id"    ]
                            ],
                            $order
                        );
                    }
                }

                DB::commit();

                return true;
            }
            catch ( Throwable $e )
            {
                DB::rollBack();

                $message = $e->getMessage();
            }
        }
        catch ( Throwable $e )
        {
            $message = $e->getMessage();
        }

        $context = [
            "file"    => $file->getClientOriginalName(),
            "message" => $message,
        ];

        log_message( channel: "triibo", message: "Falha ao processar arquivo CSV", level: "error", context: $context );

        return false;
    }
}

if ( !function_exists( "log_and_redirect" ) )
{
    /**
     * Undocumented function
     *
     * @param   string  $channel
     * @param   string  $route
     * @param   string  $type
     * @param   string  $message
     * @return  RedirectResponse
     */
    function log_and_redirect( string $channel, string $route, string $type, string $message ) : RedirectResponse
    {
        log_message( channel: $channel, message: $message );

        return redirect()->route( route: $route )->with( key: $type, value: $message );
    }
}

if ( !function_exists( "reorganize_fields" ) )
{
    /**
     * Undocumented function
     *
     * @param   array   $data
     * @param   array   $order
     * @return  void
     */
    function reorganize_fields( array $data, array $order ) : array
    {
        $result = [];

        foreach ( $order as $field )
            if ( isset( $data[ $field ] ) )
                $result[ $field ] = $data[ $field ];

        return $result;
    }
}

if ( !function_exists( "get_billing_fields_order" ) )
{
    /**
     * Undocumented function
     *
     * @return  array
     */
    function get_billing_fields_order() : array
    {
        return [
            "company",
            "email",
            "phone",
            "cnpj",
            "ie",
            "postcode",
            "address_1",
            "number",
            "address_2",
            "neighborhood",
            "city",
            "state",
            "country",
            "first_name",
            "last_name",
        ];
    }
}

if ( !function_exists( "get_shipping_fields_order" ) )
{
    /**
     * Undocumented function
     *
     * @return  array
     */
    function get_shipping_fields_order() : array
    {
        return [
            "first_name",
            "last_name",
            "email",
            "phone",
            "cpf",
            "postcode",
            "address_1",
            "number",
            "address_2",
            "neighborhood",
            "city",
            "state",
            "country",
        ];
    }
}

if ( !function_exists( "get_order_status" ) )
{
    /**
     * Undocumented function
     *
     * @param   string  $status
     * @return  array
     */
    function get_order_status( string $status ) : array
    {
        return match ( $status )
        {
            "not_processed"     => [
                "name"  => "Não Processado",
                "color" => "text-white text-bg-secondary",
            ],
            "processed"         => [
                "name"  => "Processado",
                "color" => "text-white text-bg-info",
            ],
            "approved"          => [
                "name"  => "Aprovado",
                "color" => "text-white text-bg-success",
            ],
            "canceled"          => [
                "name"  => "Cancelado",
                "color" => "text-white text-bg-danger",
            ],

            "product_not_found" => [
                "name"  => "Produto não encontrado",
                "color" => "text-dark text-bg-warning",
            ],
            "over_max"          => [
                "name"  => "Preço acima do limite",
                "color" => "text-dark text-bg-warning",
            ],

            "fs_create_error"   => [
                "name"  => "Falha ao criar na Fast Shop",
                "color" => "text-white text-bg-danger",
            ],
            "fs_approve_error"  => [
                "name"  => "Falha ao aprovar na Fast Shop",
                "color" => "text-white text-bg-danger",
            ],
            "fs_cancel_error"   => [
                "name"  => "Falha ao cancelar na Fast Shop",
                "color" => "text-white text-bg-danger",
            ],

            default             => [
                "name"  => "Não definido",
                "color" => "text-white text-bg-primary",
            ],
        };
    }
}

if ( !function_exists( "get_all_order_status" ) )
{
    /**
     * Undocumented function
     *
     * @return  array
     */
    function get_all_order_status() : array
    {
        return [
            "all"               => "Todos",
            "not_processed"     => "Não Processado",
            "processed"         => "Processado",
            "approved"          => "Aprovado",
            "canceled"          => "Cancelado",

            "product_not_found" => "Produto não encontrado",
            "over_max"          => "Preço acima do limite",
            "fs_create_error"   => "Falha ao criar na Fast Shop",
            "fs_approve_error"  => "Falha ao aprovar na Fast Shop",
            "fs_cancel_error"   => "Falha ao cancelar na Fast Shop",
        ];
    }
}

if ( !function_exists( "search_product_meta" ) )
{
    /**
     *
     * @param   array   $metas
     * @param   string  $key
     * @return  mixed
     */
    function search_product_meta( array $metas, string $key = "_fs_product_id" ) : mixed
    {
        foreach ( $metas as $meta )
            if ( $meta->key == $key )
                return $meta->value;

        return null;
    }
}

if ( !function_exists( "get_freight_vtex" ) )
{
    /**
     * Undocumented function
     *
     * @param integer $product_id
     * @return  mixed
     */
    function get_freight_vtex( int $product_id, string $postcode ) : mixed
    {
        $connector = new ConnectorService( "fast_shop" );
        $service   = new FulfillmentService( $connector );

        $req_data  = [
            "items" => [
                [
                    "id"       => $product_id,
                    "quantity" => 1,
                    "seller"   => "1",
                ]
            ],
            "isCheckedIn" => false,
            "country"     => "BRA",
            "selectedSla" => "Normal",
            "postalCode"  => $postcode,
        ];

        $response  = $service->simulate( $req_data );

        if ( $response[ 0 ] != 200 )
            return false;

        return $response[ 1 ];
    }
}

if ( !function_exists( "get_delivery_price" ) )
{
    /**
     * Undocumented function
     *
     * @param   array   $data
     * @return  mixed
     */
    function get_delivery_price( int $product_id, array $freight ) : mixed
    {
        $data = vtex_freight_to_woo( $freight );

        if ( empty( $data ) || ( isset( $data[ "code" ] ) && $data[ "code" ] != null ) )
            return false;

        return $data[ $product_id ][ "delivery_price" ];
    }
}

if ( !function_exists( "marketplace_new_order_prepare" ) )
{
    /**
     * Prepare order data for the marketplace.
     *
     * @param   Order   $order          The order data.
     * @param   string  $product_id     The product ID.
     * @param   string  $freight        The freight of order.
     * @return  array                   The prepared order data.
     */
    function marketplace_new_order_prepare( Order $order, string $product_id, string $freight ) : array
    {
        $data = [
            "customer_id"          => $order[ "customer_id" ] ?? 1717,
            "set_paid"             => true,
            "status"               => "processing",
            "payment_method"       => "triibo_payments_billet",
            "payment_method_title" => "Triibo Boleto",
            "billing"              => reorganize_fields( $order->billing,  get_billing_fields_order()  ),
            "shipping"             => reorganize_fields( $order->shipping, get_shipping_fields_order() ),
            "line_items"           => [ [
                "product_id" => $product_id,
                "quantity"   => $order->product_qntt,
            ] ],
            "shipping_lines"       => [ [
                "method_id"    => "triibo-fast-shop-shipping",
                "method_title" => "Fast Shop",
                "total"        => $freight
            ] ],
            "meta_data"            => [
                [ "key" => "_shipping_number",       "value" => $order->shipping[ "number"       ], ],
                [ "key" => "_shipping_neighborhood", "value" => $order->shipping[ "neighborhood" ], ],
                [ "key" => "_billing_number",        "value" => $order->billing [ "number"       ], ],
                [ "key" => "_billing_neighborhood",  "value" => $order->billing [ "neighborhood" ], ],
                [ "key" => "_billing_cnpj",          "value" => $order->billing [ "cnpj"         ], ],
                [ "key" => "_billing_ie",            "value" => $order->billing [ "ie"           ], ],
                [ "key" => "_billing_persontype",    "value" => 2, ] // J - pessoa juridica
            ]
        ];

        return $data;
    }
}

if ( !function_exists( "get_date_time_unix" ) )
{
    /**
     * Get datetime ms
     *
     * @param   string  $format
     * @param   bool    $immutable
     * @return  int
     */
    function get_date_time_unix( string $format = "Y-m-d H:i:s.u", bool $immutable = false ) : int
    {
        $date = ( !$immutable ) ? new \DateTime( date( $format ) ) : new \DateTimeImmutable( $format );
        $date = ( int ) $date->format( "Uv" );

        return $date;
    }
}

if ( !function_exists( "get_date_time_unix_interval" ) )
{
    /**
     * Get datetime ms, with some interval
     *
     * @param   string  $interval
     * @return  int
     */
    function get_date_time_unix_interval( string $interval ) : int
    {
        $date = new \DateTime( date( "Y-m-d H:i:s.u" ) );
        $date = $date->add( new \DateInterval( $interval ) );
        $date = ( int ) $date->format( "Uv" );

        return $date;
    }
}

if ( !function_exists( "clear_directory" ) )
{
    /**
     * Clear directory.
     *
     * @param   string  $path
     * @return  void
     */
    function clear_directory( string $path ) : void
    {
        $directory = public_path( path: $path );

        File::deleteDirectory( directory: $directory, preserve: true );
    }
}

if ( !function_exists( "cashback_float" ) )
{
    /**
     * Convert cashback to float.
     *
     * @param   array   $establishment
     * @return  void
     */
    function cashback_float( array &$establishment ) : void
    {
        if ( isset( $establishment[ "cashbackDefault" ] ) )
            $establishment[ "cashbackDefault" ] = ( float ) $establishment[ "cashbackDefault" ];
    }
}

if ( !function_exists( "update_local_establishment" ) )
{
    /**
     * Updates the local establishment.
     *
     * @param   string  $affiliate_id
     * @param   string  $push_id
     * @param   array   $establishment
     * @return  void
     */
    function update_local_establishment( string $affiliate_id, string $push_id, array $establishment ) : void
    {
        DB::table( "affiliates_establishments" )
            ->where( "affiliate_id", $affiliate_id )
            ->update( [
                "push_id" => $push_id,
                "data"    => json_encode( $establishment )
            ] );
    }
}
