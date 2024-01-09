<?php

namespace App\Http\Integrations\FastshopIntegration;

use App\Models\User;
use App\Notifications\FastShop\HubError;
use App\Notifications\FastShop\OrderError;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class Notification
{

    public static function notify( string $name, array $data )
    {
        switch ( $name )
        {
            case "newOrderError":
                return self::orderError( $data, 1 );
            break;

            case "cancelationOrderError":
                return self::orderError( $data, 2 );
            break;
            case "cancelationOrderFS":
                return self::orderError( $data, 4 );
            break;

            case "approvedOrderError":
                return self::orderError( $data, 3 );
            break;
            case "approvedOrderError2":
                return self::orderError( $data, 5 );
            break;

            case "hubError":
                return self::hubError( $data );
            break;
        }
    }

    /**
     * Undocumented function
     *
     * @param Array $params
     * @return void
     */
    private static function orderError( array $data, int $opt )
    {
        $data  = DataTranslator::translate( "orderError", $data );
        $title = "";

        switch ( $opt )
        {
            case 1:
                $title = "Falha na criação do pedido abaixo na Fast Shop";
            break;

            case 2:
                $title = "Falha no cancelamento do pedido abaixo na Fast Shop";
            break;

            case 3:
                $title = "Falha ao informar sobre a aprovação do pedido abaixo na Fast Shop";
            break;

            case 4:
                $title = "Pedido cancelado pela Fast Shop";
            break;

            case 5:
                $title = "Falha ao informar sobre a aprovação do pedido abaixo na Fast Shop - pedido sem meta '_fastshop_order_id'";
            break;
        }

        $mail = [
            "subject" => "Falha em pedido FastShop",
            "title"   => $title,
            "data"    => $data,
        ];

        $users = User::where( "email", "mateus.costa@triibo.com.br" )
                     ->orWhere( "email", "equipemarketplace@triibo.com.br" )
                     ->get();

        foreach ( $users as $user ) $user->notify( new OrderError( $mail ) );
    }

    /**
     * Undocumented function
     *
     * @param Array $params
     * @return void
     */
    private static function hubError( array $data )
    {
        $mail = [
            "subject" => "HUB não mapeado - FastShop",
            "title"   => "Consulta de frete com HUB não mapeado no marketplace",
            "data"    => $data,
        ];

        $users = User::where( "email", "mateus.costa@triibo.com.br" )
                     ->orWhere( "email", "equipemarketplace@triibo.com.br" )
                     ->get();

        foreach ( $users as $user ) $user->notify( new HubError( $mail ) );
    }
}
