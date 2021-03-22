<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PipedriveWebhookController extends Controller
{
    /**
     * Data from Pipedrive to Mautic
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function post(Request $request)
    {
        $date    = date('Ymd-his');
        $encoded = json_encode($request->all(), JSON_UNESCAPED_SLASHES);

        Storage::put('pipedrive-'.$date.'.json', $encoded);

        return response()->json([
            'message' => 'Apenas teste',
            'code'    => 200
        ], 200);
    }

}
