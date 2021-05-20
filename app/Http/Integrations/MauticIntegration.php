<?php

namespace App\Http\Integrations;

use Exception;
use Mautic\Api\Api;
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
     * Pega a api especifica dada a classe
     *
     * @param string $class
     * @return Api $api
     */
    private static function getApi(string $class)
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
     * Cria ou atualiza dada a classe
     *
     * @param string $class
     * @param array $data
     * @return array $res
     */
    public static function updateOrCreate(string $class, array $data)
    {
        $mauticApi = self::getApi($class);
        $data      = self::dataTranslator($class, $data);

        switch ($class)
        {
            case 'contacts':
                $res = NULL;

                // Verifica se tem mautic_id
                if ($data['id'])
                {
                    Log::info('Persons - searching by id - mautic_id:' . $data['id'] . ' | pipedrive_id:' . $data['pipedrive_id']);
                    // Busca por id
                    $res = self::searchMauticByField($mauticApi, 'ids', $data['id']);
                }
                // Verifica se tem email
                else if ($data['email'])
                {
                    Log::info('Persons - searching by email - mautic_id:NULL | pipedrive_id:' . $data['pipedrive_id']);
                    // Busca por email
                    $res = self::searchMauticByField($mauticApi, 'email', $data['email']);
                }
                else
                {
                    Log::info('Persons - no data to search - mautic_id:NULL | pipedrive_id:' . $data['pipedrive_id']);
                    break;
                }

                // $first = strpos(strtolower($data['firstname']), 'zteste'); // remover em prod
                // $last  = strpos(strtolower($data['lastname']),  'zteste'); // remover em prod
                // if ($first !== FALSE || $last !== FALSE)                   // remover em prod
                // {                                                          // remover em prod
                    if (!$res['total'])
                    {
                        // Não achou o contato, cria um novo
                        self::saveCreateOrUpdateMautic($mauticApi, 'persons', 0, $data, 'pipedrive_id');
                    }
                    else
                    {
                        // Achou o contato, atualiza

                        $aux = $res['object'][key($res['object'])]['fields']['all'];
                        $data['pipeline']        = $aux['pipeline'];
                        $data['pipeline_status'] = $aux['pipeline_status'];

                        self::saveCreateOrUpdateMautic($mauticApi, 'persons', key($res['object']), $data, 'pipedrive_id');
                    }
                // }
            break;

            case 'companies':
                // Verifica se tem mautic_id
                if ($data['id'])
                {
                    Log::info('Organizations - searching by id - mautic_id:' . $data['id'] . ' | pipedrive_org_id:' . $data['pipedrive_org_id']);
                    // Busca por id
                    $res = self::searchMauticByField($mauticApi, 'ids', $data['id']);
                }
                else
                {
                    Log::info('Organizations - searching by name - mautic_id:NULL | pipedrive_org_id:' . $data['pipedrive_org_id']);
                    // Busca por nome
                    $res = self::searchMauticByField($mauticApi);
                }

                // $first = strpos(strtolower($data['companyname']), 'zteste'); // remover em prod
                // if ($first !== FALSE)                                        // remover em prod
                // {                                                            // remover em prod
                    if (!$res['total'])
                    {
                        // Não achou a empresa, cria uma nova
                        self::saveCreateOrUpdateMautic($mauticApi, 'organizations', 0, $data, 'pipedrive_org_id');
                    }
                    else
                    {
                        if (count($res['object']) > 1)
                        {
                            foreach ($res['object'] as $company)
                            {
                                if ($company['fields']['all']['companyname'] == $data['companyname'])
                                {
                                    $id = $company['id'];
                                    break;
                                }
                            }

                            $id = $id ?? 0;
                        }
                        else
                        {
                            $id = key($res['object']);
                        }

                        // Achou a empresa, atualiza
                        self::saveCreateOrUpdateMautic($mauticApi, 'organizations', $id, $data, 'pipedrive_org_id');
                    }
                // }
            break;
        }
    }

    /**
     * Atualiza o estágio de um contato
     *
     * @param string $class
     * @param array $data
     * @param boolean $delete
     * @return array $res
     */
    public static function dealUpdate(string $class, array $data, bool $delete = false)
    {
        $mauticApi = self::getApi('contacts');
        $data      = self::dataTranslator($class, $data);

        // Se não está ativo e foi excluido
        if (!$data['aux_active'] && $data['aux_deleted'])
        {
            Log::info('Deals deleted - mautic_id:' . $data['id'] . ' | pipedrive_id:' . $data['pipedrive_id']);

            $data['pipeline'] = NULL;
        }

        unset($data['aux_active']);
        unset($data['aux_deleted']);

        // Verifica se tem mautic_id
        if ($data['id'])
        {
            Log::info('Deals - searching by id - mautic_id:' . $data['id'] . ' | pipedrive_id:' . $data['pipedrive_id']);

            // Busca por id
            $res = self::searchMauticByField($mauticApi, 'ids', $data['id']);
        }
        else if ($data['aux_email'])
        {
            Log::info('Deals - searching by email - mautic_id:NULL | pipedrive_id:' . $data['pipedrive_id']);

            // Busca por email
            $res = self::searchMauticByField($mauticApi, 'email', $data['aux_email']);

            if ($res['total'] && !$delete) self::updateMauticId('persons', key($res['object']), $data['pipedrive_id'], TRUE);

            unset($data['aux_email']);
        }
        else
        {
            Log::info('Deals - no data to search - mautic_id:NULL | pipedrive_id:' . $data['pipedrive_id']);
            return NULL;
        }

        $data['id'] = $data['id'] ?? key($res['object']);

        // $first = strpos(strtolower($data['firstname']), 'zteste'); // remover em prod
        // $last  = strpos(strtolower($data['lastname']),  'zteste'); // remover em prod

        // if ($first !== FALSE || $last !== FALSE)                   // remover em prod
        // {                                                          // remover em prod
        //     unset($data['firstname']);                             // remover em prod
        //     unset($data['lastname']);                              // remover em prod

            if ($res['total'])
            {
                if (!$delete)
                {
                    Log::info("Deals - contact pipeline updated | mautic_id:" . $data['id'] . " | pipedrive_id:" . $data['pipedrive_id']);
                }
                else
                {
                    Log::info("Deals - contact pipeline deleted | mautic_id:" . $data['id'] . " | pipedrive_id:" . $data['pipedrive_id']);

                    $data['pipeline']        = 0;
                    $data['pipeline_status'] = ' ';
                }

                $res = $mauticApi->edit(key($res['object']), $data);
            }
            else
            {
                Log::info("Deals - contact not found - mautic_id:" . $data['id'] . " | pipedrive_id:" . $data['pipedrive_id']);
                // throw new Exception("Contact not found - mautic_id:" . $data['id'] . " | pipedrive_id:" . $data['pipedrive_id']);
            }
        // }
    }

    /**
     * Atualiza um estágio
     *
     * @param string $class
     * @param array $data
     * @param boolean $delete
     * @return array $res
     */
    public static function stageUpdate(string $class, array $data, bool $delete = false)
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

        $mauticApi->edit($idField, $data);
    }

    /**
     * Exclui o dado segundo a classe passada
     *
     * @param string $class
     * @param array $data
     * @return void
     */
    public static function delete(string $class, array $data)
    {
        $mauticApi = self::getApi($class);
        $data      = self::dataTranslator($class, $data);

        switch ($class)
        {
            case 'contacts':
                if ($data['id'])
                {
                    Log::info("Persons - delete by id - mautic_id:" . $data['id'] . " | pipedrive_id:" . $data['pipedrive_id']);

                    $mauticApi->delete($data['id']);
                }
                else if ($data['email'])
                {
                    $res = self::searchMauticByField($mauticApi, 'email', $data['email']);

                    if ($res['total'])
                    {
                        Log::info("Persons - delete by email - mautic_id:NULL | pipedrive_id:" . $data['pipedrive_id']);

                        $mauticApi->delete(key($res['object']));
                    }
                }
                else
                {
                    Log::info("Persons - not found to delete - mautic_id: | pipedrive_id:" . $data['pipedrive_id']);
                }
            break;

            case 'companies':
                if ($data['id'])
                {
                    Log::info("Organizations - delete by id - mautic_id:" . $data['id'] . " | pipedrive_org_id:" . $data['pipedrive_org_id']);

                    $mauticApi->delete($data['id']);
                }
                else
                {
                    $res = self::searchMauticByField($mauticApi);

                    if ($res['total'])
                    {
                        foreach ($res['object'] as $id => $company)
                        {
                            if ($company['fields']['all']['companyname'] == $data['companyname'])
                            {
                                Log::info("Organizations - deleted by name - mautic_id:NULL | pipedrive_org_id:" . $data['pipedrive_org_id']);

                                $mauticApi->delete($id);
                                break;
                            }
                        }
                    }

                    Log::info("Organizations - not found to delete - mautic_id: | pipedrive_id:" . $data['pipedrive_org_id']);
                }
            break;
        }
    }

    /**
     * Traduz os dados necessários do Pipedrive para o Mautic
     *
     * @param string $class
     * @param array $data
     * @return array
     */
    private static function dataTranslator(string $class, array $data)
    {
        switch ($class) {
            case 'deals':
                // $aux                = explode(' ', $data['person_name']);
                // $data['last_name']  = end($aux);
                // array_pop($aux);
                // $data['first_name'] = implode(' ', $aux);

                return [
                    'id'                => $data['person_mautic_id']??'', // custom field for mautic_id
                    'pipeline'          => $data['stage_id'],
                    'pipeline_status'   => self::statusDeal($data['status']??''),
                    'pipedrive_id'      => $data['person_id'],

                    'aux_active'        => $data['active'],
                    'aux_deleted'       => $data['deleted'],
                    'aux_email'         => $data['person_email'],

                    // 'firstname'         => $data['first_name'],  // remover em prod
                    // 'lastname'          => $data['last_name'],   // remover em prod
                ];
            break;

            case 'contacts':
                if (!$data['first_name'])
                {
                    $name               = explode(' ', $data['last_name']);
                    $data['first_name'] = current($name);
                    array_shift($name);
                    $data['last_name']  = implode(' ', $name);
                }

                $id = $data['bd7287f0a9a02de60a8aff83b5905c489b7cf007'];

                if (!$id)
                    $id = NULL;

                return [
                    'id'                => $id, // custom field for mautic_id
                    'firstname'         => $data['first_name'],
                    'lastname'          => $data['last_name'],
                    // 'company'           => $data['org_name'],
                    'email'             => $data['email'][0]['value'],
                    'phone'             => preg_replace("/\D/", "", $data['phone'][0]['value']),
                    'pipedrive_id'      => $data['id'],
                    // 'overwriteWithBlank' => true,
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
                    'pipedrive_org_id'  => $data['id'],
                ];
            break;

            // stages
            case 'contactFields':
                return [
                    'id'                => $data['id'],
                    'name'              => $data['name'],
                ];
            break;
        }
    }

    /**
     * Procura no mautic dado um campo de filtro
     *
     * @param Api $mauticApi
     * @param string $field
     * @param string $search
     * @param int $start
     * @param int $end
     * @return void
     */
    private static function searchMauticByField(Api $mauticApi, string $field = '', string $search = '', int $start = 0, int $end = 100000)
    {
        if (empty($field))
            $obj = $mauticApi->getList("", $start, $end);
        else
            $obj = $mauticApi->getList("$field:$search");

        $ttl = (isset($obj['total'])) ? (int) $obj['total'] : NULL;

        $aux = ($mauticApi->listName() == 'contacts') ? 'Persons' : 'Organizations';

        Log::info("$aux - searching - result total:$ttl");

        $obj = $obj[$mauticApi->listName()]??NULL;

        return [
            'total'  => $ttl,
            'object' => $obj
        ];
    }

    /**
     * Cria ou atualiza o contato no Mautic
     * E chama a atualização do mautic_id no Pipedrive
     *
     * @param Api $mauticApi
     * @param string $class
     * @param int $id
     * @param array $data
     * @param string $pipeIdName
     * @return void
     */
    private static function saveCreateOrUpdateMautic(Api $mauticApi, string $class, int $id, array $data, string $pipeIdName)
    {
        $aux = ($class == 'persons') ? 'contact' : 'company';

        $res = $mauticApi->edit($id, $data, (!$id ? TRUE : FALSE));

        if (!isset($res['errors']))
        {
            // Para não atualizar o mautic_id no Pipedrive, comentar linha abaixo
            self::updateMauticId($class, $res[$aux]['id'], $data[$pipeIdName], is_null($data['id']));
        }
    }

    /**
     * Atualiza o mautic_id no Pipedrive
     *
     * @param string $class
     * @param int $mauticId
     * @param int $pipeId
     * @param boolean $hasId
     * @return void
     */
    private static function updateMauticId(string $class, int $mauticId, int $pipeId, bool $hasId)
    {
        $auxLog = ucfirst($class);

        if ($class == 'persons')
            $pipeName = 'pipedrive_id';
        else
            $pipeName = 'pipedrive_org_id';

        Log::info("$auxLog - created/updated | mautic_id:$mauticId | $pipeName:$pipeId");

        if ($hasId)
        {
            PipedriveIntegration::updateMauticId($class, [
                'pipe_id'   => $pipeId,
                'mautic_id' => $mauticId,
            ]);
        }
    }

    /**
     * Traduz status do Deal
     *
     * @param string $status
     * @return void
     */
    private static function statusDeal(string $status)
    {
        $new = '';

        switch ($status) {
            case 'open':
                $new = 'Aberto';
            break;

            case 'won':
                $new = 'Ganho';
            break;

            case 'lost':
                $new = 'Perdido';
            break;

            default:
                $new = NULL;
            break;
        }

        return $new;
    }


    /**
     * Sincroniza os ids do Mautic (pipedrive_id) e do Pipedrive (mautic_id)
     *
     * @param array $classes
     * @return void
     */
    public static function syncIdsMauticPipe(array $classes)
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

        $pipeApi   = new Pipedrive(config('pipedrive.token'));

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

    /**
     * Importa todos os Stages do Pipedrive
     *
     * @return void
     */
    public static function importAllStages()
    {
        Log::info("Stages - start importing all stages");

        $idField   = 185; // id do campo personalizavel no Mautic
        $mauticApi = self::getApi('contactFields');
        $field     = $mauticApi->get($idField);
        $list      = $field['field']['properties']['list'];

        $pipeApi   = new Pipedrive(config('pipedrive.token'));
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

        $res  = $mauticApi->edit($idField, $data);

        if (!isset($res['errors']))
        {
            Log::info("Stages - end of importing all stages");
        }
        else
        {
            Log::info("Stages - import failed");
        }
    }

}
