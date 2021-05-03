<?php

namespace App\Http\Integrations;

use Exception;
use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;
use Devio\Pipedrive\Pipedrive;
use Triibo\Mautic\Facades\Mautic;
use Illuminate\Support\Facades\Log;
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

        // dd($api->newApi($class, $auth, $apiUrl));

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

        $total     = 0;

        switch ($class)
        {
            case 'contacts':
                $contactId = 0;

                // Verifica se tem mautic_id
                if ($data['id'] != NULL)
                {
                    Log::info('Persons - searching by id - mautic_id:' . $data['id'] . ' | pipedrive_id:' . $data['pipedrive_id']);

                    // Busca por id
                    $res       = self::searchMauticByField($mauticApi, 'ids', $data['id']);
                    $total     = $res['total'];
                    $contactId = self::getId($res['object']);
                }
                // Verifica se tem email
                else if ($data['email'] != NULL)
                {
                    Log::info('Persons - searching by email - mautic_id:NULL | pipedrive_id:' . $data['pipedrive_id']);

                    // Busca por email
                    $res       = self::searchMauticByField($mauticApi, 'email', $data['email']);
                    $total     = $res['total'];
                    $contactId = self::getId($res['object']);
                }

                $first = strpos(strtolower($data['firstname']), 'zteste'); // remover em prod
                $last  = strpos(strtolower($data['lastname']),  'zteste'); // remover em prod

                if ($first !== FALSE || $last !== FALSE)                   // remover em prod
                {                                                          // remover em prod
                    if ($total == 0 || $total == NULL)
                    {
                        // Não achou o contato, cria um novo
                        self::saveCreateOrUpdateMautic($mauticApi, 'persons', 0, $data, 'pipedrive_id');
                    }
                    else
                    {
                        // Achou o contato, atualiza
                        self::saveCreateOrUpdateMautic($mauticApi, 'persons', $contactId, $data, 'pipedrive_id');
                    }
                }
            break;

            case 'companies':
                $companyId = 0;

                if ($data['id'] != NULL)
                {
                    Log::info('Organizations - searching by id - mautic_id:' . $data['id'] . ' | pipedrive_org_id:' . $data['pipedrive_org_id']);

                    // Busca por id
                    $res       = self::searchMauticByField($mauticApi, 'ids', $data['id']);
                    $total     = $res['total'];
                    $companyId = $res['object'];
                }
                else
                {
                    Log::info('Organizations - searching by name - mautic_id:NULL | pipedrive_org_id:' . $data['pipedrive_org_id']);

                    // Busca por nome
                    $res       = self::searchMauticByField($mauticApi);
                    $total     = $res['total'];
                    $companies = $res['object'];

                    foreach ($companies as $id => $comp)
                    {
                        if ($comp['fields']['all']['companyname'] == $data['companyname'])
                        {
                            $companyId = self::getId($comp);
                            break;
                        }
                    }
                }

                $first = strpos(strtolower($data['firstname']), 'zteste'); // remover em prod
                $last  = strpos(strtolower($data['lastname']),  'zteste'); // remover em prod

                if ($first !== FALSE || $last !== FALSE)                   // remover em prod
                {                                                          // remover em prod
                    if ($total == 0 || $total == NULL)
                    {
                        // Não achou a empresa, cria uma nova
                        self::saveCreateOrUpdateMautic($mauticApi, 'organizations', 0, $data, 'pipedrive_org_id');
                    }
                    else
                    {
                        // Achou a empresa, atualiza
                        self::saveCreateOrUpdateMautic($mauticApi, 'organizations', $companyId, $data, 'pipedrive_org_id');
                    }
                }
            break;
        }
    }

    /**
     * Undocumented function
     *
     * @param String $class
     * @param Array $data
     * @param boolean $delete
     * @return Array $res
     */
    public static function dealUpdate(String $class, Array $data, $delete = false)
    {
        //
    }

    /**
     * Undocumented function
     *
     * @param String $class
     * @param Array $data
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
                if ($data['id'] != NULL)
                {
                    $mauticApi->delete($data['id']);
                }
                else if ($data['email'] != NULL)
                {
                    $res = self::searchMauticByField($mauticApi, 'email', $data['email']);

                    if ($res['total'] != NULL)
                    {
                        $mauticApi->delete(self::getId($res['object']));
                    }
                }
            break;

            case 'organizations':
                if ($data['id'] != NULL)
                {
                    $mauticApi->delete($data['id']);
                }
                else
                {
                    $res       = self::searchMauticByField($mauticApi);
                    $total     = $res['total'];
                    $companies = $res['object'];

                    foreach ($companies as $id => $comp)
                    {
                        if ($comp['fields']['all']['companyname'] == $data['companyname'])
                        {
                            $mauticApi->delete(self::getId($comp));
                            break;
                        }
                    }
                }
            break;
        }
    }


    /**
     * Traduz os dados necessários do Pipedrive para o Mautic
     *
     * @param String $class
     * @param Array $data
     * @return Array
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

    /**
     * Undocumented function
     *
     * @param MauticApi $mauticApi
     * @param String $field
     * @param String $search
     * @param Integer $start
     * @param Integer $end
     * @return void
     */
    private static function searchMauticByField($mauticApi, $field = '', $search = '', $start = 0, $end = 100000)
    {
        if (empty($field))
            $obj = $mauticApi->getList("", $start, $end);
        else
            $obj = $mauticApi->getList("$field:$search");

        $ttl = (isset($obj['total'])) ? (int) $obj['total'] : NULL;

        $obj = $obj[$mauticApi->listName()];

        return [
            'total'  => $ttl,
            'object' => $obj
        ];
    }

    /**
     * Cria ou atualiza o contato no Mautic
     * E chama a atualização do mautic_id no Pipedrive
     *
     * @param MauticApi $mauticApi
     * @param String $class
     * @param Int $id
     * @param Array $data
     * @param String $pipeIdName
     * @return void
     */
    private static function saveCreateOrUpdateMautic($mauticApi, $class, $id, $data, $pipeIdName)
    {
        $res = $mauticApi->edit($id, $data, TRUE);

        // Para não atualizar o mautic_id no Pipedrive, comentar linha abaixo
        self::updateMauticId($class, $res, $data[$pipeIdName], is_null($data['id']));
    }

    /**
     * Atualiza o mautic_id no Pipedrive
     *
     * @param String $class
     * @param Array $res
     * @param String $crud
     * @param Int $pipeId
     * @param Boolean $mauticId
     * @return void
     */
    private static function updateMauticId(String $class, Array $res, Int $pipeId, Bool $mauticId)
    {
        $aux = ucfirst($class);

        if (!isset($res['errors']))
        {
            $ids = $res['contact']['fields']['all'];
            Log::info("$aux - created/updated | without errors | mautic_id:" . $ids['id'] . " | pipedrive_id:$pipeId");

            if ($mauticId)
            {
                PipedriveIntegration::updateMauticId($class, [
                    'pipe_id'   => $ids['pipedrive_id'],
                    'mautic_id' => $ids['id'],
                ]);
            }
        }
        else
        {
            Log::info("$aux - created/updated | with errors | mautic_id: | pipedrive_id:$pipeId | " . $res['errors']);
        }
    }


    /**
     * Sincroniza os ids do Mautic (pipedrive_id) e do Pipedrive (mautic_id)
     *
     * @param Array $classes
     * @return void
     */
    public static function syncIdsMauticPipe(Array $classes)
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
                        $total   = (isset($contact['total'])) ? $contact['total'] : NULL;
                        $contact = $contact[$mauticApi->listName()];

                        if ($total > 0 || $total != NULL)
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
                            $dataM      = self::dataTranslator($mauticClass, json_decode(json_encode($obj), true));
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


    public static function importAllStages()
    {
        $idField   = 185; // id do campo personalizavel no Mautic
        $mauticApi = self::getApi('contactFields');
        $field     = $mauticApi->get($idField);
        $list      = $field['field']['properties']['list'];

        $token     = config('pipedrive.token');
        $pipeApi   = new Pipedrive($token);
        $stages    = $pipeApi->stages()->all()->getData();

        foreach ($stages as &$stage)
        {
            // $stage = self::dataTranslator('contactFields', $stage);
            $stage = [
                'id'   => $stage->id,
                'name' => $stage->name
            ];
        }

        foreach ($stages as $s)
        {
            $key = array_search($s['id'], array_column($list, 'value'));

            if ($key !== false)
            {
                $list[$key]['label'] = $s['name'];
            }
            else
            {
                array_push($list, [
                    'label' => $s['name'],
                    'value' => $s['id']
                ]);
            }
        }

        usort($list, function($a, $b) {
            return $a['value'] <=> $b['value'];
        });

        $data = [
            'properties' => [
                'list' => $list
            ]
        ];

        $res = $mauticApi->edit($idField, $data);
    }

}
