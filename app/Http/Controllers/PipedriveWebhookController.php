<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Devio\Pipedrive\Pipedrive;
use App\Notifications\WebhookMarketplace;
use App\Http\Integrations\MauticIntegration;
use Illuminate\Http\{JsonResponse, Request};

class PipedriveWebhookController extends Controller
{
    /**
     * Define a class constant for the log channel.
     *
     * @var string
     */
    private const LOG_CHANNEL = "marketplace";
    private const MSG_CHANGE_BY_API = "Changed by api ignored";
    private const MSG_UNSUPPORTED_EVENT = "Unsupported event";

    /**
     * Deal to Contact->Funil
     */
    private const DEAL_ADDED_EVENT = "added.deal";
    private const DEAL_UPDATED_EVENT = "updated.deal";
    private const DEAL_DELETED_EVENT = "deleted.deal";
    private const DEAL_CODE = 1;
    private const DEAL_MONITORING = [
        "stage_id", // Etapa    / id interno: 12462
        "label",    // Etiqueta / id interno: 12463
        "status",   // Status   / id interno: 12464
        "active",
        "deleted",
    ];

    /**
     * Organization to Company
     */
    private const ORG_ADDED_EVENT = "added.organization";
    private const ORG_UPDATED_EVENT = "updated.organization";
    private const ORG_DELETED_EVENT = "deleted.organization";
    private const ORG_CODE = 2;
    private const ORG_MONITORING = [
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
    private const STAGE_ADDED_EVENT = "added.stage";
    private const STAGE_UPDATED_EVENT = "updated.stage";
    private const STAGE_DELETED_EVENT = "deleted.stage";
    private const STAGE_CODE = 4;
    private const STAGE_MONITORING = "name";

    /**
     * Person to Contact
     *
     * @param Request $request
     */
    // private const PERSON_ADDED_EVENT   = "added.person";
    // private const PERSON_UPDATED_EVENT = "updated.person";
    // private const PERSON_DELETED_EVENT = "deleted.person";
    // private const PERSON_CODE          = 3;
    // private const PERSON_MONITORING    = [
    //     // "first_name", "last_name", "email", "phone", "org_name",
    //     "name",                                     // Nome      / id interno: 9039
    //     "bd7287f0a9a02de60a8aff83b5905c489b7cf007", // Mautic Id / id interno: 9067
    // ];

    /**
     * Pipedrive - deal
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function deal( Request $request )
    {
        // Storage::put( "deal-" . date( "Ymd-his" ) . ".json", json_encode( $request->all(), JSON_UNESCAPED_SLASHES ) );

        $params = $request->all();

        if ( $params[ "meta" ][ "change_source" ] === "api" )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Deals: code 0 | ".self::MSG_CHANGE_BY_API );

            return response()->json( [
                "status" => "Ok",
                "message" => "Deal - ".self::MSG_CHANGE_BY_API,
                "code" => -1,
            ], 200 );
        }

        try
        {
            switch ( $params[ "event" ] )
            {
                case self::DEAL_ADDED_EVENT:
                case self::DEAL_UPDATED_EVENT:
                case self::DEAL_DELETED_EVENT:
                    $index = ( $params[ "event" ] === self::DEAL_DELETED_EVENT ) ? "previous" : "current";

                    if ( ! $params[ $index ][ "org_id" ] )
                    {
                        log_message( channel: self::LOG_CHANNEL, message: "Deals: code 404 | Not found: org_id" );

                        break;
                    }

                    if ( isset( $params[ "previous" ] ) && $params[ "event" ] !== self::DEAL_DELETED_EVENT )
                    {
                        $result = false;
                        $changes = array_diff_assoc_recursive( $params[ "current" ], $params[ "previous" ] );

                        foreach ( self::DEAL_MONITORING as $m )
                        {
                            if ( array_key_exists( $m, $changes ) )
                            {
                                $result = true;

                                break;
                            }
                        }

                        if ( ! $result ) break;
                    }

                    $api = new Pipedrive( config( "pipedrive.token" ) );
                    $org = $api->organizations->find( $params[ $index ][ "org_id" ] )->getData();

                    // $params[ $index ][ "email"     ] = $org->{"a007cb7cef80c909678503cbcfe49def03cad573"};
                    // $params[ $index ][ "mautic_id" ] = $org->{"918dace587a4707755926d7c02c3bb46ed53d539"};

                    $data[ "mautic_id" ] = $org->{"918dace587a4707755926d7c02c3bb46ed53d539"};
                    $data[ "email"     ] = $org->{"a007cb7cef80c909678503cbcfe49def03cad573"};
                    $data[ "org_id"    ] = $params[ $index ][ "org_id"   ];
                    $data[ "org_name"  ] = $params[ $index ][ "org_name" ];
                    $data[ "active"    ] = $params[ $index ][ "active"   ];
                    $data[ "deleted"   ] = $params[ $index ][ "deleted"  ];

                    if ( $index === "current" )
                    {
                        $data[ "new_stage_id" ] = $params[ $index ][ "stage_id" ];
                        $data[ "new_status"   ] = $params[ $index ][ "status"   ];
                        $data[ "new_label"    ] = $params[ $index ][ "label"    ];

                        if ( isset( $params[ "previous" ] ) )
                        {
                            $data[ "old_stage_id" ] = $params[ "previous" ][ "stage_id" ];
                            $data[ "old_status"   ] = $params[ "previous" ][ "status"   ];
                            $data[ "old_label"    ] = $params[ "previous" ][ "label"    ];
                        }
                        else
                        {
                            $data[ "old_stage_id" ] = 0;
                            $data[ "old_status"   ] = 0;
                            $data[ "old_label"    ] = 0;
                        }
                    }
                    else
                    {
                        $data[ "new_stage_id" ] = 0;
                        $data[ "new_status"   ] = 0;
                        $data[ "new_label"    ] = 0;

                        $data[ "old_stage_id" ] = $params[ $index ][ "stage_id" ];
                        $data[ "old_status"   ] = $params[ $index ][ "status"   ];
                        $data[ "old_label"    ] = $params[ $index ][ "label"    ];
                    }

                    MauticIntegration::dealUpdate( "deals", $data );

                    break;

                default:
                    log_message( channel: self::LOG_CHANNEL, message: "Deals: code 400 | ".self::MSG_UNSUPPORTED_EVENT );

                    return response()->json( [
                        "status" => "Error",
                        "message" => "Deals - ".self::MSG_UNSUPPORTED_EVENT,
                        "code" => 0,
                    ], 400 );

                    break;
            }
        }
        catch ( Exception $e )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Deals: code 500 | ".$e->getMessage() );

            self::sendMailNotification( $params, $e );

            return response()->json( [
                "status" => "Error",
                "message" => $e->getMessage(),
                "code" => self::DEAL_CODE,
            ], 500 );
        }

        return response()->json( [
            "status" => "Ok",
            "message" => "Success",
        ], 200 );
    }

    /**
     * Pipedrive - organization
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function organization( Request $request )
    {
        $params = $request->all();

        if ( $params[ "meta" ][ "change_source" ] === "api" )
        {
            $message = "";
            log_message( channel: self::LOG_CHANNEL, message: "Organizations: code 0 | ".self::MSG_CHANGE_BY_API );

            return response()->json( [
                "status" => "Ok",
                "message" => "Organizations - ".self::MSG_CHANGE_BY_API,
                "code" => -1,
            ], 200 );
        }

        try
        {
            switch ( $params[ "event" ] )
            {
                case self::ORG_ADDED_EVENT:
                case self::ORG_UPDATED_EVENT:
                case self::ORG_DELETED_EVENT:

                    $index = ( $params[ "event" ] === self::ORG_DELETED_EVENT ) ? "previous" : "current";
                    $del = ( $params[ "event" ] === self::ORG_DELETED_EVENT ) ? true : false;

                    if ( isset( $params[ "previous" ] ) && $params[ "event" ] !== self::ORG_DELETED_EVENT )
                    {
                        $result = false;
                        $changes = array_diff_assoc_recursive( $params[ "current" ], $params[ "previous" ] );

                        foreach ( self::ORG_MONITORING as $m )
                        {
                            if ( array_key_exists( $m, $changes ) )
                            {
                                $result = true;

                                break;
                            }
                        }

                        if ( ! $result ) break;
                    }

                    MauticIntegration::updateOrCreate( "contacts", $params[ $index ], $del );

                    break;

                default:
                    log_message( channel: self::LOG_CHANNEL, message: "Organizations: code 400 | ".self::MSG_UNSUPPORTED_EVENT );

                    return response()->json( [
                        "status" => "Error",
                        "message" => "Organizations - ".self::MSG_UNSUPPORTED_EVENT,
                        "code" => 0,
                    ], 400 );

                    break;
            }
        }
        catch ( Exception $e )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Organizations: code 500 | ".$e->getMessage() );

            self::sendMailNotification( $params, $e );

            return response()->json( [
                "status" => "Error",
                "message" => "Organizations - ".$e->getMessage(),
                "code" => self::ORG_CODE,
            ], 500 );
        }

        return response()->json( [
            "status" => "Ok",
            "message" => "Success",
        ], 200 );
    }

    /**
     * Pipedrive - stage
     * Recebe o webhook dos estágios dentro dos funis do Pipedrive
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function stage( Request $request )
    {
        $params = $request->all();

        if ( $params[ "meta" ][ "change_source" ] === "api" )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Stages: code 0 | ".self::MSG_CHANGE_BY_API );

            return response()->json( [
                "status" => "Ok",
                "message" => "Stages - ".self::MSG_CHANGE_BY_API,
                "code" => -1,
            ], 200 );
        }

        try
        {
            switch ( $params[ "event" ] )
            {
                case self::STAGE_ADDED_EVENT:
                case self::STAGE_UPDATED_EVENT:
                case self::STAGE_DELETED_EVENT:
                    MauticIntegration::updateStages();

                    break;

                default:
                    log_message( channel: self::LOG_CHANNEL, message: "Stages: code 400 | ".self::MSG_UNSUPPORTED_EVENT );

                    return response()->json( [
                        "status" => "Error",
                        "message" => "Stages - ".self::MSG_UNSUPPORTED_EVENT,
                        "code" => 0,
                    ], 400 );

                    break;
            }
        }
        catch ( Exception $e )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Stages: code 500 | ".$e->getMessage() );

            self::sendMailNotification( $params, $e );

            return response()->json( [
                "status" => "Error",
                "message" => "Stages - ".$e->getMessage(),
                "code" => self::STAGE_CODE,
            ], 500 );
        }

        return response()->json( [
            "status" => "Ok",
            "message" => "Success",
        ], 200 );
    }

    /**
     * Pipedrive - person
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function person( Request $request )
    {
        if ( env( "APP_ENV" ) === "production" || env( "APP_ENV" ) === "local" )
        {
            log_message( channel: self::LOG_CHANNEL, message: "Persons: code -1 | Deactivated" );

            return response()->json( [
                "status" => "Ok",
                "message" => "Persons - Deactivated",
            ], 200 );
        }

        // $params = $request->all();

        // if ( $params[ "meta" ][ "change_source" ] == "api" )
        // {
        //     log_message( channel: self::LOG_CHANNEL, message: "Persons: code 0 | " . self::MSG_CHANGE_BY_API );

        //     return response()->json( [
        //         "status"  => "Ok",
        //         "message" => "Persons - " . self::MSG_CHANGE_BY_API,
        //         "code"    => -1,
        //     ], 200 );
        // }
        //
        // try
        // {
        //     switch ( $params[ "event" ] )
        //     {
        //         case self::PERSON_ADDED_EVENT:
        //         case self::PERSON_UPDATED_EVENT:
        //             if ( isset( $params[ "previous" ] ) )
        //             {
        //                 $result  = FALSE;
        //                 $changes = array_diff_assoc_recursive( $params[ "current" ],$params[ "previous" ] );
        //                 foreach ( self::PERSON_MONITORING as $m )
        //                 {
        //                     if ( array_key_exists( $m, $changes ) )
        //                     {
        //                         $result = TRUE;
        //                         break;
        //                     }
        //                 }
        //
        //                if ( !$result )
        //                {
        //                    log_message( channel: self::LOG_CHANNEL, message: "Persons: code 0 | Discarded changes" );
        //                    break;
        //                }
        //            }
        //
        //             MauticIntegration::updateOrCreate( "contacts", $params[ "current" ] );
        //         break;
        //
        //         case self::PERSON_DELETED_EVENT:
        //             MauticIntegration::delete( "contacts", $params[ "previous" ] );
        //         break;
        //
        //        default:
        //            log_message( channel: self::LOG_CHANNEL, message: "Persons: code 400 | " . self::MSG_UNSUPPORTED_EVENT );
        //
        //            return response()->json( [
        //                "status"  => "Error",
        //                "message" => "Persons - " . self::MSG_UNSUPPORTED_EVENT,
        //                "code"    => 0,
        //            ], 400 );
        //        break;
        //     }
        // }
        // catch ( Exception $e )
        // {
        //     log_message( channel: self::LOG_CHANNEL, message: "Persons: code 500 | " . $e->getMessage() );

        //     self::sendMailNotification( $params, $e );

        //     return response()->json( [
        //         "status"  => "Error",
        //         "message" => $e,
        //         "code"    => self::PERSON_CODE,
        //     ], 500 );
        // }

        // log_message( channel: self::LOG_CHANNEL, message: "Persons: code 200 | Success" );

        // return response()->json( [
        //     "status"  => "Ok",
        //     "message" => "Success",
        // ], 200 );
    }

    /**
     * Undocumented function
     *
     * @param array     $params
     * @param Exception $e
     *
     * @return void
     */
    private static function sendMailNotification( array $params, Exception $e ) : void
    {
        if ( $params[ "retry" ] === 3 )
        {
            $mail = [
                "subject" => "Dev - Integração Triibo Pipedrive-Mautic",
                "title" => "Erro no webhook: ".$params[ "meta" ][ "object" ],
                "class" => $params[ "meta" ][ "object" ],
                "params" => $params,
                "stacktrace" => $e,
            ];

            $users = User::where( "email", "equipemarketplace@triibo.com.br" )
                ->orWhere( "email", "mateus.costa@triibo.com.br" )
                ->get();

            $users[ 0 ]->notify( new WebhookMarketplace( $mail ) );
            $users[ 1 ]->notify( new WebhookMarketplace( $mail ) );
        }
    }
}
