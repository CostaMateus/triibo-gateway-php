<?php

namespace App\Http\Controllers\Affiliates;

use File;
use Throwable;
use App\Http\Controllers\Controller;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Exception\FirebaseException;
use App\Http\Integrations\AffiliatesIntegration\CofryIntegration;

class CofryController extends Controller
{
    /**
     * Define a class constant for the log channel.
     *
     * @var     string
     */
    private const LOG_CHANNEL   = "cofry";

    /**
     * Define LOCAL path for establishments.
     *
     * @var     string
     */
    private const LC_ESTAB_PATH = "images/estabelecimentos/";

    /**
     * Define LOCAL path for promotions.
     *
     * @var     string
     */
    private const LC_PROMO_PATH = "images/promos/";

    /**
     * Define FIREBASE path for establishments.
     *
     * @var     string
     */
    private const FB_ESTAB_PATH = "estabelecimento";

    /**
     * Define FIREBASE path for promotions.
     *
     * @var     string
     */
    private const FB_PROMO_PATH = "promocao";

    /**
     * Process establishments and promotions
     *
     * @return  void
     */
    public function all() : void
    {
        $this->establishments();
        $this->promotions();
    }

    /**
     * Process establishments by invoking the Firebase operation for establishments.
     *
     * @return  void
     */
    public function establishments() : void
    {
        $this->processFirebaseOperation( operation_method: "insertEstablishmentsFirebase", operation_name: "Establishments" );
    }

    /**
     * Process promotions by invoking the Firebase operation for promotions.
     *
     * @return  void
     */
    public function promotions() : void
    {
        $this->processFirebaseOperation( operation_method: "insertPromotionsFirebase", operation_name: "Promotions" );
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
            log_message( channel: self::LOG_CHANNEL, message: "{$operation_name} : Sent successfully", print: true );
        }
        catch ( FirebaseException $e )
        {
            $message = "{$operation_name} : Firebase process error - aborted";

            // Handle Firebase-specific exceptions.
            log_exception( channel: self::LOG_CHANNEL, message: $message, method: $operation_method, type: "FirebaseException", e: $e );
        }
        catch ( Throwable $e )
        {
            $message = "{$operation_name} : Unexpected error - aborted";

            // Handle general exceptions.
            log_exception( channel: self::LOG_CHANNEL, message: $message, method: $operation_method, type: "Throwable", e: $e );
        }
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
    private function insertEstablishmentsFirebase() : void
    {
        // Log the start of the establishment search process.
        log_message( channel: self::LOG_CHANNEL, message: "Establishments:  Searching...", print: true );

        // Retrieve establishments from the Cofry integration.
        $cofry_establishments    = CofryIntegration::getEstablishments();

        // If no establishments are found, log the event and return early.
        if ( !$cofry_establishments )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Establishments: No found...", print: true );
            return;
        }

        // Get a reference to the Firebase database for establishments.
        $firebase                = Firebase::database()->getReference( path: "/Estabelecimento-v2" );
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
                    $firebase->update( values: [ $push => $firebase_estab ] );

                    // Log the deactivation of the establishment.
                    echo "Establishments: Disab. Estab. ID = {$push}\n";
                }
            }
        }

        // Log the start of the establishment sending process to Firebase.
        log_message( channel: self::LOG_CHANNEL, message: "Establishments: Sending to Firebase...", print: true );

        // Loop through the establishments retrieved from Cofry.
        foreach ( $cofry_establishments as $id => $establishment )
        {
            // Assume the establishment is new and set the status to 'Created'.
            $status = "Created";

            // If the establishment already exists in Firebase, merge the descriptions and update the status.
            if ( isset( $firebase_establishments[ $id ] ) )
            {
                // Merge the existing description with the new one.
                $establishment = $this->mergeDescriptions( new_data: $establishment, existing_data: $firebase_establishments[ $id ], key: "descricao" );

                // Merge other establishment data fields.
                $establishment = $this->mergeEstablishmentData( establishment: $establishment, firebase_establishment: $firebase_establishments[ $id ] );

                // Update the status to 'Updated'.
                $status        = "Updated";
            }

            // Update or create the establishment in Firebase.
            $firebase->update( values: [ $id => $establishment ] );

            // Log the creation or update of the establishment.
            echo "Establishments: {$status} Estab. ID = {$id}\n";
        }
    }

    /**
     * Process promotions and send to Firebase.
     *
     * Responsible for processing and synchronizing promotion data with Firebase.
     * It retrieves promotions from Cofry and updates or creates entries in Firebase accordingly.
     *
     * @return  void
     */
    private function insertPromotionsFirebase() : void
    {
        // Log the start of the promotion search process.
        log_message( channel: self::LOG_CHANNEL, message: "Promotions: Searching...", print: true );

        // Retrieve promotions from the Cofry integration.
        $cofry_promotions    = CofryIntegration::getPromotions();

        // If no promotions are found, log the event and return early.
        if ( !$cofry_promotions )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Promotions: No found...", print: true );
            return;
        }

        // Get a reference to the Firebase database for promotions.
        $firebase            = Firebase::database()->getReference( path: "/Store" );
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
                    $firebase->update( values: [ $push => $firebase_promo ] );

                    // Log the deactivation of the promotion.
                    echo "Promotions: Disab. Promo. ID = {$push}\n";
                }
            }
        }

        // Log the start of the promotion sending process to Firebase.
        log_message( channel: self::LOG_CHANNEL, message: "Promotions: Sending to Firebase...", print: true );

        // Loop through the promotions retrieved from Cofry.
        foreach ( $cofry_promotions as $id => $promotion )
        {
            // Assume the promotion is new and set the status to 'Created'.
            $status = "Created";

            // If the promotion already exists in Firebase, merge the descriptions and update the status.
            if ( isset( $firebase_promotions[ $id ] ) )
            {
                // Merge the existing description with the new one.
                $promotion         = $this->mergeDescriptions( new_data: $promotion, existing_data: $firebase_promotions[ $id ], key: "description" );

                // Merge other promotion data fields.
                $promotion         = $this->mergePromotionData( promotion: $promotion, firebase_promo: $firebase_promotions[ $id ] );

                $promotion[ "id" ] = $firebase_promotions[ $id ][ "id" ] ?? $id;

                // Update the status to 'Updated'.
                $status            = "Updated";
            }

            // Ensure the promotion has an ID.
            $promotion[ "id" ] = $promotion[ "id" ] ?? $id;

            // Update or create the promotion in Firebase.
            $firebase->update( values: [ $id => $promotion ] );

            // Log the creation or update of the promotion.
            echo "Promotions: {$status} Promo. ID = {$id}\n";
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
        $parts = explode( separator: "\n\n#", string: $existing_data[ $key ] ?? "" );

        // If there are additional parts, merge them with the new description.
        if ( count( value: $parts ) > 1 )
        {
            // Remove the first part, which is the main description.
            array_shift( array: $parts );

            $new_data[ $key ] .= implode( separator: "\n\n#", array: $parts );
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

        return merge_data( new_data: $establishment, existing_data: $firebase_establishment, fields: $fields );
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

        return merge_data( new_data: $promotion, existing_data: $firebase_promo, fields: $fields );
    }

    /**
     * This method is responsible for uploading images related to establishments and promotions.
     * It calls a separate method `processImages` for each type of image, providing the local and Firebase paths.
     *
     * @return  void
     */
    public function images() : void
    {
        $types = [ "establishments", "promotions" ];

        foreach ( $types as $type )
        {
            $local_path    = $type == "establishments" ? self::LC_ESTAB_PATH : self::LC_PROMO_PATH;
            $firebase_path = $type == "establishments" ? self::FB_ESTAB_PATH : self::FB_PROMO_PATH;

            // Upload images for {$type} by providing the local and Firebase storage paths.
            $this->processImages( local_path: $local_path, firebase_path: $firebase_path, type: $type );
        }
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
    private function processImages( string $local_path, string $firebase_path, string $type ): void
    {
        // Get the full path to the local directory where images are stored.
        $path  = public_path( path: $local_path );

        // Retrieve all files from the local directory.
        $files = File::allFiles( directory: $path );

        // Check if there are no files found in the local directory.
        if ( empty( $files ) )
        {
            // Log the information that image generation is starting for the given type.
            log_message( channel: self::LOG_CHANNEL, message: "Images: Generating for {$type}...", print: true );

            // Call a method to generate images if none are found.
            CofryIntegration::getImages();

            // Retrieve all files again after generation.
            $files = File::allFiles( directory: $path );
        }

        // Log the information that the system is capturing file names for the given type.
        log_message( channel: self::LOG_CHANNEL, message: "Images: Capturing names of {$type}...", print: true );

        // Get the file names from the list of files.
        $file_names = get_files_name( files: $files );

        // Execute the upload of images to Firebase using the file names and paths.
        $this->insertImagesFirebase( images: $file_names, local_path: $local_path, firebase_path: $firebase_path, type: $type );
    }

    /**
     * Send images to Firebase Storage
     *
     * This function uploads a set of images to Firebase Storage.
     * It takes an array of image file names, the local directory path where the images are stored,
     * the target directory path in Firebase Storage, and a string indicating the type of upload.
     *
     * @param   array   $images         Array of image file names to upload.
     * @param   string  $local_path     The local directory path where the images are stored.
     * @param   string  $firebase_path  The target directory path in Firebase Storage.
     * @param   string  $type           A string indicating the type of upload.
     * @return  void
     */
    private function insertImagesFirebase( array $images, string $local_path, string $firebase_path, string $type )
    {
        // Initialize Firebase storage and get the storage bucket.
        $storage = Firebase::storage();
        $bucket  = $storage->getBucket();

        // Get the full local path to the images.
        $path    = public_path( path: $local_path );

        // Log the start of the upload process.
        log_message( channel: self::LOG_CHANNEL, message: "Images: Sending to Firebase - {$type}...", print: true );

        // Iterate over each image and upload it to Firebase.
        foreach ( $images as $img )
        {
            // Create a resumable uploader instance for the current image.
            $uploader = $bucket->getResumableUploader(
                data: fopen( "{$path}/{$img}", "r" ),
                options: [ "name" => "{$firebase_path}/{$img}" ]
            );

            try
            {
                // Log the image ID being uploaded.
                log_message( channel: self::LOG_CHANNEL, message: "Images: ID {$img}", print: true );
                // Perform the upload.
                $uploader->upload();
            }
            catch ( FirebaseException $e )
            {
                $message = "Images : An error has occurred while working with the Firebase SDK";

                // Handle Firebase-specific exceptions.
                log_exception( channel: self::LOG_CHANNEL, message: $message, method: $operation_method, type: "FirebaseException", e: $e );
            }
            catch ( Throwable $e )
            {
                $message = "Images : Unexpected error";

                // Handle general exceptions.
                log_exception( channel: self::LOG_CHANNEL, message: $message, method: $operation_method, type: "Throwable", e: $e );
            }
            finally
            {
                // Attempt to retrieve the resume URI from the uploader and resume the upload.
                $resumeUri = $uploader->getResumeUri();
                $uploader->resume( resumeUri: $resumeUri );
            }
        }

        // Log the completion of the upload process.
        log_message( channel: self::LOG_CHANNEL, message: "Images: Sending finished - {$type}...", print: true );
    }
}
