<?php

namespace App\Http\Controllers\Affiliates;

use Throwable;
use App\Helpers\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Exception\FirebaseException;
use App\Http\Integrations\AffiliatesIntegration\SocialSoulIntegration;

// Lomadee
class SocialSoulController extends Controller
{
    protected $integration;

    public function __construct()
    {
        $this->integration = new SocialSoulIntegration();
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
    }

}
