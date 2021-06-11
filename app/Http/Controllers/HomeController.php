<?php

namespace App\Http\Controllers;

use Exception;
use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;
use Illuminate\Http\Request;
use Devio\Pipedrive\Pipedrive;
use Triibo\Mautic\Facades\Mautic;
use Illuminate\Support\Facades\Log;
use Triibo\Mautic\Models\MauticConsumer;
use App\Http\Integrations\MauticIntegration;
use Ramsey\Collection\Collection;

// use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    const PERSON_ADDED_EVENT   = 'added.person';
    const PERSON_UPDATED_EVENT = 'updated.person';
    const PERSON_DELETED_EVENT = 'deleted.person';
    const PERSON_CODE          = 3;
    const ORG_ADDED_EVENT      = 'added.organization';
    const ORG_UPDATED_EVENT    = 'updated.organization';
    const ORG_DELETED_EVENT    = 'deleted.organization';
    const ORG_CODE             = 2;

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
     * Pega a api especifica dada a classe
     *
     * @param string $class
     * @return Api $api
     */
    private static function getMauticApi(string $class)
    {
        $factory  = Mautic::getFactory();
        $consumer = MauticConsumer::whereNotNull('id')->orderBy('created_at', 'desc')->first();

        $expired  = $factory->checkExpirationTime($consumer->expires);

        $consumer = ($expired) ? $factory->refreshToken($consumer->refresh_token) : $consumer;

        $settings = config('mautic.connections.main');
        $settings['accessToken'] = $consumer->access_token;

        $initAuth = new ApiAuth();
        $auth     = $initAuth->newAuth($settings);
        $apiUrl   = $settings['baseUrl'];
        $api      = new MauticApi();

        // dd($api->newApi($class, $auth, $apiUrl));

        return $api->newApi($class, $auth, $apiUrl);
    }

    private static function getPipeApi()
    {
        return new Pipedrive(config('pipedrive.token'));
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

    public function teste3(Request $request)
    {

        // // dd(ini_get("max_execution_time"));
        // // 11954971 Pedro
        // // 12009079 Tiago

        // $start = 0;
        // $res   = [];
        // $exit  = false;

        // do
        // {
        //     $response = (self::getPipeApi())->deals->all([
        //         // 'user_id' => 12009079,
        //         'status' => 'lost',
        //         'start'  => $start,
        //         'limit'  => 500,
        //     ]);

        //     $objs = $response->getData();

        //     foreach ($objs as &$obj)
        //     {
        //         $aux = json_decode(json_encode($obj), true);
        //         $obj = $aux;

        //         $pId = $obj['person_id']['value']??NULL;
        //         unset($obj['person_id']);
        //         $obj['person_id'] = $pId;
        //     }

        //     $res = array_merge($res, $objs);

        //     if (count($objs) < 500)
        //     {
        //         $exit = true;
        //     }
        //     else
        //     {
        //         $start += 500;
        //     }

        // } while (!$exit);

        // $api = new Pipedrive(config('pipedrive.token'));

        // foreach ( $res as $deal )
        // {
        //     $person    = $api->persons->find($deal['person_id'])->getData();

        //     $auxPerson = json_decode(json_encode($person), true);
        //     MauticIntegration::updateOrCreate('contacts', $auxPerson);

        //     $deal['person_email']     = $person->email[0]->value;
        //     $deal['person_mautic_id'] = $person->bd7287f0a9a02de60a8aff83b5905c489b7cf007;
        //     MauticIntegration::dealUpdate('deals', $deal);

        //     $mauticId  = $auxPerson['bd7287f0a9a02de60a8aff83b5905c489b7cf007'];
        //     $pipeId    = $auxPerson['id'];

        //     Log::info("Persons - mautic_id:$mauticId | pipedrive_id:$pipeId");
        // }
    }

    public function teste2()
    {
        $token     = config('pipedrive.token');
        $pipedrive = new Pipedrive($token);

        $res = $pipedrive->persons->update(465, [
            'last_name' => '9.3-API'
        ]);

        dd($res->getData());

        return $res;
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

    public function atualizaEmailComEspacoEmBranco(Request $request)
    {
        $token     = config('pipedrive.token');
        $pipedrive = new Pipedrive($token);
        $arr       = [];
        for ($i = 1; $i <= 600; $i++)
        {
            try
            {
                $person = $pipedrive->persons->find($i);
                $found = true;
            }
            catch (Exception $e)
            {
                $found = false;
            }
            if ($found)
            {
                $data  = $person->getData();
                $email = $data->email[0]->value;
                if (preg_match('/\s/',$email))
                {
                    $arr[] = $email;
                    $new   = $pipedrive->persons->update($i, ['email' => trim($email)]);
                    $arr[] = $new->getData()->email[0]->value;
                }
            }
            else
            {
                continue;
            }
        }
        dd($arr);
    }
}
