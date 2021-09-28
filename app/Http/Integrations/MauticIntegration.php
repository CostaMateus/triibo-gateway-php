<?php

namespace App\Http\Integrations;

use Exception;
use Mautic\Api\Api;
use Mautic\MauticApi;
use App\Helpers\Helper;
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
        $consumer = MauticConsumer::whereNotNull("id")->orderBy("created_at", "desc")->first();

        $expired  = $factory->checkExpirationTime($consumer->expires);

        $consumer = ($expired) ? $factory->refreshToken($consumer->refresh_token) : $consumer;

        $settings = config("mautic.connections.main");
        $settings["accessToken"] = $consumer->access_token;

        $initAuth = new ApiAuth();
        $auth     = $initAuth->newAuth($settings);
        $apiUrl   = $settings["baseUrl"];
        $api      = new MauticApi();

        return $api->newApi($class, $auth, $apiUrl);
    }

    /**
     * Cria ou atualiza dada a classe
     *
     * @param string  $class
     * @param array   $data
     * @param boolean $delete
     * @return array  $res
     */
    public static function updateOrCreate(string $class, array $data, bool $delete)
    {
        $mauticApi = self::getApi($class);
        $data      = self::dataTranslator($class, $data);
        $logMsg    = [];

        // Verifica se tem mautic_id e busca
        if ($data["id"])
        {
            array_push($logMsg, "Searching by id");
            $res = self::searchMauticByField($mauticApi, "ids", $data["id"]);
        }
        // Verifica se tem email e busca
        else if ($data["email"])
        {
            array_push($logMsg, "Searching by email");
            $res = self::searchMauticByField($mauticApi, "email", $data["aux_email"]);

            if ($res["total"]) self::updateMauticId("organizations", key($res["object"]), $data["pd_org_id"], TRUE);
        }
        else
        {
            array_push($logMsg, "No data to search");
            Helper::log("Persons", implode(". ", $logMsg), 0, $data["pd_org_id"]??0);
            return;
        }

        $data["id"] = $data["id"] ?? key($res["object"]);

        // Achou o contato, atualiza
        if ($res["total"])
        {
            if ($delete)
            {
                $data["pd_pipeline"]        = 0;
                $data["pd_pipeline_status"] = 0;
                $data["pd_pipeline_label"]  = 0;
            }

            array_push($logMsg, "Contact updated");
            self::saveCreateOrUpdateMautic($mauticApi, "persons", $data["id"], $data, "pd_org_id");
        }
        // Não achou o contato, cria um novo
        else
        {
            array_push($logMsg, "Contact created");
            self::saveCreateOrUpdateMautic($mauticApi, "persons", 0, $data, "pd_org_id");
        }

        Helper::log("Persons", implode(". ", $logMsg), $data["id"]??0, $data["pd_org_id"]??0);


        // $mauticApi = self::getApi($class);
        // $data      = self::dataTranslator($class, $data);
        //
        // switch ($class)
        // {
        //     case "contacts":
        //         $res = NULL;
        //
        //         // Verifica se tem mautic_id
        //         if ($data["id"])
        //         {
        //             Log::channel("marketplace")->info("Persons", [
        //                 "data"         => "searching by id",
        //                 "mautic_id"    => $data["id"],
        //                 "pipedrive_id" => $data["pipedrive_id"]
        //             ]);
        //             // Busca por id
        //             $res = self::searchMauticByField($mauticApi, "ids", $data["id"]);
        //         }
        //         // Verifica se tem email
        //         else if ($data["email"])
        //         {
        //             Log::channel("marketplace")->info("Persons", [
        //                 "data"         => "searching by email",
        //                 "mautic_id"    => NULL,
        //                 "pipedrive_id" => $data["pipedrive_id"]
        //             ]);
        //             // Busca por email
        //             $res = self::searchMauticByField($mauticApi, "email", $data["email"]);
        //         }
        //         else
        //         {
        //             Log::channel("marketplace")->info("Persons", [
        //                 "data"         => "no data to search",
        //                 "mautic_id"    => NULL,
        //                 "pipedrive_id" => $data["pipedrive_id"]
        //             ]);
        //             break;
        //         }
        //
        //         if (!$res["total"])
        //         {
        //             // Não achou o contato, cria um novo
        //             self::saveCreateOrUpdateMautic($mauticApi, "persons", 0, $data, "pipedrive_id");
        //         }
        //         else
        //         {
        //             // Achou o contato, atualiza
        //             $aux = $res["object"][key($res["object"])]["fields"]["all"];
        //             $data["pipeline"]        = $aux["pipeline"];
        //             $data["pipeline_status"] = $aux["pipeline_status"];
        //
        //             self::saveCreateOrUpdateMautic($mauticApi, "persons", key($res["object"]), $data, "pipedrive_id");
        //         }
        //     break;
        //
        //     case "companies":
        //         // Verifica se tem mautic_id
        //         if ($data["id"])
        //         {
        //             Log::channel("marketplace")->info("Organizations", [
        //                 "data"             => "searching by id",
        //                 "mautic_id"        => $data["id"],
        //                 "pipedrive_org_id" => $data["pipedrive_org_id"]
        //             ]);
        //             // Busca por id
        //             $res = self::searchMauticByField($mauticApi, "ids", $data["id"]);
        //         }
        //         else
        //         {
        //             Log::channel("marketplace")->info("Organizations", [
        //                 "data"             => "searching by name",
        //                 "mautic_id"        => NULL,
        //                 "pipedrive_org_id" => $data["pipedrive_org_id"]
        //             ]);
        //             // Busca por nome
        //             $res = self::searchMauticByField($mauticApi);
        //         }
        //
        //         if (!$res["total"])
        //         {
        //             // Não achou a empresa, cria uma nova
        //             self::saveCreateOrUpdateMautic($mauticApi, "organizations", 0, $data, "pipedrive_org_id");
        //         }
        //         else
        //         {
        //             if (count($res["object"]) > 1)
        //             {
        //                 foreach ($res["object"] as $company)
        //                 {
        //                     if ($company["fields"]["all"]["companyname"] == $data["companyname"])
        //                     {
        //                         $id = $company["id"];
        //                         break;
        //                     }
        //                 }
        //
        //                 $id = $id ?? 0;
        //             }
        //             else
        //             {
        //                 $id = key($res["object"]);
        //             }
        //
        //             // Achou a empresa, atualiza
        //             self::saveCreateOrUpdateMautic($mauticApi, "organizations", $id, $data, "pipedrive_org_id");
        //         }
        //     break;
        // }
    }

    /**
     * Atualiza o estágio de um contato
     *
     * @param string $class
     * @param array  $data
     * @return array $res
     */
    public static function dealUpdate(string $class, array $data)
    {
        $mauticApi = self::getApi("contacts");
        $data      = self::dataTranslator($class, $data);
        $logMsg    = [];

        if (!$data["aux_active"] && $data["aux_deleted"]) array_push($logMsg, "Deleted deal");

        unset($data["aux_active"], $data["aux_deleted"]);

        // Verifica se tem mautic_id e busca
        if ($data["id"])
        {
            array_push($logMsg, "Searching by id");
            $res = self::searchMauticByField($mauticApi, "ids", $data["id"]);
        }
        // Verifica se tem email e busca
        else if ($data["email"])
        {
            array_push($logMsg, "Searching by email");
            $res = self::searchMauticByField($mauticApi, "email", $data["aux_email"]);

            if ($res["total"]) self::updateMauticId("organizations", key($res["object"]), $data["pd_org_id"], TRUE);
        }
        else
        {
            array_push($logMsg, "No data to search");
            Helper::log("Deals", implode(". ", $logMsg), 0, $data["pd_org_id"]??0);
            return;
        }

        $data["id"] = $data["id"] ?? key($res["object"]);

        if ($res["total"])
        {
            array_push($logMsg, "Contact deal updated");
            $res = $mauticApi->edit($data["id"], $data);
        }
        else
        {
            array_push($logMsg, "Contact not found");
        }

        Helper::log("Deals", implode(". ", $logMsg), $data["id"]??0, $data["pd_org_id"]??0);
    }

    /**
     * Atualiza um estágio
     *
     * @param string  $class
     * @param array   $data
     * @param boolean $delete
     * @return array  $res
     */
    public static function stageUpdate(string $class, array $data, bool $delete = false)
    {
        $idField   = 185; // id do campo personalizavel no Mautic
        $mauticApi = self::getApi($class);
        $data      = self::dataTranslator($class, $data);
        $field     = $mauticApi->get($idField);
        $list      = $field["field"]["properties"]["list"];
        $key       = array_search($data["id"], array_column($list, "value"));

        // Achou o id
        if ($key !== false)
        {
            if (!$delete)
            {
                // Atualiza o stage
                $list[$key]["label"] = $data["name"];
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
                "label" => $data["name"],
                "value" => $data["id"]
            ]);
        }

        $data = [
            "properties" => [
                "list" => $list
            ]
        ];

        $mauticApi->edit($idField, $data);
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
            case "deals":
                return [
                    "id"                 => $data["mautic_id"] ?? NULL,
                    "email"              => $data["email"],
                    "pd_pipeline"        => $data["stage_id"],
                    "pd_pipeline_status" => Helper::statusLabelDeal($data["status"], $data["stage_id"]),
                    "pd_pipeline_label"  => Helper::statusLabelDeal($data["label"],  $data["stage_id"], FALSE),
                    "pd_org_id"          => $data["org_id"],
                    "pd_org_name"        => $data["org_name"],
                    // "pd_person_id"       => $data["person_id"],

                    "aux_active"         => $data["active"],
                    "aux_deleted"        => $data["deleted"],
                ];
            break;

            case "contacts":
                $id = $data["918dace587a4707755926d7c02c3bb46ed53d539"]; // Mautic Id
                return [
                    "id"                    => $id ?? NULL,
                    "firstname"             => "",
                    "lastname"              => "",
                    "email"                 => $data["a007cb7cef80c909678503cbcfe49def03cad573"], // E-mail
                    "phone"                 => $data["a93024fa0b5785ae4b3b1b49a18bb5267b87dfdd"], // Telefone
                    "pd_lead"               => $data["c917493d194dbeac05ccbfd26d152f95b8763ec9"], // Fonte do Lead
                    "pd_responsible_seller" => $data["fa0b068c55533f0298d1d3d26997cdbc2f17eca6"], // Vendedor resp
                    "pd_org_id"             => $data["id"],
                    "pd_org_name"           => $data["name"],
                ];
            break;

            // case "companies":
            //     return [
            //         "id"                => $data["918dace587a4707755926d7c02c3bb46ed53d539"], // custom field for mautic_id
            //         "companyname"       => $data["name"],
            //         "companyaddress1"   => $data["address_formatted_address"],
            //         "companyemail"      => $data["a007cb7cef80c909678503cbcfe49def03cad573"], // custom field for email
            //         "companyphone"      => $data["a93024fa0b5785ae4b3b1b49a18bb5267b87dfdd"], // custom field for fone
            //         "pipedrive_org_id"  => $data["id"],
            //     ];
            // break;

            // stages
            case "contactFields":
                return [
                    "id"                => $data["id"],
                    "name"              => $data["name"],
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
    private static function searchMauticByField(Api $mauticApi, string $field = "", string $search = "", int $start = 0, int $end = 100000)
    {
        if (empty($field))
            $obj = $mauticApi->getList("", $start, $end);
        else
            $obj = $mauticApi->getList("$field:$search");

        $ttl = (isset($obj["total"])) ? (int) $obj["total"] : NULL;

        $obj = $obj[$mauticApi->listName()]??NULL;

        return [
            "total"  => $ttl,
            "object" => $obj
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
        $aux = ($class == "persons") ? "contact" : "company";

        $res = $mauticApi->edit($id, $data, (!$id ? TRUE : FALSE));

        if (!isset($res["errors"]))
        {
            // Para não atualizar o mautic_id no Pipedrive, comentar linha abaixo
            self::updateMauticId($class, $res[$aux]["id"], $data[$pipeIdName], is_null($data["id"]));
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
        $pipeName = ($class == "persons") ? "pd_person_id" : "pd_org_id";

        Log::channel("marketplace")->info(ucfirst($class), [
            "data"      => "created/updated",
            "mautic_id" => $mauticId,
            "$pipeName" => $pipeId
        ]);

        if ($hasId)
        {
            PipedriveIntegration::updateMauticId($class, [
                "pd_id"     => $pipeId,
                "mautic_id" => $mauticId,
            ]);
        }
    }



    /**
     * Sincroniza os ids do Mautic (pipedrive_id) e do Pipedrive (mautic_id)
     *
     * @param array $classes
     * @return void
     */
    public static function syncIdsMauticPipe(array $classes)
    {
        if ($classes["person"] && !$classes["organization"])
        {
            $mauticClass = "contacts";
            $pipeClass   = "persons";
        }
        else if (!$classes["person"] && $classes["organization"])
        {
            $mauticClass = "companies";
            $pipeClass   = "organizations";
        }
        else
        {
            throw new Exception("Invalid parameters, both true or false");
        }

        $mauticApi = self::getApi($mauticClass);

        $pipeApi   = new Pipedrive(config("pipedrive.token"));

        $response  = $pipeApi->$pipeClass->all();
        $pipeObjs  = $response->getData();

        switch ($mauticClass) {
            case "contacts":
                foreach ($pipeObjs as $key => $obj)
                {
                    if ($obj->email[0]->value)
                    {
                        $contact = $mauticApi->getList("email:".$obj->email[0]->value);
                        $total   = (isset($contact["total"])) ? $contact["total"] : NULL;
                        $contact = $contact[$mauticApi->listName()];

                        if ($total > 0 || $total != NULL)
                        {
                            foreach ($contact as $id => $value)
                            {
                                $mautic_id = $id;
                                break;
                            }

                            $dataM = [ "pipedrive_id"                             => $obj->id    ];
                            $dataP = [ "bd7287f0a9a02de60a8aff83b5905c489b7cf007" => $mautic_id  ];

                            $mauticApi->edit($mautic_id, $dataM, false);
                            $pipeApi->$pipeClass->update($obj->id, $dataP);
                        }
                        else
                        {
                            $dataM      = self::dataTranslator($mauticClass, json_decode(json_encode($obj), true));
                            $newContact = $mauticApi->edit(0, $dataM, true);

                            $dataP = [ "bd7287f0a9a02de60a8aff83b5905c489b7cf007" => $newContact["contact"]["id"]];
                            $pipeApi->$pipeClass->update($obj->id, $dataP);
                        }
                    }
                }
            break;

            case "companies":
                $companies = $mauticApi->getList("", 0, 100000);
                $companies = $companies[$mauticApi->listName()];

                foreach ($pipeObjs as $key => $obj)
                {
                    foreach ($companies as $id => $comp)
                    {
                        if ($comp["fields"]["all"]["companyname"] == $obj->name)
                        {
                            $mautic_id = $comp["fields"]["all"]["id"];

                            $dataM = [ "pipedrive_org_id"                         => $obj->id    ];
                            $dataP = [ "918dace587a4707755926d7c02c3bb46ed53d539" => $mautic_id  ];

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
    public static function updateStages()
    {
        Log::channel("marketplace")->info("Stages", ["Start importing all stages"]);

        $mauticApi = self::getApi("contactFields");
        $pipeApi   = new Pipedrive(config("pipedrive.token"));

        // inicio processamento etapas dos funis
        $stages = $pipeApi->stages()->all()->getData();

        foreach ($stages as &$stage)
        {
            $stage = [
                "value"    => $stage->id,
                "label"    => $stage->name,
                "pd_id"    => $stage->pipeline_id,
                "order_nr" => $stage->order_nr
            ];
        }

        $stages = Helper::array_orderby($stages, "pd_id", SORT_ASC, "order_nr", SORT_ASC);

        foreach ($stages as &$stage) unset($stage["pd_id"], $stage["order_nr"]);

        $dataStages = [
            "properties" => [
                "list" => [
                    [
                        "value" => 0,
                        "label" => "Sem Funil"
                    ],
                    ...$stages
                ],
            ]
        ];
        $resp1 = $mauticApi->edit(214, $dataStages);
        // 214 = id do campo no Mautic: (Pipedrive Funil)
        // fim processamento das etapas dos funis


        // inicio processamento dos status dos negocios em cada etapa dos funis
        $stageStatus = self::getLabels($pipeApi, 12464); // id Status no Pipe
        $dataStatus  = [
            "properties" => [
                "list" => [
                    [
                        "value" => 0,
                        "label" => "Sem Status"
                    ],
                    ...self::processStages($stages, $stageStatus)
                ],
            ]
        ];
        $resp2       = $mauticApi->edit(215, $dataStatus);
        // 215 = id do campo no Mautic (Pipedrive Funil Status)
        // fim processamento dos status dos negocios em cada etapa dos funis


        // inicio processamento dos status dos negocios em cada etapa dos funis
        $stageLabels = self::getLabels($pipeApi, 12463); // id Etiqueta no Pipe
        $dataLabels  = [
            "properties" => [
                "list" => [
                    [
                        "value" => 0,
                        "label" => "Sem Etiqueta"
                    ],
                    ...self::processStages($stages, $stageLabels)
                ],
            ]
        ];
        $resp3       = $mauticApi->edit(218, $dataLabels);
        // 218 = id do campo no Mautic (Pipedrive Funil Etiqueta)
        // fim processamento dos status dos negocios em cada etapa dos funis


        $log = (!isset($resp1["errors"]) &&
                !isset($resp2["errors"]) &&
                !isset($resp3["errors"])) ?
                    "Successfully imported all stages" :
                        "Import of stages failed";

        Log::channel("marketplace")->info("Stages", [$log]);
    }

    /**
     * Undocumented function
     *
     * @param Pipedrive $pipeApi
     * @param integer $id
     * @return array
     */
    private static function getLabels(Pipedrive $pipeApi, int $id)
    {
        $resp = $pipeApi->DealFields->find($id)->getData();
        $temp = $resp->options;

        foreach ($temp as $t)
        {
            // opc1
            $lbl = mb_strtolower($t->label);
            $arr = ["em aberto", "em espera", "em degustação"];

            if (in_array($lbl, $arr))
            {
                $label    = explode(" ", $t->label)[1];
                $t->label = ucfirst($label);
            }

            $data[] = $t->label;

            // // opc2
            // $data[] = (in_array(mb_strtolower($t->label), ["em aberto", "em espera", "em degustação"])) ? ucfirst(explode(" ", $t->label)[1]) : $t->label;
        }

        return $data;
    }

    /**
     * Undocumented function
     *
     * @param array $stages
     * @param array $labels
     * @return array
     */
    private static function processStages(array $stages, array $labels)
    {
        foreach ($stages as $s)
        {
            foreach ($labels as $lb)
            {
                $data[] = [
                    "value" => $s["value"] .  ":"  . $lb[0],
                    "label" => $s["label"] . " : " . $lb,
                ];
            }
        }

        return $data;
    }
}
