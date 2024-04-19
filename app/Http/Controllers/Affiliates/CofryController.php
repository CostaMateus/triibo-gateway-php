<?php

namespace App\Http\Controllers\Affiliates;

use File;
use Throwable;
use App\Helpers\Helper;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Google\Cloud\Core\Upload\ResumableUploader;
use Kreait\Firebase\Exception\FirebaseException;
use App\Http\Integrations\AffiliatesIntegration\CofryIntegration;

class CofryController extends Controller
{
    /**
     * Define a class constant for the log channel
     */
    private const LOG_CHANNEL   = "cofry";

    /**
     * Define local path for establishments and promotions
     */
    private const LC_STORE_PATH = "images/estabelecimentos/";
    private const LC_PROMO_PATH = "images/promos/";

    /**
     * Define Firebase path for establishments and promotions
     */
    private const FB_STORE_PATH = "estabelecimento/";
    private const FB_PROMO_PATH = "promocao/";

    /**
     * Logger
     *
     * @var     Log
     */
    protected $log;

    public function __construct()
    {
        $this->log = Log::channel( self::LOG_CHANNEL );
    }

    /**
     * Process establishments and promotions
     *
     * @return void
     */
    public function all() : void
    {
        $this->establishments();
        $this->promotions();
    }

    /**
     * Process establishments by invoking the Firebase operation for establishments.
     *
     * @return void
     */
    public function establishments() : void
    {
        $this->processFirebaseOperation( "insertEstablishmentFirebase", "Establishment" );
    }

    /**
     * Process promotions by invoking the Firebase operation for promotions.
     *
     * @return void
     */
    public function promotions() : void
    {
        $this->processFirebaseOperation( "insertPromosFirebase", "Promotions" );
    }

    /**
     * Execute a Firebase operation and handle the success or failure of the operation.
     * On success, log the success message. On failure, handle the exception.
     *
     * @param   string  $operation_method   The method name to call for the operation.
     * @param   string  $operation_name     The name of the operation for logging purposes.
     * @return  void
     */
    private function processFirebaseOperation( string $operation_method, string $operation_name ) : void
    {
        try
        {
            // Attempt to call the Firebase operation method.
            $this->{$operation_method}();
            // Log the success message if the operation is successful.
            $this->logSuccess( "{$operation_name} : {$operation_name} sent successfully" );
        }
        catch ( FirebaseException $e )
        {
            // Handle Firebase-specific exceptions.
            $this->handleException( $e, "{$operation_name} : {$operation_name}, Firebase process error - aborted", "{$operation_method}()", "FirebaseException" );
        }
        catch ( Throwable $e )
        {
            // Handle general exceptions.
            $this->handleException( $e, "{$operation_name} : Unexpected error - aborted", "{$operation_method}()", "Throwable" );
        }
    }

    /**
     * Log success messages and output them to the console.
     *
     * @param   string  $message    The success message to log and echo.
     * @return  void
     */
    private function logSuccess( string $message ) : void
    {
        echo $message . "\n";

        // Log the success message using the specified log channel.
        $this->log->info( $message, [
            "code"    => 0,
            "info"    => $message,
            "error"   => null,
            "message" => null
        ] );
    }

    /**
     * Handle exceptions by logging the error, echoing the error message, and sending an email notification.
     *
     * @param   Throwable   $e                  The exception to handle.
     * @param   string      $message            The message to log and echo.
     * @param   string      $method             The method where the exception occurred.
     * @param   string      $exception_type     The type of exception for the email notification.
     * @return  void
     */
    private function handleException( Throwable $e, string $message, string $method, string $exception_type) : void
    {
        echo $message . "\n";

        // Log the critical error message using the specified log channel.
        $this->log->critical( $message, [
            "code"    => $e->getCode(),
            "info"    => $message,
            "error"   => $e->getMessage(),
            "message" => $e
        ] );

        // Send an email notification about the exception.
        Helper::sendMailNotification( self::LOG_CHANNEL, $method, $exception_type, $e );
    }

    /**
     * Update and create establishments data on Firebase.
     *
     * Responsible for updating and creating establishments data in Firebase.
     * It fetches establishments data from Cofry, compares it with existing data in Firebase,
     * and performs updates or inserts as necessary.
     *
     * @return  void
     */
    private function insertEstablishmentFirebase() : void
    {
        // Log the start of the establishment search process.
        $this->printAndLog( "info", "Establishments", "Searching..." );

        // Retrieve establishments from the Cofry integration.
        $cofry_establishments    = CofryIntegration::getEstablishments();

        // If no establishments are found, log the event and return early.
        if ( !$cofry_establishments )
        {
            $this->printAndLog( "info", "Establishments", "No found..." );

            return;
        }

        // Get a reference to the Firebase database for establishments.
        $firebase                = Firebase::database()->getReference( "/Estabelecimento-v2" );
        $firebase_establishments = $firebase->getValue();

        // Loop through the establishments in Firebase.
        foreach ( $firebase_establishments as $push => $firebase_estab )
        {
            // Check if the establishment is from Cofry and not found in the new data set.
            if ( strpos( $push, "-cofry" ) !== false )
            {
                // Mark the establishment as unpublished.
                if ( !isset( $cofry_establishments[ $push ] ) )
                {
                    // Mark the establishment as unpublished.
                    $firebase_estab[ "isPublished" ] = false;
                    // Update the establishment in Firebase.
                    $firebase->update( [ $push => $firebase_estab ] );

                    // Log the deactivation of the establishment.
                    echo "Establishments : Disab. Estab. ID = {$push}\n";
                }
            }
        }

        // Log the start of the establishment sending process to Firebase.
        $this->printAndLog( "info", "Establishments", "Sending to Firebase..." );

        // Loop through the establishments retrieved from Cofry.
        foreach ( $cofry_establishments as $id => $establishment )
        {
            // Assume the establishment is new and set the status to 'Created'.
            $status = "Created";

            // If the establishment already exists in Firebase, merge the descriptions and update the status.
            if ( isset( $firebase_establishments[ $id ] ) )
            {
                // Merge the existing description with the new one.
                $establishment = $this->mergeDescriptions( $establishment, $firebase_establishments[ $id ], "descricao" );

                // Merge other establishment data fields.
                $establishment = $this->mergeEstablishmentData( $establishment, $firebase_establishments[ $id ] );

                // Update the status to 'Updated'.
                $status        = "Updated";
            }

            // Update or create the establishment in Firebase.
            $firebase->update([ $id => $establishment ] );

            // Log the creation or update of the establishment.
            echo "Establishments : {$status} Estab. ID = {$id}\n";
        }
    }

    /**
     * Process promotions and send to Firebase.
     *
     * Responsible for processing and synchronizing promotion data with Firebase.
     * It retrieves promotions from Cofry and updates or creates entries in Firebase accordingly.
     *
     * @return void
     */
    private function insertPromosFirebase() : void
    {
        // Log the start of the promotion search process.
        $this->printAndLog( "info", "Promotions", "Searching..." );

        // Retrieve promotions from the Cofry integration.
        $cofry_promotions    = CofryIntegration::getPromotions();

        // If no promotions are found, log the event and return early.
        if ( !$cofry_promotions )
        {
            $this->printAndLog( "info", "Promotions", "No found..." );
            return;
        }

        // Get a reference to the Firebase database for promotions.
        $firebase            = Firebase::database()->getReference( "/Store" );
        $firebase_promotions = $firebase->getValue();

        // Loop through the promotions in Firebase.
        foreach ( $firebase_promotions as $push => $firebase_promo )
        {
            // Check if the promotion is from Cofry and not found in the new data set.
            if ( strpos( $push, "-cofry" ) !== false )
            {
                // Mark the promotion as unpublished and inactive.
                if ( !isset( $promos[ $push ] ) )
                {
                    $firebase_promo[ "isPublished" ] = false;
                    $firebase_promo[ "active"      ] = false;
                    // Update the promotion in Firebase.
                    $firebase->update( [ $push => $firebase_promo ] );

                    // Log the deactivation of the promotion.
                    echo "Promotions : Disab. Promo. ID = {$push}\n";
                }
            }
        }

        // Log the start of the promotion sending process to Firebase.
        $this->printAndLog( "info", "Promotions", "Sending to Firebase..." );

        // Loop through the promotions retrieved from Cofry.
        foreach ( $cofry_promotions as $id => $promotion )
        {
            // Assume the promotion is new and set the status to 'Created'.
            $status = "Created";

            // If the promotion already exists in Firebase, merge the descriptions and update the status.
            if ( isset( $firebase_promotions[ $id ] ) )
            {
                // Merge the existing description with the new one.
                $promotion         = $this->mergeDescriptions( $promotion, $firebase_promotions[ $id ], "description" );

                // Merge other promotion data fields.
                $promotion         = $this->mergePromotionData( $promotion, $firebase_promotions[ $id ] );

                $promotion[ "id" ] = $firebase_promotions[ $id ][ "id" ] ?? $id;

                // Update the status to 'Updated'.
                $status            = "Updated";
            }

            // Ensure the promotion has an ID.
            $promotion[ "id" ] = $promotion[ "id" ] ?? $id;

            // Update or create the promotion in Firebase.
            $firebase->update( [ $id => $promotion ] );

            // Log the creation or update of the promotion.
            echo "Promotions : {$status} Promo. ID = {$id}\n";
        }
    }

    /**
     * Merge the descriptions of the existing and new data entries.
     *
     * @param   array   $new_data       The new data entry.
     * @param   array   $existing_data  The existing data entry from Firebase.
     * @param   string  $key            The key for the description field.
     * @return  array                   The merged data entry.
     */
    private function mergeDescriptions( array $new_data, array $existing_data, string $key ): array
    {
        // Split the existing description by the delimiter.
        $parts = explode( "\n\n#", $existing_data[ $key ] ?? "" );

        // If there are additional parts, merge them with the new description.
        if ( count( $parts ) > 1 )
        {
            // Remove the first part, which is the main description.
            array_shift( $parts );

            $new_data[ $key ] .= implode( "\n\n#", $parts );
        }

        return $new_data;
    }

    /**
     * Merge other establishment data fields from the existing establishment into the new establishment.
     *
     * @param   array   $establishment              The new establishment data.
     * @param   array   $firebase_establishment     The existing establishment data from Firebase.
     * @return  array                               The merged establishment data.
     */
    private function mergeEstablishmentData( array $establishment, array $firebase_establishment ): array
    {
        // Define the fields to merge from the existing establishment.
        $fields = [
            "hasPromo", "enableDate", "disableDate", "dataInclusao",
            // "isPublished"
        ];

        return $this->mergeData( $establishment, $firebase_establishment, $fields );
    }

    /**
     * Merge other promotion data fields from the existing promotion into the new promotion.
     *
     * @param   array   $promotion          The new promotion data.
     * @param   array   $firebase_promo     The existing promotion data from Firebase.
     * @return  array                       The merged promotion data.
     */
    private function mergePromotionData( array $promotion, array $firebase_promo ) : array
    {
        // Define the fields to merge from the existing promotion.
         $fields = [
            "enableDate",    "disableDate",           "dueDate",
            "buttonLabel",   "campanhaTriibo",        "droidWebView",
            "iOSWebView",    "showButtonDescription", "buttonDescription",
            "showGoToPlace", "goToPlaceLabel",
            //"isPublished"
        ];

        return $this->mergeData( $promotion, $firebase_promo, $fields );
    }

    /**
     * Merge data fields from an existing array into a new array.
     *
     * @param   array   $new_data       The new data array.
     * @param   array   $existing_data  The existing data array from Firebase.
     * @param   array   $fields         The fields to merge.
     * @return  array                   The merged data array.
     */
    private function mergeData( array $new_data, array $existing_data, array $fields ) : array
    {
        foreach ( $fields as $field )
            if ( isset( $existing_data[ $field ] ) )
                $new_data[ $field ] = $existing_data[ $field ];

        return $new_data;
    }

    /**
     * This method is responsible for uploading images related to establishments and promotions.
     * It calls a separate method `processImageUpload` for each type of image, providing the local and Firebase paths.
     *
     * @return void
     */
    public function uploadImages() : void
    {
        // Upload images for establishments by providing the local and Firebase storage paths.
        $this->processImageUpload( self::LC_STORE_PATH, self::FB_STORE_PATH, "establishment" );

        // Upload images for promotions by providing the local and Firebase storage paths.
        $this->processImageUpload( self::LC_PROMO_PATH, self::FB_PROMO_PATH, "promotion"     );
    }

    /**
     * This method handles the uploading process of images by first checking for existing files,
     * generating them if necessary, and then executing the upload to Firebase.
     *
     * @param   string  $local_path     Local file path where images are stored.
     * @param   string  $firebase_path  Firebase storage path where images will be uploaded.
     * @param   string  $type           The type of images being processed (e.g., 'establishment', 'promotion').
     * @return  void
     */
    private function processImageUpload( string $local_path, string $firebase_path, string $type ): void
    {
        // Get the full path to the local directory where images are stored.
        $path  = public_path( $local_path );

        // Retrieve all files from the local directory.
        $files = File::allFiles( $path );

        // Check if there are no files found in the local directory.
        if ( empty( $files ) )
        {
            // Log the information that image generation is starting for the given type.
            $this->printAndLog( "info", "Images", "Generating for {$type}..." );

            // Call a method to generate images if none are found.
            CofryIntegration::getImages();

            // Retrieve all files again after generation.
            $files = File::allFiles( $path );
        }

        // Log the information that the system is capturing file names for the given type.
        $this->printAndLog( "info", "Images", "Capturing names of {$type}..." );

        // Get the file names from the list of files.
        $file_names = $this->getFileNames( $files );

        // Execute the upload of images to Firebase using the file names and paths.
        $this->execUploadImages( $file_names, $local_path, $firebase_path, $type );
    }

    /**
     * Extracts the file names from an array of file paths.
     *
     * @param   array   $files  An array of file paths.
     * @return  array           An array of file names extracted from the file paths.
     */
    private function getFileNames( array $files ) : array
    {
        // Initialize an array to hold the file names.
        $files_name = [];

        // Loop through each file path in the provided array.
        // Extract the file name using pathinfo() and add it to the $files_name array.
        foreach( $files as $file )
            $files_name[] = pathinfo( $file )[ "basename" ];

        // Return the array of file names.
        return $files_name;
    }

    /**
     * Send images to Firebase Storage
     *
     * This function uploads a set of images to Firebase Storage.
     * It takes an array of image file names, the local directory path where the images are stored,
     * the target directory path in Firebase Storage, and a string indicating the type of upload.
     *
     * @param   array   $images             Array of image file names to upload.
     * @param   string  $local_folder       The local directory path where the images are stored.
     * @param   string  $firebase_folder    The target directory path in Firebase Storage.
     * @param   string  $type               A string indicating the type of upload.
     * @return  void
     */
    private function execUploadImages( array $images, string $local_folder, string $firebase_folder, string $type )
    {
        // Initialize Firebase storage and get the storage bucket.
        $st     = Firebase::storage();
        $bucket = $st->getBucket();

        // Get the full local path to the images.
        $path   = public_path( $local_folder );

        // Log the start of the upload process.
        $this->printAndLog( "info", "Images", "Sending to Firebase - {$type}..." );

        // Iterate over each image and upload it to Firebase.
        foreach ( $images as $img )
        {
            // Create a resumable uploader instance for the current image.
            $uploader = $bucket->getResumableUploader( fopen( "{$path}{$img}", "r" ), [ "name" => "{$firebase_folder}{$img}" ] );

            try
            {
                // Log the image ID being uploaded.
                $this->printAndLog( "info", "Images", "ID {$img}" );
                // Perform the upload.
                $uploader->upload();
            }
            catch ( FirebaseException $e )
            {
                // Handle any Firebase-specific exceptions during upload.
                $this->handleUploadException( $uploader, $e, $img, "FirebaseException" );
            }
            catch ( Throwable $e )
            {
                // Handle any other exceptions during upload.
                $this->handleUploadException( $uploader, $e, $img, "Throwable" );
            }
        }

        // Log the completion of the upload process.
        $this->printAndLog( "info", "Images", "Sending finished - {$type}..." );
    }

    /**
     * Handles exceptions during the image upload process.
     *
     * This function is responsible for managing exceptions that occur while using
     * a ResumableUploader to upload images. It attempts to resume the upload if possible,
     * logs the critical error with details, and sends a mail notification about the exception.
     *
     * @param   ResumableUploader   $uploader           The uploader instance used for resumable uploads.
     * @param   Throwable           $e                  The exception that was thrown during the upload process.
     * @param   string              $img                The image identifier or path that was being uploaded when the exception occurred.
     * @param   string              $exception_type     A string representing the type of exception for logging purposes.
     * @return  void
     */
    private function handleUploadException( ResumableUploader $uploader, Throwable $e, string $img, string $exception_type ) : void
    {
        // Attempt to retrieve the resume URI from the uploader and resume the upload.
        $resumeUri = $uploader->getResumeUri();
        $uploader->resume( $resumeUri );

        // Log the critical error with relevant details such as the error code, image information, and the exception message.
        $this->log->critical( "Images", [
            "code"    => $e->getCode(),
            "info"    => $img,
            "error"   => "An error has occurred while working with the SDK",
            "message" => $e
        ] );

        // Send a mail notification with the exception details using a helper function.
        Helper::sendMailNotification( "cofry", "execUploadImages()", $exception_type, $e );
    }

    /**
     * Outputs a message to the console and logs it using the logger service.
     *
     * This function is a utility to reduce duplication of code when both printing
     * a message to the console and logging it using the application's logging service.
     * It ensures that the message is consistently formatted and logged across different
     * parts of the application.
     *
     * @param   string  $type       The log level (e.g., 'info', 'error', 'critical').
     * @param   string  $message    The message to be printed and logged.
     * @param   string  $context    Additional context to be included with the message.
     * @return  void
     */
    private function printAndLog( string $type, string $message, string $context ) : void
    {
        // Print the message and context to the console.
        echo "{$message} : {$context}\n";

        // Log the message and context using the specified log level.
        // The log service is accessed via $this->log, assuming it has been set up elsewhere in the class.
        $this->log->$type( $message, [ $context ] );
    }
}
