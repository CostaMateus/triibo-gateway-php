<?php

namespace App\Http\Controllers\Vtex;

use App\Http\Controllers\Controller;
use Illuminate\Http\{JsonResponse, Request};

class VtexController extends Controller
{
    /**
     * Define a class constant for the log channel.
     *
     * @var string
     */
    protected const LOG_CHANNEL = "marketplace";

    /**
     * Credential validation.
     *
     * @param Request $request
     *
     * @return JsonResponse|true
     */
    protected function validateKeys( Request $request ) : mixed
    {
        $config = config( "woocommerce.plugins" );

        $key = $request->header( "api-key" );
        $token = $request->header( "api-token" );

        if ( $config[ "key" ] !== $key || $config[ "token" ] !== $token )
        {
            log_message(
                channel : self::LOG_CHANNEL,
                message : "WooCommerce: credentials validation failed",
                level   : "error",
                context : ["key" => $key, "token" => $token],
            );

            return response()->json( [
                "error_code" => "400",
                "message" => "invalid credentials"
            ], 400 );
        }

        return true;

    }
}
