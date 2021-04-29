<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
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

    /**
     * Organization to Company
     */
    const ORG_ADDED_EVENT      = 'added.organization';
    const ORG_UPDATED_EVENT    = 'updated.organization';
    const ORG_DELETED_EVENT    = 'deleted.organization';
    const ORG_CODE             = 2;

    /**
     * Person to Contact
     */
    const PERSON_ADDED_EVENT   = 'added.person';
    const PERSON_UPDATED_EVENT = 'updated.person';
    const PERSON_DELETED_EVENT = 'deleted.person';
    const PERSON_CODE          = 3;

    /**
     * Stage to Funil/pipeline
     */
    const STAGE_ADDED_EVENT    = 'added.stage';
    const STAGE_UPDATED_EVENT  = 'updated.stage';
    const STAGE_DELETED_EVENT  = 'deleted.stage';
    const STAGE_CODE           = 4;

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
            return response()->json([
                'status'  => 'Ok',
                'message' => 'Deal changed by api - ignored',
                'code'    => -1,
            ], 200);
        }

        try
        {
            // switch ($params['event'])
            // {
            //     case self::DEAL_ADDED_EVENT:
            //     case self::DEAL_UPDATED_EVENT:
            //         MauticIntegration::dealUpdate('deals', $params['current']);
            //     break;

            //     case self::DEAL_DELETED_EVENT:
            //         MauticIntegration::dealUpdate('deals', $params['previous'], true);
            //     break;

            //     default:
            //         Log::info("Deal - Unsupported event, 400");
            //         return response()->json([
            //             'status'  => 'Error',
            //             'message' => 'Unsupported event',
            //             'code'    => 0,
            //         ], 400);
            //     break;
            // }
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
            return response()->json([
                'status'  => 'Ok',
                'message' => 'Organization changed by api - ignored',
                'code'    => -1,
            ], 200);
        }

        try
        {
            // switch ($params['event'])
            // {
            //     case self::ORG_ADDED_EVENT:
            //     case self::ORG_UPDATED_EVENT:
            //         MauticIntegration::updateOrCreate('companies', $params['current']);
            //     break;

            //     case self::ORG_DELETED_EVENT:
            //         MauticIntegration::delete('companies', $params['previous']);
            //     break;

            //     default:
            //         Log::info("Organization - Unsupported event, 400");
            //         return response()->json([
            //             'status'  => 'Error',
            //             'message' => 'Unsupported event',
            //             'code'    => 0,
            //         ], 400);
            //     break;
            // }
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
            return response()->json([
                'status'  => 'Ok',
                'message' => 'Stage changed by api - ignored',
                'code'    => -1,
            ], 200);
        }

        try
        {
            // switch ($params['event'])
            // {
            //     case self::STAGE_ADDED_EVENT:
            //     case self::STAGE_UPDATED_EVENT:
            //         MauticIntegration::stageUpdate('contactFields', $params['current']);
            //     break;

            //     case self::STAGE_DELETED_EVENT:
            //         MauticIntegration::stageUpdate('contactFields', $params['previous'], true);
            //     break;

            //     default:
            //         Log::info("Stage - Unsupported event, 400");
            //         return response()->json([
            //             'status'  => 'Error',
            //             'message' => 'Unsupported event',
            //             'code'    => 0,
            //         ], 400);
            //     break;
            // }
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


    public function updatePipedrive(Request $request)
    {
        $classes = $request->all();

        try
        {
            MauticIntegration::updatePipedrive($classes);
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
