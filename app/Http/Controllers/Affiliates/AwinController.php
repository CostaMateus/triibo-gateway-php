<?php

namespace App\Http\Controllers\Affiliates;

use DateTime;
use Throwable;
use App\Helpers\Helper;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Kreait\Firebase\Contract\Database;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Exception\FirebaseException;
use App\Http\Integrations\AffiliatesIntegration\AwinIntegration;

class AwinController extends Controller
{
    protected $integration;
    protected $stores_path;
    protected $promos_path;
    // protected $firestore = null;

    public function __construct( bool $loadStores = true )
    {
        $this->integration = new AwinIntegration( $loadStores );
        $this->stores_path = public_path( "images/affiliates/awin/establishments/" );
        $this->promos_path = public_path( "images/affiliates/awin/promotions/"     );
    }

    /**
     * Process stores
     *
     * @return void
     */
    public function stores()
    {
        try
        {
            $this->insertStoresFirebase();
            echo "Stores : Establishments sent successfully\n";
            Log::channel( "awin" )->info( "Stores", [
                "code"    => 0,
                "info"    => "Establishments sent successfully",
                "error"   => NULL,
                "message" => NULL
            ] );
        }
        catch ( FirebaseException $e )
        {
            echo "Stores : Establishment, Firebase process error - aborted\n";
            Log::channel( "awin" )->critical( "Stores", [
                "code"    => "C-401",
                "info"    => "Establishment, Firebase process error - aborted",
                "error"   => "An error has occurred while working with the SDK",
                "message" => $e
            ] );

            Helper::sendMailNotification( "awin", "insertStoresFirebase()", "FirebaseException", $e );
        }
        catch ( Throwable $e )
        {
            echo "Stores : Unexpected error - aborted\n";
            Log::channel( "awin" )->critical( "Stores", [
                "code"    => "C-501",
                "info"    => "Unexpected error - aborted",
                "error"   => "A not-Firebase specific error has occurred",
                "message" => $e,
            ] );

            Helper::sendMailNotification( "awin", "insertStoresFirebase()", "Throwable", $e );
        }
    }

    /**
     * Process promotions
     *
     * @return void
     */
    public function promotions()
    {
        try
        {
            $this->insertOffersFirebase();
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

            Helper::sendMailNotification( "awin", "insertOffersFirebase()", "FirebaseException", $e );
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

            Helper::sendMailNotification( "awin", "insertOffersFirebase()", "Throwable", $e );
        }
    }

    /**
     * Process images
     *
     * @return void
     */
    public function images()
    {
        $store          = public_path( "images/affiliates/awin/standard.png"   );
        $coupon         = public_path( "images/affiliates/awin/coupon.webp"    );
        $promotion      = public_path( "images/affiliates/awin/promotion.webp" );

        File::copy( $store,     $this->stores_path . "/awin_standard.png"          );
        File::copy( $coupon,    $this->promos_path . "/awin_common_coupon.webp"    );
        File::copy( $promotion, $this->promos_path . "/awin_common_promotion.webp" );

        $filesStores    = File::allFiles( $this->stores_path );
        $filesPromos    = File::allFiles( $this->promos_path );

        $fileNameStores = [];
        $fileNamePromos = [];

        if ( empty( $filesStores ) && empty( $filesPromos ) )
        {
            echo "Images : No images to upload\n";
            Log::channel( "awin" )->info( "Images", [ "No images to upload" ] );
            return;
        }

        if ( $filesStores )
        {
            echo "Images : Capturing names - establishments\n";
            Log::channel( "awin" )->info( "Images", [ "Capturing names - establishments" ] );
            $fileNameStores = $this->getFileNames( $filesStores );
        }

        if ( $filesPromos )
        {
            echo "Images : Capturing names - promotions";
            Log::channel( "awin" )->info( "Images", [ "Capturing names - promotions"     ] );
            $fileNamePromos = $this->getFileNames( $filesPromos );
        }

        $this->insertImagesFirebase( [
            "stores"     => [
                "files" => $fileNameStores,
                "lc"    => $this->stores_path,
                "fb"    => "estabelecimento/",
            ],
            "promotions" => [
                "files" => $fileNamePromos,
                "lc"    => $this->promos_path,
                "fb"    => "promocao/"
            ]
        ] );

        if ( $filesStores ) File::deleteDirectory( $this->stores_path, true );
        if ( $filesPromos ) File::deleteDirectory( $this->promos_path, true );
    }

    /**
     * Process reports
     *
     * @return void
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
     * Update and create store on Firebase
     *
     * @return void
     */
    private function insertStoresFirebase()
    {
        echo "Stores : Searching establishments\n";
        Log::channel( "awin" )->info( "Stores", [ "Searching establishments" ] );

        File::deleteDirectory( $this->stores_path, true );

        $awinStores  = $this->integration->getStores();

        $fbRef       = Firebase::database()->getReference( "/Estabelecimento-v2" );
        $fbStores    = $fbRef->getValue();
        $fbAuxStores = [];

        foreach ( $fbStores as $push => $fbStore )
        {
            if ( strpos( $push, "-cofry"      ) !== false       ) continue;
            if ( strpos( $push, "-socialsoul" ) !== false       ) continue;
            if ( !isset( $fbStore[ "partnerEstablishmentId" ] ) ) continue;

            if ( strpos( $push, "-awin" ) !== false )
            {
                if ( $fbStore[ "isPublished" ] )
                {
                    // Disabling all old stores
                    echo "Stores : Disab. Estab. ID = {$push}\n";
                    $fbStore[ "isPublished" ] = false;
                    $fbStore[ "origin"      ] = "awin";
                    $fbRef->update( [ $push => $fbStore ] );
                }

                continue;
            }

            if ( isset( $fbStore[ "origin" ] ) && $fbStore[ "origin" ] == "awin" )
            {
                if ( !isset( $awinStores[ $fbStore[ "partnerEstablishmentId" ] ] ) )
                {
                    // Disabling stores that are not on the list
                    $fbStore[ "isPublished" ] = false;
                    $fbRef->update( [ $push => $fbStore ] );

                    echo "Stores : Disab. Estab. ID = {$push}\n";
                    continue;
                }

                $fbAuxStores[ $push ] = $fbStore;
            }
        }

        echo "Stores : Sending establishments to Firebase\n";
        Log::channel( "awin" )->info( "Stores", [ "Sending establishments to Firebase" ] );

        foreach ( $awinStores as $id => $store )
        {
            $founded = false;

            foreach ( $fbAuxStores as $push1 => $fbStore )
            {
                if ( $id != $fbStore[ "partnerEstablishmentId" ] ) continue;

                $founded = true;

                $descrip = $fbStore[ "descricao" ];
                $descrip = explode( "\n\n#", $descrip );

                if ( count( $descrip ) > 1 )
                {
                    $aux = "";
                    array_shift( $descrip );

                    foreach ( $descrip as $value ) $aux .= "\n\n#" . $value;

                    $store[ "descricao" ] .= $aux;
                }

                if ( $store[ "fotoThumb" ] != $fbStore[ "fotoThumb" ] )
                {
                    File::delete( $this->stores_path . $store[ "fotoThumb" ] );
                    $store[ "fotoThumb"  ] = $fbStore[ "fotoThumb"  ];
                    $store[ "listaFotos" ] = $fbStore[ "listaFotos" ];
                }

                $store[ "isPublished" ] = ( isset( $fbStore[ "isPublished" ] ) ) ? $fbStore[ "isPublished" ] : true;

                if ( isset( $fbStore[ "hasPromo"     ] ) ) $store[ "hasPromo"     ] = $fbStore[ "hasPromo"     ];

                if ( isset( $fbStore[ "enableDate"   ] ) ) $store[ "enableDate"   ] = $fbStore[ "enableDate"   ];
                if ( isset( $fbStore[ "disableDate"  ] ) ) $store[ "disableDate"  ] = $fbStore[ "disableDate"  ];
                if ( isset( $fbStore[ "dataInclusao" ] ) ) $store[ "dataInclusao" ] = $fbStore[ "dataInclusao" ];

                $fbRef->update( [ $push1 => $store ] );

                echo "Stores : Updated Estab. ID = {$push1}\n";
                break;
            }

            if ( !$founded )
            {
                $store[ "isPublished" ] = false;

                $newPush  = $fbRef->push()->getKey();
                $fbRef->update( [ $newPush => $store ] );

                $fbRef1   = Firebase::database()->getReference( "/Estabelecimento-Aprovacoes" );
                $date     = new DateTime( date( "Y-m-d H:i:s.u" ) );
                $date     = ( int ) $date->format( "U" ) * 1000;
                $approval = [
                    // Sistema Triibo - +5510999991477
                    "requesterId"  => "J4NjCHtvxuRojrXb29whbsNPAy33",

                    "id"           => $newPush,
                    "lastModified" => $date,
                    "requestDate"  => $date,
                    "status"       => "aberto",
                ];

                $newPush1 = $fbRef1->push()->getKey();
                $fbRef1->update( [ $newPush1 => $approval ] );

                echo "Stores : Created Estab. ID = {$newPush} | Need approval ID = {$newPush1}\n";
            }
        }
    }

    /**
     * Update and create promotions on Firebase
     *
     * @return void
     */
    private function insertOffersFirebase()
    {
        echo "Promotions : Searching promotions\n";
        Log::channel( "awin" )->info( "Promotions", [ "Searching promotions" ] );

        /**
         * TODO remover:
         * logs awin_promotions
         * array chg_p_arr
         * counters new_p, chg_p, dis_p
         * var aps_sql
         */
        Log::channel( "awin_promotions" )->info( "Promotions", [ "Start process - " . date( "Y-m-d H:i:s" ) ] );

        File::deleteDirectory( $this->promos_path, true );

        $chg_p_arr   = [];
        $new_p       = 0;
        $chg_p       = 0;
        $dis_p       = 0;
        $aps_sql     = \DB::table( "affiliates_promotions" )->get()->keyBy( "push_id" );

        $awinPromos  = $this->integration->getOffers();

        $db          = Firebase::database();
        $stores      = $this->filterStoresAwin( $db );

        $fbRef       = $db->getReference( "/Store" );
        $fbPromos    = $fbRef->getValue();
        $fbAuxPromos = [];

        foreach ( $fbPromos as $push => $promo )
        {
            if ( strpos( $push, "-awin"       ) !== false ) continue;
            if ( strpos( $push, "-cofry"      ) !== false ) continue;
            if ( strpos( $push, "-socialsoul" ) !== false ) continue;
            if ( !isset( $promo[ "establishmentId" ] )    ) continue;

            if ( isset( $promo[ "origin" ] ) && $promo[ "origin" ] == "awin" )
            {
                if ( !$promo[ "active" ] ) continue;

                if ( !isset( $awinPromos[ $promo[ "partnerPromotionId" ] ] ) )
                {
                    $dis_p++;

                    // Disabling promotions that are not on the list
                    $promo[ "isPublished" ] = false;
                    $promo[ "active"      ] = false;
                    $fbRef->update( [ $push => $promo ] );

                    echo "Promotions : Disab. Promo. ID = {$push}\n";
                    continue;
                }

                $fbAuxPromos[ $push ] = $promo;
            }
        }

        echo "Promotions : Sending promotions to Firebase\n";
        Log::channel( "awin" )->info( "Promotions", [ "Sending promotions to Firebase" ] );

        foreach ( $awinPromos as $id => $promo )
        {
            $founded = false;
            $keys    = array_keys( $stores );
            $index   = array_search( $promo[ "establishmentId" ], array_column( $stores, "partnerEstablishmentId" ) );
            $promo[ "establishmentId" ] = $keys[ $index ];

            if ( !empty( $fbAuxPromos ) )
            {
                foreach ( $fbAuxPromos as $push2 => $fbPromo )
                {
                    if ( $id != $fbPromo[ "partnerPromotionId" ] ) continue;

                    $founded = true;
                    $descrip = $fbPromo[ "description" ];
                    $descrip = explode( "\n\n#", $descrip );

                    if ( count( $descrip ) > 1 )
                    {
                        $aux = "";
                        array_shift( $descrip );

                        foreach ( $descrip as $value ) $aux .= "\n\n#" . $value;

                        $promo[ "description" ] .= $aux;
                    }

                    if ( isset( $fbPromo[ "enableDate"  ] ) ) $promo[ "enableDate"  ] = $fbPromo[ "enableDate"  ];
                    if ( isset( $fbPromo[ "disableDate" ] ) ) $promo[ "disableDate" ] = $fbPromo[ "disableDate" ];
                    if ( isset( $fbPromo[ "dueDate"     ] ) ) $promo[ "dueDate"     ] = $fbPromo[ "dueDate"     ];
                    if ( isset( $fbPromo[ "buttonLabel" ] ) ) $promo[ "buttonLabel" ] = $fbPromo[ "buttonLabel" ];

                    $promo[ "id" ] = ( isset( $fbPromo[ "id" ] ) ) ? $fbPromo[ "id" ] : $push2;

                    $fbRef->update( [ $push2 => $promo ] );

                    echo "Promotions : Updated Promo. ID = {$push2}\n";

                    // =================================
                    if ( isset( $aps_sql[ $push2 ] ) )
                    {
                        $aux_aps = json_decode( $aps_sql[ $push2 ]->data, true );
                        $changes = Helper::array_diff_assoc_recursive( $aux_aps, $promo );

                        if ( count( $changes ) > 0 )
                        {
                            $hasChg   = false;
                            $chg_keys = array_keys( $changes );
                            $ignore   = [ "enableDate", "disableDate", "dueDate" ];

                            foreach ( $chg_keys as $key )
                            {
                                if ( !in_array( $key, $ignore ) )
                                {
                                    $hasChg = true;
                                    break;
                                }
                            }

                            if ( $hasChg )
                            {
                                $chg_p++;

                                foreach ( $changes as $key => &$change )
                                {
                                    if ( in_array( $key, $ignore ) )
                                    {
                                        unset( $changes[ $key ] );
                                        continue;
                                    }

                                    $change = [
                                        "old" => $aux_aps[ $key ],
                                        "new" => $promo[ $key ],
                                    ];
                                }

                                $chg_p_arr[ $push2 ] = $changes;

                                \DB::table( "affiliates_promotions" )->where( "push_id", $push2 )->update( [ "data" => json_encode( $promo ) ] );
                            }
                        }
                    }
                    // =================================
                    break;
                }
            }

            if ( !$founded )
            {
                $new_p++;

                $newPush       = $fbRef->push()->getKey();
                $promo[ "id" ] = $newPush;

                $fbRef->update( [ $newPush => $promo ] );

                echo "Promotions : Created Promo. ID = {$newPush}\n";

                \DB::table( "affiliates_promotions" )->insert( [
                    "push_id"    => $newPush,
                    "data"       => json_encode( $promo ),
                    "created_at" => now(),
                    "updated_at" => now(),
                ] );
            }
        }

        // =================================
        Log::channel( "awin_promotions" )->info( "Promotions", [
            "new_promos" => $new_p,
            "dis_promos" => $dis_p,
            "chg_promos" => $chg_p,
            "changes"    => $chg_p_arr
        ] );
        Log::channel( "awin_promotions" )->info( "Promotions", [ "End process - " . date( "Y-m-d H:i:s" ) ] );
        Log::channel( "awin_promotions" )->info( "Promotions", [ "=================================" ] );
    }

    /**
     * Send images to Firebase Storage
     *
     * @param array $data
     * @return void
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

        File::deleteDirectory( $this->stores_path, true );
        File::deleteDirectory( $this->promos_path, true );
    }

    /**
     * Send and update reports to Firebase
     *
     * @return void
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
     * @param array $files
     * @return array $fileNames
     */
    private function getFileNames( array $files )
    {
        $fileNames = [];

        foreach( $files as $file ) array_push( $fileNames, pathinfo( $file )[ "basename" ] );

        return $fileNames;
    }

    /**
     *
     * @param Database $db
     * @return array
     */
    private function filterStoresAwin( Database $db )
    {
        $ref    = $db->getReference( "/Estabelecimento-v2" );
        $stores = $ref->getValue();
        $aux    = [];

        foreach ( $stores as $push => $store )
        {
            if ( !isset( $store[ "partnerEstablishmentId" ] ) ) continue;
            if ( !isset( $store[ "origin"                 ] ) ) continue;
            if ( strpos( $push, "-awin"       ) !== false     ) continue;
            if ( strpos( $push, "-cofry"      ) !== false     ) continue;
            if ( strpos( $push, "-socialsoul" ) !== false     ) continue;
            if ( "awin" != $store[ "origin" ]                 ) continue;

            $aux[ $push ] = $store;
        }

        return $aux;
    }
}
