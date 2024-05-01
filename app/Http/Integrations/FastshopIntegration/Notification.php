<?php

namespace App\Http\Integrations\FastshopIntegration;

use App\Models\User;
use App\Notifications\FastShop\HubError;
use App\Notifications\FastShop\OrderError;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class Notification
{
    /**
     * Notifies the system of various order-related errors.
     *
     * @param   string  $name   The type of notification to be processed.
     * @param   array   $data   The data associated with the notification.
     * @return  void
     */
    public static function notify( string $name, array $data )
    {
        // Map notification names to their corresponding error codes.
        $error_codes = [
            "newOrderError"         => 1,
            "cancelationOrderError" => 2,
            "cancelationOrderFS"    => 4,
            "approvedOrderError"    => 3,
            "approvedOrderError2"   => 5,
        ];

        // Check if the notification name exists in the error codes map.
        if ( isset( $error_codes[ $name ] ) )
        {
            // Call the orderError method with the corresponding error code.
            self::orderError($data, $error_codes[ $name ] );
        }
        elseif ( $name === "hubError" )
        {
            // Handle hub errors separately.
            self::hubError( $data );
        }
    }

    /**
     * Handles different types of order errors and notifies the relevant users.
     *
     * @param   array   $data   The data associated with the order error.
     * @param   int     $opt    The option indicating the type of order error.
     * @return  void
     */
    private static function orderError( array $data, int $opt )
    {
        $data         = DataTranslator::translate( "orderError", $data );

        // Map error codes to their corresponding titles.
        $error_titles = [
            1 => "Falha na criação do pedido abaixo na Fast Shop",
            2 => "Falha no cancelamento do pedido abaixo na Fast Shop",
            4 => "Pedido cancelado pela Fast Shop",
            3 => "Falha ao informar sobre a aprovação do pedido abaixo na Fast Shop",
            5 => "Falha ao informar sobre a aprovação do pedido abaixo na Fast Shop - pedido sem meta '_fastshop_order_id'",
        ];

        // Get the title based on the error code.
        $title        = $error_titles[ $opt ] ?? "Erro desconhecido";

        self::sendNotification( new OrderError( [
            "subject" => "Falha em pedido FastShop",
            "title"   => $title,
            "data"    => $data,
        ] ) );
    }

    /**
     * Handles hub errors and notifies the relevant users.
     *
     * @param   array   $data   The data associated with the hub error.
     * @return  void
     */
    private static function hubError( array $data )
    {
        self::sendNotification( new HubError( [
            "subject" => "HUB não mapeado - FastShop",
            "title"   => "Consulta de frete com HUB não mapeado no marketplace",
            "data"    => $data,
        ] ) );
    }

    /**
     * Sends a notification to a predefined list of users.
     *
     * @param   object  $notification  The notification object to be sent.
     * @return  void
     */
    private static function sendNotification( object $notification )
    {
        // List of user emails to notify.
        $to_notify = [
            "mateus.costa@triibo.com.br",
            "equipemarketplace@triibo.com.br",
        ];

        // Retrieve all users with the specified emails and notify them.
        $users     = User::whereIn( "email", $to_notify )->get();

        foreach ( $users as $user )
            $user->notify( $notification );
    }
}
