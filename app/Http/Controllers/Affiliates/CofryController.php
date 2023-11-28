<?php

namespace App\Http\Controllers\Affiliates;

use File;
use Throwable;
use App\Helpers\Helper;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Exception\FirebaseException;
use App\Http\Integrations\AffiliatesIntegration\CofryIntegration;

class CofryController extends Controller
{
    protected $log;

    public function __construct()
    {
        $this->log = Log::channel( "cofry" );
    }

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
     * Update and create store on Firebase
     *
     * @return void
     */
    public function insertStoresFirebase()
    {
        echo "Stores : Searching establishments\n";
        $this->log->info( "Stores", [ "Searching establishments" ] );

        $cofryStores = CofryIntegration::getEstabelecimentos();
        $fbRef       = Firebase::database()->getReference( "/Estabelecimento-v2" );
        $fbStores    = $fbRef->getValue();

        foreach ( $fbStores as $push => $fbStore )
        {
            if ( strpos( $push, "-cofry" ) !== false )
            {
                // Desativa só as lojas que não forem encontradas na nova carga
                if ( !isset( $cofryStores[ $push ] ) )
                {
                    $fbStore[ "isPublished" ] = false;
                    $fbRef->update( [ $push => $fbStore ] );

                    echo "Stores : Disab. Estab. ID = {$push}\n";
                }
            }
        }

        echo "Stores : Sending establishments to Firebase\n";
        $this->log->info( "Stores", [ "Sending establishments to Firebase" ] );

        foreach ( $cofryStores as $id => $store )
        {
            $status = "Created";

            // Trata a descrição da loja que já está cadastrada
            if ( isset( $fbStores[ $id ] ) )
            {
                $desc = $fbStores[ $id ][ "descricao" ];
                $desc = explode( "\n\n#", $desc );

                if ( count( $desc ) > 1 )
                {
                    $aux = "";
                    array_shift( $desc );

                    foreach ( $desc as $value ) $aux .= "\n\n#" . $value;

                    $store[ "descricao" ] .= $aux;
                }

                if ( isset( $fbStores[ $id ][ "hasPromo"     ] ) ) $store[ "hasPromo"     ] = $fbStores[ $id ][ "hasPromo"     ];

                if ( isset( $fbStores[ $id ][ "enableDate"   ] ) ) $store[ "enableDate"   ] = $fbStores[ $id ][ "enableDate"   ];
                if ( isset( $fbStores[ $id ][ "disableDate"  ] ) ) $store[ "disableDate"  ] = $fbStores[ $id ][ "disableDate"  ];
                if ( isset( $fbStores[ $id ][ "dataInclusao" ] ) ) $store[ "dataInclusao" ] = $fbStores[ $id ][ "dataInclusao" ];

                $status = "Updated";
            }

            // Atualiza ou cria a loja
            $fbRef->update([ $id => $store ] );

            echo "Stores : {$status} Estab. ID = {$id}\n";
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
        $this->log->info( "Promotions", [ "Searching for promotions" ] );

        $cofryPromos = CofryIntegration::getPromocoes();
        $fbRef       = Firebase::database()->getReference( "/Store" );
        $fbPromos    = $fbRef->getValue();

        foreach ( $fbPromos as $push => $fbPromo )
        {
            if ( strpos( $push, "-cofry" ) !== false )
            {
                // Desativa só as promoções que não forem encontradas na nova carga
                if ( !isset( $promos[ $push ] ) )
                {
                    $fbPromo[ "isPublished" ] = false;
                    $fbPromo[ "active"      ] = false;
                    $fbRef->update( [ $push => $fbPromo ] );

                    echo "Promotions : Disab. Promo. ID = {$push}\n";
                }
            }
        }

        echo "Promotions : Sending promotions to Firebase\n";
        $this->log->info( "Promotions", [ "Sending promotions to Firebase" ] );

        foreach ( $cofryPromos as $id => $promo )
        {
            $status = "Created";

            // Trata a descrição da promoção que já está cadastrada
            if ( isset( $fbPromos[ $id ] ) )
            {
                $desc = $fbPromos[ $id ][ "description" ];
                $desc = explode( "\n\n#", $desc );

                if ( count( $desc ) > 1 )
                {
                    $aux = "";
                    array_shift( $desc );

                    foreach ( $desc as $value ) $aux .= "\n\n#" . $value;

                    $promo[ "description" ] .= $aux;
                }

                if ( isset( $fbPromos[ $id ][ "enableDate"  ] ) ) $promo[ "enableDate"  ] = $fbPromos[ $id ][ "enableDate"  ];
                if ( isset( $fbPromos[ $id ][ "disableDate" ] ) ) $promo[ "disableDate" ] = $fbPromos[ $id ][ "disableDate" ];
                if ( isset( $fbPromos[ $id ][ "dueDate"     ] ) ) $promo[ "dueDate"     ] = $fbPromos[ $id ][ "dueDate"     ];
                if ( isset( $fbPromos[ $id ][ "buttonLabel" ] ) ) $promo[ "buttonLabel" ] = $fbPromos[ $id ][ "buttonLabel" ];

                $status = "Updated";
            }

            // Atualiza ou cria a promoção
            $fbRef->update( [ $id => $promo ] );

            echo "Promotions : {$status} Promo. ID = {$id}\n";
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
            $this->log->info( "Images", [ "Generating images" ] );
            CofryIntegration::getImagens();

            $filesStores = File::allFiles(public_path( $lcStore ) );
            $filesPromos = File::allFiles(public_path( $lcPromo ) );
        }

        echo "IMG : Capturing names - $lcStore\n";
        $this->log->info( "Images", [ "Capturing names - $lcStore" ] );
        $fileNameStores = $this->getFileNames( $filesStores );

        echo "IMG : Capturing names - $lcPromo\n";
        $this->log->info( "Images", [ "Capturing names - $lcPromo" ] );
        $fileNamePromos = $this->getFileNames( $filesPromos );

        $this->execUploadImages( $fileNameStores, $lcStore, $fbStore );
        $this->execUploadImages( $fileNamePromos, $lcPromo, $fbPromo );
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
     * @param array $images
     * @param string $lcFolder
     * @param string $fbFolder
     * @return void
     */
    private function execUploadImages( array $images, string $lcFolder, string $fbFolder )
    {
        $st     = Firebase::storage();
        $bucket = $st->getBucket();

        echo "IMG : Start sending images to Firebase - $lcFolder\n";
        $this->log->info( "Images", [ "Start sending images to Firebase - $lcFolder" ] );

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

                $this->log->critical( "Images", [
                    "code"    => "C-403",
                    "info"    => $img,
                    "error"   => "An error has occurred while working with the SDK",
                    "message" => $e
                ] );

                Helper::sendMailNotification( "cofry", "execUploadImages()", "FirebaseException", $e );
            }
            catch ( Throwable $e )
            {
                $this->log->critical( "Images", [
                    "code"    => "C-503",
                    "info"    => $img,
                    "error"   => "A not-Firebase specific error has occurred",
                    "message" => $e
                ] );

                Helper::sendMailNotification( "cofry", "execUploadImages()", "Throwable", $e );
            }
        }

        echo "IMG : End uploading images to Firebase - $lcFolder\n";
        $this->log->info("Images", [ "End uploading images to Firebase - $lcFolder" ]);
    }
}
