<?php

namespace App\Http\Controllers\Affiliates;

use File;
use Throwable;
use App\Helpers\Helper;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Kreait\Firebase\Contract\Database;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Exception\FirebaseException;
use App\Http\Integrations\AffiliatesIntegration\CofryIntegration;

class CofryController extends Controller
{
    /**
     * Process stores
     *
     * @return void
     */
    public function stores()
    {
        try
        {
            echo "Stores : Establishments init\n";

            $fbStores    = Firebase::database()->getReference( "/Store" )->getValue();

            foreach ( $fbStores as $push => $store )
            {
                if ( strpos( $push, "-awin"       ) !== false ) continue;
                if ( strpos( $push, "-cofry"      ) !== false ) continue;
                if ( strpos( $push, "-socialsoul" ) !== false ) continue;
                // if ( !isset( $store[ "establishmentId" ] )    ) continue;

                if ( isset( $store[ "origin" ] ) && in_array( $store[ "origin" ], [ "awin", "cofry", "socialsoul" ] ) )
                {
                    echo "Stores : Delete establishments id = $push\n";
                    Firebase::database()->getReference( "Store/{$push}" )->remove();
                }
            }

            echo "Stores : Establishments finish\n";
        }
        catch ( FirebaseException $e )
        {
            dump( $e );
            echo "Stores : Establishment, Firebase process error - aborted\n";
        }
        catch ( Throwable $e )
        {
            dump( $e );
            echo "Stores : Unexpected error - aborted\n";
        }
    }
}
