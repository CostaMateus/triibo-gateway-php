<?php

namespace App\Http\Controllers\Affiliates;

use File;
use Throwable;
use App\Helpers\Helper;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Kreait\Firebase\Contract\Database;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Exception\FirebaseException;
use App\Http\Integrations\AffiliatesIntegration\CofryIntegration;

class CofryController extends Controller
{
    /**
     * Process stores and promotions
     *
     * @return void
     */
    public function all()
    {
        $this->stores();
        $this->promotions();
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
            Log::channel( "cofry" )->info( "Stores", [
                "code"    => 0,
                "info"    => "Establishments sent successfully",
                "error"   => NULL,
                "message" => NULL
            ] );
        }
        catch ( FirebaseException $e )
        {
            echo "Stores : Establishment, Firebase process error - aborted\n";
            Log::channel( "cofry" )->critical( "Stores", [
                "code"    => "C-401",
                "info"    => "Establishment, Firebase process error - aborted",
                "error"   => "An error has occurred while working with the SDK",
                "message" => $e
            ] );

            Helper::sendMailNotification( "cofry", "insertStoresFirebase()", "FirebaseException", $e );
        }
        catch ( Throwable $e )
        {
            echo "Stores : Unexpected error - aborted\n";
            Log::channel( "cofry" )->critical( "Stores", [
                "code"    => "C-501",
                "info"    => "Unexpected error - aborted",
                "error"   => "A not-Firebase specific error has occurred",
                "message" => $e
            ] );

            Helper::sendMailNotification( "cofry", "insertStoresFirebase()", "Throwable", $e );
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
            $this->insertPromosFirebase();
            echo "Promotions : Promotions sent successfully\n";
            Log::channel( "cofry" )->info( "Promotions", [
                "code"    => 0,
                "info"    => "Promotions sent successfully",
                "error"   => NULL,
                "message" => NULL
            ] );
        }
        catch ( FirebaseException $e )
        {
            echo "Promotions : Promotions, Firebase process error - aborted\n";
            Log::channel( "cofry" )->critical( "Promotions", [
                "code"    => "C-402",
                "info"    => "Promotions, Firebase process error - aborted",
                "error"   => "An error has occurred while working with the SDK",
                "message" => $e
            ] );

            Helper::sendMailNotification( "cofry", "insertPromosFirebase()", "FirebaseException", $e );
        }
        catch ( Throwable $e )
        {
            echo "Promotions : Unexpected error - aborted\n";
            Log::channel( "cofry" )->critical( "Promotions", [
                "code"    => "C-502",
                "info"    => "Unexpected error - aborted",
                "error"   => "A not-Firebase specific error has occurred",
                "message" => $e
            ] );

            Helper::sendMailNotification( "cofry", "insertPromosFirebase()", "Throwable", $e );
        }
    }

    /**
     * Process stores and send to Firebase
     *
     * @return void
     */
    public function insertStoresFirebase()
    {
        echo "Stores : Searching establishments\n";
        Log::channel( "cofry" )->info( "Stores", [ "Searching establishments" ] );

        $cofryStores = CofryIntegration::getEstabelecimentos();

        $db          = Firebase::database();
        $fbRef       = $db->getReference( "/Estabelecimento-v2" );
        $fbStores    = $fbRef->getValue();
        $fbAuxStores = [];

        foreach ( $fbStores as $push => $store )
        {
            if ( strpos( $push, "-awin"       ) !== false     ) continue;
            if ( strpos( $push, "-socialsoul" ) !== false     ) continue;
            if ( !isset( $store[ "partnerEstablishmentId" ] ) ) continue;

            if ( strpos( $push, "-cofry" ) !== false )
            {
                if ( $store[ "isPublished" ] )
                {
                    // Disabling all old stores
                    echo "Stores : Disab. Estab. ID = $push\n";
                    $store[ "isPublished" ] = false;
                    $store[ "origin"      ] = "cofry";
                    $fbRef->update( [ $push => $store ] );
                }

                continue;
            }

            if ( isset( $store[ "origin" ] ) && $store[ "origin" ] == "cofry" )
            {
                // Disabling all stores with push
                echo "Stores : Disab. Estab. ID = $push\n";
                $store[ "isPublished" ] = false;
                $fbRef->update( [ $push => $store ] );

                $fbAuxStores[ $push ] = $store;
            }
        }

        echo "Stores : Sending establishments to Firebase\n";
        Log::channel( "cofry" )->info( "Stores", [ "Sending establishments to Firebase" ] );

        foreach ( $cofryStores as $id => $store )
        {
            $founded = false;
            $cofryId = substr( $id, 6 );

            foreach ( $fbAuxStores as $push => $fbStore )
            {
                if ( $cofryId != $fbStore[ "partnerEstablishmentId" ] ) continue;

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
     * Process promotions and send to Firebase
     *
     * @return void
     */
    public function insertPromosFirebase()
    {
        echo "Promotions : Searching for promotions\n";
        Log::channel( "cofry" )->info( "Promotions", [ "Searching for promotions" ] );

        $cofryPromos = CofryIntegration::getPromocoes();

        $db          = Firebase::database();
        $stores      = $this->filterStoresCofry( $db );

        $fbRef       = $db->getReference( "/Store" );
        $fbPromos    = $fbRef->getValue();
        $fbAuxPromos = [];

        foreach ( $fbPromos as $push => $promo )
        {
            if ( strpos( $push, "-awin"       ) !== false ) continue;
            if ( strpos( $push, "-socialsoul" ) !== false ) continue;
            if ( !isset( $promo[ "establishmentId" ] )    ) continue;

            if ( strpos( $push, "-cofry" ) !== false )
            {
                if ( $promo[ "isPublished" ] || $promo[ "active" ] )
                {
                    // Disabling all old promotions
                    echo "Stores : Disab. Estab. ID = $push\n";
                    $promo[ "isPublished" ] = false;
                    $promo[ "active"      ] = false;
                    $promo[ "origin"      ] = "cofry";
                    $fbRef->update( [ $push => $promo ] );
                }

                continue;
            }

            if ( isset( $promo[ "origin" ] ) && $promo[ "origin" ] == "cofry" )
            {
                // Disabling all promotions with push
                echo "Stores : Disab. Estab. ID = $push\n";
                $promo[ "isPublished" ] = false;
                $promo[ "active"      ] = false;
                $fbRef->update( [ $push => $promo ] );

                $fbAuxPromos[ $push ] = $promo;
            }
        }

        echo "Promotions : Sending promotions to Firebase\n";
        Log::channel( "cofry" )->info( "Promotions", [ "Sending promotions to Firebase" ] );

        foreach ( $cofryPromos as $id => $promo )
        {
            foreach ( $stores as $push => $store )
            {
                $cofryId = substr( $promo[ "establishmentId" ], 6 );

                if ( $cofryId != $store[ "partnerEstablishmentId" ] ) continue;

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
     * Process images of stores and promotions
     *
     * @return void
     */
    public function uploadImages()
    {
        // Local
        $lcStore     = "images/estabelecimentos/";
        $lcPromo     = "images/promos/";

        // Firebase
        $fbStore     = "estabelecimento/";
        $fbPromo     = "promocao/";

        $filesStores = File::allFiles( public_path( $lcStore ) );
        $filesPromos = File::allFiles( public_path( $lcPromo ) );

        if ( empty( $filesStores ) || empty( $filesPromos ) )
        {
            echo "IMG : Generating images\n";
            Log::channel( "cofry" )->info( "Images", [ "Generating images" ] );
            CofryIntegration::getImagens();

            $filesStores = File::allFiles(public_path( $lcStore ) );
            $filesPromos = File::allFiles(public_path( $lcPromo ) );
        }

        echo "IMG : Capturing names - $lcStore\n";
        Log::channel( "cofry" )->info( "Images", [ "Capturing names - $lcStore" ] );
        $fileNameStores = self::getFileNames( $filesStores );

        echo "IMG : Capturing names - $lcPromo\n";
        Log::channel( "cofry" )->info( "Images", [ "Capturing names - $lcPromo" ] );
        $fileNamePromos = self::getFileNames( $filesPromos );

        // ~ 20 min
        self::execUploadImages( $fileNameStores, $lcStore, $fbStore );

        // ~ 57 min
        self::execUploadImages( $fileNamePromos, $lcPromo, $fbPromo );
    }

    /**
     * Recupera uma lista de exclusão de lojas, dado o parceiro
     *
     * @param string $partner
     * @return array
     */
    public static function getDenylist( $partner )
    {
        $db       = Firebase::database();
        $firebase = $db->getReference( "/Partners-Denylist/$partner" );
        $denylist = $firebase->getValue();

        return $denylist;
    }

    /**
     * Get the file names from list of files
     *
     * @param array $files
     * @return array $fileNames
     */
    private static function getFileNames( array $files )
    {
        $fileNames = [];

        foreach( $files as $file )
            array_push( $fileNames, pathinfo( $file )[ "basename" ] );

        return $fileNames;
    }

    /**
     * Send images to Firebase Storage
     *
     * @param array $images
     * @param string $lcFolder
     * @param string $fbFolder
     * @return void
     */
    private static function execUploadImages( array $images, string $lcFolder, string $fbFolder )
    {
        $st     = Firebase::storage();
        $bucket = $st->getBucket();

        echo "IMG : Start sending images to Firebase - $lcFolder\n";
        Log::channel( "cofry" )->info( "Images", [ "Start sending images to Firebase - $lcFolder" ] );

        foreach ( $images as $img )
        {
            $uploader = $bucket->getResumableUploader(
                fopen( public_path( $lcFolder . $img ), "r" ),
                [ "name" => $fbFolder . $img ]
            );

            try
            {
                echo "IMG : Image ID = $img\n";
                $uploader->upload();
            }
            catch ( FirebaseException $e )
            {
                $resumeUri = $uploader->getResumeUri();
                $object    = $uploader->resume( $resumeUri );

                Log::channel( "cofry" )->critical( "Images", [
                    "code"    => "C-403",
                    "info"    => $img,
                    "error"   => "An error has occurred while working with the SDK",
                    "message" => $e
                ] );

                Helper::sendMailNotification( "cofry", "execUploadImages()", "FirebaseException", $e );
            }
            catch ( Throwable $e )
            {
                Log::channel( "cofry" )->critical( "Images", [
                    "code"    => "C-503",
                    "info"    => $img,
                    "error"   => "A not-Firebase specific error has occurred",
                    "message" => $e
                ] );

                Helper::sendMailNotification( "cofry", "execUploadImages()", "Throwable", $e );
            }
        }

        echo "IMG : End uploading images to Firebase - $lcFolder\n";
        Log::channel( "cofry" )->info( "Images", [ "End uploading images to Firebase - $lcFolder" ] );
    }

    /**
     *
     * @param Database $db
     * @return array
     */
    private function filterStoresCofry( Database $db )
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
            if ( "cofry" != $store[ "origin" ]                ) continue;

            $aux[ $push ] = $store;
        }

        return $aux;
    }

}
