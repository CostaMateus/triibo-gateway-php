<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use Exception;
use Mautic\Api\Api;
use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;
use Illuminate\Http\Request;
use Devio\Pipedrive\Pipedrive;
use Triibo\Mautic\Facades\Mautic;
use Illuminate\Support\Facades\Log;
use Triibo\Mautic\Models\MauticConsumer;
use App\Http\Integrations\PipedriveIntegration;

class zTesteController extends Controller
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

    // /**
    //  * Importa todos os Stages do Pipedrive
    //  *
    //  * @return void
    //  */
    // public static function importAllStages()
    // {
    //     Log::channel("marketplace")->info("Stages", [
    //         "data" => "start importing all stages"
    //     ]);

    //     $idField   = 185; // id do campo personalizavel no Mautic
    //     $mauticApi = self::getApi("contactFields");

    //     $pipeApi   = new Pipedrive(config("pipedrive.token"));
    //     $stages    = $pipeApi->stages()->all()->getData();

    //     foreach ($stages as &$stage)
    //     {
    //         $stage = [
    //             "value" => $stage->id,
    //             "label" => $stage->name
    //         ];
    //     }

    //     usort($stages, function($a, $b) {
    //         return $a["value"] <=> $b["value"];
    //     });

    //     $data = [
    //         "properties" => [
    //             "list" => [
    //                 [
    //                     "value" => 0,
    //                     "label" => "Sem Funil"
    //                 ],
    //                 ...$stages
    //             ],
    //         ]
    //     ];

    //     $res  = $mauticApi->edit($idField, $data);

    //     if (!isset($res["errors"]))
    //     {
    //         Log::channel("marketplace")->info("Stages", [
    //             "data" => "end of importing all stages"
    //         ]);
    //     }
    //     else
    //     {
    //         Log::channel("marketplace")->info("Stages", [
    //             "data" => "import failed"
    //         ]);
    //     }
    // }

    /**
     * Importa todas as Etiquetas (labels) do Negócios do Pipedrive
     *
     * @return void
     */
    public static function index()
    {
        Log::channel("marketplace")->info("Stages", ["Start importing all stages"]);

        $mauticApi = self::getApi("contactFields");

        $pipeApi   = new Pipedrive(config("pipedrive.token"));
        $stages    = $pipeApi->stages()->all()->getData();

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
        $resp1 = $mauticApi->edit(214, $dataStages); // id do campo no Mautic: Pipedrive Funil


        $status = [];
        foreach ($stages as $s)
        {
            $status[] = [
                "value" => $s["value"] . ":A",
                "label" => $s["label"] . " : Aberto",
            ];
            $status[] = [
                "value" => $s["value"] . ":G",
                "label" => $s["label"] . " : Ganho",
            ];
            $status[] = [
                "value" => $s["value"] . ":P",
                "label" => $s["label"] . " : Perdido",
            ];
            $status[] = [
                "value" => $s["value"] . ":E",
                "label" => $s["label"] . " : Excluído",
            ];
        }

        $dataStatus = [
            "properties" => [
                "list" => [
                    [
                        "value" => 0,
                        "label" => "Sem Funil"
                    ],
                    ...$status
                ],
            ]
        ];
        $resp2 = $mauticApi->edit(215, $dataStatus); // id do campo no Mautic: Pipedrive Funil Status

        $log = (!isset($resp1["errors"]) &&
                !isset($resp2["errors"])) ?
                    "Successfully imported all stages" :
                        "Import of stages failed";

        Log::channel("marketplace")->info("Stages", [$log]);
    }

}
