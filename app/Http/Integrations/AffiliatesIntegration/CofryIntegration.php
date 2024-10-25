<?php

namespace App\Http\Integrations\AffiliatesIntegration;

use DateTime;
use DateInterval;
use App\Helpers\Helper;
use Gregwar\Image\Image;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Illuminate\Support\Facades\{ Log, File, Http };

class CofryIntegration
{
    /**
     * Timestamp for when establishments should be enabled.
     *
     * @var     integer
     */
    private static $date_enable;

    /**
     * Timestamp for when establishments should be disabled.
     *
     * @var     integer
     */
    private static $date_disable;

    /**
     * Undocumented variable
     *
     * @var     array
     */
    private static $deny_list;

    /**
     * Initialize static date variables for enable and disable timestamps.
     * This method sets up the timestamps only once to avoid redundant calculations.
     *
     * @return  void
     */
    private static function initDates() : void
    {
        // Check if the enable date has not been set yet.
        if ( !isset( self::$date_enable ) )
        {
            // Create a new DateTime object for the current time.
            $date = new DateTime();

            // Set the enable date as a Unix timestamp in milliseconds.
            self::$date_enable  = ( int ) $date->format( "U" ) * 1000;

            // Add 7 years to the current date to calculate the disable date.
            // Set the disable date as a Unix timestamp in milliseconds.
            self::$date_disable = ( int ) $date->add( new DateInterval( "P7Y" ) )->format( "U" ) * 1000;
        }
    }

    /**
     * Retrieves an exclusion list of stores, given the partner
     *
     * This function connects to a Firebase database, navigates to the specific reference
     * based on the partner's name, and retrieves the deny_list associated with that partner.
     * The deny_list is an array of stores that the partner does not have access to.
     *
     * @param   string  $partner    The identifier for the partner whose deny_list is being requested.
     * @return  array               An array containing the list of denied stores for the partner.
     */
    private static function getDenylist( $partner ) : array
    {
        if ( !isset( self::$deny_list ) )
        {
            // Connect to the Firebase database and retrieve the deny_list for the specified partner
            self::$deny_list = Firebase::database()
                                       ->getReference( "/Partners-Denylist/$partner" )
                                       ->getValue();
        }

        return self::$deny_list;
    }

    /**
     * Formats the logo URL by removing the S3 bucket URL and appending the appropriate prefix.
     * If the logo filename is empty after removing the S3 bucket URL, 'standard.png' is used as a default.
     *
     * @param   string  $logo   The full URL of the logo stored in the S3 bucket.
     * @param   string  $prefix The prefix to be appended before the logo filename.
     * @return  string          The formatted logo URL with the prefix and filename.
     */
    private static function formatLogoUrl( string $logo, string $prefix ) : string
    {
        // Remove the S3 bucket URL from the logo URL to get just the filename.
        $logo = str_replace( "https://imgcofrynew.s3.amazonaws.com/", "", $logo );

        // If the logo filename is not empty, use it; otherwise, use 'standard.png'.
        // Prepend the provided prefix to the filename.
        return $prefix . ( basename( $logo ) ?: "standard.png" );
    }

    /**
     * Combines various text segments into a single formatted description.
     * The function takes a mandatory 'about' section and 'general_rules', as well as optional
     * 'additional_rules' and 'specific_rules'. Each non-empty section is separated by two newlines.
     *
     * @param   string       $about             The main description of the entity.
     * @param   string       $general_rules     General rules or information that always apply.
     * @param   string|null  $additional_rules  Additional rules or information, optional.
     * @param   string|null  $specific_rules    Specific rules or information, optional.
     * @return  string                          The combined and formatted description text.
     */
    private static function prepareDescription( string $about, string $general_rules, ?string $additional_rules = null, ?string $specific_rules = null ) : string
    {
        $about       = str_replace( "  ", " ", trim( $about ) );

        // Start with the 'about' section, ensuring it has no leading/trailing whitespace.
        $description = ( $about ? $about . "\n\n" : "" ) . $general_rules;

        // If 'additional_rules' is provided, append it separated by two newlines.
        if ( $additional_rules )
            $description .= "\n\n" . $additional_rules;

        // If 'specific_rules' is provided, append it separated by two newlines.
        if ( $specific_rules )
            $description .= "\n\n" . $specific_rules;

        // Return the combined description.
        return $description;
    }

    /**
     * Prepares and formats rules by decoding JSON strings and replacing HTML tags with plain text.
     * The function throws an exception if there is an error in decoding the JSON.
     *
     * @param   string      $rules          JSON string containing general rules with HTML formatting.
     * @param   string|null $specificRules  Optional JSON string containing specific rules with HTML formatting.
     * @return  array                       Array with 'general' and 'specific' rules as plain text.
     * @throws  \Exception                  Throws an exception if there is an error in JSON decoding.
     */
    private static function prepareRules( string $rules, ?string $specific_rules = null ) : array
    {
        // Decode the JSON string and remove tabs.
        $decoded_rules = json_decode( str_replace( "\t", "", $rules ), true );

        // Check for JSON decoding errors and throw an exception if necessary.
        if ( json_last_error() !== JSON_ERROR_NONE )
            throw new \Exception( "Func prepareRules(). Error decoding JSON rules: " . json_last_error_msg() );

        // Replace HTML list tags with plain text formatting.
        $search        = [ "<ol><li>", "</li></ol>", "</li><li>" ];
        $replace       = [ "",         "",           "\n"        ];

        $decoded_rules[ "general"  ] = str_replace( $search, $replace, $decoded_rules[ "general" ] );

        // Initialize 'specific' rules as an empty string.
        $decoded_rules[ "specific" ] = "";

        // Process specific rules if provided.
        if ( $specific_rules )
        {
            $decoded_specific = json_decode( $specific_rules, true );

            // Check for JSON decoding errors and ensure the 'description' key exists.
            if ( json_last_error() === JSON_ERROR_NONE && isset( $decoded_specific[ "rules" ][ 0 ][ "description" ] ) )
            {
                // Replace bold tags with plain text formatting.
                $decoded_specific[ "rules" ][ 0 ][ "description"] = str_replace( [ "<b>", "</b>" ], "", $decoded_specific[ "rules" ][ 0 ][ "description" ] );

                $decoded_rules[ "specific" ] = $decoded_specific[ "rules" ][ 0 ][ "description" ];
            }
        }

        return $decoded_rules;
    }

    /**
     * Makes a POST request to the Cofry API to retrieve data such as stores, promotions, or store statuses.
     *
     * This function handles the common logic for making an HTTP POST request to the Cofry API using
     * the application's configuration settings for the API endpoint and parameters. If the API call
     * is successful and returns an error code of 0, the requested data is returned. Otherwise, the
     * error is logged, and null is returned.
     *
     * @param   string      $type   The type of data being requested (used for logging).
     * @param   string      $url    The API endpoint URL.
     * @return  array|null          The requested data on success, or null on failure.
     */
    private static function getData( string $type, string $url ) : array|null
    {
        // Retrieve the API configuration from the application's configuration settings.
        $api_config = config( "cofry.api" );

        // Make an HTTP POST request to the Cofry API endpoint with the API configuration parameters.
        // SSL certificate verification is disabled for this request.
        $response   = Http::withoutVerifying()->asForm()->post( $url, $api_config );

        // Check if the response from the API is successful.
        if ( $response->successful() )
        {
            // Decode the JSON response into an associative array.
            $data = $response->json();

            // Check if the 'errorCode' is set in the response and equals 0, indicating success.
            if ( isset( $data[ "errorCode" ] ) && $data[ "errorCode" ] == 0 )
            {
                // Return the 'store' data from the response, or null if it's not set.
                return $data[ "store" ] ?? null;
            }
        }

        // If the response is not successful or contains an error code, log the error details.
        $code    = $response[ "errorCode"    ] ?? "Unknow";
        $error   = $response[ "errorMessage" ] ?? "No error message provided";
        $message = "API: {$type} | Code: {$code} | Message: {$error}";

        log_message( channel: "marketplace", level: "error", message: $message );

        echo $message;

        // Return null if the request was not successful or if an error code was present.
        return null;
    }

    /**
     * Retrieves a list of establishments from the Cofry API.
     *
     * @return  array|null  The list of establishments on success, or null on failure.
     */
    private static function getEstablishmentsCofry() : array|null
    {
        // Retrieve the store URL from the application's configuration settings.
        $url = config( "cofry.url.establishment" );

        return self::getData( "getEstablishments", $url );
    }

    /**
     * Retrieves a list of promotions from the Cofry API.
     *
     * @return  array|null  The list of promotions on success, or null on failure.
     */
    private static function getPromotionsCofry() : array|null
    {
        // Retrieve the promo URL from the application's configuration settings.
        $url = config( "cofry.url.promotion" );

        return self::getData( "getPromotions", $url );
    }

    /**
     * Retrieves the status of establishments from the Cofry API.
     *
     * @return array|null The status of establishments on success, or null on failure.
     */
    private static function getEstablishmentsCofryStatus() : array|null
    {
        // Retrieve the status URL from the application's configuration settings.
        $url = config( "cofry.url.status" );

        return self::getData( "getEstablishmentsStatus", $url );
    }

    /**
     * Sorts an array by the "uuid" key using the spaceship operator.
     *
     * @param   array $arr The array to be sorted by reference.
     * @return  void
     */
    private static function usort( array &$arr ) : void
    {
        usort( $arr, function( array $a, array $b ) : int
        {
            return $a[ "uuid" ] <=> $b[ "uuid" ];
        } );
    }

    /**
     * Get Cofry actives establishments.
     *
     * @return  array|null  Returns an array of active stores or null if establishments or status are not available.
     */
    private static function getActiveEstablishments() : array|null
    {
        // Retrieve the list of Cofry establishments.
        $establishments = self::getEstablishmentsCofry();

        // Retrieve the status of Cofry establishments.
        $status         = self::getEstablishmentsCofryStatus();

        // Return null if establishments or status are not available.
        if ( !$establishments || !$status ) return null;

        // Sort the establishments and status arrays.
        self::usort( $establishments );
        self::usort( $status         );

        /** Lojas tag Destaques */
            // - 123  Booking
            // - 137  Nike
            // - 347  Amazon - removido
            // - 349  Americanas
            // - 351  Submarino
            // - 357  Centauro
            // - 445  Carrefour
            // - 503  Cobasi
        /************************/

        // Define the uuids of highlighted stores.
        $highlights     = [ 123, 137, 349, 351, 357, 445, 503 ];
        // Flip the highlights array for faster isset checks.
        $highlights_map = array_flip( $highlights );

        // Create a map of establishment status with uuid as key for quick lookup.
        $status_map     = array_column( $status, null, "uuid" );

        // Iterate over the establishments to set their active status and segments.
        foreach ( $establishments as $index => &$store )
        {
            // Retrieve the uuid of the current store.
            $uuid              = $store[ "uuid" ];
            // Set the active status of the store, defaulting to false if not set.
            $store[ "Active" ] = $status_map[ $uuid ][ "Active" ] ?? false;

            // Split the store's segments into an array, if any.
            $tags              = $store[ "Segment" ] ? preg_split( "/\b,\b/u", $store[ "Segment" ] ) : [];

            // Unset and skip the current store if it has the "Seguros e Finanças" segment.
            if ( in_array( "Seguros e Finanças", $tags, true ) )
            {
                unset( $establishments[ $index ] );
                continue;
            }

            // Add the "Destaques" tag if the store is in the highlights list.
            if ( isset( $highlights_map[ $uuid ] ) )
                $tags[] = "Destaques";

            // Add "Cofry" as the first tag.
            array_unshift( $tags, "Cofry" );

            // Update the store's segments.
            $store[ "Segment" ] = $tags;
        }

        // Return the list of modified establishments.
        return $establishments;
    }

    /**
     * Translates Cofry store data into Triibo Establishments.
     *
     * This function is responsible for converting establishment data from
     * Cofry into a format that can be used for Triibo establishments.
     * It performs string manipulations, data formatting, and checks against a
     * deny list to determine if a establishment should be published.
     *
     * @param   array   $data       The array of store data to be translated.
     * @return  array   $result     The array of translated establishments.
     */
    private static function translateEstablishments( array $data ) : array
    {
        // Initialize date-related settings for the promotion.
        self::initDates();

        // Retrieve the Cofry ID prefix from the configuration.
        $prefix_cofry = config( "cofry.id" );

        // Get the list of establishments that should not be published.
        $deny_list    = self::getDenylist( "Mapfre" );

        // Initialize an array to hold the resulting translated establishments.
        $result       = [];

        // Loop through each establishment in the provided data.
        foreach ( $data as $establishment )
        {
            // Concatenate the prefix with the establishment UUID to create a unique ID.
            $id            = $prefix_cofry . $establishment[ "uuid" ];

            // Trim the name of the advertiser.
            $name          = trim( $establishment[ "Advertiser" ] );

            // Format the cashback percentage for display.
            $cashback      = number_format( ( float ) $establishment[ "CashbackClient" ], $establishment[ "CashbackFormat" ], ",", "" );

            // Format the logo URL using a predefined function.
            $logo          = self::formatLogoUrl( $establishment[ "Logo" ], config( "cofry.prefix.establishment" ) );

            // Prepare the rules for the establishment by combining general and specific rules.
            $rules         = self::prepareRules( $establishment[ "GeneralRules" ], ( $establishment[ "SpecificRules" ] ?? null ) );

            // Prepare the full description for the establishment.
            $description   = self::prepareDescription( $establishment[ "About" ], $rules[ "general" ], $rules[ "additional" ], ( $rules[ "specific" ] ?: null ) );
            $description   = Helper::addTagsToDescriptionCofry( $description, $establishment[ "Segment" ] );

            // Determine if the establishment should be published based on the deny list.
            $is_published  = in_array( $name, $deny_list ) ? false : ( $establishment[ "Active" ] ? true : false );

            // Add the translated establishment to the result array.
            $result[ $id ] = [
                "partnerEstablishmentId" => $establishment[ "uuid" ],
                "cashbackDefault"        => $cashback,
                "enableDate"             => self::$date_enable,
                "updateDate"             => self::$date_enable,
                "disableDate"            => self::$date_disable,
                "dataAlteracao"          => self::$date_enable,
                "dataInclusao"           => self::$date_enable,
                "idGestor"               => "+5511949289507@sms,triibo,com,br",
                "contatos"               => [ "+55 (11) 94496-5799" ],
                "isPublished"            => $is_published,
                "endereco"               => "Brasil",
                "lat"                    => -15.52047783763576,
                "long"                   => -48.015408891158984,
                "nome"                   => $name,
                "descricao"              => $description,
                "fotoThumb"              => $logo,
                "listaFotos"             => [ $logo ],
                "funcionamento"          => [ ...get_schedules_operation()        ],
                "horarios"               => [ ...get_schedules_operation( false ) ],
                "tags"                   => [ ...$establishment[ "Segment" ] ],
                "keyWordsSet"            => [],
                "origin"                 => "cofry",
            ];
        }

        // Return the array of translated establishments.
        return $result;
    }

    /**
     * Translates Cofry Establishments into Triibo promotions.
     *
     * This function is responsible for converting establishment data from
     * Cofry into a format that can be used for Triibo promotions.
     * It performs string manipulations, data formatting, and checks against a
     * deny list to determine if a promotion should be published.
     *
     * @param   array   $data  The array of establishment data to be translated.
     * @return  array          The array of translated promotions.
     */
    private static function translatePromotionDefault( array $data ) : array
    {
        // Initialize date-related settings for the promotion.
        self::initDates();

        // Retrieve the Cofry ID prefix from the configuration.
        $prefix_cofry = config( "cofry.id" );

        // Get the list of establishments that should not be published.
        $deny_list    = self::getDenylist( "Mapfre" );

        // Initialize an array to hold the resulting translated promotions.
        $result       = [];

        // Loop through each establishment in the provided data.
        foreach ( $data as $establishment )
        {
            // Construct the establishment ID by appending the establishment UUID to the Cofry prefix.
            $establishment_id = $prefix_cofry . $establishment[ "uuid" ];

            // Remove non-word characters and transliterate to ASCII, then remove spaces to form the promotion ID.
            $aux_advertiser   = preg_replace( "/[^\w\s]/", "", iconv( "UTF-8", "ASCII//TRANSLIT//IGNORE", $establishment[ "Advertiser" ] ) );
            $promotion_id     = strtolower( str_replace( " ", "", str_replace( "\t", "", $aux_advertiser ) ) );
            $id               = $establishment_id . $promotion_id;

            // Trim the advertiser's name to remove any extra whitespace.
            $name             = trim( $establishment[ "Advertiser" ] );

            // Format the cashback percentage for display.
            $cashback         = number_format( ( float ) $establishment[ "CashbackClient" ], $establishment[ "CashbackFormat" ], ",", "" );

            // Format the logo URL using a predefined function.
            $logo             = self::formatLogoUrl( $establishment[ "Logo" ], config( "cofry.prefix.promotion" ) );

            // Clean up the 'About' text by removing extra spaces.
            $about            = str_replace( "  ", " ", trim( $establishment[ "About" ] ) );

            // Prepare the rules for the promotion by combining general and specific rules.
            $rules            = self::prepareRules( $establishment[ "GeneralRules" ], ( $establishment[ "SpecificRules" ] ?? null ) );

            // Prepare the full description for the promotion.
            $description      = self::prepareDescription( "", $rules[ "general" ], ( $rules[ "additional" ] ?: null ), ( $rules[ "specific" ] ?: null ) );

            // Determine if the promotion should be published based on the deny list.
            $is_published     = !in_array( $name, $deny_list );

            // Add the translated promotion to the result array.
            $result[ $id ]    = [
                "partnerPromotionId" => $promotion_id,
                "establishmentId"    => $establishment_id,
                "establishmentName"  => $name,
                "title"              => "Ganhe " . $cashback . "% de volta!",
                "detailsTitle"       => "Ganhe " . $cashback . "% de volta!",
                "description"        => $about,
                "detailsDescription" => $description,
                "enableDate"         => self::$date_enable,
                "disableDate"        => self::$date_disable,
                "dueDate"            => self::$date_disable,
                "isPublished"        => $is_published,
                "active"             => true,
                "lat"                => -15.52047783763576,
                "long"               => -48.015408891158984,
                "level"              => 99,
                "photo"              => $logo,
                "thumbnail"          => $logo,
                "region"             => "nacional",
                "type"               => "businessPartner",
                "url"                => "{urlCofry}",
                "keyWordsSet"        => [],
                "origin"             => "cofry",
                "buttonLabel"        => "Conferir",
                "id"                 => "",
            ];
        }

        // Return the array of translated promotions.
        return $result;
    }

    /**
     * Translates Cofry promotions into Triibo promotions.
     *
     * This function takes an array of promotions from Cofry and translates them into a format suitable for Triibo.
     * It performs various string manipulations to format the promotion details correctly and checks against a deny list
     * to determine if a promotion should be published.
     *
     * @param   array   $data   Array of promotions to be translated.
     * @return  array           Translated promotions with Triibo-specific formatting.
     */
    private static function translatePromotion( array $data ) : array
    {
        // Initialize date-related variables for the promotion period.
        self::initDates();

        // Fetch the Cofry ID prefix from configuration.
        $prefix_cofry = config( "cofry.id" );

        // Retrieve the deny list for promotions that should not be published.
        $deny_list    = self::getDenylist( "Mapfre" );

        // Initialize the result array to hold the translated promotions.
        $result       = [];

        // Loop through each promotion in the provided data array.
        foreach ( $data as $promotion )
        {
            // Construct the establishment ID by appending the promotion UUID to the Cofry prefix.
            $establishment_id = $prefix_cofry . $promotion[ "uuid" ];
            // Remove any periods from the promotion ID.
            $promotion_id     = str_replace( ".", "", $promotion[ "ProductAPIID" ] );
            // Concatenate the establishment ID and promotion ID to form a unique identifier.
            $id               = $establishment_id . $promotion_id;

            // Format the cashback percentage as a string without decimals.
            $cashback         = number_format( ( float ) $promotion[ "CashbackClient" ], $promotion[ "CashbackFormat" ], ",", "" );

            // Trim any whitespace from the advertiser's name.
            $name             = trim( $promotion[ "Advertiser" ] );

            // Define the search patterns for the promotion code and replace them with an empty string.
            $search_code      = [ " ", "Cupom:", "\t" ];
            $code             = str_replace( $search_code, "", $promotion[ "ItemCode" ] );

            // Check if the item title is a placeholder and replace it with a formatted string.
            $item_title       = ( $promotion[ "ItemTitle" ] === "******" ) ? "Cupom: ******" : $promotion[ "ItemTitle" ];

            // Define the search and replace patterns for the item title.
            $search_item      = [
                "( Código: ******)",  "Código ******",    "(código: ******)", "(cÓdigo: ******)",
                "(código: ******  )", "Cupom******",      "Cupom ******",     "Cupom:******",
                "Cupom = ******",     "(cupom: ******)",  " ****** ",         "****** de ",
                "****** & ",          " ******!",         "   ",              "  "
            ];
            $replace_item     = [
                " (Código: ******)",  "(Código: ******)", "(Código: ******)", "(Código: ******)",
                "(Código: ******)",   "Cupom: ******",    "Cupom: ******",    "Cupom: ******",
                "Cupom: ******",      "Cupom: ******",    " ",                "",
                "",                   "",                 " ",                " "
            ];

            // Perform the replacements on the item title.
            $item_title       = str_replace( $search_item, $replace_item, $item_title );
            // Replace placeholders with the actual promotion code.
            $item_title       = str_replace( [ ": ******", "Use ******" ], [ ": {$code}", "Use {$code}" ], $item_title );

            // Determine if the promotion should be published based on the deny list.
            $is_published     = ( in_array( $name, $deny_list ) ) ? false : true;

            // Construct the promotion array with the formatted details and add it to the result array.
            $result[ $id ]    = [
                "partnerPromotionId" => $promotion_id,
                "establishmentId"    => $establishment_id,
                "establishmentName"  => $name,
                "title"              => "Ganhe " . $cashback . "% de volta!",
                "detailsTitle"       => "Ganhe " . $cashback . "% de volta!",
                "description"        => $item_title,
                "detailsDescription" => "{$item_title}\n\nUtilize o código de desconto {$code} no fechamento da compra.",
                "checkoutCode"       => $code,
                "enableDate"         => self::$date_enable,
                "disableDate"        => self::$date_disable,
                "dueDate"            => self::$date_disable,
                "isPublished"        => $is_published,
                "active"             => true,
                "lat"                => -15.52047783763576,
                "long"               => -48.015408891158984,
                "level"              => 99,
                "photo"              => "common_promotion.png",
                "thumbnail"          => "common_promotion.png",
                "region"             => "nacional",
                "type"               => "businessPartner",
                "url"                => "{urlCofry}",
                "keyWordsSet"        => [],
                "origin"             => "cofry",
                "buttonLabel"        => "Conferir",
                "id"                 => "",
            ];
        }

        // Construct the promotion array with the formatted details and add it to the result array.
        return $result;
    }

    /**
     * Download and save the image
     *
     * This function is responsible for saving the logo image for an establishment or promotion.
     * It fetches the image from a remote URL, processes it, and saves it to the appropriate directories.
     *
     * @param   string  $logo
     * @return  void
     */
    private static function saveImage( string $logo ) : void
    {
        // Define the base public path for images.
        $public_path        = public_path( "images" );

        // Define the paths for establishment and promotion images.
        $establishment_path = "{$public_path}/estabelecimentos";
        $promotion_path     = "{$public_path}/promos";

        // Extract the image name from the logo URL.
        $image_name         = basename( str_replace( "https://imgcofrynew.s3.amazonaws.com/", "", $logo ) );

        if ( $image_name )
        {
            // Retrieve the prefix configuration for store and promo images.
            $prefix_establishment    = config( "cofry.prefix.establishment" );
            $prefix_promotion        = config( "cofry.prefix.promotion"     );

            // Construct the full paths for the establishment and promotion images.
            $establishment_full_path = "{$establishment_path}/{$prefix_establishment}{$image_name}";
            $promotion_full_path     = "{$promotion_path}/{$prefix_promotion}{$image_name}";

            try
            {
                // Fetch the image from the remote URL without SSL verification.
                $response = Http::withoutVerifying()->get( $logo );

                // If the response is successful, save the image and process it.
                if ( $response->successful() )
                {
                    // Save the image content to the establishment path.
                    file_put_contents( $establishment_full_path, $response->body() );

                    // Open the image, resize it, and save it to both establishment and promotion paths.
                    $handle = Image::open( $establishment_full_path );
                    $handle->scaleResize( 200, 150, "#FFF" );
                    $handle->save( $establishment_full_path );
                    $handle->save( $promotion_full_path     );
                }
            }
            catch ( \Throwable $e )
            {
                // Log the exception details to the 'cofry' channel.

                $message = "Images: Failure to get establishments images";

                log_exception( channel: "cofry", message: $message, method: "saveImage", type: "Throwable", e: $e );
            }
        }
    }

    // =============================================

    /**
     * Retrieves a list of promotions from Cofry, sorted by establishment name.
     *
     * @return  array|null  Returns an array of promotions or null if stores or promotions are not available.
     */
    public static function getPromotions() : array|null
    {
        // Retrieve the list of Cofry establishments.
        $establishments = self::getEstablishmentsCofry();
        // Retrieve the list of Cofry promotions.
        $promotions     = self::getPromotionsCofry();

        // If either establishments or promotions are not available, return null.
        if ( !$establishments || !$promotions ) return null;

        // Sort the establishments and promotions arrays for consistency.
        self::usort( $establishments );
        self::usort( $promotions );

        // Translate the default and regular promotions.
        $default        = self::translatePromotionDefault( $establishments );
        $regular        = self::translatePromotion( $promotions );

        // Merge the translated promotions into a single array.
        $promotions     = array_merge_recursive( $default, $regular );
        // Remove any duplicate promotions from the array.
        $promotions     = array_unique( $promotions, SORT_REGULAR );

        // Sort the promotions by the name of the establishment.
        $keys           = array_column( $promotions, "establishmentName" );
		array_multisort( $keys, SORT_ASC, $promotions );

        // Define the path for the common promotion image.
        $cupom_path     = public_path( "images"        ) . "/cupom.png";
        $promotion_path = public_path( "images/promos" ) . "/common_promotion.png";

        // Copy the common promotion image to the promotions directory if it doesn't exist.
        if ( !file_exists( $promotion_path ) )
            File::copy( $cupom_path, $promotion_path );

        // Return the sorted and unique list of promotions.
        return $promotions;
    }

    /**
     * Retrieves and translates the list of active Cofry establishments.
     * If no establishments are found, it returns null.
     *
     * @return  array|null  The translated list of establishments or null if none are found.
     */
    public static function getEstablishments() : array|null
    {
        // Retrieve the list of active Cofry establishments.
        $establishments = self::getActiveEstablishments();

        // Check if the establishments list is empty.
        if ( !$establishments ) return null;

        // Translate the establishment details and return the translated list.
        return self::translateEstablishments( $establishments );
    }

    /**
     * Retrieves active establishments' logos and copies default images to specific directories.
     *
     * This function is responsible for handling the retrieval and management of image assets for active establishments.
     * It fetches logos for each active establishment and ensures that default images are copied to the correct directories.
     * This is useful for maintaining a consistent visual representation of establishments across a platform.
     *
     * @return  void
     */
    public static function getImages() : void
    {
        // Retrieve the list of active establishments.
        $establishments = self::getActiveEstablishments();

        // If no establishments are found, exit the function early.
        if ( !$establishments ) return;

        // Save the logo for each establishment if it exists.
        foreach ( $establishments as $establishment )
            if ( $establishment[ "Logo" ] )
                self::saveImage( $establishment[ "Logo" ] );

        // Define the base public path for images.
        $public_path    = public_path( "images" );

        $standard       = "standard.png";

        // Retrieve the prefix configuration for store and promo images.
        $establishment  = config( "cofry.prefix.establishment" );
        $promotion      = config( "cofry.prefix.promotion"     );

        // Copy the default 'standard.png' image to the 'establishments' directory with the prefix from configuration.
        File::copy( "{$public_path}/standard.png", "{$public_path}/estabelecimentos/{$establishment}{$standard}" );

        // Copy the default 'standard.png' image to the 'promotions' directory with the prefix from configuration.
        File::copy( "{$public_path}/standard.png", "{$public_path}/promos/{$promotion}{$standard}"               );

        // Copy the 'coupon.png' image to be used as a common promotion image.
        File::copy( "{$public_path}/cupom.png",    "{$public_path}/common_promotion.png"                         );
    }
}
