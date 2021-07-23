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
    const DEAL_MONITORING      = ["stage_id", "active", "deleted", "status", "label"];

    /**
     * Organization to Company
     */
    const ORG_ADDED_EVENT      = "added.organization";
    const ORG_UPDATED_EVENT    = "updated.organization";
    const ORG_DELETED_EVENT    = "deleted.organization";
    const ORG_CODE             = 2;
    const ORG_MONITORING       = [
        "name",
        "address_formatted_address",
        "a93024fa0b5785ae4b3b1b49a18bb5267b87dfdd", // telefone
        "a007cb7cef80c909678503cbcfe49def03cad573", // email
        "918dace587a4707755926d7c02c3bb46ed53d539"  // mautic_id
    ];

    /**
     * Person to Contact
     */
    const PERSON_ADDED_EVENT   = "added.person";
    const PERSON_UPDATED_EVENT = "updated.person";
    const PERSON_DELETED_EVENT = "deleted.person";
    const PERSON_CODE          = 3;
    const PERSON_MONITORING    = [
        "first_name", "last_name", "email", "phone",
        "bd7287f0a9a02de60a8aff83b5905c489b7cf007", // mautic_id
        // "org_name",
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
     * Pipedrive - deal
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function deal(Request $request)
    {
        $params = $request->all();

        // if ($params["meta"]["change_source"] == "api")
        if ($params["meta"]["host"] == "app.pipedrive.com")
        {
            Log::channel("marketplace")->info("Deals", [
                "0" => "Changed by api ignored"
            ]);

            return response()->json([
                "status"  => "Ok",
                "message" => "Deal - changed by api ignored",
                "code"    => -1,
            ], 200);
        }

        // Storage::put(date("Ymd-his")."-deal.json", json_encode($params, JSON_UNESCAPED_SLASHES));

        try
        {
            switch ($params["event"])
            {
                case self::DEAL_ADDED_EVENT:
                case self::DEAL_UPDATED_EVENT:
                    if (!$params["current"]["person_id"]) break;

                    if ($params["previous"])
                    {
                        $result  = FALSE;
                        $changes = Helper::array_diff_assoc_recursive($params["current"],$params["previous"]);
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

                    if (!$params["current"]["person_id"]) break;

                    $api    = new Pipedrive(config("pipedrive.token"));
                    $person = $api->persons->find($params["current"]["person_id"])->getData();

                    $params["current"]["person_email"]     = $person->email[0]->value;
                    $params["current"]["person_mautic_id"] = $person->bd7287f0a9a02de60a8aff83b5905c489b7cf007;

                    MauticIntegration::dealUpdate("deals", $params["current"]);
                break;

                case self::DEAL_DELETED_EVENT:
                    if (!$params["current"]["person_id"]) break;

                    $api    = new Pipedrive(config("pipedrive.token"));
                    $person = $api->persons->find($params["previous"]["person_id"])->getData();

                    $params["previous"]["person_email"]     = $person->email[0]->value;
                    $params["previous"]["person_mautic_id"] = $person->bd7287f0a9a02de60a8aff83b5905c489b7cf007;

                    MauticIntegration::dealUpdate("deals", $params["previous"], TRUE);
                break;

                default:
                    Log::channel("marketplace")->info("Deals", [
                        "400" => "Unsupported event"
                    ]);

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
            Log::channel("marketplace")->info("Deals", [
                "500" => $e
            ]);

            self::sendMailNotification($params, $e);

            return response()->json([
                "status"  => "Error",
                "message" => $e,
                "code"    => self::DEAL_CODE,
            ], 500);
        }

        Log::channel("marketplace")->info("Deals", [
            "200" => "Success"
        ]);

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
        if (env("APP_ENV") == "production" || env("APP_ENV") == "local")
        {
            Log::channel("marketplace")->info("Organizations", [
                "0" => "deactivated"
            ]);

            return response()->json([
                "status"  => "Ok",
                "message" => "Deactivated",
            ], 200);
        }

        $params = $request->all();

        // if ($params["meta"]["change_source"] == "api")
        if ($params["meta"]["host"] == "app.pipedrive.com")
        {
            Log::channel("marketplace")->info("Organizations", [
                "0" => "changed by api ignored"
            ]);

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
                    if ($params["previous"])
                    {
                        $result  = FALSE;
                        $changes = Helper::array_diff_assoc_recursive($params["current"],$params["previous"]);
                        foreach (self::ORG_MONITORING as $m)
                        {
                            if (array_key_exists($m, $changes))
                            {
                                $result = TRUE;
                                break;
                            }
                        }

                        if (!$result)
                        {
                            Log::channel("marketplace")->info("Organizations", [
                                "0" => "discarded changes"
                            ]);
                            break;
                        }
                    }

                    MauticIntegration::updateOrCreate("companies", $params["current"]);
                break;

                case self::ORG_DELETED_EVENT:
                    MauticIntegration::delete("companies", $params["previous"]);
                break;

                default:
                    Log::channel("marketplace")->info("Organizations", [
                        "400" => "Unsupported event"
                    ]);

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
            Log::channel("marketplace")->info("Organizations", [
                "500" => $e
            ]);

            self::sendMailNotification($params, $e);

            return response()->json([
                "status"  => "Error",
                "message" => $e,
                "code"    => self::ORG_CODE,
            ], 500);
        }

        Log::channel("marketplace")->info("Organizations", [
            "200" => "Success"
        ]);

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
        $params = $request->all();

        // if ($params["meta"]["change_source"] == "api")
        if ($params["meta"]["host"] == "app.pipedrive.com")
        {
            Log::channel("marketplace")->info("Persons", [
                "0" => "Changed by api ignored"
            ]);

            return response()->json([
                "status"  => "Ok",
                "message" => "Persons - changed by api ignored",
                "code"    => -1,
            ], 200);
        }

        try
        {
            switch ($params["event"])
            {
                case self::PERSON_ADDED_EVENT:
                case self::PERSON_UPDATED_EVENT:
                    if ($params["previous"])
                    {
                        $result  = FALSE;
                        $changes = Helper::array_diff_assoc_recursive($params["current"],$params["previous"]);
                        foreach (self::PERSON_MONITORING as $m)
                        {
                            if (array_key_exists($m, $changes))
                            {
                                $result = TRUE;
                                break;
                            }
                        }

                        if (!$result)
                        {
                            Log::channel("marketplace")->info("Persons", [
                                "0" => "Discarded changes"
                            ]);
                            break;
                        }
                    }

                    MauticIntegration::updateOrCreate("contacts", $params["current"]);
                break;

                case self::PERSON_DELETED_EVENT:
                    MauticIntegration::delete("contacts", $params["previous"]);
                break;

                default:
                    Log::channel("marketplace")->info("Persons", [
                        "400" => "Unsupported event"
                    ]);

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
            Log::channel("marketplace")->info("Persons", [
                "500" => $e
            ]);

            self::sendMailNotification($params, $e);

            return response()->json([
                "status"  => "Error",
                "message" => $e,
                "code"    => self::PERSON_CODE,
            ], 500);
        }

        Log::channel("marketplace")->info("Persons", [
            "200" => "Success"
        ]);

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

        // if ($params["meta"]["change_source"] == "api")
        if ($params["meta"]["host"] == "app.pipedrive.com")
        {
            Log::channel("marketplace")->info("Persons", [
                "0" => "Changed by api ignored"
            ]);

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
                    if ($params["previous"])
                    {
                        $result  = FALSE;
                        $changes = Helper::array_diff_assoc_recursive($params["current"],$params["previous"]);

                        if (array_key_exists(self::STAGE_MONITORING, $changes)) $result = TRUE;

                        if (!$result) break;
                    }

                    MauticIntegration::stageUpdate("contactFields", $params["current"]);
                break;

                case self::STAGE_DELETED_EVENT:
                    MauticIntegration::stageUpdate("contactFields", $params["previous"], true);
                break;

                default:
                    Log::channel("marketplace")->info("Persons", [
                        "400" => "Unsupported event"
                    ]);

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
            Log::channel("marketplace")->info("Stages", [
                "500" => $e
            ]);

            self::sendMailNotification($params, $e);

            return response()->json([
                "status"  => "Error",
                "message" => $e,
                "code"    => self::STAGE_CODE,
            ], 500);
        }

        Log::channel("marketplace")->info("Stages", [
            "200" => "Success"
        ]);

        return response()->json([
            "status"  => "Ok",
            "message" => "Success",
        ], 200);
    }



    public function syncIdsMauticPipe(Request $request)
    {
        $classes = $request->all();

        try
        {
            MauticIntegration::syncIdsMauticPipe($classes);
        }
        catch (Exception $e)
        {
            return response()->json([
                "status"  => "Error",
                "message" => $e,
                "code"    => 5,
            ], 500);
        }

        return response()->json([
            "status"  => "Ok",
            "message" => "Success",
        ], 200);
    }

    public function importAllStages(Request $request)
    {
        try
        {
            MauticIntegration::importAllStages();
        }
        catch (Exception $e)
        {
            return response()->json([
                "status"  => "Error",
                "message" => $e,
                "code"    => 5,
            ], 500);
        }

        return response()->json([
            "status"  => "Ok",
            "message" => "Success",
        ], 200);
    }


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
