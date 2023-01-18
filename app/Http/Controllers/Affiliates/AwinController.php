<?php

namespace App\Http\Controllers\Affiliates;

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
                "message" => $e->getMessage()
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
                "message" => $e->getMessage(),
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
                "message" => $e->getMessage()
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
                "message" => $e->getMessage(),
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
                "message" => $e->getMessage()
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
                "message" => $e->getMessage(),
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

        foreach ( $fbStores as $push => $store )
        {
            if ( strpos( $push, "-cofry"      ) !== false     ) continue;
            if ( strpos( $push, "-socialsoul" ) !== false     ) continue;
            if ( !isset( $store[ "partnerEstablishmentId" ] ) ) continue;

            if ( strpos( $push, "-awin" ) !== false )
            {
                if ( $store[ "isPublished" ] )
                {
                    // Disabling all old stores
                    echo "Stores : Disab. Estab. ID = $push\n";
                    $store[ "isPublished" ] = false;
                    $store[ "origin"      ] = "awin";
                    $fbRef->update( [ $push => $store ] );
                }

                continue;
            }

            if ( isset( $store[ "origin" ] ) && $store[ "origin" ] == "awin" )
            {
                // Disabling all stores with push
                echo "Stores : Disab. Estab. ID = $push\n";
                $store[ "isPublished" ] = false;
                $fbRef->update( [ $push => $store ] );

                $fbAuxStores[ $push ] = $store;
            }
        }

        echo "Stores : Sending establishments to Firebase\n";
        Log::channel( "awin" )->info( "Stores", [ "Sending establishments to Firebase" ] );

        foreach ( $awinStores as $id => $store )
        {
            $founded = false;
            $awinId  = substr( $id, 5 );

            foreach ( $fbAuxStores as $push => $fbStore )
            {
                if ( $awinId != $fbStore[ "partnerEstablishmentId" ] ) continue;

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

                $fbRef->update( [ $push => $store ] );

                echo "Stores : Updated Estab. ID = $push\n";
                break;
            }

            if ( !$founded )
            {
                $push = $fbRef->push()->getKey();
                $fbRef->update( [ $push => $store ] );

                echo "Stores : Created Estab. ID = $push\n";
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

        File::deleteDirectory( $this->promos_path, true );

        $awinPromos  = $this->integration->getOffers();

        $db          = Firebase::database();
        $stores      = $this->filterStoresAwin( $db );

        $fbRef       = $db->getReference( "/Store" );
        $fbPromos    = $fbRef->getValue();
        $fbAuxPromos = [];

        foreach ( $fbPromos as $push => $promo )
        {
            if ( strpos( $push, "-cofry"      ) !== false ) continue;
            if ( strpos( $push, "-socialsoul" ) !== false ) continue;
            if ( !isset( $promo[ "establishmentId" ] )    ) continue;

            if ( strpos( $push, "-awin" ) !== false )
            {
                if ( $promo[ "isPublished" ] || $promo[ "active" ] )
                {
                    // Disabling all old promotions
                    echo "Promotions : Disab. Promo. ID = $push\n";
                    $promo[ "isPublished" ] = false;
                    $promo[ "active"      ] = false;
                    $promo[ "origin"      ] = "awin";
                    $fbRef->update( [ $push => $promo ] );
                }

                continue;
            }

            if ( isset( $promo[ "origin" ] ) && $promo[ "origin" ] == "awin" )
            {
                // Disabling all promotions with push
                echo "Promotions : Disab. Promo. ID = $push\n";
                $promo[ "isPublished" ] = false;
                $promo[ "active"      ] = false;
                $fbRef->update( [ $push => $promo ] );

                $fbAuxPromos[ $push ] = $promo;
            }
        }

        echo "Promotions : Sending promotions to Firebase\n";
        Log::channel( "awin" )->info( "Promotions", [ "Sending promotions to Firebase" ] );

        foreach ( $awinPromos as $id => $promo )
        {
            foreach ( $stores as $push => $store )
            {
                $awinId = substr( $promo[ "establishmentId" ], 6 );

                if ( $awinId != $store[ "partnerEstablishmentId" ] ) continue;

                $promo[ "establishmentId" ] = $push;
                break;
            }

            $founded = false;

            if ( !empty( $fbAuxPromos ) )
            {
                foreach ( $fbAuxPromos as $push => $fbPromo )
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

                    $fbRef->update( [ $push => $promo ] );

                    echo "Promotions : Updated Promo. ID = $push\n";
                    break;
                }
            }

            if ( !$founded )
            {
                $push = $fbRef->push()->getKey();
                $fbRef->update( [ $push => $promo ] );

                echo "Promotions : Created Promo. ID = $push\n";
            }
        }
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
                    // $resumeUri = $uploader->getResumeUri();
                    // $object    = $uploader->resume( $resumeUri );

                    echo "Images : Image, Firebase process error - aborted\n";
                    Log::channel( "awin" )->critical( "Images", [
                        "code"    => "C-403",
                        "info"    => $img,
                        "error"   => "An error has occurred while working with the SDK",
                        "message" => $e->getMessage(),
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
                        "message" => $e->getMessage(),
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

        $fbRef   = Firebase::database()->getReference( "/Triibo-Purchases-Awin" );

        // new transactions
        $reports = $this->integration->getReports();

        if ( $reports[ "success" ] && !empty( $reports[ "data" ] ) )
        {
            echo "Reports : Sending new transactions to Firebase\n";
            Log::channel( "awin" )->info( "Reports", [ "Sending new transactions to Firebase" ] );

            foreach ( $reports[ "data" ] as $report )
            {
                $report[ "validationDate"   ] = null;
                $report[ "commissionStatus" ] = "pending";

                foreach ( $report as $key => &$value ) is_null( $value ) ? "" : $value;

                $push = $fbRef->push()->getKey();
                $fbRef->update( [ $push => $report ] );

                echo "Reports : New transaction ID = $push\n";
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
            $fbReports  = $fbRef->getValue();

            echo "Reports : Sending updated transactions to Firebase\n";
            Log::channel( "awin" )->info( "Reports", [ "Sending updated transactions to Firebase" ] );

            foreach ( $fbReports as $push => $fbReport )
            {
                foreach ( $updReports[ "data" ] as $uReport )
                {
                    $id         = $fbReport[ "id"           ] == $uReport[ "id"           ];
                    $advertiser = $fbReport[ "advertiserId" ] == $uReport[ "advertiserId" ];

                    if ( !$id || !$advertiser ) continue;

                    $fbReport = $uReport;
                    break;
                }

                $fbRef->update( [ $push => $fbReport ] );

                echo "Reports : Updated transaction ID = $push\n";
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
