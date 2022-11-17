<?php

namespace App\Http\Controllers\Affiliates;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Integrations\AffiliatesIntegration\SocialSoulIntegration;

// Lomadee
class SocialSoulController extends Controller
{
    //

    public function coupons()
    {
        $integration = new SocialSoulIntegration();
        $coupons     = $integration->getCoupons();

        dd( $coupons );
    }

    public function stores()
    {
        $integration = new SocialSoulIntegration();
        $stores      = $integration->getStores();

        foreach ( $stores[ "data" ] as $id => &$store )
        {
            if ( $store[ "hasOffer" ] > 0 )
            {
                echo $store[ "id" ] . "\n";

                $data = $integration->getOffers( $store[ "id" ] );

                if ( $data[ "success" ] )
                    $store[ "dataOffer" ] = $data[ "data" ];
            }
        }

        return $stores[ "data" ];
    }
}
