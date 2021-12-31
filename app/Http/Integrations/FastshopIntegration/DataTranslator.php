<?php

namespace App\Http\Integrations\FastshopIntegration;

use App\Helpers\Helper;
use App\Http\Integrations\FastshopIntegration\Log;

class DataTranslator
{

    public static function translate(string $type, array $data)
    {
        switch ($type) {
            case "updateStockPrice":
                return self::updateStockPrice($data);
            break;
            case "queryStockPrice":
                return self::queryStockPrice($data[0]);
            break;

            case "newProduct":
                return self::newProductFsToWc($data);
            break;

            case "freightCalcFs":
                return self::freightCalcFs($data);
            break;
            case "freightCalcWc":
                return self::freightCalcWc($data);
            break;
            case "freightCalcFailed":
                return self::freightCalcFailed($data);
            break;
            case "freightCalcNewOrder":
                return self::freightCalcNewOrder($data);
            break;

            case "newOrderFs":
                return self::newOrderFs($data);
            break;
            case "newOrderError":
                return self::newOrderError($data);
            break;
        }
    }

    /**
     * Converte a atualização de um produto da Fastshop em WooCommerce
     *
     * @param array $data
     * @return array
     */
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
        $regular = Helper::insertDecimal($item["listPrice"]);
        $sale    = Helper::insertDecimal($item["price"]);

        $wcData  = [
            "regular_price"  => $regular,
            "sale_price"     => $sale,
            "stock_quantity" => $qntty,
            "stock_status"   => $status,
            "manage_stock"   => true
        ];

        return $wcData;
    }

    /**
     * Prepara a consulta de preço e estoque
     *
     * @param string $sku
     * @return array
     */
    private static function queryStockPrice(string $sku)
    {
        return [
            "items" => [[
                "id"       => $sku,
                "quantity" => 1,
                "seller"   => "FSH"
            ]],
            "postalCode"     => "",
            "country"        => "",
            "geoCoordinates" => []
        ];
    }

    /**
     * Converte um produto da Fastshop em WooCommerce
     *
     * @param array $data
     * @return array
     */
    private static function newProductFsToWc(array $data)
    {
        $attributes = [];
        $categories = [];
        $images     = [];

        $slug       = Helper::hyphenize($data["ProductName"]);
        $sku        = $data["ProductId"] == $data["SellerStockKeepingUnitId"] ? $data["ProductId"] : $data["SellerStockKeepingUnitId"];

        foreach ($data["CategoryFullPath"] as $ctg)
        {
            $categories[] = [ "id" => $ctg ];
        }

        foreach ($data["Images"] as $img)
        {
            $images[] = [
                "src"  => $img["imageUrl"],
                "name" => $img["imageName"],
                "alt"  => $img["imageName"]
            ];
        }

        // VERIFICAR OUTROS PRODUTOS
        foreach ($data["SkuSpecifications"] as $key => $attr)
        {
            $attributes[] = [
                "name"      => $attr["fieldName"],
                "position"  => $key,
                "visible"   => true,
                "variation" => true,
                "options"   => [
                    $attr["fieldValue"]
                ],
            ];
        }
        foreach ($data["ProductSpecifications"] as $key => $attr)
        {
            if ($attr["fieldName"] == "ProductLineId") continue;

            $fieldValue   = ($attr["fieldValue"] === "true") ? "SIM" : $attr["fieldValue"];

            $attributes[] = [
                "name"      => $attr["fieldName"],
                "position"  => $key,
                "visible"   => true,
                "variation" => true,
                "options"   => [ $fieldValue ],
            ];
        }

        $shortDescript = explode("\r\n", $data["ProductDescription"])[0];
        $shortDescript = (strlen($shortDescript) <= 50) ? $data["ProductName"] : $shortDescript;

        $wcData        = [
            "name"              => $data["ProductName"],
            "slug"              => $slug,

            "description"       => $data["ProductDescription"],
            "short_description" => $shortDescript,
            "sku"               => "FS-$sku",

            "regular_price"     => Helper::insertDecimal($data["Pricing"]["ListPrice"]),
            "sale_price"        => Helper::insertDecimal($data["Pricing"]["SalePrice"]),

            "manage_stock"      => true,
            "stock_quantity"    => $data["AvailableQuantity"],
            "stock_status"      => $data["AvailableQuantity"] > 0 ? "instock" : "outofstock",
            "backorders"        => "no",
            "sold_individually" => false,

            "weight"            => Helper::convertWeight($data["WeightKg"]),
            "dimensions"        => [
                "length" => (string) $data["Length"],
                "width"  => (string) $data["Width"],
                "height" => (string) $data["Height"]
            ],

            "shipping_class"    => "fast-shop",

            "upsell_ids"        => [],
            "cross_sell_ids"    => [],
            "parent_id"         => 0,
            "purchase_note"     => "",

            "tags"              => [ [ "id" => 289 ] ],

            "categories"        => $categories,
            "images"            => $images,
            "attributes"        => $attributes,
        ];

        return $wcData;
    }

    /**
     * Prepara uma consulta de frete do WooCommerce para a Fastshop
     *
     * @param array $data
     * @return array
     */
    private static function freightCalcFs(array $data)
    {
        $items = [];

        foreach ($data["items"] as $item)
        {
            $items[] = [
                "id"       => explode("FS-", $item["sku"])[1],
                "quantity" => $item["quantity"],
                "seller"   => "FSH",
            ];
        }

        return [
            "items"          => $items,
            "postalCode"     => $data["postalCode"],
            "country"        => "BRA",
            "geoCoordinates" => []
        ];
    }

    /**
     * Converte retorno de frete da Fastshop para WooCommerce
     *
     * @param array $data
     * @return array
     */
    private static function freightCalcWc(array $data)
    {
        $products  = [];
        $logistics = [];

        foreach ($data["items"] as $key => $p) $products[$key] = $p["id"];

        foreach ($data["logisticsInfo"] as $key => $lg)
        {
            $delivery  = null;

            $idChannel = array_search("delivery", array_column($lg["deliveryChannels"], "id"));
            $idSlas    = array_search("delivery", array_column($lg["slas"], "deliveryChannel"));

            if ($idChannel !== false && $idSlas !== false)
            {
                $delivery["stock"]        = $lg["deliveryChannels"][$idChannel]["stockBalance"];
                $delivery["price"]        = $lg["slas"][$idSlas]["price"];
                $delivery["deliveryTime"] = $lg["slas"][$idSlas]["shippingEstimate"];
            }

            $stock             = (isset($delivery["stock"]))        ? $delivery["stock"]        : 0;
            $price             = (isset($delivery["price"]))        ? $delivery["price"]        : 0;
            $deliveryTime      = (isset($delivery["deliveryTime"])) ? $delivery["deliveryTime"] : 0;
            $availableQuantity = ($lg["quantity"] <= $stock)        ? true                      : false;

            $logistics[]       = [
                "sku"               => "FS-" . $products[$lg["itemIndex"]],
                "quantity"          => $lg["quantity"],
                "stock"             => $stock,
                "deliveryPrice"     => Helper::convertPrice($price),
                "deliveryTime"      => Helper::convertDeliveryTime($deliveryTime),
                "availableQuantity" => $availableQuantity
            ];
        }

        return $logistics;
    }

    /**
     * Converte retorno de frete da Fastshop para WooCommerce
     * Quando há falta de estoque ou entrega indisponivel
     *
     * @param array $data
     * @return array
     */
    private static function freightCalcFailed(array $data)
    {
        $products  = [];
        $logistics = [];

        if (isset($data["logisticsInfo"]))
        {
            foreach ($data["items"] as $key => $p) $products[$key] = $p["id"];

            foreach ($data["logisticsInfo"] as $key => $lg)
            {
                $idChannel = array_search("delivery", array_column($lg["deliveryChannels"], "id"));

                $stock = ($idChannel !== false) ? $lg["deliveryChannels"][$idChannel]["stockBalance"] : 0;

                $logistics[] = [
                    "sku"               => "FS-" . $products[$lg["itemIndex"]],
                    "quantity"          => $lg["quantity"],
                    "stock"             => $stock,
                    "deliveryPrice"     => "0.00",
                    "deliveryTime"      => "0",
                    "availableQuantity" => false
                ];
            }
        }
        else
        {
            foreach ($data["items"] as $key => $p)
            {
                $logistics[] = [
                    "sku"               => $p["sku"],
                    "quantity"          => $p["quantity"],
                    "stock"             => 0,
                    "deliveryPrice"     => "0.00",
                    "deliveryTime"      => "0",
                    "availableQuantity" => false
                ];
            }
        }

        return $logistics;
    }

    /**
     * Converte retorno de frete da Fastshop para WooCommerce, novo pedido
     *
     * @param array $data
     * @return array
     */
    private static function freightCalcNewOrder(array $data)
    {
        $items = [];

        foreach ($data["line_items"] as $item)
        {
            $items[] = [
                "id"       => explode("FS-", $item["sku"])[1],
                "quantity" => $item["quantity"],
                "seller"   => "FSH",
            ];
        }

        return [
            "items"          => $items,
            "postalCode"     => $data["shipping"]["postcode"],
            "country"        => "BRA",
            "geoCoordinates" => []
        ];
    }

    /**
     * Converte os dados do pedido WC para enviar a FS
     *
     * @param array $data
     * @return array
     */
    private static function newOrderFs(array $data)
    {
        return [""];
    }

    /**
     * Converte os dados do pedido para enviar email
     *
     * @package array $data
     * @return array
     */
    private static function newOrderError(array $data)
    {
        if (empty($data["parent"]))
        {
            $orderId    = $data["order"]["id"];
            $subOrderId = "";
        }
        else
        {
            $orderId    = $data["parent"]["id"];
            $subOrderId = $data["order"]["id"];
        }

        return [
            "orderId"     => $orderId,
            "subOrderId"  => $subOrderId,
            "customer_id" => $data["order"]["customer_id"],
            "name"        => $data["order"]["billing"]["first_name"] . " " . $data["order"]["billing"]["last_name"],
            "email"       => $data["order"]["billing"]["email"],
            "phone"       => $data["order"]["billing"]["phone"],
        ];
    }
}
