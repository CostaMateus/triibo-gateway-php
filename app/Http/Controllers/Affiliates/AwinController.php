<?php

namespace App\Http\Controllers\Affiliates;

use DateTime;
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
    protected $integration;
    protected $establishments_path;
    protected $promotions_path;

    // Sistema Triibo +5510999991477
    const REQUESTER_ID                 = "J4NjCHtvxuRojrXb29whbsNPAy33";

    const IGNORE_ESTABLISHMENTS_FIELDS = [
        "dataAlteracao",
        "dataInclusao",
        "disableDate",
        "enableDate",
        "listaFotos",
        "fotoThumb",
    ];

    const IGNORE_PROMOTIONS_FIELDS     = [];

    /**
     * Constructor
     *
     * @param   boolean $loadEstablishments     Load establishments from Awin, default true
     * @return  void
     */
    public function __construct( bool $loadEstablishments = true )
    {
        $this->integration         = new AwinIntegration( $loadEstablishments );
        $this->establishments_path = public_path( "images/affiliates/awin/establishments/" );
        $this->promotions_path     = public_path( "images/affiliates/awin/promotions/"     );

        File::ensureDirectoryExists( $this->establishments_path );
        File::ensureDirectoryExists( $this->promotions_path     );
    }

    /**
     * TODO remover após usar em prod
     */
    public function populate()
    {
        $awin_estabs     = $this->integration->getEstablishments();
        $firebase_estabs = $this->filterEstablishments( Firebase::database()->getReference( "/Estabelecimento-v2" ) );
        $local_estabs    = [];

        foreach ( $awin_estabs as $origin_id => $store )
        {
            $affiliate_id   = $store[ "origin" ] . $origin_id;
            $local_estabs[] = [
                "origin"       => $store[ "origin" ],
                "origin_id"    => $origin_id,
                "affiliate_id" => $affiliate_id,
                "push_id"      => null,
                "name"         => $store[ "nome"   ],
                "data"         => json_encode( $store ),
                "created_at"   => now(),
                "updated_at"   => now(),
            ];
        }

        foreach ( $firebase_estabs as $push => $store )
        {
            $search_id = $store[ "origin" ] . $store[ "partnerEstablishmentId" ];
            $index     = Helper::arraySearchByKey( $search_id, "affiliate_id", $local_estabs );

            if ( $index !== false )
            {
                $local_estabs[ $index ][ "push_id" ] = $push;
            }
            else
            {
                echo "{$search_id} : false : {$push}\n";
            }
        }

        \DB::table( "affiliates_establishments" )->truncate();
        \DB::table( "affiliates_establishments" )->insert( $local_estabs );

        \Storage::put( "establishments_awin.json",     json_encode( $awin_estabs     ) );
        \Storage::put( "establishments_firebase.json", json_encode( $firebase_estabs ) );
        \Storage::put( "establishments_local.json",    json_encode( $local_estabs    ) );

        $this->populatePromos();

        dd(
            "estabs local : " . count( $local_estabs    ),
            "estabs awin  : " . count( $awin_estabs     ),
            "estabs fire  : " . count( $firebase_estabs )
        );
    }

    /**
     * TODO remover após usar em prod
     */
    public function populatePromos()
    {
        $awin_promos     = $this->integration->getPromotions();
        $firebase_promos = $this->filterPromotions( Firebase::database()->getReference( "/Store" ) );
        $local_promos    = [];

        foreach ( $awin_promos as $origin_id => $promo )
        {
            $affiliate_id   = $promo[ "origin" ] . $origin_id;
            $local_promos[] = [
                "origin"       => $promo[ "origin" ],
                "origin_id"    => $origin_id,
                "affiliate_id" => $affiliate_id,
                "push_id"      => null,
                "data"         => json_encode( $promo ),
                "created_at"   => now(),
                "updated_at"   => now(),
            ];
        }

        foreach ( $firebase_promos as $push => $promo )
        {
            $search_id = $promo[ "origin" ] . $promo[ "partnerPromotionId" ];
            $index     = Helper::arraySearchByKey( $search_id, "affiliate_id", $local_promos );

            if ( $index !== false )
            {
                $local_promos[ $index ][ "push_id" ] = $push;
            }
            else
            {
                echo "{$search_id} : false : {$push}\n";
            }
        }

        \DB::table( "affiliates_promotions" )->truncate();
        \DB::table( "affiliates_promotions" )->insert( $local_promos );

        \Storage::put( "promotions_awin.json",     json_encode( $awin_promos     ) );
        \Storage::put( "promotions_firebase.json", json_encode( $firebase_promos ) );
        \Storage::put( "promotions_local.json",    json_encode( $local_promos    ) );

        dump(
            "promos local : " . count( $local_promos    ),
            "promos awin  : " . count( $awin_promos     ),
            "promos fire  : " . count( $firebase_promos )
        );
    }

    /**
     * Process establishments
     *
     * @return  void
     */
    public function establishments()
    {
        try
        {
            $this->insertEstablishmentsFirebase();
            echo "Establishments : Establishments sent successfully\n";
            Log::channel( "awin" )->info( "Establishments", [
                "code"    => 0,
                "info"    => "Establishments sent successfully",
                "error"   => NULL,
                "message" => NULL
            ] );
        }
        catch ( FirebaseException $e )
        {
            echo "Establishments : Establishment, Firebase process error - aborted\n";
            Log::channel( "awin" )->critical( "Establishments", [
                "code"    => "C-401",
                "info"    => "Establishment, Firebase process error - aborted",
                "error"   => "An error has occurred while working with the SDK",
                "message" => $e
            ] );

            Helper::sendMailNotification( "awin", "insertEstablishmentsFirebase()", "FirebaseException", $e );
        }
        catch ( Throwable $e )
        {
            echo "Establishments : Unexpected error - aborted\n";
            Log::channel( "awin" )->critical( "Establishments", [
                "code"    => "C-501",
                "info"    => "Unexpected error - aborted",
                "error"   => "A not-Firebase specific error has occurred",
                "message" => $e,
            ] );

            Helper::sendMailNotification( "awin", "insertEstablishmentsFirebase()", "Throwable", $e );
        }
    }

    /**
     * Process promotions
     *
     * @return  void
     */
    public function promotions()
    {
        try
        {
            $this->insertPromotionsFirebase();
            echo "Promotions : Promotions sent successfully\n";
            Log::channel( "awin" )->info( "Promotions", [
                "code"    => 0,
                "info"    => "Promotions sent successfully",
                "error"   => NULL,
                "message" => NULL
            ] );
        }
        catch ( FirebaseException $e )
        {
            echo "Promotions : Promotion, Firebase process error - aborted\n";
            Log::channel( "awin" )->critical( "Promotions", [
                "code"    => "C-401",
                "info"    => "Promotion, Firebase process error - aborted",
                "error"   => "An error has occurred while working with the SDK",
                "message" => $e
            ] );

            Helper::sendMailNotification( "awin", "insertPromotionsFirebase()", "FirebaseException", $e );
        }
        catch ( Throwable $e )
        {
            echo "Promotions : Unexpected error - aborted\n";
            Log::channel( "awin" )->critical( "Promotions", [
                "code"    => "C-501",
                "info"    => "Unexpected error - aborted",
                "error"   => "A not-Firebase specific error has occurred",
                "message" => $e,
            ] );

            Helper::sendMailNotification( "awin", "insertPromotionsFirebase()", "Throwable", $e );
        }
    }

    /**
     * Process images
     *
     * @return  void
     */
    public function images()
    {
        $establishments         = public_path( "images/affiliates/awin/standard.png"   );
        $coupon                 = public_path( "images/affiliates/awin/coupon.webp"    );
        $promotion              = public_path( "images/affiliates/awin/promotion.webp" );

        File::copy( $establishments, $this->establishments_path . "/awin_standard.png"          );
        File::copy( $coupon,         $this->promotions_path     . "/awin_common_coupon.webp"    );
        File::copy( $promotion,      $this->promotions_path     . "/awin_common_promotion.webp" );

        $filesEstablishments    = File::allFiles( $this->establishments_path );
        $filesPromos            = File::allFiles( $this->promotions_path     );

        $fileNameEstablishments = [];
        $fileNamePromos         = [];

        if ( empty( $filesEstablishments ) && empty( $filesPromos ) )
        {
            echo "Images : No images to upload\n";
            Log::channel( "awin" )->info( "Images", [ "No images to upload" ] );
            return;
        }

        if ( $filesEstablishments )
        {
            echo "Images : Capturing names - establishments\n";
            Log::channel( "awin" )->info( "Images", [ "Capturing names - establishments" ] );
            $fileNameEstablishments = $this->getFileNames( $filesEstablishments );
        }

        if ( $filesPromos )
        {
            echo "Images : Capturing names - promotions";
            Log::channel( "awin" )->info( "Images", [ "Capturing names - promotions"     ] );
            $fileNamePromos = $this->getFileNames( $filesPromos );
        }

        $this->insertImagesFirebase( [
            "establishments" => [
                "files" => $fileNameEstablishments,
                "lc"    => $this->establishments_path,
                "fb"    => "estabelecimento/",
            ],
            "promotions"     => [
                "files" => $fileNamePromos,
                "lc"    => $this->promotions_path,
                "fb"    => "promocao/"
            ]
        ] );

        if ( $filesEstablishments ) File::deleteDirectory( $this->establishments_path, true );
        if ( $filesPromos         ) File::deleteDirectory( $this->promotions_path,     true );
    }

    /**
     * Process reports
     *
     * @return  void
     */
    public function reports()
    {
        try
        {
            $this->insertReportsFirebase();
            echo "Reports : Reports sent successfully\n";
            Log::channel( "awin" )->info( "Reports", [
                "code"    => 0,
                "info"    => "Reports sent successfully",
                "error"   => NULL,
                "message" => NULL
            ] );
        }
        catch ( FirebaseException $e )
        {
            echo "Reports : Reports, Firebase process error - aborted\n";
            Log::channel( "awin" )->critical( "Reports", [
                "code"    => "C-401",
                "info"    => "Reports, Firebase process error - aborted",
                "error"   => "An error has occurred while working with the SDK",
                "message" => $e
            ] );

            Helper::sendMailNotification( "awin", "insertReportsFirebase()", "FirebaseException", $e );
        }
        catch ( Throwable $e )
        {
            echo "Reports : Unexpected error - aborted\n";
            Log::channel( "awin" )->critical( "Reports", [
                "code"    => "C-501",
                "info"    => "Unexpected error - aborted",
                "error"   => "A not-Firebase specific error has occurred",
                "message" => $e,
            ] );

            Helper::sendMailNotification( "awin", "insertReportsFirebase()", "Throwable", $e );
        }
    }





    /**
     * Update and create establishments on Firebase
     *
     * @return  void
     */
    private function insertEstablishmentsFirebase()
    {
        echo "Establishments : Searching establishments\n";
        // Log::channel( "awin" )->info( "Establishments", [ "Searching establishments" ] );

        File::deleteDirectory( $this->establishments_path, true );

        $localData           = [];
        $db                  = Firebase::database();
        $referenceEstab      = $db->getReference( "/Estabelecimento-v2"            );
        $referenceApproval   = $db->getReference( "/Estabelecimento-Aprovacoes"    );
        $referenceChanges    = $db->getReference( "/Establishment-changes-request" );

        $fireEstablishments  = $this->filterEstablishments( $referenceEstab );
        $awinEstablishments  = $this->integration->getEstablishments();
        $localEstablishments = \DB::table( "affiliates_establishments" )->where( "origin", "awin" )->get()->keyBy( "origin_id" )->toArray();
        $localEstablishments = json_decode( json_encode( $localEstablishments ), true );

        echo "Establishments : Validating establishments\n";
        // Log::channel( "awin" )->info( "Establishments", [ "Validating establishments" ] );

        /**
         * Checks if Firebase establishments are on Awin establishments
         * If not, disable the establishments on Firebase
         */
        foreach ( $fireEstablishments as $push => $fireEstablishment )
        {
            if ( !isset( $awinEstablishments[ $fireEstablishment[ "partnerEstablishmentId" ] ] ) )
            {
                if ( $fireEstablishment[ "isPublished" ] )
                {
                    /**
                     * Disabling establishments that are not on the list
                     */
                    $fireEstablishment[ "isPublished" ] = false;
                    $referenceEstab->update( [ $push => $fireEstablishment ] );

                    echo "Establishments : Disabled establishment ID = {$push}\n";
                    // Log::channel( "awin" )->info( "Establishments", [ "Disabled establishment ID = {$push}" ] );

                    unset( $fireEstablishments[ $push ] );
                }
            }
        }

        /**
         * Check if Awin establishments are on Local establishments
         */
        foreach ( $awinEstablishments as $id => $awinEstablishment )
        {
            /**
             * If not, add to temp variable, $localData
             */
            if ( !isset( $localEstablishments[ $id ] ) )
            {
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

            /**
             * Compare Awin establishments with Local establishments
             */
            $dataEstablishment  = $localEstablishments[ $id ];
            $localEstablishment = json_decode( $dataEstablishment[ "data" ], true );

            /**
             * Get diff between Awin and Local establishments
             */
            $diff               = Helper::arrayDiffAssocRecursive( $awinEstablishment, $localEstablishment );

            /**
             * Removing ignored fields
             */
            foreach ( self::IGNORE_ESTABLISHMENTS_FIELDS as $field )
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
                "id"           => $dataEstablishment[ "push_id" ],
                "lastModified" => $date,
                "requestDate"  => $date,
                "status"       => "aberto",
                "action"       => "edit"
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
                "establishmentId"   => $dataEstablishment[ "push_id" ],
                "establishmentName" => $dataEstablishment[ "name"    ],
                "requesterUid"      => self::REQUESTER_ID,
                // "validatorUid"      => "",
                // "comment"           => "",
                "type"              => "validation",
                "changedFields"     => $diff,
            ];
            $referenceChanges->update( [ $newPushChanges => $changesRequest ] );

            /**
             * Update establishment in Local establishments
             */
            \DB::table( "affiliates_establishments" )
                ->where( "affiliate_id", $dataEstablishment[ "affiliate_id" ] )
                ->update( [ "data" => json_encode( $awinEstablishment ) ] );

            echo "Establishments : Edited establishment ID = {$dataEstablishment[ "push_id" ]} | Need approval ID = {$newPushApproval} | Change Request ID = {$newPushChanges}\n";
            // Log::channel( "awin" )->info( "Establishments", [ "Edited establishment ID = {$dataEstablishment[ "push_id" ]} | Need approval ID = {$newPushApproval} | Change Request ID = {$newPushChanges}" ] );
        }

        /**
         * If $localData is not empty
         */
        if ( !empty( $localData ) )
        {
            /**
             * Add the new establishments ($localData) in the Local establishments
             */
            \DB::table( "affiliates_establishments" )->insert( $localData );
            echo "Establishments : Added " . count( $localData ) . " new establishments in the local table\n";
            // Log::channel( "awin" )->info( "Establishments", [ "Added " . count( $localData ) . " new establishments in the local table" ] );

            /**
             * Add the new establishments on Firebase
             * And update push_id column in Local establishments
             */
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

                echo "Establishments : Created establishment ID = {$newPushEstab} | Need approval ID = {$newPushApproval}\n";
                // Log::channel( "awin" )->info( "Establishments", [ "Created establishment ID = {$newPushEstab} | Need approval ID = {$newPushApproval}" ] );

                $affiliate_id         = $aux[ "origin" ] . $aux[ "partnerEstablishmentId" ];
                \DB::table( "affiliates_establishments" )->where( "affiliate_id", $affiliate_id )->update( [ "push_id" => $newPushEstab ] );

                unset( $awinEstablishments[ $data[ "origin_id" ] ] );
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
            foreach ( self::IGNORE_PROMOTIONS_FIELDS as $field )
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
     * Send images to Firebase Storage
     *
     * @param   array $data
     * @return  void
     */
    private function insertImagesFirebase( array $data )
    {
        $st     = Firebase::storage();
        $bucket = $st->getBucket();

        foreach ( $data as $key => $images )
        {
            $files   = $images[ "files" ];

            if ( empty( $files ) ) continue;

            echo "Images : Start sending images to Firebase - {$key}\n";
            Log::channel( "awin" )->info("Images", [ "Start sending images to Firebase - {$key}" ]);

            $lc_path = $images[ "lc"    ];
            $fb_path = $images[ "fb"    ];

            foreach ( $files as $img )
            {
                $uploader = $bucket->getResumableUploader(
                    fopen( "{$lc_path}/{$img}", "r" ),
                    [ "name" => $fb_path . $img ]
                );

                try
                {
                    echo "Images : Image ID = {$img}\n";
                    $uploader->upload();
                }
                catch ( FirebaseException $e )
                {
                    echo "Images : Image, Firebase process error - aborted\n";
                    Log::channel( "awin" )->critical( "Images", [
                        "code"    => "C-403",
                        "info"    => $img,
                        "error"   => "An error has occurred while working with the SDK",
                        "message" => $e,
                    ] );

                    Helper::sendMailNotification( "awin", "insertImagesFirebase()", "FirebaseException", $e );
                }
                catch (Throwable $e)
                {
                    echo "Images : Unexpected error - aborted\n";
                    Log::channel( "awin" )->critical( "Images", [
                        "code"    => "C-503",
                        "info"    => $img,
                        "error"   => "A not-Firebase specific error has occurred",
                        "message" => $e,
                    ] );

                    Helper::sendMailNotification( "awin", "insertImagesFirebase()", "Throwable", $e );
                }
            }

            echo "Images : End uploading images to Firebase - {$key}\n";
            Log::channel( "awin" )->info( "Images", [ "End uploading images to Firebase - {$key}" ] );
        }

        File::deleteDirectory( $this->establishments_path, true );
        File::deleteDirectory( $this->promotions_path,     true );
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
     * Get the file names from list of files
     *
     * @param   array   $files
     * @return  array   $fileNames
     */
    private function getFileNames( array $files )
    {
        $fileNames = [];

        foreach( $files as $file ) array_push( $fileNames, pathinfo( $file )[ "basename" ] );

        return $fileNames;
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
}
