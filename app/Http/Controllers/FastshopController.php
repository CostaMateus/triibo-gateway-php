<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FastshopController extends Controller
{

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return void
     */
    public function changeNotification(Request $request, $sellerId, $sellerSkuId)
    {
        //
        dd($request->all(), $sellerId, $sellerSkuId);
    }

    /**
     * Undocumented function
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
     * Undocumented function
     *
     * @param Request $request
     * @return void
     */
    public function skuUpdate(Request $request, $sellerId, $sellerSkuId)
    {
        //
        dd($request->all(), $sellerId, $sellerSkuId);
    }
}
