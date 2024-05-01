<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Helpers\Helper;
use Illuminate\Http\Request;
use Devio\Pipedrive\Pipedrive;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Notifications\WebhookMarketplace;
use App\Http\Integrations\MauticIntegration;

class PipedriveWebhookController extends Controller
{

    /**
     * Deal to Contact->Funil
     */
    const DEAL_ADDED_EVENT     = "added.deal";
    const DEAL_UPDATED_EVENT   = "updated.deal";
    const DEAL_DELETED_EVENT   = "deleted.deal";
    const DEAL_CODE            = 1;
    const DEAL_MONITORING      = [
        "stage_id", // Etapa    / id interno: 12462
        "label",    // Etiqueta / id interno: 12463
        "status",   // Status   / id interno: 12464
        "active",
        "deleted",
    ];

    /**
     * Organization to Company
     */
    const ORG_ADDED_EVENT      = "added.organization";
    const ORG_UPDATED_EVENT    = "updated.organization";
    const ORG_DELETED_EVENT    = "deleted.organization";
    const ORG_CODE             = 2;
    const ORG_MONITORING       = [
        // "name",
        // "address_formatted_address",
        "a93024fa0b5785ae4b3b1b49a18bb5267b87dfdd", // Telefone      / id interno: 4022
        "a007cb7cef80c909678503cbcfe49def03cad573", // E-mail        / id interno: 4023
        "918dace587a4707755926d7c02c3bb46ed53d539", // Mautic Id     / id interno: 4026
        "c917493d194dbeac05ccbfd26d152f95b8763ec9", // Fonte do Lead / id interno: 4028
        "fa0b068c55533f0298d1d3d26997cdbc2f17eca6", // Vendedor resp / id interno: 4032
    ];

    /**
     * Stage to Funil/pipeline
     */
    const STAGE_ADDED_EVENT    = "added.stage";
    const STAGE_UPDATED_EVENT  = "updated.stage";
    const STAGE_DELETED_EVENT  = "deleted.stage";
    const STAGE_CODE           = 4;
    const STAGE_MONITORING     = "name";

    /**
     * Person to Contact
     */
    // const PERSON_ADDED_EVENT   = "added.person";
    // const PERSON_UPDATED_EVENT = "updated.person";
    // const PERSON_DELETED_EVENT = "deleted.person";
    // const PERSON_CODE          = 3;
    // const PERSON_MONITORING    = [
    //     // "first_name", "last_name", "email", "phone", "org_name",
    //     "name",                                     // Nome      / id interno: 9039
    //     "bd7287f0a9a02de60a8aff83b5905c489b7cf007", // Mautic Id / id interno: 9067
    // ];

    /**
     * Pipedrive - deal
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function deal(Request $request)
    {
        // Storage::put("deal-" . date("Ymd-his") . ".json", json_encode($request->all(), JSON_UNESCAPED_SLASHES));

        $params = $request->all();

        if ($params["meta"]["change_source"] == "api")
        {
            Log::channel("marketplace")->info("Deals", [ "0" => "Changed by api ignored" ]);

            return response()->json([
                "status"  => "Ok",
                "message" => "Deal - changed by api ignored",
                "code"    => -1,
            ], 200);
        }

        try
        {
            switch ($params["event"])
            {
                case self::DEAL_ADDED_EVENT:
                case self::DEAL_UPDATED_EVENT:
                case self::DEAL_DELETED_EVENT:
                    $index = ($params["event"] == self::DEAL_DELETED_EVENT) ? "previous" : "current";

                    if (!$params[$index]["org_id"])
                    {
                        Log::channel("marketplace")->info("Deals", [ "404" => "Not found: org_id" ]);
                        break;
                    }

                    if (isset($params["previous"]) && $params["event"] != self::DEAL_DELETED_EVENT)
                    {
                        $result  = FALSE;
                        $changes = Helper::arrayDiffAssocRecursive($params["current"],$params["previous"]);
                        foreach (self::DEAL_MONITORING as $m)
                        {
                            if (array_key_exists($m, $changes))
                            {
                                $result = TRUE;
                                break;
                            }
                        }

                        if (!$result) break;
                    }

                    $api = new Pipedrive(config("pipedrive.token"));
                    $org = $api->organizations->find($params[$index]["org_id"])->getData();

                    // $params[$index]["email"]     = $org->{"a007cb7cef80c909678503cbcfe49def03cad573"};
                    // $params[$index]["mautic_id"] = $org->{"918dace587a4707755926d7c02c3bb46ed53d539"};

                    $data["mautic_id"] = $org->{"918dace587a4707755926d7c02c3bb46ed53d539"};
                    $data["email"]     = $org->{"a007cb7cef80c909678503cbcfe49def03cad573"};
                    $data["org_id"]    = $params[$index]["org_id"];
                    $data["org_name"]  = $params[$index]["org_name"];
                    $data["active"]    = $params[$index]["active"];
                    $data["deleted"]   = $params[$index]["deleted"];

                    if ($index == "current")
                    {
                        $data["new_stage_id"] = $params[$index]["stage_id"];
                        $data["new_status"]   = $params[$index]["status"];
                        $data["new_label"]    = $params[$index]["label"];

                        if (isset($params["previous"]))
                        {
                            $data["old_stage_id"] = $params["previous"]["stage_id"];
                            $data["old_status"]   = $params["previous"]["status"];
                            $data["old_label"]    = $params["previous"]["label"];
                        }
                        else
                        {
                            $data["old_stage_id"] = 0;
                            $data["old_status"]   = 0;
                            $data["old_label"]    = 0;
                        }
                    }
                    else
                    {
                        $data["new_stage_id"] = 0;
                        $data["new_status"]   = 0;
                        $data["new_label"]    = 0;

                        $data["old_stage_id"] = $params[$index]["stage_id"];
                        $data["old_status"]   = $params[$index]["status"];
                        $data["old_label"]    = $params[$index]["label"];
                    }

                    MauticIntegration::dealUpdate("deals", $data);
                break;

                default:
                    Log::channel("marketplace")->info("Deals", [ "400" => "Unsupported event" ]);

                    return response()->json([
                        "status"  => "Error",
                        "message" => "Unsupported event",
                        "code"    => 0,
                    ], 400);
                break;
            }
        }
        catch (Exception $e)
        {
            Log::channel("marketplace")->info("Deals", [ "500" => $e ]);

            self::sendMailNotification($params, $e);

            return response()->json([
                "status"  => "Error",
                "message" => $e,
                "code"    => self::DEAL_CODE,
            ], 500);
        }

        return response()->json([
            "status"  => "Ok",
            "message" => "Success",
        ], 200);
    }

    /**
     * Pipedrive - organization
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function organization(Request $request)
    {
        $params = $request->all();

        if ($params["meta"]["change_source"] == "api")
        {
            Log::channel("marketplace")->info("Organizations", [ "0" => "changed by api ignored" ]);

            return response()->json([
                "status"  => "Ok",
                "message" => "Organizations - changed by api ignored",
                "code"    => -1,
            ], 200);
        }

        try
        {
            switch ($params["event"])
            {
                case self::ORG_ADDED_EVENT:
                case self::ORG_UPDATED_EVENT:
                case self::ORG_DELETED_EVENT:

                    if ($params["event"] == self::ORG_DELETED_EVENT)
                    {
                        $index = "previous";
                        $del   = TRUE;
                    }
                    else
                    {
                        $index = "current";
                        $del   = FALSE;
                    }

                    if (isset($params["previous"]) && $params["event"] != self::ORG_DELETED_EVENT)
                    {
                        $result  = FALSE;
                        $changes = Helper::arrayDiffAssocRecursive($params["current"],$params["previous"]);
                        foreach (self::ORG_MONITORING as $m)
                        {
                            if (array_key_exists($m, $changes))
                            {
                                $result = TRUE;
                                break;
                            }
                        }

                        if (!$result) break;
                    }

                    MauticIntegration::updateOrCreate("contacts", $params[$index], $del);
                break;

                default:
                    Log::channel("marketplace")->info("Organizations", [ "400" => "Unsupported event" ]);

                    return response()->json([
                        "status"  => "Error",
                        "message" => "Unsupported event",
                        "code"    => 0,
                    ], 400);
                break;
            }
        }
        catch (Exception $e)
        {
            Log::channel("marketplace")->info("Organizations", [ "500" => $e ]);

            self::sendMailNotification($params, $e);

            return response()->json([
                "status"  => "Error",
                "message" => $e,
                "code"    => self::ORG_CODE,
            ], 500);
        }

        return response()->json([
            "status"  => "Ok",
            "message" => "Success",
        ], 200);
    }

    /**
     * Pipedrive - stage
     * Recebe o webhook dos estágios dentro dos funis do Pipedrive
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function stage(Request $request)
    {
        $params = $request->all();

        if ($params["meta"]["change_source"] == "api")
        {
            Log::channel("marketplace")->info("Stages", [ "0" => "Changed by api ignored" ]);

            return response()->json([
                "status"  => "Ok",
                "message" => "Stages - changed by api ignored",
                "code"    => -1,
            ], 200);
        }

        try
        {
            switch ($params["event"])
            {
                case self::STAGE_ADDED_EVENT:
                case self::STAGE_UPDATED_EVENT:
                case self::STAGE_DELETED_EVENT:
                    MauticIntegration::updateStages();
                break;

                default:
                    Log::channel("marketplace")->info("Stages", [ "400" => "Unsupported event" ]);

                    return response()->json([
                        "status"  => "Error",
                        "message" => "Unsupported event",
                        "code"    => 0,
                    ], 400);
                break;
            }
        }
        catch (Exception $e)
        {
            Log::channel("marketplace")->info("Stages", [ "500" => $e ]);

            self::sendMailNotification($params, $e);

            return response()->json([
                "status"  => "Error",
                "message" => $e,
                "code"    => self::STAGE_CODE,
            ], 500);
        }

        return response()->json([
            "status"  => "Ok",
            "message" => "Success",
        ], 200);
    }

    /**
     * Pipedrive - person
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function person(Request $request)
    {
        if (env("APP_ENV") == "production" || env("APP_ENV") == "local")
        {
            Log::channel("marketplace")->info("Persons", [ "0" => "deactivated" ]);
            return response()->json([
                "status"  => "Ok",
                "message" => "Deactivated",
            ], 200);
        }

        // $params = $request->all();
        //
        // if ($params["meta"]["change_source"] == "api")
        // {
        //     Log::channel("marketplace")->info("Persons", [
        //         "0" => "Changed by api ignored"
        //     ]);
        //
        //     return response()->json([
        //         "status"  => "Ok",
        //         "message" => "Persons - changed by api ignored",
        //         "code"    => -1,
        //     ], 200);
        // }
        //
        // try
        // {
        //     switch ($params["event"])
        //     {
        //         case self::PERSON_ADDED_EVENT:
        //         case self::PERSON_UPDATED_EVENT:
        //             if (isset($params["previous"]))
        //             {
        //                 $result  = FALSE;
        //                 $changes = Helper::arrayDiffAssocRecursive($params["current"],$params["previous"]);
        //                 foreach (self::PERSON_MONITORING as $m)
        //                 {
        //                     if (array_key_exists($m, $changes))
        //                     {
        //                         $result = TRUE;
        //                         break;
        //                     }
        //                 }
        //
        //                 if (!$result)
        //                 {
        //                     Log::channel("marketplace")->info("Persons", ["0" => "Discarded changes"]);
        //                     break;
        //                 }
        //             }
        //
        //             MauticIntegration::updateOrCreate("contacts", $params["current"]);
        //         break;
        //
        //         case self::PERSON_DELETED_EVENT:
        //             MauticIntegration::delete("contacts", $params["previous"]);
        //         break;
        //
        //         default:
        //             Log::channel("marketplace")->info("Persons", ["400" => "Unsupported event"]);
        //
        //             return response()->json([
        //                 "status"  => "Error",
        //                 "message" => "Unsupported event",
        //                 "code"    => 0,
        //             ], 400);
        //         break;
        //     }
        // }
        // catch (Exception $e)
        // {
        //     Log::channel("marketplace")->info("Persons", ["500" => $e]);
        //
        //     self::sendMailNotification($params, $e);
        //
        //     return response()->json([
        //         "status"  => "Error",
        //         "message" => $e,
        //         "code"    => self::PERSON_CODE,
        //     ], 500);
        // }
        //
        // Log::channel("marketplace")->info("Persons", ["200" => "Success"]);
        //
        // return response()->json([
        //     "status"  => "Ok",
        //     "message" => "Success",
        // ], 200);
    }

    /**
     * Undocumented function
     *
     * @param Array $params
     * @param Exception $e
     * @return void
     */
    private static function sendMailNotification(Array $params, Exception $e)
    {
        if ($params["retry"] == 3)
        {
            $mail = [
                "subject"    => "Dev - Integração Triibo Pipedrive-Mautic",
                "title"      => "Erro no webhook: " . $params["meta"]["object"],
                "class"      => $params["meta"]["object"],
                "params"     => $params,
                "stacktrace" => $e,
            ];
            $users = User::where("email", "equipemarketplace@triibo.com.br")->orWhere("email", "mateus.costa@triibo.com.br")->get();
            $users[0]->notify(new WebhookMarketplace($mail));
            $users[1]->notify(new WebhookMarketplace($mail));
        }
    }
}
