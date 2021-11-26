<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Codexshaper\WooCommerce\Facades\Product;
use App\Http\Integrations\FastshopIntegration\DataTranslator;

class FastshopController extends Controller
{

    /**
     * Notificação de catálogo
     * Fastshop sugere novos SKUs a serem vendidos no Marketplace e
     * informa ao Marketplace sobre as mudanças em seus SKUs que já foram integrados
     * como alterações de cadastro, estoque e preço. Ambas as ações começam com uma notificação de catálogo.
     *
     * @param Request $request
     * @param string $sellerId
     * @param string $sellerSkuId
     * @return Response
     */
    public function changeNotification(Request $request, $sellerId, $sellerSkuId)
    {
        $appKey   = $request->header("x-vtex-api-appkey");
        $appToken = $request->header("x-vtex-api-apptoken");

        // Verifição de chaves
        if ($appKey != config("fastshop.hml.key") && $appToken != config("fastshop.hml.token"))
        {
            return response()->json([
                "errorCode" => "400",
                "message"   => "invalid credentials"
            ], 400);
        }

        $sku = strtoupper($sellerSkuId);

        // Procura produto no Marketplace pelo SKU
        $product = Product::all([
            "sku" => "FS-$sku"
        ])->first();

        if (!$product)
        {
            return response()->json([
                "errorCode" => "404",
                "message"   => "Seller StockKeepingUnit {$sellerSkuId} not found for this seller id {$sellerId}",
            ], 404);
        }

        // Atualiza produto no Marketplace
        $this->updateStockPrice($product->id, $sellerSkuId);

        return response()->json([
            "errorCode" => "200",
            "message"   => "Ok",
        ], 200);
    }

    /**
     * Registro de catálogo
     *
     * @param Request $request
     * @return void
     */
    public function skuSuggestion(Request $request, $sellerId, $sellerSkuId)
    {
        //
        dd($request->all(), $sellerId, $sellerSkuId);
    }

    /**
     * Atualização de catálogo
     *
     * @param Request $request
     * @return void
     */
    public function updateSku(Request $request, $sellerId, $sellerSkuId)
    {
        //
        dd($request->all(), $sellerId, $sellerSkuId);
    }


    /**
     * Atualização de preço/estoque
     *
     * @param Request $request
     * @return void
     */
    public function updateStockPrice($productId, $sellerSkuId)
    {
        $headers = [
            "Accept"              => "application/json",
            "Content-Type"        => "application/json",
            "X-VTEX-API-AppKey"   => config("fastshop.hml.key"),
            "X-VTEX-API-AppToken" => config("fastshop.hml.token")
        ];

        $url  = config("fastshop.hml.url") . "/pvt/orderForms/simulation";

        $body = json_encode([
            "items" => [[
                "id"       => $sellerSkuId,
                "quantity" => 1,
                "seller"   => "FSH"
            ]],
            "postalCode"     => "",
            "country"        => "",
            "geoCoordinates" => []
        ]);

        // Consulta produto na FS
        $response = Http::withHeaders($headers)->withBody($body, "application/json")->get($url);

        if ($response->successful())
        {
            $fsData  = $response->json();

            // Converte produto da FS para WC
            $wcData  = DataTranslator::translate("updateStockPrice", $fsData);

            // Atualiza produto no Marketplace
            $product = Product::update($productId, $wcData);

            // Registrar log de sucesso
            //
        }

        // Registrar log de falha
        //
    }
}
