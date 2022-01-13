<?php

namespace App\Http\Integrations\FastshopIntegration;

use Exception;
use App\Models\User;
use Illuminate\Support\Facades\Log as LogSys;

class Log
{

    /**
     * Log de atualização de preço/estoque dos produtos
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

            LogSys::channel("fastshop")->info("updateStockPrice", [
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
            LogSys::channel("fastshop")->info("updateStockPrice", [
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
     * Log de criação/atualização de produto
     *
     * @param string $func
     * @param string $sku
     * @param boolean $update
     * @return void
     */
    public static function createOrUpdateProduct(string $func, string $sku, bool $update)
    {
        $update = ($update) ? "produtc update" : "product create";

        LogSys::channel("fastshop")->info($func, [$sku => $update]);
    }

    /**
     * Log de request com credenciais invalidas
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
     * Log de falha na consulta de frete
     *
     * @param array $data
     * @return void
     */
    public static function freightCalculation(array $data)
    {
        LogSys::channel("fastshop")->info("Shipping query failed", $data);
    }

    /**
     * Log de falha ao enviar pedido de cancelamento do WC para o FS
     * *@param array $data
     * *@return void
      */
    public static function orderCancelation(array $data)
    {
        LogSys::channel("fastshop")->info("Cancelation order error", $data);
    }

    /**
     * Log de novo pedido
     *
     * @param string|null $key
     * @param string|null $token
     * @return void
     */
    public static function orderCreation(array $error, array $data)
    {
        LogSys::channel("fastshop")->error("New order error", [$error, $data]);
    }

    /**
     * Log erros imprevistos
     *
     * @param Exception $e
     * @return void
     */
    public static function exception(string $func, Exception $e)
    {
        LogSys::channel("fastshop")->error("Exception - $func", [$e]);
    }

    /**
     * Log padrão, apenas informativo
     *
     * @param string $func
     * @param array $data
     * @return void
     */
    public static function default(string $func, array $data)
    {
        LogSys::channel("fastshop")->info($func, $data);
    }
}
