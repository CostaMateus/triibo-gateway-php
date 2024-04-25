<?php

namespace App\Http\Controllers\Affiliates;

use Throwable;
use App\Helpers\Helper;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Kreait\Firebase\Database\Reference;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Exception\FirebaseException;
use App\Http\Integrations\AffiliatesIntegration\AwinIntegration;

class AwinController extends Controller
{
    /**
     * Undocumented variable
     *
     * @var     AwinIntegration
     */
    protected $integration;

    /**
     * Define a class constant for the log channel.
     *
     * @var     string
     */
    private const LOG_CHANNEL   = "awin";

    /**
     * Define the types establishments and promotions
     *
     * @var     array
     */
    private const TYPES         = [ "establishments", "promotions" ];

    /**
     * Define LOCAL base path for establishments and promotions.
     *
     * @var     string
     */
    private const LC_BASE_PATH  = "images/affiliates/awin";

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
     * Sistema Triibo +5510999991477.
     *
     * @var     string
     */
    private const REQUESTER_ID  = "J4NjCHtvxuRojrXb29whbsNPAy33";

    /**
     * Establishment fields to ignore.
     *
     * @var     array
     */
    private const IGNORE_ESTAB_FIELDS = [
        "dataAlteracao",
        "dataInclusao",
        "disableDate",
        "enableDate",
        "listaFotos",
        "fotoThumb",
    ];

    /**
     * Promotion fields to ignore.
     *
     * @var     array
     */
    private const IGNORE_PROMO_FIELDS = [];

    /**
     * Constructor
     *
     * @param   boolean     $loadEstablishments     Load establishments from Awin, default true
     * @return  void
     */
    public function __construct( bool $loadEstablishments = true )
    {
        $this->integration = new AwinIntegration( $loadEstablishments );

        foreach ( self::TYPES as $type )
            File::ensureDirectoryExists( path: public_path ( path: self::LC_BASE_PATH . "/{$type}" ) );
    }

    /**
     * TODO remover após usar em prod
     */
    public function populate()
    {
        $awin_establsihment      = $this->integration->getEstablishments();
        $firebase_establishments = $this->filterEstablishments( Firebase::database()->getReference( "/Estabelecimento-v2" ) );
        $local_establishments    = [];

        foreach ( $awin_establsihment as $origin_id => $establishment )
        {
            $affiliate_id           = $establishment[ "origin" ] . $origin_id;
            $local_establishments[] = [
                "origin"       => $establishment[ "origin" ],
                "origin_id"    => $origin_id,
                "affiliate_id" => $affiliate_id,
                "push_id"      => null,
                "name"         => $establishment[ "nome"   ],
                "data"         => json_encode( $establishment ),
                "created_at"   => now(),
                "updated_at"   => now(),
            ];
        }

        foreach ( $firebase_establishments as $push => $establishment )
        {
            $search_id = $establishment[ "origin" ] . $establishment[ "partnerEstablishmentId" ];
            $index     = Helper::arraySearchByKey( $search_id, "affiliate_id", $local_establishments );

            if ( $index !== false )
                $local_establishments[ $index ][ "push_id" ] = $push;
            else
                echo "{$search_id} : false : {$push}\n";
        }

        \DB::table( "affiliates_establishments" )->truncate();
        \DB::table( "affiliates_establishments" )->insert( $local_establishments );

        \Storage::put( "establishments_awin.json",     json_encode( $awin_establsihment     ) );
        \Storage::put( "establishments_firebase.json", json_encode( $firebase_establishments ) );
        \Storage::put( "establishments_local.json",    json_encode( $local_establishments    ) );

        $this->populatePromos();

        dd(
            "estabs local : " . count( $local_establishments    ),
            "estabs awin  : " . count( $awin_establsihment      ),
            "estabs fire  : " . count( $firebase_establishments )
        );
    }

    /**
     * TODO remover após usar em prod
     */
    public function populatePromos()
    {
        $awin_promotions     = $this->integration->getPromotions();
        $firebase_promotions = $this->filterPromotions( Firebase::database()->getReference( "/Store" ) );
        $local_promotionss   = [];

        foreach ( $awin_promotions as $origin_id => $promo )
        {
            $affiliate_id        = $promo[ "origin" ] . $origin_id;
            $local_promotionss[] = [
                "origin"       => $promo[ "origin" ],
                "origin_id"    => $origin_id,
                "affiliate_id" => $affiliate_id,
                "push_id"      => null,
                "data"         => json_encode( $promo ),
                "created_at"   => now(),
                "updated_at"   => now(),
            ];
        }

        foreach ( $firebase_promotions as $push => $promotion )
        {
            $search_id = $promotion[ "origin" ] . $promotion[ "partnerPromotionId" ];
            $index     = Helper::arraySearchByKey( $search_id, "affiliate_id", $local_promotionss );

            if ( $index !== false )
                $local_promotionss[ $index ][ "push_id" ] = $push;
            else
                echo "{$search_id} : false : {$push}\n";
        }

        \DB::table( "affiliates_promotions" )->truncate();
        \DB::table( "affiliates_promotions" )->insert( $local_promotionss );

        \Storage::put( "promotions_awin.json",     json_encode( $awin_promotions     ) );
        \Storage::put( "promotions_firebase.json", json_encode( $firebase_promotions ) );
        \Storage::put( "promotions_local.json",    json_encode( $local_promotionss   ) );

        dump(
            "promos local : " . count( $local_promotionss   ),
            "promos awin  : " . count( $awin_promotions     ),
            "promos fire  : " . count( $firebase_promotions )
        );
    }

    /**
     * Process establishments by invoking the Firebase operation for establishments.
     *
     * @return void
     */
    public function establishments()
    {
        $this->processFirebaseOperation( operation_method: "insertEstablishmentsFirebase", operation_name: "Establishments" );
    }

    /**
     * Process promotions by invoking the Firebase operation for promotions.
     *
     * @return void
     */
    public function promotions()
    {
        $this->processFirebaseOperation( operation_method: "insertPromotionsFirebase", operation_name: "Promotions" );
    }

    /**
     * Process reports by invoking the Firebase operation for reports.
     *
     * @return  void
     */
    public function reports()
    {
        $this->processFirebaseOperation( operation_method: "insertReportsFirebase", operation_name: "Reports" );
    }

    /**
     * Upload images related to establishments and promotions.
     * It calls a separate method `processImages` for each type of image, providing the local and Firebase paths.
     *
     * @return  void
     */
    public function images()
    {
        // Copy default images to their respective locations.
        $this->copyDefaultImages();

        // Iterate over each image type in the $types array.
        foreach ( self::TYPES as $type )
        {
            // Construct the local path where the images for the current type are located.
            $local_path    = public_path( path: self::LC_BASE_PATH . "/{$type}" );

            // Determine the Firebase path where the images will be uploaded based on the type.
            $firebase_path = ( $type == "establishments" ) ? self::FB_ESTAB_PATH : self::FB_PROMO_PATH;

            // Upload images for {$type} by providing the local and Firebase storage paths.
            $this->processImages( local_path: $local_path, firebase_path: $firebase_path, type: $type );
        }
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
     * Update and create establishments on Firebase
     *
     * This PHP method is responsible for synchronizing establishment data between a local database and Firebase.
     * It performs the following operations:
     * - Deletes the existing establishments directory.
     * - Retrieves establishments from Firebase and the local database.
     * - Disables establishments in Firebase that are not present in the Awin list.
     * - Adds new establishments from Awin to the local database and Firebase.
     * - Requests approval for any changes to existing establishments.
     * - Updates the local database with changes from Awin.
     * - Logs actions and outputs status messages to the console.
     *
     * @return  void
     */
    private function insertEstablishmentsFirebase()
    {
        log_message( channel: self::LOG_CHANNEL, message: "Establishments", context: [ "Searching ..." ], print: true );

        // Delete the existing establishments directory.
        File::deleteDirectory( $this->establishments_path, true );

        $local_data              = [];

        // Initialize Firebase database references
        $db                      = Firebase::database();
        $establishment_reference = $db->getReference( "/Estabelecimento-v2"            );
        $approval_reference      = $db->getReference( "/Estabelecimento-Aprovacoes"    );
        $changes_reference       = $db->getReference( "/Establishment-changes-request" );

        // Retrieve establishments from Firebase and local sources.
        $firebase_establishments = $this->filterEstablishments( $establishment_reference );
        $awin_establishments     = $this->integration->getEstablishments();
        $local_establishments    = \DB::table( "affiliates_establishments" )->where( "origin", "awin" )->get()->keyBy( "origin_id" )->toArray();
        $local_establishments    = json_decode( json_encode( $local_establishments ), true );

        log_message( channel: self::LOG_CHANNEL, message: "Establishments", context: [ "Validating ..." ], print: true );

        // Disable Firebase establishments not present in Awin establishments.
        foreach ( $firebase_establishments as $push => $firebase_stablishment )
        {
            if ( !isset( $awin_establishments[ $firebase_stablishment[ "partnerEstablishmentId" ] ] ) )
            {
                if ( $firebase_stablishment[ "isPublished" ] )
                {
                    // Disable the establishment.
                    $firebase_stablishment[ "isPublished" ] = false;
                    $establishment_reference->update( [ $push => $firebase_stablishment ] );

                    log_message( channel: self::LOG_CHANNEL, message: "Establishments", context: [ "Disabled ID {$push}" ], print: true );

                    // Remove the establishment from the list.
                    unset( $firebase_establishments[ $push ] );
                }
            }
        }

        // Process Awin establishments for addition or update.
        foreach ( $awin_establishments as $id => $awin_establishment )
        {
            if ( !isset( $local_establishments[ $id ] ) )
            {
                // Add new establishment to local data array.
                $affiliate_id = $awin_establishment[ "origin" ] . $id;
                $local_data[] = [
                    "origin"       => $awin_establishment[ "origin" ],
                    "origin_id"    => $id,
                    "affiliate_id" => $affiliate_id,
                    "push_id"      => null,
                    "name"         => $awin_establishment[ "nome"   ],
                    "data"         => json_encode( $awin_establishment ),
                    "created_at"   => now(),
                    "updated_at"   => now(),
                ];

                continue;
            }

            // Compare Awin and local establishments
            $data_establishment  = $local_establishments[ $id ];
            $local_establishment = json_decode( $data_establishment[ "data" ], true );
            // And get differences.
            $diff                = Helper::arrayDiffAssocRecursive( $awin_establishment, $local_establishment );

            // Remove ignored fields from the differences.
            foreach ( self::IGNORE_ESTAB_FIELDS as $field )
                unset( $diff[ $field ] );

            // Skip if there are no changes
            if ( empty( $diff ) ) continue;

            $date                = Helper::getDateTimeUnix();

            // Add edit approval request in Firebase.
            $new_push_approval   = $approval_reference->push()->getKey();
            $approval_reference->update( [ $new_push_approval => [
                "requesterId"  => self::REQUESTER_ID,
                "id"           => $data_establishment[ "push_id" ],
                "lastModified" => $date,
                "requestDate"  => $date,
                "status"       => "aberto",
                "action"       => "edit"
            ] ] );

            // Add edit change request in Firebase.
            $new_push_changes    = $changes_reference->push()->getKey();
            $changes_reference->update( [ $new_push_changes => [
                "id"                => $new_push_changes,
                "createDate"        => $date,
                // "updateDate"        => "",
                // "reviewDate"        => "",
                "establishmentId"   => $data_establishment[ "push_id" ],
                "establishmentName" => $data_establishment[ "name"    ],
                "requesterUid"      => self::REQUESTER_ID,
                // "validatorUid"      => "",
                // "comment"           => "",
                "type"              => "validation",
                "changedFields"     => $diff,
            ] ] );

            // Update the local establishment with the new data.
            \DB::table( "affiliates_establishments" )
                ->where( "affiliate_id", $data_establishment[ "affiliate_id" ] )
                ->update( [ "data" => json_encode( $awin_establishment ) ] );

            $context = "Edited ID = {$data_establishment[ "push_id" ]} | Need approval ID = {$new_push_approval} | Change Request ID = {$new_push_changes}\n";
            log_message( channel: self::LOG_CHANNEL, message: "Establishments", context: [ $context ], print: true );
        }

        // Insert new establishments into the local database if any.
        if ( !empty( $local_data ) )
        {
            \DB::table( "affiliates_establishments" )->insert( $local_data );

            log_message( channel: self::LOG_CHANNEL, message: "Establishments", context: [ "Added " . count( $local_data ) . " new establishments in the local table" ], print: true );

            // Add new establishments to Firebase and update local database with push IDs.
            foreach ( $local_data as $establishment )
            {
                $aux                  = $awin_establishments[ $establishment[ "origin_id" ] ];
                $aux[ "isPublished" ] = false;

                $newPushEstab         = $establishment_reference->push()->getKey();
                $establishment_reference->update( [ $newPushEstab => $aux ] );

                $date                 = Helper::getDateTimeUnix();

                $new_push_approval    = $approval_reference->push()->getKey();
                $approval_reference->update( [ $new_push_approval => [
                    "requesterId"  => self::REQUESTER_ID,
                    "id"           => $newPushEstab,
                    "lastModified" => $date,
                    "requestDate"  => $date,
                    "status"       => "aberto",
                    "action"       => "create"
                ] ] );

                log_message( channel: self::LOG_CHANNEL, message: "Establishments", context: [ "Created ID = {$newPushEstab} | Need approval ID = {$new_push_approval}" ], print: true );

                $affiliate_id         = $aux[ "origin" ] . $aux[ "partnerEstablishmentId" ];
                \DB::table( "affiliates_establishments" )->where( "affiliate_id", $affiliate_id )->update( [ "push_id" => $newPushEstab ] );
            }
        }
    }

    /**
     * Update and create promotions on Firebase
     *
     * @return  void
     */
    private function insertPromotionsFirebase()
    {
        log_message( channel: self::LOG_CHANNEL, message: "Promotions", context: [ "Searching ..." ], print: true );

        // Delete the existing promotions directory.
        File::deleteDirectory( $this->promotions_path, true );

        $local_data           = [];

        // Initialize Firebase database references
        $db                   = Firebase::database();
        $promotions_reference = $db->getReference( "/Store"                 );
        $approval_reference   = $db->getReference( "/Store-Aprovacoes"      );
        $changes_reference    = $db->getReference( "/Store-changes-request" );

        // Retrieve promotions from Firebase and local sources.
        $firebase_promotions  = $this->filterPromotions( $promotions_reference );
        $awin_promotions      = $this->integration->getPromotions();
        $local_promotions     = \DB::table( "affiliates_promotions" )->where( "origin", "awin" )->get()->keyBy( "origin_id" )->toArray();
        $local_promotions     = json_decode( json_encode( $local_promotions ), true );

        log_message( channel: self::LOG_CHANNEL, message: "Promotions", context: [ "Validating ..." ], print: true );

        // Disable Firebase promotions not present in Awin promotions.
        foreach ( $firebase_promotions as $push => $firebase_promotion )
        {
            if ( !isset( $awin_promotions[ $firebase_promotion[ "partnerPromotionId" ] ] ) )
            {
                if ( $firebase_promotion[ "isPublished" ] )
                {
                    // Disable the promotion.
                    $firebase_promotion[ "isPublished" ] = false;
                    $promotions_reference->update( [ $push => $firebase_promotion ] );

                    log_message( channel: self::LOG_CHANNEL, message: "Promotions", context: [ "Disabled ID {$push}" ], print: true );

                    // Remove the promotion from the list.
                    unset( $firebase_promotions[ $push ] );
                }
            }
        }

        // Process Awin promotions for addition or update.
        foreach ( $awin_promotions as $id => $awin_promotion )
        {
            if ( !isset( $local_promotions[ $id ] ) )
            {
                // Add new promotion to local data array.
                $affiliate_id = $awin_promotion[ "origin" ] . $id;
                $local_data[] = [
                    "origin"       => $awin_promotion[ "origin" ],
                    "origin_id"    => $id,
                    "affiliate_id" => $affiliate_id,
                    "push_id"      => null,
                    "data"         => json_encode( $awin_promotion ),
                    "created_at"   => now(),
                    "updated_at"   => now(),
                ];

                continue;
            }

            // Compare Awin and local insertPromotionsFirebase
            $data_promotion    = $local_promotions[ $id ];
            $local_promotion   = json_decode( $data_promotion[ "data" ], true );
            // And get differences.
            $diff              = Helper::arrayDiffAssocRecursive( $awin_promotion, $local_promotion );

            // Remove ignored fields from the differences.
            foreach ( self::IGNORE_PROMO_FIELDS as $field )
                unset( $diff[ $field ] );

            // Skip if there are no changes
            if ( empty( $diff ) ) continue;

            $date              = Helper::getDateTimeUnix();

            // Add edit approval request in Firebase.
            $new_push_approval = $approval_reference->push()->getKey();
            $approval_reference->update( [ $new_push_approval => [
                "requesterId"  => self::REQUESTER_ID,
                "id"           => $data_promotion[ "push_id" ],
                "lastModified" => $date,
                "requestDate"  => $date,
                "status"       => "aberto",
                "action"       => "edit",
                "type"         => "promotion",
            ] ] );

            // Add edit change request in Firebase.
            $new_push_changes  = $changes_reference->push()->getKey();
            $changes_reference->update( [ $new_push_changes => [
                "id"                => $new_push_changes,
                "createDate"        => $date,
                // "updateDate"        => "",
                // "reviewDate"        => "",
                "storeId"           => $data_promotion[ "push_id" ],
                "establishmentId"   => $local_promotion[ "establishmentId"   ],
                "establishmentName" => $local_promotion[ "establishmentName" ],
                "requesterUid"      => self::REQUESTER_ID,
                // "validatorUid"      => "",
                // "comment"           => "",
                "type"              => "validation",
                "changedFields"     => $diff,
            ] ] );

            // Update the local promotion with the new data.
            \DB::table( "affiliates_promotions" )
                ->where( "affiliate_id", $data_promotion[ "affiliate_id" ] )
                ->update( [ "data" => json_encode( $awin_promotion ) ] );

            $context = "Edited ID {$data_promotion[ "push_id" ]} | Need approval ID = {$new_push_approval} | Change Request ID = {$new_push_changes}\n";
            log_message( channel: self::LOG_CHANNEL, message: "Promotions", context: [ $context ], print: true );
        }

        // Insert new promotions into the local database if any.
        if ( !empty( $local_data ) )
        {
            \DB::table( "affiliates_promotions" )->insert( $local_data );

            log_message( channel: self::LOG_CHANNEL, message: "Promotions", context: [ "Added " . count( $local_data ) . " new promotions in the local table" ], print: true );

            // Add new promotion to Firebase and update local database with push IDs.
            foreach ( $local_data as $promotion )
            {
                $aux                  = $awin_promotions[ $promotion[ "origin_id" ] ];
                $aux[ "isPublished" ] = false;

                $new_push_promotion   = $promotions_reference->push()->getKey();
                $promotions_reference->update( [ $new_push_promotion => $aux ] );

                $date                 = Helper::getDateTimeUnix();

                $new_push_approval    = $approval_reference->push()->getKey();
                $approval_reference->update( [ $new_push_approval => [
                    "requesterId"  => self::REQUESTER_ID,
                    "id"           => $new_push_promotion,
                    "lastModified" => $date,
                    "requestDate"  => $date,
                    "status"       => "aberto",
                    "action"       => "create",
                    "type"         => "promotion",
                ] ] );

                log_message( channel: self::LOG_CHANNEL, message: "Promotions", context: [ "Created ID = {$new_push_promotion} | Need approval ID = {$new_push_approval}" ], print: true );

                $affiliate_id         = $aux[ "origin" ] . $aux[ "partnerPromotionId" ];
                \DB::table( "affiliates_promotions" )->where( "affiliate_id", $affiliate_id )->update( [ "push_id" => $new_push_promotion ] );
            }
        }
    }

    /**
     * Send and update reports to Firebase.
     *
     * Handles the insertion and updating of reports in Firebase.
     * It sends new reports to Firebase and updates existing reports with the latest data.
     *
     * @return  void
     */
    private function insertReportsFirebase()
    {
        log_message( channel: self::LOG_CHANNEL, message: "Reports", context: [ "Searching for new..." ], print: true );

        // Get a reference to the "Triibo-Purchases-Awin" node in Firebase.
        $firebase_awin     = Firebase::database()->getReference( "/Triibo-Purchases-Awin"     );

        // Get a reference to the "Triibo-Purchases-Cashback" node in Firebase.
        $firebase_cashback = Firebase::database()->getReference( "/Triibo-Purchases-Cashback" );

        // Retrieve new reports from the integration.
        $new_reports       = $this->integration->getReports();

        // If new reports are available and not empty.
        if ( $new_reports[ "success" ] && !empty( $new_reports[ "data" ] ) )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Reports", context: [ "Sending new transactions to Firebase ..." ], print: true );

            // Loop through each new report.
            foreach ( $new_reports[ "data" ] as $report )
            {
                // Set empty strings in the report to an empty string.
                $report    = Helper::setEmptyString( $report );

                // Push the report to the "Triibo-Purchases-Awin" node and get the generated key.
                $push_awin = $firebase_awin->push()->getKey();
                $firebase_awin->update( [ $push_awin => $report ] );

                // Push the report's metadata to the "Triibo-Purchases-Cashback" node and get the generated key.
                $push_cash = $firebase_cashback->push()->getKey();
                $firebase_cashback->update( [ $push_cash => $report[ "metaData" ] ] );

                log_message( channel: self::LOG_CHANNEL, message: "Reports", context: [ "New transaction ID = {$push_awin} | {$push_cash}" ], print: true );
            }
        }
        else
        {
            log_message( channel: self::LOG_CHANNEL, message: "Reports", context: [ "No new transactions ..." ], print: true );
        }

        log_message( channel: self::LOG_CHANNEL, message: "Reports", context: [ "Searching for updated .." ], print: true );

        // Retrieve updated reports from the integration.
        $updated_reports = $this->integration->getReports( true );

        // If updated reports are available and not empty.
        if ( $updated_reports[ "success" ] && !empty( $updated_reports[ "data" ] ) )
        {
            // Get the current values of the "Triibo-Purchases-Awin" and "Triibo-Purchases-Cashback" nodes.
            $firebase_reports_awin = $firebase_awin->getValue();
            $firebase_reports_cash = $firebase_cashback->getValue();

            log_message( channel: self::LOG_CHANNEL, message: "Reports", context: [ "Sending updated transactions for updated ..." ], print: true );

            // Loop through each report in the "Triibo-Purchases-Awin" node.
            foreach ( $firebase_reports_awin as $push_awin => $firebase_report_awin )
            {
                // Loop through each updated report.
                foreach ( $updated_reports[ "data" ] as $updated_report )
                {
                    // Skip if the report doesn't have an id or advertiserId.
                    if ( !isset( $firebase_report_awin[ "id" ] ) || !isset( $firebase_report_awin[ "advertiserId" ] ) ) continue;

                    // Set empty strings in the updated report to an empty string.
                    $updated_report = Helper::setEmptyString( $updated_report );

                    // Check if the id and advertiserId match the current report.
                    $id             = $firebase_report_awin[ "id"           ] == $updated_report[ "id"           ];
                    $advertiser     = $firebase_report_awin[ "advertiserId" ] == $updated_report[ "advertiserId" ];

                    // If the id and advertiserId don't match, continue to the next report.
                    if ( !$id || !$advertiser ) continue;

                    // Add any missing keys from the updated report to the current report.
                    foreach ( $firebase_report_awin as $key => $value )
                        if ( !isset( $updated_report[ $key ] ) )
                            $updated_report[ $key ] = $value;

                    // Update the current report with the updated report data.
                    $firebase_report_awin = $updated_report;
                    break;
                }

                // Update the report in the "Triibo-Purchases-Awin" node.
                $firebase_awin->update( [ $push_awin => $firebase_report_awin ] );

                log_message( channel: self::LOG_CHANNEL, message: "Reports", context: [ "Updated Awin transactions ID {$push_awin}" ], print: true );
            }

            // Loop through each report in the "Triibo-Purchases-Cashback" node.
            foreach ( $firebase_reports_cash as $push_cash => $firebase_report_cash )
            {
                // Loop through each updated report.
                foreach ( $updated_reports[ "data" ] as $updated_report )
                {
                    // Check if the transactionId, advertiserId, and uid match the current report.
                    $transaction = $firebase_report_cash[ "transactionId" ] == $updated_report[ "metaData" ][ "transactionId" ];
                    $advertiser  = $firebase_report_cash[ "advertiserId"  ] == $updated_report[ "metaData" ][ "advertiserId"  ];
                    $uid         = isset( $firebase_report_cash[ "uid" ] ) && ( $firebase_report_cash[ "uid"  ] == $updated_report[ "metaData" ][ "uid" ] );

                    // If the transactionId, advertiserId, and uid match.
                    if ( $transaction && $advertiser && $uid )
                    {
                        foreach ( $firebase_report_cash as $key => $value )
                            if ( in_array( $key, [ "status", "updateDate" ] ) )
                                $firebase_report_cash[ $key ] = $updated_report[ "metaData"][ $key ];

                        break;
                    }
                }

                // Update the report in the "Triibo-Purchases-Cashback" node.
                $firebase_cashback->update( [ $push_cash => $firebase_report_cash ] );

                log_message( channel: self::LOG_CHANNEL, message: "Reports", context: [ "Updated Cash transactions ID {$push_cash}" ], print: true );
            }
        }
        else
        {
            log_message( channel: self::LOG_CHANNEL, message: "Reports", context: [ "No transactions for update ..." ], print: true );
        }
    }

    /**
     * Filter Establishments Awin
     *
     * @param   Reference   $ref
     * @return  array       $establishments
     */
    private function filterEstablishments( Reference $ref )
    {
        $establishments = [];

        foreach ( $ref->getValue() as $push => $establishment )
        {
            if ( strpos( $push, "-awin"       ) !== false ) continue;
            if ( strpos( $push, "-cofry"      ) !== false ) continue;
            if ( strpos( $push, "-socialsoul" ) !== false ) continue;

            if ( !isset( $establishment[ "partnerEstablishmentId" ] ) ) continue;
            if ( !isset( $establishment[ "origin"                 ] ) ) continue;

            if ( $establishment[ "origin" ] != "awin"                 ) continue;

            $establishments[ $push ] = $establishment;
        }

        return $establishments;
    }

    /**
     * Filter Promotions Awin
     *
     * @param   Reference   $ref
     * @return  array       $promotions
     */
    private function filterPromotions( Reference $ref )
    {
        $promotions = [];

        foreach ( $ref->getValue() as $push => $promo )
        {
            if ( strpos( $push, "-awin"       ) !== false ) continue;
            if ( strpos( $push, "-cofry"      ) !== false ) continue;
            if ( strpos( $push, "-socialsoul" ) !== false ) continue;

            if ( !isset( $promo[ "establishmentId"    ] ) ) continue;
            if ( !isset( $promo[ "partnerPromotionId" ] ) ) continue;
            if ( !isset( $promo[ "origin"             ] ) ) continue;

            if ( $promo[ "origin" ] != "awin"             ) continue;

            $promotions[ $push ] = $promo;
        }

        return $promotions;
    }

    /**
     * Copies default images to specific locations within the application.
     *
     * @return  void
     */
    private function copyDefaultImages()
    {
        // Get the base path for the default images.
        $base_path = public_path( path: self::LC_BASE_PATH );

        // Define an associative array with original image names as keys and target paths as values.
        $paths     = [
            "standard.png"   => "establishments/awin_standard.png",
            "coupon.webp"    => "promotions/awin_common_coupon.webp",
            "promotion.webp" => "promotions/awin_common_promotion.webp",
        ];

        // Loop through the $paths array
        foreach ( $paths as $original => $copy )
        {
            // Copy the original image from the base path to the target path
            File::copy( path: "{$base_path}/{$original}", target: "{$base_path}/{$copy}" );
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
        $path  = public_path( path: "{$local_path}/{$type}" );

        // Retrieve all files from the local directory.
        $files = File::allFiles( directory: $path );

        // Check if there are no files found in the local directory.
        if ( empty( $files ) )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Images", context: [ "No images to upload - {$type}..." ], print: true );
            return;
        }

        log_message( channel: self::LOG_CHANNEL, message: "Images", context: [ "Capturing names of {$type}..." ], print: true );

        // Get the file names from the list of files.
        $file_names = get_files_names( files: $files );

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
        $path    = public_path( path: "{$local_path}/{$type}" );

        log_message( channel: self::LOG_CHANNEL, message: "Images", context: [ "Sending to Firebase - {$type}..." ], print: true );

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
                log_message( channel: self::LOG_CHANNEL, message: "Images", context: [ "ID {$img}" ], print: true );
                // Perform the upload.
                $uploader->upload();
            }
            catch ( FirebaseException $e )
            {
                $message = "Images : An error has occurred while working with the Firebase SDK";

                // Handle Firebase-specific exceptions.
                log_exception( channel: self::LOG_CHANNEL, message: $message, method: $operation_method, type: "FirebaseException", e: $e );
            }
            catch (Throwable $e)
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

        log_message( channel: self::LOG_CHANNEL, message: "Images", context: [ "Sending finished - {$type}..." ], print: true );

        File::deleteDirectory( $path, true );
    }
}
