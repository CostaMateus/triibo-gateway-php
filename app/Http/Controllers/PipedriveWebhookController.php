<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Http\Integrations\MauticIntegration;
// use Devio\Pipedrive\Pipedrive;

define('TOKEN', config('pipedrive.token'));

class PipedriveWebhookController extends Controller
{

    /**
     * Organization to Company
     */
    const ORG_ADDED_EVENT      = 'added.organization';
    const ORG_UPDATED_EVENT    = 'updated.organization';
    const ORG_DELETED_EVENT    = 'deleted.organization';

    /**
     * Person to Contact
     */
    const PERSON_ADDED_EVENT   = 'added.person';
    const PERSON_UPDATED_EVENT = 'updated.person';
    const PERSON_DELETED_EVENT = 'deleted.person';

    /**
     * Stage to Funil/pipeline
     */
    const STAGE_ADDED_EVENT    = 'added.stage';
    const STAGE_UPDATED_EVENT  = 'updated.stage';
    const STAGE_DELETED_EVENT  = 'deleted.stage';

    /**
     * Deal to Contact->Funil
     */
    const DEAL_ADDED_EVENT     = 'added.deal';
    const DEAL_UPDATED_EVENT   = 'updated.deal';
    const DEAL_DELETED_EVENT   = 'deleted.deal';

    /**
     * Pipedrive - organization
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function organization(Request $request)
    {
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

        try
        {
            switch ($params['event'])
            {
                case self::PERSON_ADDED_EVENT:
                    MauticIntegration::updateOrCreate('contacts', $params['current']);
                break;

                case self::PERSON_UPDATED_EVENT:
                    MauticIntegration::updateOrCreate('contacts', $params['current']);
                break;

                case self::PERSON_DELETED_EVENT:
                    MauticIntegration::delete('contacts', $params['previous']);
                break;

                default:
                    return response()->json([
                        'status'  => 'Error',
                        'message' => 'Unsupported event',
                    ], 400);
                break;
            }
        }
        catch (Exception $e)
        {
            return response()->json([
                'status'  => 'Error',
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status'  => 'Ok',
            'message' => 'Success',
        ], 200);
    }

    /**
     * Pipedrive - stage
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function stage(Request $request)
    {
        return response()->json([
            'status'  => 'Ok',
            'message' => 'Success',
        ], 200);
    }

    /**
     * Pipedrive - deal
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function deal(Request $request)
    {
        return response()->json([
            'message' => 'pipedrive - deal',
            'code'    => 200
        ], 200);
    }

    // public function teste()
    // {
    //     $token         = config('pipedrive.token');
    //     $pipedrive     = new Pipedrive($token);
    //     $response      = $pipedrive->deals->all();
    //     $organizations = $response->getData();
    //     foreach ($organizations as $org)
    //     {
    //         if ($org->owner_name == 'Pedro')
    //         {
    //             $aux[] = $org;
    //         }
    //     }
    //     dd($aux);
    // }
}
