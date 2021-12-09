<?php

namespace App\Http\Integrations\FastshopIntegration;

use Exception;
use Illuminate\Support\Facades\Log as LogSys;

class Log
{

    /**
     * Registra log de atualização de preço/estoque dos produtos
     *
     * @param object $old
     * @param object $new
     * @param bool $error
     * @return void
     */
    public static function updateStockPrice(object $old, object $new = null, bool $error = false)
    {
        if (!$error)
        {
            $new = json_decode($new->toJson());

            LogSys::channel("fastshop")->info("StockPrice", [
                $old->sku => [
                    "old" => [
                        "regular_price"  => $old->regular_price,
                        "sale_price"     => $old->sale_price,
                        "stock_quantity" => $old->stock_quantity,
                        "stock_status"   => $old->stock_status,
                    ],
                    "new" => [
                        "regular_price"  => $new->regular_price,
                        "sale_price"     => $new->sale_price,
                        "stock_quantity" => $new->stock_quantity,
                        "stock_status"   => $new->stock_status,
                    ],
                ],
            ]);
        }
        else
        {
            LogSys::channel("fastshop")->info("StockPrice", [
                $old->sku => [
                    "old" => [
                        "regular_price"  => $old->regular_price,
                        "sale_price"     => $old->sale_price,
                        "stock_quantity" => $old->stock_quantity,
                        "stock_status"   => $old->stock_status,
                    ],
                    "new" => [
                        "failed to get data for update",
                    ],
                ],
            ]);
        }
    }

    /**
     * Registra log quando credenciais invalidas
     *
     * @param string|null $key
     * @param string|null $token
     * @return void
     */
    public static function invalidCredentials(string $key = null, string $token = null)
    {
        LogSys::channel("fastshop")->error("Invalid Credentials", [
            "appKey"   => $key,
            "appToken" => $token
        ]);
    }

    /**
     * Registra log quando erros imprevistos
     *
     * @param Exception $e
     * @return void
     */
    public static function exception(Exception $e)
    {
        LogSys::channel("fastshop")->error("Exception", [$e]);
    }
}
