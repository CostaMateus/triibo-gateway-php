<?php

namespace App\Http\Controllers\Vtex;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class VtexController extends Controller
{
    /**
     * Define a class constant for the log channel.
     *
     * @var     string
     */
    protected const LOG_CHANNEL = "marketplace";

    /**
     * Credential validation.
     *
     * @param   Request     $request
     * @return  true|Response
     */
    protected function validateKeys( Request $request )
    {
        $_key   = config( "woocommerce.plugins.key"   );
        $_token = config( "woocommerce.plugins.token" );

        $key    = $request->header( "api-key"    );
        $token  = $request->header( "api-token"  );

        if ( $_key != $key || $_token != $token )
        {
            log_message(
                channel : self::LOG_CHANNEL,
                message : "WooCommerce: credentials validation failed",
                level   : "error",
                context : [ "key" => $key, "token" => $token ],
            );

            return response()->json( [
                "errorCode" => "400",
                "message"   => "invalid credentials"
            ], 400 );
        }
        else
        {
            return true;
        }
    }
}
