<?php

namespace App\Http\Controllers;

use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;
use Illuminate\Http\Request;
use Triibo\Mautic\Facades\Mautic;
use Triibo\Mautic\Models\MauticConsumer;
// use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function teste(Request $request)
    {
        $factory    = Mautic::getFactory();
        $consumer   = MauticConsumer::whereNotNull('id')->orderBy('created_at', 'desc')->first();

        $expired    = $factory->checkExpirationTime($consumer->expires);

        $consumer   = ($expired) ? $factory->refreshToken($consumer->refresh_token) : $consumer;

        $settings   = config('mautic.connections.main');
        $settings['accessToken'] = $consumer->access_token;

        $initAuth   = new ApiAuth();
        $auth       = $initAuth->newAuth($settings);
        $apiUrl     = $settings['baseUrl'];
        $api        = new MauticApi();
        $contactApi = $api->newApi($request['class'], $auth, $apiUrl);

        // $res = $contactApi->getList('email:teste2@corp.com');
        // $res = $contactApi->getList('pipedrive_id:247');

        // $res = $contactApi->getList('companyemail:pedeminas3@gmail.com');
        // $res = $contactApi->getList('Pe de Minas', 0, 0, 'companyname', 'asc');

        // if (!isset($res['errors']))
        // {
        //     if ($res['total'] == 0)
        //     {
                $res = $contactApi->getList();

        //         foreach ($res[$request['class']] as $id => $obj)
        //         {
        //             if ($obj['fields']['all']['companyname'] == 'Pé de Minas')
        //             {
        //                 $s = $obj;
        //                 break;
        //             }
        //             else
        //             {
        //                 $s = null;
        //             }
        //         }
        //     }
        //     else
        //     {
        //         $s = $res;
        //     }
        // }
        // else
        // {
        //     $s = $res['errors'][0];
        // }

        dd($res);

        // $endpoints = [
        //     "assets",
        //     "campaigns",
        //     "categories",
        //     "companies",
        //     "contacts",
        //     "data",
        //     "emails",
        //     "fields/company",
        //     "fields/contact",
        //     "files/images",
        //     "forms",
        //     "messages",
        //     "notes",
        //     "notifications",
        //     "pages",
        //     "points",
        //     "reports",
        //     "roles",
        //     "segments",
        //     "smses",
        //     "stages",
        //     "stats",
        //     "tags",
        //     "themes",
        //     "tweets",
        //     "users",
        //     "hooks",
        // ];

        // foreach ($endpoints as $e)
        // {
        //     $res     = Mautic::request('GET', $e);
        //     $encoded = json_encode($res, JSON_UNESCAPED_SLASHES);
        //     Storage::put($e . '.json', $encoded);
        //     $result[] = [
        //         $e => 'success'
        //     ];
        // }

        // return print_r($result);
    }
}
