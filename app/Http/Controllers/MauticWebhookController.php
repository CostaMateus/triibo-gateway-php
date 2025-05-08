<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Triibo\Mautic\Facades\Mautic;
use Illuminate\Support\Facades\Storage;

class MauticWebhookController extends Controller
{
    /**
     * Data from Mautic to Pipedrive
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function post( Request $request )
    {
        $date = date( "Ymd-his" );
        $encoded = json_encode( $request->all(), JSON_UNESCAPED_SLASHES );

        Storage::put( "mautic-" . $date . ".json", $encoded );

        return response()->json( [
            "message" => "Apenas teste",
            "code" => 200
        ], 200 );
    }

    public function get( Request $request )
    {
        $date = date( "Ymd-his" );
        $encoded = json_encode( $request->all(), JSON_UNESCAPED_SLASHES );

        Storage::put( "mautic-app-register-" . $date . ".json", $encoded );

        return response()->json( [
            "message" => "Callback Mautic teste",
            "code" => 200,
            "body" => $request->all()
        ], 200 );
    }

    public function index( $id )
    {

        return Mautic::request( "GET", "contacts/158095" );
    }
}
