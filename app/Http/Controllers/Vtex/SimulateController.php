<?php

namespace App\Http\Controllers\Vtex;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Vtex\VtexController;

class SimulateController extends VtexController
{
    /**
     *
     *
     * @param   Request     $request
     * @return  JsonResponse
     */
    public function freightCalculation( Request $request ) : JsonResponse
    {
        if ( ( $verified = $this->validateKeys( $request ) ) !== true )
            return $verified;

        $wc_data = $request->all();
        $seller  = $request->header( "seller" );

        return vtex_freight_exec( parent::LOG_CHANNEL, $seller, $wc_data );
    }
}
