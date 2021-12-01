<?php

namespace App\Http\Integrations\FastshopIntegration;

use App\Helpers\Helper;

class DataTranslator
{

    public static function translate(string $type, array $data)
    {
        switch ($type) {
            case "updateStockPrice":
                return self::updateStockPrice($data);
            break;

            case "newProduct":
                return self::newProductFsToWc($data);
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

    public static function newProductFsToWc(array $data)
    {
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

        $wcData = [
            "name" => $data["ProductName"],
            "slug" => $slug,

            // "type"               => "simple",
            // "status"             => "publish",
            // "featured"           => false,
            // "catalog_visibility" => "visible",

            "description"       => $data["ProductDescription"],
            "short_description" => $data["ProductDescription"],
            "sku"               => "FS-$sku",

            "regular_price" => Helper::insertDecimal($data["Pricing"]["ListPrice"]),
            "sale_price"    => Helper::insertDecimal($data["Pricing"]["SalePrice"]),
            // "date_on_sale_from"     => null,
            // "date_on_sale_from_gmt" => null,
            // "date_on_sale_to"       => null,
            // "date_on_sale_to_gmt"   => null,

            // "virtual"         => false,
            // "downloadable"    => false,
            // "downloads"       => [],
            // "download_limit"  => -1,
            // "download_expiry" => -1,

            // "external_url" => "",
            // "button_text"  => "",

            // "tax_status" => "taxable",
            // "tax_class"  => "",

            "manage_stock"      => true, // false
            "stock_quantity"    => $data["AvailableQuantity"],
            "stock_status"      => $data["AvailableQuantity"] > 0 ? "instock" : "outofstock",
            "backorders"        => "no",
            "sold_individually" => false,

            // mkt_gr == fs_kg
            "weight"     => (string) ($data["WeightKg"] * 1000),
            "dimensions" => [
                "length" => (string) $data["Length"],
                "width"  => (string) $data["Width"],
                "height" => (string) $data["Height"]
            ],

            "shipping_class" => "fast-shop",
            // "reviews_allowed" => true,

            "upsell_ids"     => [],
            "cross_sell_ids" => [],
            "parent_id"      => 0,
            "purchase_note"  => "",

            "tags"           => [ [ "id" => 289 ] ], // tag: novo

            "categories"     => $categories,
            "imgaes"         => $images,

        ];

        // dd($wcData);

        return $wcData;
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
