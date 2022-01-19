<?php

namespace App\Http\Integrations\FastshopIntegration;

use App\Models\User;
use App\Notifications\FastShop\OrderError;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class Notification
{

    public static function notify(string $name, array $data)
    {
        switch ($name) {
            case "newOrderError":
                return self::orderError($data, 1);
            break;

            case "cancelationOrderError":
                return self::orderError($data, 2);
            break;
            case "cancelationOrderFS":
                return self::orderError($data, 4);
            break;

            case "approvedOrderError":
                return self::orderError($data, 3);
            break;
        }
    }

    /**
     * Undocumented function
     *
     * @param Array $params
     * @return void
     */
    private static function orderError(array $data, int $opt)
    {
        $data  = DataTranslator::translate("orderError", $data);
        $title = "";

        switch ($opt)
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
        }

        $mail = [
            "subject" => "Falha em pedido FastShop",
            "title"   => $title,
            "data"    => $data,
        ];

        $users = User::where("email", "mateus.costa@triibo.com.br")
                    //  ->orWhere("email", "equipemarketplace@triibo.com.br")
                     ->get();

        foreach ($users as $user)
        {
            $user->notify(new OrderError($mail));
        }
    }
}
