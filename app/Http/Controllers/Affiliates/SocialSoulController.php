<?php

namespace App\Http\Controllers\Affiliates;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Integrations\AffiliatesIntegration\SocialSoulIntegration;

// Lomadee
class SocialSoulController extends Controller
{
    protected $integration;

    public function __construct()
    {
        $this->integration = new SocialSoulIntegration();
    }

    public function coupons()
    {
        $coupons = $this->integration->getCoupons();

        dd( $coupons );
    }

    public function stores()
    {
        $stores = $this->integration->getStores();

        dd( $stores );
    }

    public function offers()
    {
        $offers = $this->integration->getOffers();

        dd( $offers );
    }
}
