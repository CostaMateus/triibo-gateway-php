<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FastshopController extends Controller
{

    /**
     * Notificação de catálogo
     *
     * @param Request $request
     * @return void
     */
    public function changeNotification(Request $request, $sellerId, $sellerSkuId)
    {
        //
        dd($request->all(), $sellerId, $sellerSkuId);

        $sku_exist_mkt = true;

        if (!$sku_exist_mkt)
        {
            return response()->json([
                "message" => "Seller StockKeepingUnit {$sellerSkuId} not found for this seller id {$sellerId}",
            ], 400);
        }

        $this->updateStockPrice();

        return response()->json([
            "message" => "Ok",
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


    public function updateStockPrice()
    {
        //
    }
}
