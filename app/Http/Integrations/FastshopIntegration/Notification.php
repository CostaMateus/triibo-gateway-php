<?php

namespace App\Http\Integrations\FastshopIntegration;

use App\Models\User;
use App\Notifications\FastShop\NewOrderError;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class Notification
{

    public static function notify(string $name, array $data)
    {
        switch ($name) {
            case "newOrderError":
                return self::newOrderError($data);
            break;

        }
    }

    /**
     * Undocumented function
     *
     * @param Array $params
     * @return void
     */
    private static function newOrderError(array $data)
    {
        $data = DataTranslator::translate("newOrderError", $data);

        $mail = [
            "subject" => "Falha em pedido FastShop",
            "title"   => "Falha na criação do pedido abaixo na Fast Shop",
            "data"    => $data,
        ];

        $users = User::where("email", "mateus.costa@triibo.com.br")
                    //  ->orWhere("email", "equipemarketplace@triibo.com.br")
                     ->get();

        foreach ($users as $user)
        {
            $user->notify(new NewOrderError($mail));
        }
    }
}
