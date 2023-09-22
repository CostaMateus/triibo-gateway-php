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
            $res = self::searchMauticByField($mauticApi, "email", $data["email"]);

            if ($res["total"]) self::updateMauticId("organizations", key($res["object"]), $data["pd_org_id"], TRUE);
        }
        else
        {
            array_push($logMsg, "No data to search");
            Helper::log("Persons", implode(". ", $logMsg), 0, $data["pd_org_id"]??0);
            return;
        }

        // $data["id"] = $data["id"] ?? key($res["object"]);

        // $orgName = strpos(strtolower($data['pd_org_name']), 'zteste'); // remover em prod
        // if ($orgName !== FALSE)                                        // remover em prod
        // {                                                              // remover em prod

            // Achou o contato, atualiza
            if ($res["total"])
            {
                if ($delete)
                {
                    $data["pd_pipeline"]        = 0;
                    $data["pd_pipeline_status"] = 0;
                    $data["pd_pipeline_label"]  = 0;
                }

                $data["id"] = key($res["object"]);

                array_push($logMsg, "Contact updated");
                // self::saveCreateOrUpdateMautic($mauticApi, "persons", $data["id"], $data, "pd_org_id");
            }
            // Não achou o contato, cria um novo
            else
            {
                $data["id"] = 0;

                array_push($logMsg, "Contact created");
                // self::saveCreateOrUpdateMautic($mauticApi, "persons", 0,           $data, "pd_org_id");
            }

            self::saveCreateOrUpdateMautic($mauticApi, "persons", $data, "pd_org_id");

            Helper::log("Persons", implode(". ", $logMsg), $data["id"]??0, $data["pd_org_id"]??0);

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

        // $orgName = strpos(strtolower($data['pd_org_name']), 'zteste'); // remover em prod
        // if ($orgName !== FALSE)                                        // remover em prod
        // {                                                              // remover em prod

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
                $res = self::searchMauticByField($mauticApi, "email", $data["email"]);

                if ($res["total"]) self::updateMauticId("organizations", key($res["object"]), $data["pd_org_id"], TRUE);
            }
            // Caso não tenha nenhum, ingora
            else
            {
                array_push($logMsg, "No data to search");
                Helper::log("Deals", implode(". ", $logMsg), 0, $data["pd_org_id"]??0);
                return;
            }


            // Achou correspondente no Mauitc
            if ($res["total"])
            {
                $data["id"] = $data["id"] ?? key($res["object"]);
                $oldData    = $res["object"][$data["id"]]["fields"]["all"];

                self::treatFieldsDeal($data, $oldData["pd_pipeline"]       ??"", "pd_pipeline",        "new_stage_id", "old_stage_id");
                self::treatFieldsDeal($data, $oldData["pd_pipeline_status"]??"", "pd_pipeline_status", "new_status",   "old_status");
                self::treatFieldsDeal($data, $oldData["pd_pipeline_label"] ??"", "pd_pipeline_label",  "new_label",    "old_label");

                array_push($logMsg, "Contact deal updated");
                $res = $mauticApi->edit($data["id"], $data);
            }
            // Não achou
            else
            {
                array_push($logMsg, "Contact not found");
            }

            Helper::log("Deals", implode(". ", $logMsg), $data["id"]??0, $data["pd_org_id"]??0);

        // }
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
                    "id"           => $data["mautic_id"] ?? NULL,
                    "email"        => $data["email"],
                    "pd_org_id"    => $data["org_id"],
                    "pd_org_name"  => $data["org_name"],

                    "new_stage_id" => $data["new_stage_id"]??0,
                    "new_status"   => Helper::statusLabelDeal($data["new_status"], $data["new_stage_id"]),
                    "new_label"    => Helper::statusLabelDeal($data["new_label"],  $data["new_stage_id"], FALSE),

                    "old_stage_id" => $data["old_stage_id"]??0,
                    "old_status"   => Helper::statusLabelDeal($data["old_status"], $data["old_stage_id"]),
                    "old_label"    => Helper::statusLabelDeal($data["old_label"],  $data["old_stage_id"], FALSE),

                    "aux_active"   => $data["active"],
                    "aux_deleted"  => $data["deleted"],
                ];
            break;

            case "contacts":
                $id    = $data["918dace587a4707755926d7c02c3bb46ed53d539"]; // Mautic Id
                $email = $data["a007cb7cef80c909678503cbcfe49def03cad573"]; // E-mail
                $phone = $data["a93024fa0b5785ae4b3b1b49a18bb5267b87dfdd"]; // Telefone
                $lead  = $data["c917493d194dbeac05ccbfd26d152f95b8763ec9"]; // Fonte do Lead
                $resp  = $data["fa0b068c55533f0298d1d3d26997cdbc2f17eca6"]; // Vendedor resposavel

                return [
                    "id"                    => $id ?? NULL,
                    "firstname"             => "",
                    "lastname"              => "",
                    "email"                 => $email ?? NULL,
                    "pd_lead"               => Helper::getOrgCustomField(4028, $lead??0),
                    "pd_responsible_seller" => Helper::getOrgCustomField(4032, $resp??0),
                    "pd_org_id"             => $data["id"],
                    "pd_org_name"           => $data["name"],
                    "pd_org_phone"          => $phone,
                ];
            break;

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
    private static function saveCreateOrUpdateMautic(Api $mauticApi, string $class, array $data, string $pipeIdName)
    {
        $aux = ($class == "persons") ? "contact" : "company";

        $id  = $data["id"] ?? 0;

        $res = $mauticApi->edit($id, $data, ($id ? FALSE : TRUE));

        if (!isset($res["errors"]))
        {
            // Para não atualizar o mautic_id no Pipedrive, comentar linha abaixo
            self::updateMauticId($class, $res[$aux]["id"], $data[$pipeIdName], TRUE);
        }
        else
        {
            Helper::log("Persons", json_encode($res, TRUE), $id??0, $data["pd_org_id"]??0);
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
        // $pipeName = ($class == "persons") ? "pd_person_id" : "pd_org_id";

        Log::channel("marketplace")->info(ucfirst($class), [
            "data"      => "created/updated",
            "mautic_id" => $mauticId,
            "pd_org_id" => $pipeId
            // "$pipeName" => $pipeId
        ]);

        if ($hasId)
        {
            // PipedriveIntegration::updateMauticId($class, [
            PipedriveIntegration::updateMauticId("organizations", [
                "pd_id"     => $pipeId,
                "mautic_id" => $mauticId,
            ]);
        }
    }

    /**
     * Importa todos os Stages do Pipedrive
     *
     * @return void
     */
    public static function updateStages()
    {
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

        $stages = Helper::arrayOrderBy($stages, "pd_id", SORT_ASC, "order_nr", SORT_ASC);

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


        // inicio processamento dos status dos negocios nas etapas dos funis
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
        // fim processamento dos status dos negocios nas etapas dos funis


        // inicio processamento das etiquetas dos negocios nas etapas dos funis
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
        // fim processamento das etiquetas dos negocios nas etapas dos funis


        $log = (!isset($resp1["errors"]) &&
                !isset($resp2["errors"]) &&
                !isset($resp3["errors"])) ?
                    "Successfully imported" :
                        "Import failed";

        Log::channel("marketplace")->info("update-stages", [$log]);
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

    /**
     * Undocumented function
     *
     * @param array  $data
     * @param string $oldData
     * @param string $field
     * @param string $new
     * @param string $old
     * @return void
     */
    private static function treatFieldsDeal(array &$data, string $oldData, string $field, string $new, string $old)
    {
        $arr = explode("|", $oldData);

        if (($i = array_search("0", $arr)) !== false) unset($arr[$i]);

        if ($data[$new] && $data[$old])
        {
            // atualiza
            foreach ($arr as $i => $v)
            {
                if ($v == $data[$old])
                {
                    unset($arr[$i]);
                    array_push($arr, $data[$new]);
                    break;
                }
            }
        }
        elseif ($data[$new] && !$data[$old])
        {
            // adiciona
            array_push($arr, $data[$new]);
        }
        elseif (!$data[$new] && $data[$old])
        {
            // remove
            if ($arr && ($i = array_search($data[$old], $arr)) !== false) unset($arr[$i]);
        }
        else
        {
            // não faz nada
            $arr = [0];
        }

        unset($data[$new], $data[$old]);

        $data[$field] = $arr ? implode("|", $arr) : 0;
    }


    /**
     * Sincroniza os ids do Mautic (pd_org_id) e do Pipedrive (mautic_id / 918dace587a4707755926d7c02c3bb46ed53d539)
     *
     * @return void
     */
    public static function syncIdsMauticPipe()
    {
        $mauticApi = self::getApi("contacts");
        $pipeApi   = new Pipedrive(config("pipedrive.token"));
        $arrObjs   = [];
        $start     = 0;

        do {
            $objs = $pipeApi->organizations->all([
                "start" => $start,
                "limit" =>  500
            ])->getData();

            array_push($arrObjs, $objs);

            $start += 500;
        }
        while (count($objs) == 500);

        echo "\nE-mail : pipe ID : mautic ID";

        foreach ($arrObjs as $objs)
        {
            foreach ($objs as $key => $org)
            {
                $mtcId = NULL;
                $email = $org->{"a007cb7cef80c909678503cbcfe49def03cad573"};

                if (!$email) continue;

                $res = self::searchMauticByField($mauticApi, "email", $email);

                if (!$res["total"]) continue;

                if ($res["total"] > 1)
                {
                    foreach ($res["object"] as $key => $org)
                    {
                        if ($org["fields"] && $org["fields"]["all"] && $org["fields"]["all"]["email"])
                        {
                            if ($org["fields"]["all"]["email"] != $email) continue;

                            $mtcId = $key;
                        }
                    }
                }

                $mtcId = $mtcId ?? key($res["object"]);

                $dataM = [ "pd_org_id"                                => $org->id ];
                $dataP = [ "918dace587a4707755926d7c02c3bb46ed53d539" => $mtcId   ];

                $mauticApi->edit($mtcId, $dataM, false);
                $pipeApi->organizations->update($org->id, $dataP);


                echo "\n$email : $org->id : $mtcId";
            }
        }
    }
}
