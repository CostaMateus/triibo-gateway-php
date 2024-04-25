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
            {
                $local_establishments[ $index ][ "push_id" ] = $push;
            }
            else
            {
                echo "{$search_id} : false : {$push}\n";
            }
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
        // // // Output a message indicating the start of the establishment search.
        // // echo "Establishments : Searching establishments\n";
        // // Log::channel( "awin" )->info( "Establishments", [ "Searching establishments" ] );

        // Delete the existing establishments directory.
        File::deleteDirectory( $this->establishments_path, true );

        $localData           = [];

        // Initialize Firebase database references
        $db                  = Firebase::database();
        $referenceEstab      = $db->getReference( "/Estabelecimento-v2"            );
        $referenceApproval   = $db->getReference( "/Estabelecimento-Aprovacoes"    );
        $referenceChanges    = $db->getReference( "/Establishment-changes-request" );

        // Retrieve establishments from Firebase and local sources.
        $fireEstablishments  = $this->filterEstablishments( $referenceEstab );
        $awinEstablishments  = $this->integration->getEstablishments();
        $localEstablishments = \DB::table( "affiliates_establishments" )->where( "origin", "awin" )->get()->keyBy( "origin_id" )->toArray();
        $localEstablishments = json_decode( json_encode( $localEstablishments ), true );

        // // // Output a message indicating the start of the establishment validation
        // // echo "Establishments : Validating establishments\n";
        // // Log::channel( "awin" )->info( "Establishments", [ "Validating establishments" ] );

        // Disable Firebase establishments not present in Awin establishments.
        foreach ( $fireEstablishments as $push => $fireEstablishment )
        {
            if ( !isset( $awinEstablishments[ $fireEstablishment[ "partnerEstablishmentId" ] ] ) )
            {
                if ( $fireEstablishment[ "isPublished" ] )
                {
                    // Disable the establishment.
                    $fireEstablishment[ "isPublished" ] = false;
                    $referenceEstab->update( [ $push => $fireEstablishment ] );

                    // // // Output a message indicating the disabled establishment.
                    // // echo "Establishments : Disabled establishment ID = {$push}\n";
                    // // Log::channel( "awin" )->info( "Establishments", [ "Disabled establishment ID = {$push}" ] );

                    // Remove the establishment from the list.
                    unset( $fireEstablishments[ $push ] );
                }
            }
        }

        // Process Awin establishments for addition or update.
        foreach ( $awinEstablishments as $id => $awinEstablishment )
        {
            if ( !isset( $localEstablishments[ $id ] ) )
            {
                // Add new establishment to local data array.
                $affiliate_id = $awinEstablishment[ "origin" ] . $id;
                $localData[]  = [
                    "origin"       => $awinEstablishment[ "origin" ],
                    "origin_id"    => $id,
                    "affiliate_id" => $affiliate_id,
                    "push_id"      => null,
                    "name"         => $awinEstablishment[ "nome"   ],
                    "data"         => json_encode( $awinEstablishment ),
                    "created_at"   => now(),
                    "updated_at"   => now(),
                ];

                continue;
            }

            // Compare Awin and local establishments
            $dataEstablishment  = $localEstablishments[ $id ];
            $localEstablishment = json_decode( $dataEstablishment[ "data" ], true );
            // And get differences.
            $diff               = Helper::arrayDiffAssocRecursive( $awinEstablishment, $localEstablishment );

            // Remove ignored fields from the differences.
            foreach ( self::IGNORE_ESTAB_FIELDS as $field )
                unset( $diff[ $field ] );

            // Skip if there are no changes
            if ( empty( $diff ) ) continue;

            $date               = Helper::getDateTimeUnix();

            // Add edit approval request in Firebase.
            $newPushApproval    = $referenceApproval->push()->getKey();
            $referenceApproval->update( [ $newPushApproval => [
                "requesterId"  => self::REQUESTER_ID,
                "id"           => $dataEstablishment[ "push_id" ],
                "lastModified" => $date,
                "requestDate"  => $date,
                "status"       => "aberto",
                "action"       => "edit"
            ] ] );

            // Add edit change request in Firebase.
            $newPushChanges     = $referenceChanges->push()->getKey();
            $referenceChanges->update( [ $newPushChanges => [
                "id"                => $newPushChanges,
                "createDate"        => $date,
                // "updateDate"        => "",
                // "reviewDate"        => "",
                "establishmentId"   => $dataEstablishment[ "push_id" ],
                "establishmentName" => $dataEstablishment[ "name"    ],
                "requesterUid"      => self::REQUESTER_ID,
                // "validatorUid"      => "",
                // "comment"           => "",
                "type"              => "validation",
                "changedFields"     => $diff,
            ] ] );

            // Update the local establishment with the new data.
            \DB::table( "affiliates_establishments" )
                ->where( "affiliate_id", $dataEstablishment[ "affiliate_id" ] )
                ->update( [ "data" => json_encode( $awinEstablishment ) ] );

            // // // Output a message indicating the edited establishment.
            // // echo "Establishments : Edited establishment ID = {$dataEstablishment[ "push_id" ]} | Need approval ID = {$newPushApproval} | Change Request ID = {$newPushChanges}\n";
            // // Log::channel( "awin" )->info( "Establishments", [ "Edited establishment ID = {$dataEstablishment[ "push_id" ]} | Need approval ID = {$newPushApproval} | Change Request ID = {$newPushChanges}" ] );
        }

        // Insert new establishments into the local database if any.
        if ( !empty( $localData ) )
        {
            \DB::table( "affiliates_establishments" )->insert( $localData );

            // // // Output a message indicating the added establishments.
            // // echo "Establishments : Added " . count( $localData ) . " new establishments in the local table\n";
            // // Log::channel( "awin" )->info( "Establishments", [ "Added " . count( $localData ) . " new establishments in the local table" ] );

            // Add new establishments to Firebase and update local database with push IDs.
            foreach ( $localData as $data )
            {
                $aux                  = $awinEstablishments[ $data[ "origin_id" ] ];
                $aux[ "isPublished" ] = false;

                $newPushEstab         = $referenceEstab->push()->getKey();
                $referenceEstab->update( [ $newPushEstab => $aux ] );

                $date                 = Helper::getDateTimeUnix();
                $approval             = [
                    "requesterId"  => self::REQUESTER_ID,
                    "id"           => $newPushEstab,
                    "lastModified" => $date,
                    "requestDate"  => $date,
                    "status"       => "aberto",
                    "action"       => "create"
                ];

                $newPushApproval      = $referenceApproval->push()->getKey();
                $referenceApproval->update( [ $newPushApproval => $approval ] );

                // // // Output a message indicating the creation of a new establishment.
                // // echo "Establishments : Created establishment ID = {$newPushEstab} | Need approval ID = {$newPushApproval}\n";
                // // Log::channel( "awin" )->info( "Establishments", [ "Created establishment ID = {$newPushEstab} | Need approval ID = {$newPushApproval}" ] );

                $affiliate_id         = $aux[ "origin" ] . $aux[ "partnerEstablishmentId" ];
                \DB::table( "affiliates_establishments" )->where( "affiliate_id", $affiliate_id )->update( [ "push_id" => $newPushEstab ] );

                // // // Remove the establishment from the Awin list.
                // // unset( $awinEstablishments[ $data[ "origin_id" ] ] );
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
        echo "Promotions : Searching promotions\n";
        // Log::channel( "awin" )->info( "Promotions", [ "Searching promotions" ] );

        File::deleteDirectory( $this->promotions_path, true );

        $localData         = [];
        $db                = Firebase::database();
        $referencePromo    = $db->getReference( "/Store"                 );
        $referenceApproval = $db->getReference( "/Store-Aprovacoes"      );
        $referenceChanges  = $db->getReference( "/Store-changes-request" );

        $firePromotions    = $this->filterPromotions( $referencePromo );
        $awinPromotions    = $this->integration->getPromotions();
        $localPromotions   = \DB::table( "affiliates_promotions" )->where( "origin", "awin" )->get()->keyBy( "origin_id" )->toArray();
        $localPromotions   = json_decode( json_encode( $localPromotions ), true );

        echo "Promotions : Validating promotions\n";
        // Log::channel( "awin" )->info( "Promotions", [ "Validating promotions" ] );

        /**
         * Checks if Firebase promotions are on Awin promotions
         * If not, disable the promotions on Firebase
         */
        foreach ( $firePromotions as $push => $firePromotion )
        {
            if ( !isset( $awinPromotions[ $firePromotion[ "partnerPromotionId" ] ] ) )
            {
                if ( $firePromotion[ "isPublished" ] )
                {
                    /**
                     * Disabling promotions that are not on the list
                     */
                    $firePromotion[ "isPublished" ] = false;
                    $referencePromo->update( [ $push => $firePromotion ] );

                    echo "Promotions : Disabled promotion ID = {$push}\n";
                    // Log::channel( "awin" )->info( "Promotions", [ "Disabled promotion ID = {$push}" ] );

                    unset( $firePromotions[ $push ] );
                }
            }
        }

        /**
         * Check if Awin promotions are on Local promotions
         */
        foreach ( $awinPromotions as $id => $awinPromotion )
        {
            /**
             * If not, add to temp variable, $localData
             */
            if ( !isset( $localPromotions[ $id ] ) )
            {
                $affiliate_id = $awinPromotion[ "origin" ] . $id;
                $localData[]  = [
                    "origin"       => $awinPromotion[ "origin" ],
                    "origin_id"    => $id,
                    "affiliate_id" => $affiliate_id,
                    "push_id"      => null,
                    "data"         => json_encode( $awinPromotion ),
                    "created_at"   => now(),
                    "updated_at"   => now(),
                ];

                continue;
            }

            /**
             * Compare Awin promotions with Local promotions
             */
            $dataPromotion  = $localPromotions[ $id ];
            $localPromotion = json_decode( $dataPromotion[ "data" ], true );

            /**
             * Get diff between Awin and Local establishments
             */
            $diff           = Helper::arrayDiffAssocRecursive( $awinPromotion, $localPromotion );

            /**
             * Removing ignored fields
             */
            foreach ( self::IGNORE_PROMO_FIELDS as $field )
                unset( $diff[ $field ] );

            /**
             * No changes founded
             */
            if ( empty( $diff ) ) continue;

            /**
             * Adding edit approval in Firebase
             */
            $date               = Helper::getDateTimeUnix();
            $approval           = [
                "requesterId"  => self::REQUESTER_ID,
                "id"           => $dataPromotion[ "push_id" ],
                "lastModified" => $date,
                "requestDate"  => $date,
                "status"       => "aberto",
                "action"       => "edit",
                "type"         => "promotion",
            ];
            $newPushApproval    = $referenceApproval->push()->getKey();
            $referenceApproval->update( [ $newPushApproval => $approval ] );

            /**
             * Adding edit changes-request in Firebase
             */
            $newPushChanges     = $referenceChanges->push()->getKey();
            $changesRequest     = [
                "id"                => $newPushChanges,
                "createDate"        => $date,
                // "updateDate"        => "",
                // "reviewDate"        => "",
                "storeId"           => $dataPromotion[ "push_id" ],
                "establishmentId"   => $localPromotion[ "establishmentId"   ],
                "establishmentName" => $localPromotion[ "establishmentName" ],
                "requesterUid"      => self::REQUESTER_ID,
                // "validatorUid"      => "",
                // "comment"           => "",
                "type"              => "validation",
                "changedFields"     => $diff,
            ];
            $referenceChanges->update( [ $newPushChanges => $changesRequest ] );

            /**
             * Update promotion in Local promotions
             */
            \DB::table( "affiliates_promotions" )
                ->where( "affiliate_id", $dataPromotion[ "affiliate_id" ] )
                ->update( [ "data" => json_encode( $awinPromotion ) ] );

            echo "Promotions : Edited promotion ID = {$dataPromotion[ "push_id" ]} | Need approval ID = {$newPushApproval} | Change Request ID = {$newPushChanges}\n";
            // Log::channel( "awin" )->info( "Promotions", [ "Edited promotion ID = {$dataPromotion[ "push_id" ]} | Need approval ID = {$newPushApproval} | Change Request ID = {$newPushChanges}" ] );
        }

        /**
         * If $localData is not empty
         */
        if ( !empty( $localData ) )
        {
            /**
             * Add the new promotions ($localData) in the Local promotions
             */
            \DB::table( "affiliates_promotions" )->insert( $localData );
            echo "Promotions : Added " . count( $localData ) . " new promotions in the local table\n";
            // Log::channel( "awin" )->info( "Promotions", [ "Added " . count( $localData ) . " new promotions in the local table" ] );

            /**
             * Add the new promotions on Firebase
             * And update push_id column in Local promotions
             */
            foreach ( $localData as $data )
            {
                $aux                  = $awinPromotions[ $data[ "origin_id" ] ];
                $aux[ "isPublished" ] = false;

                $newPushPromo         = $referencePromo->push()->getKey();
                $referencePromo->update( [ $newPushPromo => $aux ] );

                $date                 = Helper::getDateTimeUnix();
                $approval             = [
                    "requesterId"  => self::REQUESTER_ID,
                    "id"           => $newPushPromo,
                    "lastModified" => $date,
                    "requestDate"  => $date,
                    "status"       => "aberto",
                    "action"       => "create",
                    "type"         => "promotion",
                ];

                $newPushApproval      = $referenceApproval->push()->getKey();
                $referenceApproval->update( [ $newPushApproval => $approval ] );

                echo "Promotions : Created promotion ID = {$newPushPromo} | Need approval ID = {$newPushApproval}\n";
                // Log::channel( "awin" )->info( "Promotions", [ "Created promotion ID = {$newPushPromo} | Need approval ID = {$newPushApproval}" ] );

                $affiliate_id         = $aux[ "origin" ] . $aux[ "partnerPromotionId" ];
                \DB::table( "affiliates_promotions" )->where( "affiliate_id", $affiliate_id )->update( [ "push_id" => $newPushPromo ] );

                unset( $awinPromotions[ $data[ "origin_id" ] ] );
            }
        }
    }

    /**
     * Send and update reports to Firebase
     *
     * @return  void
     */
    private function insertReportsFirebase()
    {
        echo "Reports : Searching for new transactions\n";
        Log::channel( "awin" )->info( "Reports", [ "Searching for new transactions" ] );

        // instance of purchases-awin
        $fbAwin  = Firebase::database()->getReference( "/Triibo-Purchases-Awin"     );

        // instance of purchases-cashback
        $fbCash  = Firebase::database()->getReference( "/Triibo-Purchases-Cashback" );

        // new transactions awin
        $reports = $this->integration->getReports();

        if ( $reports[ "success" ] && !empty( $reports[ "data" ] ) )
        {
            echo "Reports : Sending new transactions to Firebase\n";
            Log::channel( "awin" )->info( "Reports", [ "Sending new transactions to Firebase" ] );

            foreach ( $reports[ "data" ] as $report )
            {
                $report   = Helper::setEmptyString( $report );

                // create in purchases-awin
                $pushAwin = $fbAwin->push()->getKey();
                $fbAwin->update( [ $pushAwin => $report ] );

                // create in purchases-cashback
                $pushCash = $fbCash->push()->getKey();
                $fbCash->update( [ $pushCash => $report[ "metaData" ] ] );

                echo "Reports : New transaction ID = {$pushAwin} | {$pushCash}\n";
            }
        }
        else
        {
            echo "Reports : No new transactions\n";
            Log::channel( "awin" )->info( "Reports", [ "No new transactions" ] );
        }

        echo "Reports : Searching for updated transactions\n";
        Log::channel( "awin" )->info( "Reports", [ "Searching for updated transactions" ] );

        // update transaction
        $updReports = $this->integration->getReports( true );

        if ( $updReports[ "success" ] && !empty( $updReports[ "data" ] ) )
        {
            // reports purchases-awin
            $fbReportsAwin = $fbAwin->getValue();

            // reports purchases-cashback
            $fbReportsCash = $fbCash->getValue();

            echo "Reports : Sending updated transactions to Firebase\n";
            Log::channel( "awin" )->info( "Reports", [ "Sending updated transactions to Firebase" ] );

            // purchases-awin
            foreach ( $fbReportsAwin as $pushAwin => $fbReportAwin )
            {
                foreach ( $updReports[ "data" ] as $uReport )
                {
                    if ( !isset( $fbReportAwin[ "id" ] ) || !isset( $fbReportAwin[ "advertiserId" ] ) ) continue;

                    $uReport    = Helper::setEmptyString( $uReport );

                    $id         = $fbReportAwin[ "id"           ] == $uReport[ "id"           ];
                    $advertiser = $fbReportAwin[ "advertiserId" ] == $uReport[ "advertiserId" ];

                    if ( !$id || !$advertiser ) continue;

                    foreach ( $fbReportAwin as $key => $value )
                        if ( !isset( $uReport[ $key ] ) )
                            $uReport[ $key ] = $value;

                    $fbReportAwin = $uReport;
                    break;
                }

                $fbAwin->update( [ $pushAwin => $fbReportAwin ] );

                echo "Reports : Updated Awin transaction ID = {$pushAwin}\n";
            }

            // purchases-cashback
            foreach ( $fbReportsCash as $pushCash => $fbReportCash )
            {
                foreach ( $updReports[ "data" ] as $uReport )
                {
                    $transaction = $fbReportCash[ "transactionId" ] == $uReport[ "metaData" ][ "transactionId" ];
                    $advertiser  = $fbReportCash[ "advertiserId"  ] == $uReport[ "metaData" ][ "advertiserId"  ];
                    $uid         = isset( $fbReportCash[ "uid" ] ) && ( $fbReportCash[ "uid"  ] == $uReport[ "metaData" ][ "uid" ] );

                    if ( $transaction && $advertiser && $uid )
                    {
                        foreach ( $fbReportCash as $key => $value )
                            if ( in_array( $key, [ "status", "updateDate" ] ) )
                                $fbReportCash[ $key ] = $uReport[ "metaData"][ $key ];

                        break;
                    }
                }

                $fbCash->update( [ $pushCash => $fbReportCash ] );

                echo "Reports : Updated Cash transaction ID = {$pushCash}\n";
            }
        }
        else
        {
            echo "Reports : No transactions for update\n";
            Log::channel( "awin" )->info( "Reports", [ "No transactions for update" ] );
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
            // Log the information that image generation is starting for the given type.
            log_message( channel: self::LOG_CHANNEL, message: "Images", context: [ "No images to upload - {$type}..." ], print: true );
            return;
        }

        // Log the information that the system is capturing file names for the given type.
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

        // Log the start of the upload process.
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
                // Log the image ID being uploaded.
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

        // Log the completion of the upload process.
        log_message( channel: self::LOG_CHANNEL, message: "Images", context: [ "Sending finished - {$type}..." ], print: true );

        File::deleteDirectory( $path, true );
    }
}
