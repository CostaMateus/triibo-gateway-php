<?php

namespace App\Http\Controllers\Affiliates;

use Throwable;
use App\Helpers\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Exception\FirebaseException;
use App\Http\Integrations\AffiliatesIntegration\SocialSoulIntegration;

// Lomadee
class SocialSoulController extends Controller
{
    protected $integration;
    protected $stores_path;
    protected $promos_path;

    public function __construct()
    {
        $this->integration = new SocialSoulIntegration();
        $this->stores_path = public_path( "images/affiliates/socialsoul/establishments/" );
        $this->promos_path = public_path( "images/affiliates/socialsoul/promotions/"     );
    }

    public function stores()
    {
        try
        {
            $this->insertStoresFirebase();
            echo "Stores : Establishments sent successfully\n";
            Log::channel( "socialsoul" )->info( "Stores", [
                "code"    => 0,
                "info"    => "Establishments sent successfully",
                "error"   => NULL,
                "message" => NULL
            ] );
        }
        catch ( FirebaseException $e )
        {
            echo "Stores : Establishment, Firebase process error - aborted\n";
            Log::channel( "socialsoul" )->critical( "Stores", [
                "code"    => "C-401",
                "info"    => "Establishment, Firebase process error - aborted",
                "error"   => "An error has occurred while working with the SDK",
                "message" => $e
            ] );

            Helper::sendMailNotification( "socialsoul", "insertStoresFirebase()", "FirebaseException", $e );
        }
        catch ( Throwable $e )
        {
            echo "Stores : Unexpected error - aborted\n";
            Log::channel( "socialsoul" )->critical( "Stores", [
                "code"    => "C-501",
                "info"    => "Unexpected error - aborted",
                "error"   => "A not-Firebase specific error has occurred",
                "message" => $e
            ] );

            Helper::sendMailNotification( "socialsoul", "insertStoresFirebase()", "Throwable", $e );
        }
    }

    public function promotions()
    {
        try
        {
            $this->insertCouponsOffersFirebase();
            echo "Promotions : Promotions sent successfully\n";
            Log::channel( "socialsoul" )->info( "Promotions", [
                "code"    => 0,
                "info"    => "Promotions sent successfully",
                "error"   => NULL,
                "message" => NULL
            ] );
        }
        catch ( FirebaseException $e )
        {
            echo "Promotions : Promotion, Firebase process error - aborted\n";
            Log::channel( "socialsoul" )->critical( "Promotions", [
                "code"    => "C-401",
                "info"    => "Promotion, Firebase process error - aborted",
                "error"   => "An error has occurred while working with the SDK",
                "message" => $e
            ] );

            Helper::sendMailNotification( "socialsoul", "insertCouponsOffersFirebase()", "FirebaseException", $e );
        }
        catch ( Throwable $e )
        {
            echo "Promotions : Unexpected error - aborted\n";
            Log::channel( "socialsoul" )->critical( "Promotions", [
                "code"    => "C-501",
                "info"    => "Unexpected error - aborted",
                "error"   => "A not-Firebase specific error has occurred",
                "message" => $e
            ] );

            Helper::sendMailNotification( "socialsoul", "insertCouponsOffersFirebase()", "Throwable", $e );
        }
    }

    public function images()
    {
        $filesStores = File::allFiles( $this->stores_path );
        $filesPromos = File::allFiles( $this->promos_path );

        if ( empty( $filesStores ) || empty( $filesPromos ) )
        {
            echo "Images : No images to upload\n";
            Log::channel( "socialsoul" )->info( "Images", [ "No images to upload" ] );
            return;
        }

        echo "Images : Capturing names - establishments\n";
        Log::channel( "socialsoul" )->info( "Images", [ "Capturing names - establishments" ] );
        $fileNameStores = $this->getFileNames( $filesStores );

        echo "Images : Capturing names - promotions";
        Log::channel( "socialsoul" )->info( "Images", [ "Capturing names - promotions"     ] );
        $fileNamePromos = $this->getFileNames( $filesPromos );

        $this->insertImagesFirebase( [
            "stores"     => [
                "files" => $fileNameStores,
                "lc"    => $this->stores_path,
                "fb"    => "estabelecimento/",
            ],
            "promotions" => [
                "files" => $fileNamePromos,
                "lc"    => $this->promotions_path,
                "fb"    => "promocao/"
            ]
        ] );
    }

    /**
     * Process stores and send to Firebase
     *
     * @return void
     */
    private function insertStoresFirebase()
    {
        $db             = Firebase::database();
        $firebaseStores = $db->getReference( "/Estabelecimento-v2" );

        echo "Stores : Searching establishments\n";
        Log::channel( "socialsoul" )->info( "Stores", [ "Searching establishments" ] );

        File::deleteDirectory( $this->stores_path, true );

        $stores         = $this->integration->getStores();
        $allStores      = $firebaseStores->getValue();

        foreach ( $allStores as $id => $s )
        {
            if (strpos( $id, "-socialsoul" ) !== false )
            {
                if ( !isset( $stores[ $id ] ) )
                {
                    echo "Stores : Disab. Estab. ID = $id\n";
                    $s[ "isPublished" ] = false;
                    $firebaseStores->update( [ $id => $s ] );
                }
            }
        }

        echo "Stores : Sending establishments to Firebase\n";
        Log::channel( "socialsoul" )->info( "Stores", [ "Sending establishments to Firebase" ] );

        foreach ( $stores as $id => $s )
        {
            echo "Stores : Estab. ID = $id\n";

            if ( isset( $allStores[ $id ] ) )
            {
                $desc = $allStores[ $id ][ "descricao" ];
                $desc = explode( "\n\n#", $desc );

                if ( count( $desc ) > 1 )
                {
                    array_shift( $desc );

                    $aux = "";
                    foreach ( $desc as $value ) $aux .= "\n\n#" . $value;

                    $s[ "descricao" ] .= $aux;
                }
            }

            $firebaseStores->update( [ $id => $s ] );
        }

        File::deleteDirectory( $this->stores_path, true );
    }

    /**
     * Process coupons/offers and send to Firebase
     *
     * @return void
     */
    private function insertCouponsOffersFirebase()
    {
        $db             = Firebase::database();
        $firebasePromos = $db->getReference( "/Store" );

        echo "Promotions : Searching for promotions\n";
        Log::channel( "socialsoul" )->info( "Promotions", [ "Searching for promotions" ] );

        File::deleteDirectory( $this->promos_path, true );

        $coupons   = $this->integration->getCoupons();
        $offers    = $this->integration->getOffers();
        $promos    = array_merge( $coupons, $offers );
        $allPromos = $firebasePromos->getValue();

        foreach ( $allPromos as $id => $p )
        {
            if ( strpos( $id, "-socialsoul" ) !== false )
            {
                if ( !isset( $promos[ $id ] ) )
                {
                    echo "Promotions : Disab. Promo. ID = $id\n";
                    $p[ "isPublished" ] = false;
                    $p[ "active"      ] = false;

                    $firebasePromos->update( [ $id => $p ] );
                }
            }
        }

        echo "Promotions : Sending promos to Firebase\n";
        Log::channel( "socialsoul" )->info( "Promotions", [ "Sending promos to Firebase" ] );

        foreach ( $promos as $id => $p )
        {
            echo "Promotions : Insert/Update Promo ID = $id\n";

            if ( isset( $allPromos[ $id ] ) )
            {
                $desc = $allPromos[ $id ][ "description" ];
                $desc = explode( "\n\n#", $desc );

                if ( count( $desc ) > 1 )
                {
                    array_shift( $desc );

                    $aux = "";
                    foreach ( $desc as $value ) $aux .= "\n\n#" . $value;

                    $p[ "description" ] .= $aux;
                }
            }

            $firebasePromos->update( [ $id => $p ] );
        }

        File::deleteDirectory( $this->promos_path, true );
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

        foreach( $files as $file )
            array_push( $fileNames, pathinfo( $file )[ "basename" ] );

        return $fileNames;
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
            echo "Images : Start sending images to Firebase - {$key}\n";
            Log::channel( "socialsoul" )->info("Images", [ "Start sending images to Firebase - {$key}" ]);

            $files   = $images[ "files" ];
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
                    Log::channel( "socialsoul" )->critical( "Images", [
                        "code"    => "C-403",
                        "info"    => $img,
                        "error"   => "An error has occurred while working with the SDK",
                        "message" => $e
                    ] );

                    Helper::sendMailNotification( "socialsoul", "insertImagesFirebase()", "FirebaseException", $e );
                }
                catch (Throwable $e)
                {
                    echo "Images : Unexpected error - aborted\n";
                    Log::channel( "socialsoul" )->critical( "Images", [
                        "code"    => "C-503",
                        "info"    => $img,
                        "error"   => "A not-Firebase specific error has occurred",
                        "message" => $e
                    ] );

                    Helper::sendMailNotification( "socialsoul", "insertImagesFirebase()", "Throwable", $e );
                }
            }

            echo "Images : End uploading images to Firebase - {$key}\n";
            Log::channel( "socialsoul" )->info( "Images", [ "End uploading images to Firebase - {$key}" ] );
        }
    }
}
