<?php

namespace App\Http\Controllers;

use Exception;
use App\Helpers\Helper;
use Illuminate\Http\Request;
use Devio\Pipedrive\Pipedrive;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Integrations\MauticIntegration;

class PipedriveWebhookController extends Controller
{

    /**
     * Deal to Contact->Funil
     */
    const DEAL_ADDED_EVENT     = 'added.deal';
    const DEAL_UPDATED_EVENT   = 'updated.deal';
    const DEAL_DELETED_EVENT   = 'deleted.deal';
    const DEAL_CODE            = 1;
    const DEAL_MONITORING      = ["stage_id", "active", "deleted", "status"];

    /**
     * Organization to Company
     */
    const ORG_ADDED_EVENT      = 'added.organization';
    const ORG_UPDATED_EVENT    = 'updated.organization';
    const ORG_DELETED_EVENT    = 'deleted.organization';
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
    const PERSON_ADDED_EVENT   = 'added.person';
    const PERSON_UPDATED_EVENT = 'updated.person';
    const PERSON_DELETED_EVENT = 'deleted.person';
    const PERSON_CODE          = 3;
    const PERSON_MONITORING    = [
        "first_name",
        "last_name",
        "org_name",
        "bd7287f0a9a02de60a8aff83b5905c489b7cf007"  // mautic_id
    ];

    /**
     * Stage to Funil/pipeline
     */
    const STAGE_ADDED_EVENT    = 'added.stage';
    const STAGE_UPDATED_EVENT  = 'updated.stage';
    const STAGE_DELETED_EVENT  = 'deleted.stage';
    const STAGE_CODE           = 4;
    const STAGE_MONITORING      = "name";

    /**
     * Pipedrive - deal
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function deal(Request $request)
    {
        $params = $request->all();

        if ($params['meta']['change_source'] == 'api')
        {
            Log::info("Deal changed by api - ignored");
            return response()->json([
                'status'  => 'Ok',
                'message' => 'Deal changed by api - ignored',
                'code'    => -1,
            ], 200);
        }

        try
        {
            switch ($params['event'])
            {
                case self::DEAL_ADDED_EVENT:
                case self::DEAL_UPDATED_EVENT:
                    if ($params['previous'])
                    {
                        $result  = FALSE;
                        $changes = Helper::array_diff_assoc_recursive($params['current'],$params['previous']);
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

                    $api    = new Pipedrive(config('pipedrive.token'));
                    $person = $api->persons->find($params['current']['person_id'])->getData();

                    $params['current']['person_email']     = $person->email[0]->value;
                    $params['current']['person_mautic_id'] = $person->bd7287f0a9a02de60a8aff83b5905c489b7cf007;

                    MauticIntegration::dealUpdate('deals', $params['current']);
                break;

                case self::DEAL_DELETED_EVENT:
                    $api    = new Pipedrive(config('pipedrive.token'));
                    $person = $api->persons->find($params['previous']['person_id'])->getData();

                    $params['previous']['person_email'] = $person->email[0]->value;

                    MauticIntegration::dealUpdate('deals', $params['previous'], TRUE);
                break;

                default:
                    Log::info("Deal - Unsupported event, 400");
                    return response()->json([
                        'status'  => 'Error',
                        'message' => 'Unsupported event',
                        'code'    => 0,
                    ], 400);
                break;
            }
        }
        catch (Exception $e)
        {
            Log::info("Deal - $e, 500");
            return response()->json([
                'status'  => 'Error',
                'message' => $e,
                'code'    => self::DEAL_CODE,
            ], 500);
        }

        Log::info("Deal - Success, 200");
        return response()->json([
            'status'  => 'Ok',
            'message' => 'Success',
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

        if ($params['meta']['change_source'] == 'api')
        {
            Log::info("Organization changed by api - ignored");
            return response()->json([
                'status'  => 'Ok',
                'message' => 'Organization changed by api - ignored',
                'code'    => -1,
            ], 200);
        }

        try
        {
            switch ($params['event'])
            {
                case self::ORG_ADDED_EVENT:
                case self::ORG_UPDATED_EVENT:
                    if ($params['previous'])
                    {
                        $result  = FALSE;
                        $changes = Helper::array_diff_assoc_recursive($params['current'],$params['previous']);
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

                    MauticIntegration::updateOrCreate('companies', $params['current']);
                break;

                case self::ORG_DELETED_EVENT:
                    MauticIntegration::delete('companies', $params['previous']);
                break;

                default:
                    Log::info("Organization - Unsupported event, 400");
                    return response()->json([
                        'status'  => 'Error',
                        'message' => 'Unsupported event',
                        'code'    => 0,
                    ], 400);
                break;
            }
        }
        catch (Exception $e)
        {
            Log::info("Organization - $e, 500");
            return response()->json([
                'status'  => 'Error',
                'message' => $e,
                'code'    => self::ORG_CODE,
            ], 500);
        }

        Log::info("Organization - Success, 200");
        return response()->json([
            'status'  => 'Ok',
            'message' => 'Success',
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

        if ($params['meta']['change_source'] == 'api')
        {
            Log::info("Person changed by api - ignored");
            return response()->json([
                'status'  => 'Ok',
                'message' => 'Person changed by api - ignored',
                'code'    => -1,
            ], 200);
        }

        try
        {
            switch ($params['event'])
            {
                case self::PERSON_ADDED_EVENT:
                case self::PERSON_UPDATED_EVENT:
                    if ($params['previous'])
                    {
                        $result  = FALSE;
                        $changes = Helper::array_diff_assoc_recursive($params['current'],$params['previous']);
                        foreach (self::PERSON_MONITORING as $m)
                        {
                            if (array_key_exists($m, $changes))
                            {
                                $result = TRUE;
                                break;
                            }
                        }

                        if (!$result) break;
                    }

                    MauticIntegration::updateOrCreate('contacts', $params['current']);
                break;

                case self::PERSON_DELETED_EVENT:
                    MauticIntegration::delete('contacts', $params['previous']);
                break;

                default:
                    Log::info("Person - Unsupported event, 400");
                    return response()->json([
                        'status'  => 'Error',
                        'message' => 'Unsupported event',
                        'code'    => 0,
                    ], 400);
                break;
            }
        }
        catch (Exception $e)
        {
            Log::info("Person - $e, 500");
            return response()->json([
                'status'  => 'Error',
                'message' => $e,
                'code'    => self::PERSON_CODE,
            ], 500);
        }

        Log::info("Person - Success, 200");
        return response()->json([
            'status'  => 'Ok',
            'message' => 'Success',
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

        if ($params['meta']['change_source'] == 'api')
        {
            Log::info("Stage changed by api - ignored");
            return response()->json([
                'status'  => 'Ok',
                'message' => 'Stage changed by api - ignored',
                'code'    => -1,
            ], 200);
        }

        try
        {
            switch ($params['event'])
            {
                case self::STAGE_ADDED_EVENT:
                case self::STAGE_UPDATED_EVENT:
                    if ($params['previous'])
                    {
                        $result  = FALSE;
                        $changes = Helper::array_diff_assoc_recursive($params['current'],$params['previous']);

                        if (array_key_exists(self::STAGE_MONITORING, $changes))
                        {
                            $result = TRUE;
                            break;
                        }

                        if (!$result) break;
                    }

                    MauticIntegration::stageUpdate('contactFields', $params['current']);
                break;

                case self::STAGE_DELETED_EVENT:
                    MauticIntegration::stageUpdate('contactFields', $params['previous'], true);
                break;

                default:
                    Log::info("Stage - Unsupported event, 400");
                    return response()->json([
                        'status'  => 'Error',
                        'message' => 'Unsupported event',
                        'code'    => 0,
                    ], 400);
                break;
            }
        }
        catch (Exception $e)
        {
            Log::info("Stage - $e, 500");
            return response()->json([
                'status'  => 'Error',
                'message' => $e,
                'code'    => self::STAGE_CODE,
            ], 500);
        }

        Log::info("Stage - Success, 200");
        return response()->json([
            'status'  => 'Ok',
            'message' => 'Success',
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
                'status'  => 'Error',
                'message' => $e,
                'code'    => 5,
            ], 500);
        }

        return response()->json([
            'status'  => 'Ok',
            'message' => 'Success',
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
                'status'  => 'Error',
                'message' => $e,
                'code'    => 5,
            ], 500);
        }

        return response()->json([
            'status'  => 'Ok',
            'message' => 'Success',
        ], 200);
    }
}
