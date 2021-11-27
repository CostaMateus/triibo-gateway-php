<?php

namespace App\Http\Integrations\FastshopIntegration;

class DataTranslator
{

    public static function translate(string $type, array $data)
    {
        switch ($type) {
            case "updateStockPrice":
                return self::updateStockPrice($data);
            break;
        }
    }

    private static function updateStockPrice(array $data)
    {
        if (isset($data["logisticsInfo"]) && count($data["logisticsInfo"]) > 0)
        {
            $status = "instock";
            $qntty  = $data["logisticsInfo"][0]["stockBalance"];
        }
        else
        {
            $status = "outofstock";
            $qntty  = 0;
        }

        $item    = $data["items"][0];
        $regular = substr_replace($item["listPrice"], ".", -2, 0);
        $sale    = substr_replace($item["price"],     ".", -2, 0);

        $wcData  = [
            "regular_price"  => $regular,
            "sale_price"     => $sale,
            "stock_quantity" => $qntty,
            "stock_status"   => $status,
            "manage_stock"   => true
        ];

        return $wcData;
    }

    public static function newProductFsToWc(array $data)
    {
        return [];
    }

    public static function dataFastshopToWoocommerce(array $data)
    {

        return [];
    }

    public static function dataWoocommerceToFastshop(array $data)
    {

        return [];
    }
}
