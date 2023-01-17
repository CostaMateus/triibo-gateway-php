<?php

namespace App\Http\Controllers\Affiliates;

use Throwable;
use App\Helpers\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Kreait\Firebase\Contract\Database;
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
                "message" => $e->getMessage()
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
                "message" => $e->getMessage(),
            ] );

            Helper::sendMailNotification( "socialsoul", "insertStoresFirebase()", "Throwable", $e );
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
                "message" => $e->getMessage(),
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
                "message" => $e->getMessage(),
            ] );

            Helper::sendMailNotification( "socialsoul", "insertCouponsOffersFirebase()", "Throwable", $e );
        }
    }

    /**
     * Process images
     *
     * @return void
     */
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
                "lc"    => $this->promos_path,
                "fb"    => "promocao/"
            ]
        ] );
    }

    /**
     * Recebe o callback de vendas da SocialSoul
     *
     * @param Request $request
     * @return Response
     */
    public function postback( Request $request )
    {
        $params          = [
            "orderId",
            "advertiserId",
            "userId",
            "saleValue",
            "commissionValue"
        ];

        $query           = $request->query();
        $validated       = true;

        foreach ( $params as $param )
            if ( !array_key_exists( $param, $query ) )
                $validated = false;

        if ( !$validated ) return response()->json( [ "message" => "Missing query strings" ], 400 );

        // Tratar dados
        $orderId         = $query[ "orderId"         ];
        $advertiserId    = $query[ "advertiserId"    ];
        $userId          = $query[ "userId"          ];
        $saleValue       = $query[ "saleValue"       ];
        $commissionValue = $query[ "commissionValue" ];

        // enviar para Firebase

        return response()->json( [ "message" => "ok" ], 200 );
    }

    /**
     * Process reports
     *
     * @return void
     */
    public function reports()
    {
        $reports = $this->integration->getReports();

        // enviar para o Firebase
    }

    /**
     * Update and create store on Firebase
     *
     * @return void
     */
    private function insertStoresFirebase()
    {
        echo "Stores : Searching establishments\n";
        Log::channel( "socialsoul" )->info( "Stores", [ "Searching establishments" ] );

        File::deleteDirectory( $this->stores_path, true );

        $ssStores    = $this->integration->getStores();

        $fbRef       = Firebase::database()->getReference( "/Estabelecimento-v2" );
        $fbStores    = $fbRef->getValue();
        $fbAuxStores = [];

        foreach ( $fbStores as $push => $store )
        {
            if ( strpos( $push, "-awin"  ) !== false          ) continue;
            if ( strpos( $push, "-cofry" ) !== false          ) continue;
            if ( !isset( $store[ "partnerEstablishmentId" ] ) ) continue;

            if ( strpos( $push, "-socialsoul" ) !== false )
            {
                if ( $store[ "isPublished" ] )
                {
                    // Disabling all old stores
                    echo "Stores : Disab. Estab. ID = $push\n";
                    $store[ "isPublished" ] = false;
                    $store[ "origin"      ] = "socialsoul";
                    $fbRef->update( [ $push => $store ] );
                }

                continue;
            }

            if ( isset( $store[ "origin" ] ) && $store[ "origin" ] == "socialsoul" )
            {
                // Disabling all stores with push
                echo "Stores : Disab. Estab. ID = $push\n";
                $store[ "isPublished" ] = false;
                $fbRef->update( [ $push => $store ] );

                $fbAuxStores[ $push ] = $store;
            }
        }

        echo "Stores : Sending establishments to Firebase\n";
        Log::channel( "socialsoul" )->info( "Stores", [ "Sending establishments to Firebase" ] );

        foreach ( $ssStores as $id => $store )
        {
            $founded = false;
            $ssId    = substr( $id, 11 );

            foreach ( $fbAuxStores as $push => $fbStore )
            {
                if ( $ssId != $fbStore[ "partnerEstablishmentId" ] ) continue;

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
    private function insertCouponsOffersFirebase()
    {
        echo "Promotions : Searching for promotions\n";
        Log::channel( "socialsoul" )->info( "Promotions", [ "Searching for promotions" ] );

        File::deleteDirectory( $this->promos_path, true );

        $coupons     = $this->integration->getCoupons();
        $offers      = $this->integration->getOffers();
        $ssPromos    = array_merge( $coupons, $offers );

        $db          = Firebase::database();
        $stores      = $this->filterStoresSocialSoul( $db );

        $fbRef       = $db->getReference( "/Store" );
        $fbPromos    = $fbRef->getValue();
        $fbAuxPromos = [];

        foreach ( $fbPromos as $push => $promo )
        {
            if ( strpos( $push, "-awin"  ) !== false   ) continue;
            if ( strpos( $push, "-cofry" ) !== false   ) continue;
            if ( !isset( $promo[ "establishmentId" ] ) ) continue;

            if ( strpos( $push, "-socialsoul" ) !== false )
            {
                if ( $promo[ "isPublished" ] || $promo[ "active" ] )
                {
                    // Disabling all old promotions
                    echo "Promotions : Disab. Promo. ID = $push\n";
                    $promo[ "isPublished" ] = false;
                    $promo[ "active"      ] = false;
                    $promo[ "origin"      ] = "socialsoul";
                    $fbRef->update( [ $push => $promo ] );
                }

                continue;
            }

            if ( isset( $promo[ "origin" ] ) && $promo[ "origin" ] == "socialsoul" )
            {
                // Disabling all promotions with push
                echo "Promotions : Disab. Promo. ID = $push\n";
                $promo[ "isPublished" ] = false;
                $promo[ "active"      ] = false;
                $fbRef->update( [ $push => $promo ] );

                $fbAuxPromos[ $push ] = $promo;
            }
        }

        echo "Promotions : Sending promos to Firebase\n";
        Log::channel( "socialsoul" )->info( "Promotions", [ "Sending promos to Firebase" ] );

        foreach ( $ssPromos as $id => $promo )
        {
            foreach ( $stores as $push => $store )
            {
                $ssId = substr( $promo[ "establishmentId" ], 6 );

                if ( $ssId != $store[ "partnerEstablishmentId" ] ) continue;

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
                        "message" => $e->getMessage(),
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
                        "message" => $e->getMessage(),
                    ] );

                    Helper::sendMailNotification( "socialsoul", "insertImagesFirebase()", "Throwable", $e );
                }
            }

            echo "Images : End uploading images to Firebase - {$key}\n";
            Log::channel( "socialsoul" )->info( "Images", [ "End uploading images to Firebase - {$key}" ] );
        }

        File::deleteDirectory( $this->stores_path, true );
        File::deleteDirectory( $this->promos_path, true );
    }

    /**
     *
     * @param Database $db
     * @return array
     */
    private function filterStoresSocialSoul( Database $db )
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
            if ( "socialsoul" != $store[ "origin" ]           ) continue;

            $aux[ $push ] = $store;
        }

        return $aux;
    }

}
