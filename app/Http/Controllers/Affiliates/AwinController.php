<?php

namespace App\Http\Controllers\Affiliates;

use DB;
use DateTime;
use Throwable;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Kreait\Firebase\Database\Reference;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Exception\FirebaseException;
use App\Console\Commands\Affiliates\Awin\ProcessDataAwin;
use App\Http\Integrations\AffiliatesIntegration\AwinIntegration;

class AwinController extends Controller
{
    /**
     * Timestamp for when establishments should be enabled.
     *
     * @var     integer
     */
    private static $date;

    /**
     * Undocumented variable
     *
     * @var     ProcessDataAwin
     */
    protected $logger;

    /**
     * Undocumented variable
     *
     * @var     AwinIntegration
     */
    protected $integration;

    /**
     * Define the types establishments and promotions
     *
     * @var     array
     */
    private const TYPES               = [ "establishments", "promotions" ];

    /**
     * Define LOCAL base path for establishments and promotions.
     *
     * @var     string
     */
    private const LC_BASE_PATH        = "images/affiliates/awin";

    /**
     * Define FIREBASE path for establishments.
     *
     * @var     string
     */
    private const FB_ESTAB_PATH       = "estabelecimento";

    /**
     * Define FIREBASE path for promotions.
     *
     * @var     string
     */
    private const FB_PROMO_PATH       = "promocao";

    /**
     * Sistema Triibo +5510999991477.
     *
     * @var     string
     */
    private const REQUESTER_ID        = "J4NjCHtvxuRojrXb29whbsNPAy33";

    /**
     * Establishment fields to ignore.
     *
     * @var     array
     */
    private const IGNORE_ESTAB_FIELDS = [
        "id",
        "fotoThumb",
        "listaFotos",
        "enableDate",
        "disableDate",
        // "isPublished", // TODO validar
        "dataInclusao",
        "dataAlteracao",
    ];

    /**
     * Promotion fields to ignore.
     *
     * @var     array
     */
    private const IGNORE_PROMO_FIELDS = [
        "id",
        "dueDate",
        "enableDate",
        "disableDate",
        // "isPublished", // TODO validar
        "establishmentId",
    ];

    /**
     * Constructor
     *
     * @param   ProcessDataAwin     $logger     Logger instance for logging data processing.
     * @param   boolean             $load       Load establishments from Awin, default true.
     * @return  void
     */
    public function __construct( ProcessDataAwin $logger, bool $load = true )
    {
        $this->logger      = $logger;
        $this->integration = new AwinIntegration( logger: $logger, load: $load );

        self::initDate();

        File::ensureDirectoryExists( path: public_path( path: "images/affiliates/awin/establishments" ) );
        File::ensureDirectoryExists( path: public_path( path: "images/affiliates/awin/promotions"     ) );
    }

    /**
     * Initialize static date variables for enable and disable timestamps.
     * This method sets up the timestamps only once to avoid redundant calculations.
     *
     * @return  void
     */
    private static function initDate() : void
    {
        // Check if the enable date has not been set yet.
        if ( !isset( self::$date ) )
        {
            // Create a new DateTime object for the current time.
            $date       = new DateTime();

            // Set the enable date as a Unix timestamp in milliseconds.
            self::$date = ( int ) $date->format( format: "Uv" );
        }
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

            $this->logger->log_message( message: "{$operation_name}: Sent successfully" );
        }
        catch ( FirebaseException $e )
        {
            // Handle Firebase-specific exceptions.
            $this->logger->log_exception(
                message : "{$operation_name}: Firebase process error - aborted",
                method  : $operation_method,
                type    : "FirebaseException",
                e       : $e
            );
        }
        catch ( Throwable $e )
        {
            // Handle general exceptions.
            $this->logger->log_exception(
                message : "{$operation_name}: Unexpected error - aborted",
                method  : $operation_method,
                type    : "Throwable",
                e       : $e
            );
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
        // Delete the existing establishments directory.
        clear_directory( path: self::LC_BASE_PATH . "/establishments" );

        // Initialize Firebase database references
        $db                      = Firebase::database();
        $reference_establishment = $db->getReference( path: "/Estabelecimento-v2"            );
        $reference_approval      = $db->getReference( path: "/Estabelecimento-Aprovacoes"    );
        $reference_changes       = $db->getReference( path: "/Establishment-changes-request" );

        // Retrieve establishments from Awin.
        $establishments_awin     = $this->getAwinEstablishments();

        // Retrieve establishments from Firebase.
        $establishments_firebase = $this->getFirebaseEstablishments( reference: $reference_establishment );
        $establishments_fb_aux   = [];

        // Retrieve establishments from local.
        $establishments_local    = $this->getLocalEstablishments();

        $this->logger->log_message( message: "Establishments: Validating..." );

        // Disable Firebase establishments not present in Awin establishments.
        foreach ( $establishments_firebase as $push => $establishment_fb )
        {
            $partner_id   = $establishment_fb[ "partnerEstablishmentId" ];
            $is_duplicate = in_array( $partner_id, $establishments_fb_aux );

            if ( !isset( $establishments_awin[ $partner_id ] ) || $is_duplicate )
            {
                if ( $establishment_fb[ "isPublished" ] )
                {
                    $approval = $db->getReference( path: "/Estabelecimento-Aprovacoes/{$push}" )->getValue();

                    if ( $approval )
                    {
                        $approval[ "status"       ] = "negado";
                        $approval[ "comment"      ] = "Estabelecimento desativado, aprovação fechada pelo sistema.";
                        $approval[ "approvalDate" ] = self::$date;

                        $reference_approval->update( values: [ $push => $approval ] );

                        $this->logger->log_message( message: "Establishments: Canceling open curatorship of ID {$push}" );
                    }

                    $establishment_fb[ "isPublished" ] = false;

                    $reference_establishment->update( values: [ $push => $establishment_fb ] );

                    $this->logger->log_message( message: "Establishments: Disabling ID {$push}" );
                }

                if ( $is_duplicate )
                    unset( $establishments_firebase[ $push ] );

                continue;
            }

            $establishments_fb_aux[] = $partner_id;
        }

        // Process Awin establishments for addition or update.
        foreach ( $establishments_awin as $partner_id => $establishment_aw )
        {
            list( $firebase_est, $firebase_push ) = $this->checkFirebaseEstablishment( id: $partner_id, establishments: $establishments_firebase );

            // Checks if the Awin establishment not exists in local.
            if ( !isset( $establishments_local[ $partner_id ] ) )
            {
                // Establishment does not exist in firebase, create curation creation request.
                if ( $firebase_est === null )
                {
                    $this->processNewEstablishmentFirebase(
                        id            : $partner_id,
                        establishment : $establishment_aw,
                        action        : "create"
                    );
                }

                // Establishment exists in firebase, check differences and create local record.
                else
                {
                    $establishment_aw[ "id" ] = $firebase_push;

                    $this->createOrUpdateLocalEstablishment(
                        id            : $partner_id,
                        push          : $firebase_push,
                        establishment : $establishment_aw,
                        action        : "create"
                    );

                    $this->processEditEstablishmentFirebase(
                        push          : $firebase_push,
                        establishment : $establishment_aw,
                        compare       : $firebase_est,
                        reference     : $reference_changes
                    );
                }
            }

            // Awin establishment exist in local.
            else
            {
                $affiliate_id = $establishment_aw[ "origin" ] . $partner_id;

                // Establishment does not exist in firebase, create curation creation request.
                if ( $firebase_est === null )
                {
                    $this->processNewEstablishmentFirebase(
                        id            : $partner_id,
                        establishment : $establishment_aw,
                        action        : "update"
                    );
                }

                // Establishment exists in firebase, check differences.
                else
                {
                    $establishment_lc         = $establishments_local[ $partner_id ][ "data" ];

                    $establishment_lc[ "id" ] = $firebase_push;
                    $establishment_aw[ "id" ] = $firebase_push;

                    $this->processEditEstablishmentFirebase(
                        push          : $firebase_push,
                        establishment : $establishment_aw,
                        compare       : $establishment_lc,
                        reference     : $reference_changes,
                        partner_id    : $partner_id,
                    );
                }
            }
        }
    }

    /**
     * Update and create promotions on Firebase
     *
     * This PHP method is responsible for synchronizing promotion data between a local database and Firebase.
     * It performs the following operations:
     * - Deletes the existing promotions directory.
     * - Retrieves promotions from Firebase and the local database.
     * - Disables promotions in Firebase that are not present in the Awin list.
     * - Adds new promotions from Awin to the local database and Firebase.
     * - Requests approval for any changes to existing promotions.
     * - Updates the local database with changes from Awin.
     * - Logs actions and outputs status messages to the console.
     *
     * @return  void
     */
    private function insertPromotionsFirebase()
    {
        // Delete the existing promotions directory.
        clear_directory( path: self::LC_BASE_PATH . "/promotions" );

        $local_data              = [];

        // Initialize Firebase database references
        $db                      = Firebase::database();
        $reference_establishment = $db->getReference( path: "/Estabelecimento-v2"    );
        $reference_promotions    = $db->getReference( path: "/Store"                 );
        $reference_approval      = $db->getReference( path: "/Store-Aprovacoes"      );
        $reference_changes       = $db->getReference( path: "/Store-changes-request" );

        // Retrieve promotions from Awin.
        $promotions_awin         = $this->getAwinPromotions();

        // Retrieve establishments from Firebase.
        $establishments_firebase = $this->getFirebaseEstablishments( reference: $reference_establishment );

        $this->logger->log_message( message: "Promotions: Filtering promotions firebase..." );

        // Retrieve promotions from Firebase.
        $promotions_firebase     = $this->getFirebasePromotions( reference: $reference_promotions );

        // Retrieve promotions from local.
        $promotions_local        = $this->getLocalPromotions();

        $this->logger->log_message( message: "Promotions: Validating ..." );

        // Disable Firebase promotions not present in Awin promotions.
        foreach ( $promotions_firebase as $push => $firebase_promotion )
        {
            if ( !isset( $promotions_awin[ $firebase_promotion[ "partnerPromotionId" ] ] ) )
            {
                if ( $firebase_promotion[ "isPublished" ] )
                {
                    $this->logger->log_message( message: "Promotions: Disabling ID {$push}" );
                    $firebase_promotion[ "isPublished" ] = false;
                    $reference_promotions->update( values: [ $push => $firebase_promotion ] );

                    $approval_promotion = $db->getReference( path: "/Store-Aprovacoes/{$push}" )->getValue();

                    if ( $approval_promotion && $approval_promotion[ "status" ] == "aberto" )
                    {
                        $approval_promotion[ "status"       ] = "negado";
                        $approval_promotion[ "comment"      ] = "Promoção desativada, aprovação fechada pelo sistema.";
                        $approval_promotion[ "approvalDate" ] = self::$date;

                        $this->logger->log_message( message: "Promotions: Canceling open curatorship of ID {$push}" );
                        $reference_approval->update( values: [ $push => $approval_promotion ] );
                    }
                }
            }
        }

        // Process Awin promotions for addition or update.
        foreach ( $promotions_awin as $id => $awin_promotion )
        {
            if ( !isset( $promotions_local[ $id ] ) )
            {
                // Add new promotion to local data array.
                $local_data[] = [
                    "origin_id"    => $id,
                    "origin"       => $awin_promotion[ "origin" ],
                    "affiliate_id" => $awin_promotion[ "origin" ] . $id,
                    "push_id"      => null,
                    "data"         => json_encode( $awin_promotion ),
                    "created_at"   => now(),
                    "updated_at"   => now(),
                ];

                continue;
            }

            $local_promotion  = $promotions_local[ $id ];
            $data_promotion   = json_decode( $local_promotion[ "data" ], true );
            $establishment_id = $data_promotion[ "establishmentId" ] ?? "";

            if ( empty( $establishment_id ) )
            {
                $partner_establishment_id = $awin_promotion[ "partnerEstablishmentId" ];
                $index                    = array_search( $partner_establishment_id, array_column( $establishments_firebase, "partnerEstablishmentId" ) );
                $pushes                   = array_keys( $establishments_firebase );
                $establishment_id         = $pushes[ $index ];
            }

            $awin_promotion[ "establishmentId" ] = $establishment_id;

            unset( $awin_promotion[ "partnerEstablishmentId" ] );

            // Compare Awin and local promotions and get differences.
            $diff             = array_diff_assoc_recursive( $awin_promotion, $data_promotion );

            // Remove ignored fields from the differences.
            foreach ( self::IGNORE_PROMO_FIELDS as $field )
                unset( $diff[ $field ] );

            // Skip if there are no changes
            if ( empty( $diff ) ) continue;

            $this->logger->log_message( message: "Promotions: Has changes ID " . $local_promotion[ "push_id" ] );

            // Add edit approval request in Firebase.
            $db->getReference( path: "/Store-Aprovacoes/" . $local_promotion[ "push_id" ] )
                ->update( values: [
                "requesterId"       => self::REQUESTER_ID,
                "id"                => $local_promotion[ "push_id" ],
                "establishmentId"   => $establishment_id,
                "establishmentName" => $awin_promotion[ "establishmentName" ],
                "lastModified"      => self::$date,
                "requestDate"       => self::$date,
                "status"            => "aberto",
                "action"            => "edit",
                "type"              => "promotion",
                "origin"            => "awin",
                "dueDate"           => $awin_promotion[ "dueDate" ],
            ] );

            // Get pending changes
            $past_diffs       = invalidate_past_promotion_changes( reference: $reference_changes, push_id: $local_promotion[ "push_id" ] );

            if ( !empty( $past_diffs ) )
                $this->logger->log_message( message: "Promotions: Invalidating past changes ID " . $local_promotion[ "push_id" ] );

            $current_changes  = array_merge( $past_diffs, $diff );

            // Add edit change request in Firebase.
            $new_push_changes = $reference_changes->push()->getKey();
            $reference_changes->update( values: [ $new_push_changes => [
                "id"                => $new_push_changes,
                "createDate"        => self::$date,
                // "updateDate"        => "",
                // "reviewDate"        => "",
                "storeId"           => $local_promotion[ "push_id" ],
                "establishmentId"   => $establishment_id,
                "establishmentName" => $awin_promotion[ "establishmentName" ],
                "requesterUid"      => self::REQUESTER_ID,
                // "validatorUid"      => "",
                // "comment"           => "",
                "type"              => "validation",
                "changedFields"     => $current_changes,
            ] ] );

            // Update the local promotion with the new data.
            DB::table( "affiliates_promotions" )
                ->where( "affiliate_id", $local_promotion[ "affiliate_id" ] )
                ->update( [ "data" => json_encode( $awin_promotion ) ] );

            $this->logger->log_message(
                message : "Promotions: Edited ID " . $local_promotion[ "push_id" ],
                context : [
                    "Need approval ID "  . $local_promotion[ "push_id" ],
                    "Change Request ID " . $new_push_changes,
                    $current_changes
                ]
            );
        }

        // Insert new promotions into the local database if any.
        if ( !empty( $local_data ) )
        {
            // Add new promotion to Firebase and update local database with push IDs.
            foreach ( $local_data as $promotion )
            {
                // Add new promotion in Firebase.
                $new_push_promotion       = $reference_promotions->push()->getKey();

                $aux                      = json_decode( $promotion[ "data" ], true );
                $partner_establishment_id = $aux[ "partnerEstablishmentId" ];

                $index                    = array_search_by_key( needle: $partner_establishment_id, column: "partnerEstablishmentId", array: $establishments_firebase );
                $pushes                   = array_keys( $establishments_firebase );

                $aux[ "establishmentId" ] = $pushes[ $index ];
                $aux[ "id"              ] = $new_push_promotion;
                $aux[ "isPublished"     ] = false;

                unset( $aux[ "partnerEstablishmentId" ] );

                $reference_promotions->update( values: [ $new_push_promotion => $aux ] );

                // Add create approval request in Firebase.
                $db->getReference( path: "/Store-Aprovacoes/{$new_push_promotion}" )
                    ->set( value: [
                    "requesterId"       => self::REQUESTER_ID,
                    "id"                => $new_push_promotion,
                    "establishmentId"   => $aux[ "establishmentId"   ],
                    "establishmentName" => $aux[ "establishmentName" ],
                    "lastModified"      => self::$date,
                    "requestDate"       => self::$date,
                    "status"            => "aberto",
                    "action"            => "create",
                    "type"              => "promotion",
                    "origin"            => "awin",
                    "dueDate"           => $aux[ "dueDate" ],
                ] );

                $this->logger->log_message(
                    message : "Promotions: Created ID = " . $new_push_promotion,
                    context : [ "Need approval ID = " . $new_push_promotion ]
                );

                $promotion[ "push_id" ] = $new_push_promotion;
                $promotion[ "data"    ] = json_encode( $aux );

                DB::table( "affiliates_promotions" )->insert( $promotion );
            }

            $this->logger->log_message( message: "Promotions: Added " . count( $local_data ) . " new promotions in the local table" );
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
        $this->logger->log_message( message: "Reports: Searching for new..." );

        // Get a reference to the "Triibo-Purchases-Awin" node in Firebase.
        $firebase_awin     = Firebase::database()->getReference( path: "/Triibo-Purchases-Awin"     );

        // Get a reference to the "Triibo-Purchases-Cashback" node in Firebase.
        $firebase_cashback = Firebase::database()->getReference( path: "/Triibo-Purchases-Cashback" );

        // Retrieve new reports from the integration.
        $new_reports       = $this->integration->getReports();

        // If new reports are available and not empty.
        if ( $new_reports[ "success" ] && !empty( $new_reports[ "data" ] ) )
        {
            $this->logger->log_message( message: "Reports: Sending new transactions to Firebase ..." );

            // Loop through each new report.
            foreach ( $new_reports[ "data" ] as $report )
            {
                // Set empty strings in the report to an empty string.
                $report    = set_empty_string( data: $report );

                // Push the report to the "Triibo-Purchases-Awin" node and get the generated key.
                $push_awin = $firebase_awin->push()->getKey();
                $firebase_awin->update( values: [ $push_awin => $report ] );

                // Push the report's metadata to the "Triibo-Purchases-Cashback" node and get the generated key.
                $push_cash = $firebase_cashback->push()->getKey();
                $firebase_cashback->update( values: [ $push_cash => $report[ "metaData" ] ] );

                $this->logger->log_message( message: "Reports: New transaction awin ID = {$push_awin} | cashback ID {$push_cash}" );
            }
        }
        else
        {
            $this->logger->log_message( message: "Reports: No new transactions ..." );
        }

        $this->logger->log_message( message: "Reports: Searching for updated ..." );

        // Retrieve updated reports from the integration.
        $updated_reports = $this->integration->getReports( is_update: true );

        // If updated reports are available and not empty.
        if ( $updated_reports[ "success" ] && !empty( $updated_reports[ "data" ] ) )
        {
            // Get the current values of the "Triibo-Purchases-Awin" and "Triibo-Purchases-Cashback" nodes.
            $firebase_reports_awin = $firebase_awin->getValue();
            $firebase_reports_cash = $firebase_cashback->getValue();

            $this->logger->log_message( message: "Reports: Sending updated transactions for updated ..." );

            // Loop through each report in the "Triibo-Purchases-Awin" node.
            foreach ( $firebase_reports_awin as $push_awin => $firebase_report_awin )
            {
                // Loop through each updated report.
                foreach ( $updated_reports[ "data" ] as $updated_report )
                {
                    // Skip if the report doesn't have an id or advertiserId.
                    if ( !isset( $firebase_report_awin[ "id" ] ) || !isset( $firebase_report_awin[ "advertiserId" ] ) ) continue;

                    // Set empty strings in the updated report to an empty string.
                    $updated_report = set_empty_string( data: $updated_report );

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
                $firebase_awin->update( values: [ $push_awin => $firebase_report_awin ] );

                $this->logger->log_message( message: "Reports: Updated transaction | awin ID = {$push_awin}" );
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
                                $firebase_report_cash[ $key ] = $updated_report[ "metaData" ][ $key ];

                        break;
                    }
                }

                // Update the report in the "Triibo-Purchases-Cashback" node.
                $firebase_cashback->update( values: [ $push_cash => $firebase_report_cash ] );

                $this->logger->log_message( message: "Reports: Updated transaction | cashback ID = {$push_cash}" );
            }
        }
        else
        {
            $this->logger->log_message( message: "Reports: No transactions for update ..." );
        }
    }

    /**
     * Filter Establishments Awin
     *
     * @param   Reference   $ref
     * @return  array       $establishments
     */
    private function filterEstablishments( Reference $reference )
    {
        $blacklist = [ "-awin", "-cofry", "-socialsoul"   ];
        $fields    = [ "partnerEstablishmentId", "origin" ];

        return filter_data( data: $reference->getValue(), blacklist: $blacklist, fields: $fields, origin: "awin" );
    }

    /**
     * Filter Promotions Awin
     *
     * @param   Reference   $reference
     * @return  array       $promotions
     */
    private function filterPromotions( Reference $reference )
    {
        $blacklist = [ "-awin", "-cofry", "-socialsoul"                  ];
        $fields    = [ "establishmentId", "partnerPromotionId", "origin" ];

        return filter_data( data: $reference->getValue(), blacklist: $blacklist, fields: $fields, origin: "awin" );
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
        // Retrieve all files from the local directory.
        $files        = File::allFiles( directory: $local_path );

        // Check if there are no files found in the local directory.
        if ( empty( $files ) )
        {
            $this->logger->log_message( message: "Images: No images to upload - {$type}..." );
            return;
        }

        $this->logger->log_message( message: "Images: Capturing names of {$type}..." );

        // Get the file names from the list of files.
        $file_names   = get_files_name( files: $files );

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
    private function insertImagesFirebase( array $images, string $local_path, string $firebase_path, string $type ) : void
    {
        // Initialize Firebase storage and get the storage bucket.
        $storage      = Firebase::storage();
        $bucket       = $storage->getBucket();

        $this->logger->log_message( message: "Images: Sending to Firebase - {$type}..." );

        // Iterate over each image and upload it to Firebase.
        foreach ( $images as $img )
        {
            // Create a resumable uploader instance for the current image.
            $uploader = $bucket->getResumableUploader(
                data    : fopen( "{$local_path}/{$img}", "r" ),
                options : [ "name" => "{$firebase_path}/{$img}" ]
            );

            try
            {
                $this->logger->info( string: "Images: ID {$img}" );

                // Perform the upload.
                $uploader->upload();
            }
            catch ( FirebaseException $e )
            {
                // Handle Firebase-specific exceptions.
                $this->logger->log_exception(
                    message : "Images : An error has occurred while working with the Firebase SDK",
                    method  : $operation_method,
                    type    : "FirebaseException",
                    e       : $e
                );
            }
            catch ( Throwable $e )
            {
                // Handle general exceptions.
                $this->logger->log_exception(
                    message : "Images : Unexpected error",
                    method  : $operation_method,
                    type    : "Throwable",
                    e       : $e
                );
            }
            finally
            {
                // Attempt to retrieve the resume URI from the uploader and resume the upload.
                $resumeUri = $uploader->getResumeUri();
                $uploader->resume( resumeUri: $resumeUri );
            }
        }

        $this->logger->log_message( message: "Images: Sending finished - {$type}..." );

        File::deleteDirectory( directory: $local_path, preserve: true );
    }

    // ============================================================================================================

    private function getAwinEstablishments() : array
    {
        $establishments = $this->integration->getEstablishments();
        $count          = count( $establishments );

        $this->logger->log_message(
            message : "Establishments: {$count} filtered from awin...",
            context : $establishments,
        );

        return $establishments;
    }

    private function getFirebaseEstablishments( Reference $reference ) : array
    {
        $establishments = $this->filterEstablishments( reference: $reference );
        $count          = count( $establishments );

        $this->logger->log_message(
            message : "Establishments: {$count} filtered from firebase...",
            context : $establishments,
        );

        return $establishments;
    }

    private function getLocalEstablishments() : array
    {
        $establishments = DB::table( "affiliates_establishments" )
                            ->where( "origin", "awin" )
                            ->get( [ "origin_id", "affiliate_id", "push_id", "name", "data" ] )
                            ->keyBy( "origin_id" )
                            ->map( function ( $est )
                            {
                                $est->data = json_decode( $est->data, true );
                                return $est;
                            } );

        $establishments = json_decode( $establishments, true );
        $count          = count( $establishments );

        $this->logger->log_message(
            message : "Establishments: {$count} filtered from local...",
            context : $establishments,
        );

        return $establishments;
    }

    private function getAwinPromotions() : array
    {
        $promotions = $this->integration->getPromotions();
        $count      = count( $promotions );

        $this->logger->log_message(
            message : "Promotions: {$count} filtered from awin...",
            context : $promotions,
        );

        return $promotions;
    }

    private function getFirebasePromotions( Reference $reference ) : array
    {
        $promotions = $this->filterPromotions( reference: $reference );
        $count      = count( $promotions );

        $this->logger->log_message(
            message : "Promotions: {$count} filtered from firebase...",
            context : $promotions,
        );

        return $promotions;
    }

    private function getLocalPromotions() : array
    {
        $promotions = DB::table( "affiliates_promotions" )
                        ->where("origin", "awin")
                        ->get([ "origin_id", "affiliate_id", "push_id", "data" ])
                        ->keyBy( "origin_id" )
                        ->map( function ( $promo )
                        {
                            $promo->data = json_decode( $promo->data, true );
                            return $promo;
                        } );

        $promotions = json_decode( $promotions, true );
        $count      = count( $promotions );

        $this->logger->log_message(
            message : "Promotions: {$count} filtered from local...",
            context : $promotions,
        );

        return $promotions;
    }

    private function checkFirebaseEstablishment( int $id, array $establishments ) : array
    {
        $firebase_est  = null;
        $firebase_push = null;

        // Checks if the Awin establishment exists in firebase.
        foreach ( $establishments as $push => $estab )
        {
            if ( isset( $estab[ "partnerEstablishmentId" ] ) && $estab[ "partnerEstablishmentId" ] == $id )
            {
                $firebase_est  = $estab;
                $firebase_push = $push;
                break;
            }
        }

        return [
            $firebase_est,
            $firebase_push,
        ];
    }

    // ============================================================================================================

    private function processNewEstablishmentFirebase( int $id, array $establishment, string $action )
    {
        // Add new establishment in Firebase.
        $new_push = $this->createFirebaseEstablishment( establishment: $establishment );

        // Add create approval request in Firebase.
        $this->createEstablishmentApprovalRequest(
            push   : $new_push,
            name   : $establishment[ "nome" ],
            action : "create"
        );

        $this->createOrUpdateLocalEstablishment(
            id            : $id,
            push          : $new_push,
            establishment : $establishment,
            action        : $action
        );
    }

    private function processEditEstablishmentFirebase( string $push, array $establishment, array $compare, Reference $reference, ?int $partner_id = null )
    {
        // Compare Awin and local establishments and get differences.
        $diff = array_diff_assoc_recursive( $establishment, $compare );

        // Remove ignored fields from the differences.
        foreach ( self::IGNORE_ESTAB_FIELDS as $field )
            unset( $diff[ $field ] );

        if ( !empty( $diff ) )
        {
            // Add edit approval request in Firebase.
            $this->createEstablishmentApprovalRequest(
                push   : $push,
                name   : $establishment[ "nome" ],
                action : "edit"
            );

            // Get pending changes
            $past_diffs      = invalidate_past_establishment_changes( reference: $reference, push_id: $push );
            $current_changes = array_merge( $past_diffs, $diff );

            // Add edit change request in Firebase.
            $this->createEstablishmentChangesRequest(
                push    : $push,
                name    : $establishment[ "nome" ],
                changes : $current_changes
            );

            if ( $partner_id !== null )
            {
                $this->createOrUpdateLocalEstablishment(
                    id            : $partner_id,
                    push          : $push,
                    establishment : $establishment,
                    action        : "update"
                );
            }
        }
    }

    private function createFirebaseEstablishment( array &$establishment ) : string
    {
        $reference = Firebase::database()->getReference( path: "/Estabelecimento-v2" );
        $new_push  = $reference->push()->getKey();

        $establishment[ "id"          ] = $new_push;
        $establishment[ "isPublished" ] = false;

        $reference->update( values: [ $new_push => $establishment ] );

        $this->logger->log_message( message : "Establishments: Created ID = {$new_push}" );

        return $new_push;
    }

    private function createEstablishmentApprovalRequest( string $push, string $name, string $action ): void
    {
        Firebase::database()
            ->getReference( path: "/Estabelecimento-Aprovacoes/{$push}" )
            ->set( value: [
                "requesterId"       => self::REQUESTER_ID,
                "id"                => $push,
                "establishmentName" => $name,
                "lastModified"      => self::$date,
                "requestDate"       => self::$date,
                "status"            => "aberto",
                "action"            => $action,
                "origin"            => "awin"
            ] );

        $this->logger->log_message( message : "Establishments: Need approval ID = {$push}" );
    }

    private function createEstablishmentChangesRequest( string $push, string $name, array $changes ) : void
    {
        $reference = Firebase::database()->getReference( path: "/Establishment-changes-request" );
        $new_push  = $reference->push()->getKey();

        // Add edit change request in Firebase.
        $reference->update( values: [
            $new_push => [
                "id"                => $new_push,
                "createDate"        => self::$date,
                "establishmentId"   => $push,
                "establishmentName" => $name,
                "requesterUid"      => self::REQUESTER_ID,
                "type"              => "validation",
                "changedFields"     => $changes,
            ]
        ] );

        $this->logger->log_message(
            message : "Establishments: Edited ID " . $push,
            context : [
                "Need approval ID "  . $push,
                "Change Request ID " . $new_push,
                $changes
            ]
        );
    }

    private function createOrUpdateLocalEstablishment( int $id, string $push, array $establishment, string $action ) : void
    {
        $affiliate_id = $establishment[ "origin" ] . $id;

        $query = DB::table( "affiliates_establishments" );

        if ( $action == "create" )
        {
            $query->insert( [
                "origin_id"    => $id,
                "origin"       => $establishment[ "origin" ],
                "name"         => $establishment[ "nome"   ],
                "affiliate_id" => $affiliate_id,
                "push_id"      => $push,
                "data"         => json_encode( $establishment ),
                "created_at"   => now(),
                "updated_at"   => now(),
            ] );
        }
        elseif ( $action == "update" )
        {
            $query->where( "affiliate_id", $affiliate_id )
                ->update( [
                "origin_id" => $id,
                "origin"    => $establishment[ "origin" ],
                "name"      => $establishment[ "nome"   ],
                "push_id"   => $push,
                "data"      => json_encode( $establishment ),
            ] );
        }
    }
}
