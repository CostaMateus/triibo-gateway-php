<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;

class MarketplaceController extends Controller
{
    /**
     * Show the marketplace orders.
     *
     * @return  Renderable
     */
    public function orders()
    {
        return view( "marketplace.orders" );
    }
}
