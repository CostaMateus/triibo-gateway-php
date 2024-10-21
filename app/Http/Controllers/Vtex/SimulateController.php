<?php

namespace App\Http\Controllers\Vtex;

use Throwable;
use Illuminate\Http\Request;
use App\Services\Vtex\ConnectorService;
use App\Services\Vtex\FulfillmentService;
use App\Http\Controllers\Vtex\VtexController;

class SimulateController extends VtexController
{
    /**
     *
     *
     * @param   Request     $request
     * @return  Response
     */
    public function freightCalculation( Request $request )
    {
        if ( ( $verified = $this->validateKeys( $request ) ) !== true )
            return $verified;

        $wc_data    = $request->all();
        $seller     = $request->header( "seller" );
        $connector  = new ConnectorService( $seller );
        $connector->enableLogMessage( false );

        $simulation = new FulfillmentService( $connector );

        try
        {
            $req_data  = vtex_fulfillment_simulation( $wc_data );
            $response  = $simulation->simulate( $req_data );

            if ( $response[ 0 ] != 200 )
            {
                log_message(
                    channel : parent::LOG_CHANNEL,
                    level   : "error",
                    message : "VTEX - freightCalculation",
                    context : [
                        "seller"     => $seller,
                        "vtex_data"  => $vtex_data,
                        "response"   => $response,
                    ]
                );

                return response()->json( [
                    "errorCode" => "5001",
                    "message"   => "Something wrong when consulting the delivery price",
                    "data"      => null,
                ], 200 );
            }

            $vtex_data = $response[ 1 ][ "data" ];
            $data      = [];

            $translated_data = vtex_freight_to_woo( $vtex_data );

            foreach ( $req_data[ "items" ] as $product )
            {
                if ( isset( $translated_data[ $product[ "id" ] ] ) )
                {
                    $temp_data = $translated_data[ $product[ "id" ] ];
                    $code      = $temp_data[ "code"    ];
                    $message   = $temp_data[ "message" ];

                    unset(
                        $temp_data[ "code"    ],
                        $temp_data[ "message" ]
                    );

                    $data[ $product[ "id" ] ] = [
                        "errorCode" => $code,
                        "message"   => $message,
                        "data"      => $temp_data,
                    ];
                }
            }

            return response()->json( [
                "errorCode" => null,
                "message"   => "ok",
                "data"      => $data,
            ], 200 );
        }
        catch ( Throwable $e )
        {
            // Log the exception for debugging purposes.
            log_exception(
                channel : parent::LOG_CHANNEL,
                message : $e->getMessage(),
                method  : "freightCalculation",
                type    : "Throwable",
                e       : $e
            );

            return response()->json( [
                "errorCode" => "5000", // 5000 - erro generico
                "message"   => $e->getMessage(),
                "data"      => $wc_data[ "items" ],
            ], 500 );
        }
    }
}
