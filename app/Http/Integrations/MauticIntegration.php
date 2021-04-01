<?php

namespace App\Http\Integrations;

use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;
use Triibo\Mautic\Facades\Mautic;
use Triibo\Mautic\Models\MauticConsumer;
use App\Http\Integrations\PipedriveIntegration;

class MauticIntegration
{

    /**
     * Undocumented function
     *
     * @param String $class
     * @return \Mautic\MauticApi $api
     */
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

    /**
     * Undocumented function
     *
     * @param String $class
     * @param Array $data
     * @return Array $res
     */
    public static function updateOrCreate(String $class, Array $data)
    {
        $mauticApi = self::getApi($class);
        $data      = self::dataTranslator($class, $data);

        switch ($class)
        {
            case 'contacts':
                // Busca por id ou busca por email
                $contact = $data['id']              ? $mauticApi->get($data['id'])     : $mauticApi->getList('email:'.$data['email']);

                // Se achou um contato, atualiza. Se não achou, cria um novo
                $res     = ($contact['total'] == 0) ? $mauticApi->edit(0, $data, true) : $mauticApi->edit(self::getId($class, $contact), $data, true);

                PipedriveIntegration::updateMauticId('persons', [
                    'pipe_id'   => $res['contact']['fields']['all']['pipedrive_id'],
                    'mautic_id' => $res['contact']['fields']['all']['id'],
                ]);
            break;

            case 'organizations':
                $res = [];
            break;
        }

        return $res;
    }

    /**
     * Undocumented function
     *
     * @param String $class
     * @param [type] $data
     * @param boolean $delete
     * @return Array $res
     */
    public static function dealUpdate(String $class, Array $data, $delete = false)
    {
        return $res = [];
    }

    /**
     * Undocumented function
     *
     * @param String $class
     * @param [type] $data
     * @param boolean $delete
     * @return Array $res
     */
    public static function stageUpdate(String $class, Array $data, $delete = false)
    {
        $idField   = 185; // id do campo personalizavel no Mautic
        $mauticApi = self::getApi($class);
        $data      = self::dataTranslator($class, $data);
        $field     = $mauticApi->get($idField);
        $list      = $field['field']['properties']['list'];
        $key       = array_search($data['id'], array_column($list, 'value'));

        // Achou o id
        if ($key !== false)
        {
            if (!$delete)
            {
                // Atualiza o stage
                $list[$key]['label'] = $data['name'];
            }
            else
            {
                // Exclui o stage
                unset($list[$key]);
            }
        }
        else if (!$delete)
        {
            // Adiciona o stage
            array_push($list, [
                'label' => $data['name'],
                'value' => $data['id']
            ]);
        }

        $data = [
            'properties' => [
                'list' => $list
            ]
        ];

        $res = $mauticApi->edit($idField, $data);
    }

    /**
     * Undocumented function
     *
     * @param String $class
     * @param Array $data
     * @return void
     */
    public static function delete(String $class, Array $data)
    {
        $mauticApi = self::getApi($class);
        $data      = self::dataTranslator($class, $data);

        switch ($class)
        {
            case 'contacts':
                if ($data['id'])
                {
                    $res     = $mauticApi->delete($data['id']);
                }
                else
                {
                    $contact = $mauticApi->getList('email:'.$data['email']);
                    $res     = ($contact['total'] == 0) ? null : $mauticApi->delete(self::getId($class, $contact));
                }
            break;

            case 'organizations':
                return [];
            break;
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
            case 'deals':
                return [];
            break;

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

            case 'organizations':
                return [];
            break;

            // stages
            case 'contactFields':
                return [
                    "id"   => $data["id"],
                    "name" => $data["name"],
                ];
            break;
        }
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
