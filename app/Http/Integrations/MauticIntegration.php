<?php

namespace App\Http\Integrations;

use Exception;
use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;
use Devio\Pipedrive\Pipedrive;
use Triibo\Mautic\Facades\Mautic;
use Mautic\QueryBuilder\QueryBuilder;
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
                // $contact = $data['id']              ? $mauticApi->get($data['id'])     : $mauticApi->getList('email:'.$data['email']);
                if ($data['id'])
                {
                    $contact = $mauticApi->get($data['id']);
                    $total   = (isset($contact['total'])) ? $contact['total'] : null;
                    $contact = $contact[$mauticApi->itemName()];
                }
                else
                {
                    $contact = $mauticApi->getList('email:'.$data['email']);
                    $total   = $contact['total'];
                    $contact = $contact[$mauticApi->listName()];
                }

                // Se achou um contato, atualiza. Se não achou, cria um novo
                $res = ($total == 0) ? $mauticApi->edit(0, $data, true) : $mauticApi->edit(self::getId($contact), $data, true);

                PipedriveIntegration::updateMauticId('persons', [
                    'pipe_id'   => $res['contact']['fields']['all']['pipedrive_id'],
                    'mautic_id' => $res['contact']['fields']['all']['id'],
                ]);
            break;

            case 'companies':
                if ($data['id'])
                {
                    // Busca por id
                    $company = $mauticApi->get($data['id']);
                    $total   = (isset($company['total'])) ? $company['total'] : null;
                    $company = $company[$mauticApi->itemName()];
                }
                else
                {
                    // Busca por nome
                    $companies = $mauticApi->getList('', 0, 100000);
                    $total     = $companies['total'];
                    $companies = $companies[$mauticApi->listName()];

                    foreach ($companies as $id => $comp)
                    {
                        if ($comp['fields']['all']['companyname'] == $data['companyname'])
                        {
                            $company = $comp;
                            break;
                        }
                    }
                }

                $res = ($total == 0) ? $mauticApi->edit(0, $data, true) : $mauticApi->edit(self::getId($company), $data, true);

                PipedriveIntegration::updateMauticId('organizations', [
                    'pipe_id'   => $res['company']['fields']['all']['pipedrive_org_id'],
                    'mautic_id' => $res['company']['fields']['all']['id'],
                ]);
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
                    $res     = ($contact['total'] == 0) ? null : $mauticApi->delete(self::getId($contact));
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
                return [
                    'pipedrive_id' => $data['person_id'],
                    'value'        => $data['stage_id'],
                    'status'       => $data['status'],
                    // '' => $data[''],
                ];
            break;

            case 'contacts':
                return [
                    'id'           => $data['bd7287f0a9a02de60a8aff83b5905c489b7cf007'], // custom field for mautic_id
                    'firstname'    => $data['first_name'],
                    'lastname'     => $data['last_name'],
                    'company'      => $data['org_name'],
                    'email'        => $data['email'][0]['value'],
                    'phone'        => $data['phone'][0]['value'],
                    'pipedrive_id' => $data['id']
                ];
            break;

            case 'companies':
                return [
                    'id'                => $data['918dace587a4707755926d7c02c3bb46ed53d539'], // custom field for mautic_id
                    'companyname'       => $data['name'],
                    'companyaddress1'   => $data['address_formatted_address'],
                    'companyemail'      => $data['a007cb7cef80c909678503cbcfe49def03cad573'], // custom field for email
                    'companyphone'      => $data['a93024fa0b5785ae4b3b1b49a18bb5267b87dfdd'], // custom field for fone
                    // 'companycity'       => $data['address_admin_area_level_1'],
                    // 'companystate'      => $data['address_admin_area_level_2'],
                    // 'companyzipcode'    => $data['address_postal_code'],
                    // 'companycountry'    => $data['address_country'],
                    'pipedrive_org_id' => $data['id'],
                ];
            break;

            // stages
            case 'contactFields':
                return [
                    'id'   => $data['id'],
                    'name' => $data['name'],
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
    private static function getId(Array $data)
    {
        foreach ($data as $id => $value)
        {
            if (gettype($value) == 'array')
            {
                return $id;
            }

            if ($id == 'id')
            {
                return $value;
            }
        }
    }



    public static function updatePipedrive(Array $classes)
    {
        if ($classes['person'] && !$classes['organization'])
        {
            $mauticClass = 'contacts';
            $pipeClass   = 'persons';
        }
        else if (!$classes['person'] && $classes['organization'])
        {
            $mauticClass = 'companies';
            $pipeClass   = 'organizations';
        }
        else
        {
            throw new Exception('Invalid parameters, both true or false');
        }

        $mauticApi = self::getApi($mauticClass);

        $token     = config('pipedrive.token');
        $pipeApi   = new Pipedrive($token);

        $response  = $pipeApi->$pipeClass->all();
        $pipeObjs  = $response->getData();

        switch ($mauticClass) {
            case 'contacts':
                foreach ($pipeObjs as $key => $obj)
                {
                    if ($obj->email[0]->value)
                    {
                        $contact = $mauticApi->getList('email:'.$obj->email[0]->value);
                        $total   = $contact['total'];
                        $contact = $contact[$mauticApi->listName()];

                        if ($total > 0)
                        {
                            foreach ($contact as $id => $value)
                            {
                                $mautic_id = $id;
                                break;
                            }

                            $dataM = [ 'pipedrive_id'                             => $obj->id    ];
                            $dataP = [ 'bd7287f0a9a02de60a8aff83b5905c489b7cf007' => $mautic_id  ];

                            $mauticApi->edit($mautic_id, $dataM, false);
                            $pipeApi->$pipeClass->update($obj->id, $dataP);
                        }
                        else
                        {
                            $dataM = self::dataTranslator($mauticClass, json_decode(json_encode($obj), true));
                            $newContact = $mauticApi->edit(0, $dataM, true);

                            $dataP = [ 'bd7287f0a9a02de60a8aff83b5905c489b7cf007' => $newContact['contact']['id']];
                            $pipeApi->$pipeClass->update($obj->id, $dataP);
                        }
                    }
                }
            break;

            case 'companies':
                $companies = $mauticApi->getList('', 0, 100000);
                $companies = $companies[$mauticApi->listName()];

                foreach ($pipeObjs as $key => $obj)
                {
                    foreach ($companies as $id => $comp)
                    {
                        if ($comp['fields']['all']['companyname'] == $obj->name)
                        {
                            $mautic_id = $comp['fields']['all']['id'];

                            $dataM = [ 'pipedrive_org_id'                         => $obj->id    ];
                            $dataP = [ '918dace587a4707755926d7c02c3bb46ed53d539' => $mautic_id  ];

                            $mauticApi->edit($mautic_id, $dataM, false);
                            $pipeApi->$pipeClass->update($obj->id, $dataP);
                        }
                    }
                }
            break;
        }
    }

}
