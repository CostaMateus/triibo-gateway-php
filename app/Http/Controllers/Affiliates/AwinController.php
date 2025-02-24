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
        // "isPublished",
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
        "isPublished",
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

        foreach ( self::TYPES as $type )
            File::ensureDirectoryExists( path: public_path( path: self::LC_BASE_PATH . "/{$type}" ) );
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
        $local_path              = public_path( path: self::LC_BASE_PATH . "/establishments" );
        File::deleteDirectory( directory: $local_path, preserve: true );

        $local_data              = [];

        // Initialize Firebase database references
        $db                      = Firebase::database();
        $establishment_reference = $db->getReference( path: "/Estabelecimento-v2"            );
        $approval_reference      = $db->getReference( path: "/Estabelecimento-Aprovacoes"    );
        $changes_reference       = $db->getReference( path: "/Establishment-changes-request" );

        // Retrieve establishments from Awin.
        $awin_establishments     = $this->integration->getEstablishments();
        $this->logger->log_message(
            message : "Establishments: " . count( $awin_establishments ) . " filtered from awin...",
            context : $awin_establishments,
        );

        // Retrieve establishments from Firebase.
        $firebase_establishments = $this->filterEstablishments( reference: $establishment_reference );
        $this->logger->log_message(
            message : "Establishments: " . count( $firebase_establishments ) . " filtered from firebase...",
            context : $firebase_establishments,
        );

        // Retrieve establishments from local.
        $local_establishments    = DB::table( "affiliates_establishments" )->where( "origin", "awin" )->get()->keyBy( "origin_id" )->toArray();
        $local_establishments    = json_decode( json_encode( $local_establishments ), true );
        $this->logger->log_message(
            message : "Establishments: " . count( $local_establishments ) . " filtered from local...",
            context : $local_establishments,
        );

        $this->logger->log_message( message: "Establishments: Validating..." );

        // Disable Firebase establishments not present in Awin establishments.
        foreach ( $firebase_establishments as $push => $firebase_stablishment )
        {
            if ( !isset( $awin_establishments[ $firebase_stablishment[ "partnerEstablishmentId" ] ] ) )
            {
                if ( $firebase_stablishment[ "isPublished" ] )
                {
                    $this->logger->log_message( message: "Establishments: Disabling ID {$push}" );
                    $firebase_stablishment[ "isPublished" ] = false;
                    $establishment_reference->update( values: [ $push => $firebase_stablishment ] );

                    $approval_establishment = $db->getReference( path: "/Estabelecimento-Aprovacoes/{$push}" )->getValue();

                    if ( $approval_establishment )
                    {
                        $approval_establishment[ "status"       ] = "negado";
                        $approval_establishment[ "comment"      ] = "Estabelecimento desativado, aprovação fechada pelo sistema.";
                        $approval_establishment[ "approvalDate" ] = self::$date;

                        $this->logger->log_message( message: "Establishments: Canceling open curatorship of ID {$push}" );
                        $approval_reference->update( values: [ $push => $approval_establishment ] );
                    }
                }
            }
            else
            {
                $firebase_stablishment[ "isPublished" ] = true;
                $establishment_reference->update( values: [ $push => $firebase_stablishment ] );
            }
        }

        // // Process Awin establishments for addition or update.
        // foreach ( $awin_establishments as $id => $awin_establishment )
        // {
        //     if ( !isset( $local_establishments[ $id ] ) )
        //     {
        //         // Add new establishment to local data array.
        //         $local_data[] = [
        //             "origin_id"    => $id,
        //             "origin"       => $awin_establishment[ "origin" ],
        //             "affiliate_id" => $awin_establishment[ "origin" ] . $id,
        //             "name"         => $awin_establishment[ "nome"   ],
        //             "push_id"      => null,
        //             "data"         => json_encode( $awin_establishment ),
        //             "created_at"   => now(),
        //             "updated_at"   => now(),
        //         ];

        //         continue;
        //     }

        //     $local_establishment = $local_establishments[ $id ];
        //     $data_establishment  = json_decode( $local_establishment[ "data" ], true );

        //     if ( isset( $awin_establishment[ "cashbackDefault" ] ) )
        //         $awin_establishment[ "cashbackDefault" ] = ( float ) $awin_establishment[ "cashbackDefault" ];

        //     if ( isset( $data_establishment[ "cashbackDefault" ] ) )
        //         $data_establishment[ "cashbackDefault" ] = ( float ) $data_establishment[ "cashbackDefault" ];

        //     // Compare Awin and local establishments and get differences.
        //     $diff                = array_diff_assoc_recursive( $awin_establishment, $data_establishment );

        //     // Remove ignored fields from the differences.
        //     foreach ( self::IGNORE_ESTAB_FIELDS as $field )
        //         unset( $diff[ $field ] );

        //     // Skip if there are no changes
        //     if ( empty( $diff ) ) continue;

        //     if ( count( $diff ) == 1 && isset( $diff[ "cashbackDefault" ] ) )
        //     {
        //         $push_id                            = $local_establishment[ "push_id"      ];
        //         $affiliate_id                       = $local_establishment[ "affiliate_id" ];
        //         $establishment                      = $firebase_establishments[ $push_id ];

        //         // Update establishment cashbackDefault.
        //         $establishment[ "cashbackDefault" ] = $diff[ "cashbackDefault" ];
        //         $establishment_reference->update( values: [ $push_id => $establishment ] );

        //         // Update the local establishment with new cashbackDefault.
        //         DB::table( "affiliates_establishments" )
        //             ->where( "affiliate_id", $affiliate_id )
        //             ->update( [ "data" => json_encode( $awin_establishment ) ] );

        //         $this->logger->log_message(
        //             message : "Establishments: Edited ID = " . $push_id,
        //             context : [
        //                 "Only cashback changed",
        //                 "from " . $data_establishment[ "cashbackDefault" ],
        //                 "to "   . $diff[ "cashbackDefault" ]
        //             ]
        //         );

        //         continue;
        //     }

        //     // TODO remover
        //     $this->updateIsPublished(
        //         reference    : $establishment_reference,
        //         push_id      : $local_establishment[ "push_id"      ],
        //         affiliate_id : $local_establishment[ "affiliate_id" ],
        //         awin_data    : $awin_establishment,
        //         fb_data      : $firebase_establishments[ $local_establishment[ "push_id" ] ]
        //     );
        //     // ============

        //     $this->logger->log_message( message: "Establishments: Has changes ID " . $local_establishment[ "push_id" ] );

        //     // Add edit approval request in Firebase.
        //     $db->getReference( path: "/Estabelecimento-Aprovacoes/" . $local_establishment[ "push_id" ] )
        //        ->set( value: [
        //         "requesterId"       => self::REQUESTER_ID,
        //         "id"                => $local_establishment[ "push_id" ],
        //         "establishmentName" => $local_establishment[ "name"    ],
        //         "lastModified"      => self::$date,
        //         "requestDate"       => self::$date,
        //         "status"            => "aberto",
        //         "action"            => "edit",
        //         "origin"            => "awin"
        //     ] );

        //     // Get pending changes
        //     $past_diffs       = invalidate_past_establishment_changes( reference: $changes_reference, push_id: $local_establishment[ "push_id" ] );

        //     if ( !empty( $past_diffs ) )
        //         $this->logger->log_message( message: "Establishments: Invalidating past changes ID " . $local_establishment[ "push_id" ] );

        //     $current_changes  = array_merge( $past_diffs, $diff );

        //     // Add edit change request in Firebase.
        //     $new_push_changes = $changes_reference->push()->getKey();
        //     $changes_reference->update( values: [ $new_push_changes => [
        //         "id"                => $new_push_changes,
        //         "createDate"        => self::$date,
        //         // "updateDate"        => "",
        //         // "reviewDate"        => "",
        //         "establishmentId"   => $local_establishment[ "push_id" ],
        //         "establishmentName" => $local_establishment[ "name"    ],
        //         "requesterUid"      => self::REQUESTER_ID,
        //         // "validatorUid"      => "",
        //         // "comment"           => "",
        //         "type"              => "validation",
        //         "changedFields"     => $current_changes,
        //     ] ] );

        //     // Update the local establishment with the new data.
        //     DB::table( "affiliates_establishments" )
        //         ->where( "affiliate_id", $local_establishment[ "affiliate_id" ] )
        //         ->update( [ "data" => json_encode( $awin_establishment ) ] );

        //     $this->logger->log_message(
        //         message : "Establishments: Edited ID " . $local_establishment[ "push_id" ],
        //         context : [
        //             "Need approval ID "  . $local_establishment[ "push_id" ],
        //             "Change Request ID " . $new_push_changes,
        //             $current_changes
        //         ]
        //     );
        // }

        // // Insert new establishments into the local database if any.
        // if ( !empty( $local_data ) )
        // {
        //     // Add new establishments to Firebase and update local database with push IDs.
        //     foreach ( $local_data as $establishment )
        //     {
        //         // Add new establishment in Firebase.
        //         $new_push_establishment = $establishment_reference->push()->getKey();

        //         $aux                    = $awin_establishments[ $establishment[ "origin_id" ] ];
        //         $aux[ "id"          ]   = $new_push_establishment;
        //         $aux[ "isPublished" ]   = false;

        //         $establishment_reference->update( values: [ $new_push_establishment => $aux ] );

        //         // Add create approval request in Firebase.
        //         $db->getReference( path: "/Estabelecimento-Aprovacoes/{$new_push_establishment}" )
        //            ->set( value: [
        //             "requesterId"       => self::REQUESTER_ID,
        //             "id"                => $new_push_establishment,
        //             "establishmentName" => $establishment[ "name" ],
        //             "lastModified"      => self::$date,
        //             "requestDate"       => self::$date,
        //             "status"            => "aberto",
        //             "action"            => "create",
        //             "origin"            => "awin",
        //         ] );

        //         $this->logger->log_message(
        //             message : "Establishments: Created ID = " . $new_push_establishment,
        //             context : [ "Need approval ID = " . $new_push_establishment ]
        //         );

        //         $establishment[ "push_id" ] = $new_push_establishment;
        //         $establishment[ "data"    ] = json_encode( $aux );

        //         DB::table( "affiliates_establishments" )->insert( $establishment );
        //     }

        //     $this->logger->log_message( message: "Establishments: Added " . count( $local_data ) . " new establishments in the local table" );
        // }
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
        $local_path              = public_path( path: self::LC_BASE_PATH . "/promotions" );
        File::deleteDirectory( directory: $local_path, preserve: true );

        $local_data              = [];

        // Initialize Firebase database references
        $db                      = Firebase::database();
        $establishment_reference = $db->getReference( path: "/Estabelecimento-v2"    );
        $promotions_reference    = $db->getReference( path: "/Store"                 );
        $approval_reference      = $db->getReference( path: "/Store-Aprovacoes"      );
        $changes_reference       = $db->getReference( path: "/Store-changes-request" );

        // Retrieve promotions from Awin.
        $awin_promotions         = $this->integration->getPromotions();
        $this->logger->log_message(
            message : "Promotions: " . count( $awin_promotions ) . " filtered from awin...",
            context : $awin_promotions,
        );

        // Retrieve establishments from Firebase.
        $firebase_establishments = $this->filterEstablishments( reference: $establishment_reference );

        $this->logger->log_message( message: "Promotions: Filtering promotions firebase..." );
        // Retrieve promotions from Firebase.
        $firebase_promotions     = $this->filterPromotions( reference: $promotions_reference );
        $this->logger->log_message(
            message : "Promotions: " . count( $firebase_promotions ) . " promotions filtered from firebase...",
            context : $firebase_promotions,
        );

        // Retrieve promotions from local.
        $local_promotions        = DB::table( "affiliates_promotions" )->where( "origin", "awin" )->get()->keyBy( "origin_id" )->toArray();
        $local_promotions        = json_decode( json_encode( $local_promotions ), true );
        $this->logger->log_message(
            message : "Promotions: " . count( $local_promotions ) . " filtered from local...",
            context : $local_promotions,
        );

        $this->logger->log_message( message: "Promotions: Validating ..." );

        // Disable Firebase promotions not present in Awin promotions.
        foreach ( $firebase_promotions as $push => $firebase_promotion )
        {
            if ( !isset( $awin_promotions[ $firebase_promotion[ "partnerPromotionId" ] ] ) )
            {
                if ( $firebase_promotion[ "isPublished" ] )
                {
                    $this->logger->log_message( message: "Promotions: Disabling ID {$push}" );
                    $firebase_promotion[ "isPublished" ] = false;
                    $promotions_reference->update( values: [ $push => $firebase_promotion ] );

                    $approval_promotion = $db->getReference( path: "/Store-Aprovacoes/{$push}" )->getValue();

                    if ( $approval_promotion && $approval_promotion[ "status" ] == "aberto" )
                    {
                        $approval_promotion[ "status"       ] = "negado";
                        $approval_promotion[ "comment"      ] = "Promoção desativada, aprovação fechada pelo sistema.";
                        $approval_promotion[ "approvalDate" ] = self::$date;

                        $this->logger->log_message( message: "Promotions: Canceling open curatorship of ID {$push}" );
                        $approval_reference->update( values: [ $push => $approval_promotion ] );
                    }
                }
            }
        }

        // Process Awin promotions for addition or update.
        foreach ( $awin_promotions as $id => $awin_promotion )
        {
            if ( !isset( $local_promotions[ $id ] ) )
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

            $local_promotion  = $local_promotions[ $id ];
            $data_promotion   = json_decode( $local_promotion[ "data" ], true );
            $establishment_id = $data_promotion[ "establishmentId" ] ?? "";

            if ( empty( $establishment_id ) )
            {
                $partner_establishment_id = $awin_promotion[ "partnerEstablishmentId" ];
                $index                    = array_search( $partner_establishment_id, array_column( $firebase_establishments, "partnerEstablishmentId" ) );
                $pushes                   = array_keys( $firebase_establishments );
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
            $past_diffs       = invalidate_past_promotion_changes( reference: $changes_reference, push_id: $local_promotion[ "push_id" ] );

            if ( !empty( $past_diffs ) )
                $this->logger->log_message( message: "Promotions: Invalidating past changes ID " . $local_promotion[ "push_id" ] );

            $current_changes  = array_merge( $past_diffs, $diff );

            // Add edit change request in Firebase.
            $new_push_changes = $changes_reference->push()->getKey();
            $changes_reference->update( values: [ $new_push_changes => [
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
                $new_push_promotion       = $promotions_reference->push()->getKey();

                $aux                      = json_decode( $promotion[ "data" ], true );
                $partner_establishment_id = $aux[ "partnerEstablishmentId" ];

                $index                    = array_search_by_key( needle: $partner_establishment_id, column: "partnerEstablishmentId", array: $firebase_establishments );
                $pushes                   = array_keys( $firebase_establishments );

                $aux[ "establishmentId" ] = $pushes[ $index ];
                $aux[ "id"              ] = $new_push_promotion;
                $aux[ "isPublished"     ] = false;

                unset( $aux[ "partnerEstablishmentId" ] );

                $promotions_reference->update( values: [ $new_push_promotion => $aux ] );

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
    private function insertImagesFirebase( array $images, string $local_path, string $firebase_path, string $type )
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


    private function updateIsPublished( $reference, $push_id, $affiliate_id, $awin_data, $fb_data )
    {
        // Update establishment isPublished.
        $fb_data[ "isPublished" ] = true;
        $reference->update( values: [ $push_id => $fb_data ] );

        // Update the local establishment with new isPublished.
        DB::table( "affiliates_establishments" )
            ->where( "affiliate_id", $affiliate_id )
            ->update( [ "data" => json_encode( $awin_data ) ] );

        $this->logger->log_message(
            message : "Establishments: Edited ID = {$push_id}",
            context : [
                "isPublished changed from false to true",
                $fb_data
           ]
        );
    }
}
