<?php

namespace App\Http\Integrations;

use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;
use Triibo\Mautic\Facades\Mautic;
use Triibo\Mautic\Models\MauticConsumer;

class MauticIntegration
{

    private static function getApi(String $class)
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

        return $api->newApi($class, $auth, $apiUrl);
    }

    public static function updateOrCreate(String $class, Array $data)
    {
        $mauticApi = self::getApi($class);
        $data      = self::dataTranslator($class, $data);

        /**
         * Busca por id ou busca por email
         */
        $contact   = $data['id']              ? $mauticApi->get($data['id'])     : $mauticApi->getList('email:'.$data['email']);

        /**
         * Se achou um contato, atualiza
         * Se não achou, cria um novo
         */
        $res       = ($contact['total'] == 0) ? $mauticApi->edit(0, $data, true) : $mauticApi->edit(self::getId($class, $contact), $data, true) ;

        // if ($data['id']) {
        //     $contact = $mauticApi->get($data['id']);
        // } else {
        //     $contact = $mauticApi->getList('email:'.$data['email']);
        // }
        //
        // if ($contact['total'] == 0) {
        //     $res = $mauticApi->edit(0, $data, true);
        // } else {
        //     $id  = self::getId($class, $contact);
        //     $res = $mauticApi->edit($id, $data, true);
        // }

        return $res;
    }

    public static function delete(String $class, Array $data)
    {
        $mauticApi = self::getApi($class);
        $data      = self::dataTranslator($class, $data);

        if ($data['id'])
        {
            $res     = $mauticApi->delete($data['id']);
        }
        else
        {
            $contact = $mauticApi->getList('email:'.$data['email']);
            $res     = ($contact['total'] == 0) ? null : $mauticApi->delete(self::getId($class, $contact));
        }

        return $res;
    }


    /**
     * Undocumented function
     *
     * @param Array $data
     * @return Array $person
     */
    private static function dataTranslator(String $class, Array $data)
    {
        switch ($class) {
            case 'contacts':
                return [
                    "id"           => $data['bd7287f0a9a02de60a8aff83b5905c489b7cf007'], // custom field for mautic_id
                    "firstname"    => $data['first_name'],
                    "lastname"     => $data['last_name'],
                    "company"      => $data['org_name'],
                    "email"        => $data['email'][0]['value'],
                    "phone"        => $data['phone'][0]['value'],
                    "pipedrive_id" => $data['id']
                ];
            break;

            case 'deals':
                return [];
            break;

            case 'organizations':
                return [];
            break;

            case 'stages':
                return [];
            break;
        }

        // return [
        //     "id"           => $data['bd7287f0a9a02de60a8aff83b5905c489b7cf007'], // custom field for mautic_id
        //     "firstname"    => $data['first_name'],
        //     "lastname"     => $data['last_name'],
        //     "company"      => ($data['org_name']),
        //     "email"        => $data['email'][0]['value'],
        //     "phone"        => $data['phone'][0]['value'],
        //     "pipedrive_id" => $data['id']
        // ];
    }

    /**
     * Undocumented function
     *
     * @param String $class
     * @param Array $data
     * @return Integer $id
     */
    private static function getId(String $class, Array $data)
    {
        foreach ($data[$class] as $id => $obj)
        {
            return $id;
        }
    }

}