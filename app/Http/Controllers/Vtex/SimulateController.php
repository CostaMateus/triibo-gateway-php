<?php

namespace App\Http\Controllers\Vtex;

use Throwable;
use App\Models\Vtex\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Vtex\VtexController;

class SimulateController extends VtexController
{
    /**
     * TODO doc
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
        $simulation = new FulfillmentService( $connector );

        try
        {
            $vtex_data = vtex_fulfillment_simulation( $wc_data );

            $response  = $simulation->simulate( $vtex_data );

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

                return null;
            }

            foreach ( $resps as $key => $response )
            {
                // falha na consulta
                // não altera nada no MKT
                // não exibe frete
                if ( !$response->successful() )
                {
                    Log::freightCalculation( $fsData[ $key ] );

                    $item         = $wc_data[ "items" ][ $key ];
                    $sku          = $item[ "sku" ];
                    $data[ $sku ] = [
                        "errorCode" => "5001",
                        "message"   => "Something wrong when consulting the delivery price",
                        "data"      => $item
                    ];

                    continue;
                }

                $item                  = $wc_data[ "items" ][ $key ];
                $sku                   = $item[ "sku" ];

                $fsData                = $response->json();

                $aWc_data               = DataTranslator::translate( "freightCalcWc", $fsData );

                $aWc_data[ "sku"      ] = isset( $aWc_data[ "sku"      ] ) ? $aWc_data[ "sku"      ] : $sku;
                $aWc_data[ "quantity" ] = isset( $aWc_data[ "quantity" ] ) ? $aWc_data[ "quantity" ] : $item[ "quantity" ];

                $code                  = $aWc_data[ "code"    ];
                $message               = $aWc_data[ "message" ];

                unset( $aWc_data[ "code" ], $aWc_data[ "message" ] );

                $data[ $sku ] = [
                    "errorCode" => $code,
                    "message"   => $message,
                    "data"      => $aWc_data,
                ];
            }

            return response()->json( [
                "errorCode" => null,
                "message"   => "ok",
                "data"      => $data,
            ], 200 );
        }
        catch ( Exception $e )
        {
            Log::exception( "MP - freightCalculation", $e );

            // log_exception( $e );

            return response()->json( [
                "errorCode" => "5000", // 5000 - erro generico
                "message"   => $e->getMessage(),
                "data"      => $wc_data[ "items" ],
            ], 500 );
        }
    }
}
